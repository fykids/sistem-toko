<?php

use App\Models\Buyer;
use App\Models\CarTransaction;
use App\Models\SaleNote;
use App\Models\User;
use App\Services\TransactionBackupService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->master()->create());
});

test('it renders SQLite backup and import actions on the dashboard', function () {
    $response = $this->get(route('transactions.index'));

    $response->assertOk()
        ->assertSee('Backup SQLite', false)
        ->assertSee(route('transactions.export'), false)
        ->assertSee(route('transactions.import'), false)
        ->assertSee('type="file"', false)
        ->assertSee('accept=".sqlite,.db"', false);
});

test('it downloads an intact SQLite backup containing transactions and sale notes', function () {
    $transaction = CarTransaction::create([
        'plate_number' => '=HYPERLINK("https://example.com")',
        'brand' => 'Toyota',
        'model' => 'Innova',
        'year' => 2020,
        'transmission' => 'Automatic',
        'color' => 'Hitam',
        'buy_date' => '2026-10-17',
        'buy_price' => 125000000,
        'tax_items' => [['name' => 'Pajak', 'cost' => 1250000]],
        'additional_repairs' => [['name' => 'Servis', 'cost' => 500000]],
        'intertine_expenses' => [['name' => 'KR Jual', 'cost' => 250000]],
        'leasing_options' => [['name' => 'BCA', 'disbursement' => 90000000, 'dp' => 35000000]],
    ]);
    Buyer::create([
        'car_transaction_id' => $transaction->id,
        'buyer_name' => 'Rina Pembeli',
        'buyer_nik' => '3174012345678901',
        'buyer_phone' => '081234567890',
        'buyer_address' => 'Jakarta',
        'sell_price_deal' => 150000000,
        'payment_method' => 'Transfer bank',
        'sell_date' => '2026-10-22',
        'notes' => 'Pembayaran lunas',
        'created_by' => auth()->id(),
    ]);
    $transaction->update([
        'sell_date' => '2026-10-22',
        'selling_price' => 150000000,
    ]);
    $saleNote = $transaction->saleNote()->create([
        'nama_konsumen' => 'Dewi Pembeli',
        'created_by' => auth()->id(),
    ]);
    $saleNote->payments()->create([
        'amount' => 25000000,
        'payment_method' => 'bank_transfer',
        'payment_date' => '2026-10-23',
        'notes' => 'Referensi backup',
        'recorded_by' => auth()->id(),
    ]);

    $response = $this->get(route('transactions.export'));

    $response->assertOk()
        ->assertDownload();

    $backupContents = $response->streamedContent();
    $backupPath = tempnam(sys_get_temp_dir(), 'test-sistemtoko-backup-');
    file_put_contents($backupPath, $backupContents);
    $backupDatabase = null;

    try {
        $backupDatabase = new SQLite3($backupPath, SQLITE3_OPEN_READONLY);
        $columnsResult = $backupDatabase->query('PRAGMA table_info("car_transactions")');
        $backupColumns = [];

        while ($column = $columnsResult->fetchArray(SQLITE3_ASSOC)) {
            $backupColumns[] = $column['name'];
        }

        $transaction = $backupDatabase->querySingle('SELECT * FROM car_transactions', true);
        $buyer = $backupDatabase->querySingle('SELECT * FROM buyers', true);
        $saleNote = $backupDatabase->querySingle('SELECT * FROM sale_notes', true);
        $payment = $backupDatabase->querySingle('SELECT * FROM sale_payments', true);

        expect($backupDatabase->querySingle('PRAGMA integrity_check'))->toBe('ok')
            ->and($backupColumns)->toBe(Schema::getColumnListing('car_transactions'))
            ->and($transaction['plate_number'])->toBe('=HYPERLINK("https://example.com")')
            ->and(json_decode($transaction['tax_items'], true, flags: JSON_THROW_ON_ERROR))
            ->toBe([['name' => 'Pajak', 'cost' => 1250000]])
            ->and(json_decode($transaction['additional_repairs'], true, flags: JSON_THROW_ON_ERROR))
            ->toBe([['name' => 'Servis', 'cost' => 500000]])
            ->and(json_decode($transaction['intertine_expenses'], true, flags: JSON_THROW_ON_ERROR))
            ->toBe([['name' => 'KR Jual', 'cost' => 250000]])
            ->and(json_decode($transaction['leasing_options'], true, flags: JSON_THROW_ON_ERROR))
            ->toBe([['name' => 'BCA', 'disbursement' => 90000000, 'dp' => 35000000]])
            ->and($buyer['buyer_name'])->toBe('Rina Pembeli')
            ->and((int) $buyer['sell_price_deal'])->toBe(150000000)
            ->and((int) $buyer['car_transaction_id'])->toBe((int) $transaction['id'])
            ->and($saleNote['nama_konsumen'])->toBe('Dewi Pembeli')
            ->and((int) $saleNote['car_transaction_id'])->toBe((int) $transaction['id'])
            ->and((int) $payment['amount'])->toBe(25000000)
            ->and($payment['notes'])->toBe('Referensi backup');

    } finally {
        if ($backupDatabase instanceof SQLite3) {
            $backupDatabase->close();
        }

        unlink($backupPath);
    }
});

test('it imports sale notes and reconnects them to newly imported vehicles', function () {
    $sourceTransaction = CarTransaction::create([
        'plate_number' => 'B 1234 SALE',
        'brand' => 'Toyota',
        'model' => 'Avanza',
        'year' => 2022,
        'transmission' => 'Automatic',
        'color' => 'Hitam',
        'buy_date' => '2026-10-01',
        'buy_price' => 50000000,
    ]);
    $sourceId = $sourceTransaction->id;
    $sale = Buyer::create([
        'car_transaction_id' => $sourceTransaction->id,
        'buyer_name' => 'Rina Pembeli',
        'buyer_nik' => null,
        'buyer_phone' => '081234567890',
        'buyer_address' => 'Jakarta',
        'sell_price_deal' => 80000000,
        'payment_method' => 'Tunai',
        'sell_date' => '2026-10-22',
        'notes' => 'Lunas',
        'created_by' => auth()->id(),
    ]);
    $sourceTransaction->update([
        'sell_date' => $sale->sell_date->toDateString(),
        'selling_price' => $sale->sell_price_deal,
    ]);

    $existingTransaction = CarTransaction::create([
        'plate_number' => 'EXISTING',
        'brand' => 'Honda',
        'model' => 'Civic',
        'year' => 2021,
        'transmission' => 'Automatic',
        'color' => 'Putih',
        'buy_date' => '2026-01-01',
        'buy_price' => 100000000,
    ]);
    $existingTransaction->id = 100000;
    $existingTransaction->save();

    $backupPath = app(TransactionBackupService::class)->createBackup();
    $backupContents = file_get_contents($backupPath);
    $sourceTransaction->delete();

    try {
        $response = $this->post(route('transactions.import'), [
            'backup' => UploadedFile::fake()->createWithContent('sales.sqlite', $backupContents),
        ]);
    } finally {
        unlink($backupPath);
    }

    $response->assertRedirect(route('transactions.index'))
        ->assertSessionHas('success', 'Backup berhasil digabungkan. 2 transaksi baru ditambahkan.');

    $importedSale = Buyer::query()->where('buyer_name', 'Rina Pembeli')->sole();
    $importedTransaction = $importedSale->carTransaction;

    expect($importedTransaction->id)->not->toBe($sourceId)
        ->and($importedTransaction->plate_number)->toBe('B 1234 SALE')
        ->and($importedTransaction->sell_date)->toBe('2026-10-22')
        ->and((float) $importedTransaction->selling_price)->toBe(80000000.0)
        ->and($importedSale->created_by)->toBeNull();
});

test('it imports sale payments with their sale note and vehicle relationships', function () {
    $creator = auth()->user();
    $sourceTransaction = CarTransaction::create([
        'plate_number' => 'B 5566 PAYMENT',
        'brand' => 'Honda',
        'model' => 'Brio',
        'year' => 2023,
        'transmission' => 'Automatic',
        'color' => 'Putih',
        'buy_date' => '2026-10-01',
        'buy_price' => 100000000,
        'sell_date' => '2026-10-20',
        'selling_price' => 120000000,
        'down_payment' => 20000000,
    ]);
    $sourceSaleNote = SaleNote::query()->create([
        'car_transaction_id' => $sourceTransaction->id,
        'nama_konsumen' => 'Ayu Import',
        'created_by' => $creator->id,
    ]);
    $sourceSaleNote->payments()->create([
        'amount' => 15000000,
        'payment_method' => 'leasing',
        'payment_date' => '2026-10-24',
        'notes' => 'Pencairan tahap pertama',
        'recorded_by' => $creator->id,
    ]);

    $backupPath = app(TransactionBackupService::class)->createBackup();
    $backupContents = file_get_contents($backupPath);
    $sourceTransaction->delete();
    $userLookupQueries = 0;

    DB::listen(static function (QueryExecuted $query) use (&$userLookupQueries): void {
        if (str_contains(strtolower($query->sql), 'from "users"')) {
            $userLookupQueries++;
        }
    });

    try {
        $this->post(route('transactions.import'), [
            'backup' => UploadedFile::fake()->createWithContent('sale-payments.sqlite', $backupContents),
        ])
            ->assertRedirect(route('transactions.index'))
            ->assertSessionHas('success');
    } finally {
        unlink($backupPath);
    }

    $importedTransaction = CarTransaction::query()
        ->where('plate_number', 'B 5566 PAYMENT')
        ->sole();
    $importedSaleNote = $importedTransaction->saleNote()->sole();
    $importedPayment = $importedSaleNote->payments()->sole();

    expect($importedSaleNote->nama_konsumen)->toBe('Ayu Import')
        ->and($importedSaleNote->created_by)->toBe($creator->id)
        ->and((float) $importedPayment->amount)->toBe(15000000.0)
        ->and($importedPayment->payment_method)->toBe('leasing')
        ->and($importedPayment->notes)->toBe('Pencairan tahap pertama')
        ->and($importedPayment->recorded_by)->toBe($creator->id)
        ->and($userLookupQueries)->toBe(1);
});

test('it accepts legacy backups without a buyers table', function () {
    CarTransaction::create([
        'plate_number' => 'LEGACY BACKUP',
        'brand' => 'Honda',
        'model' => 'Civic',
        'year' => 2021,
        'transmission' => 'Automatic',
        'color' => 'Putih',
        'buy_date' => '2026-01-01',
    ]);
    $backupPath = app(TransactionBackupService::class)->createBackup();
    $legacyDatabase = new SQLite3($backupPath);

    try {
        $legacyDatabase->exec('DROP TABLE "buyers"');
    } finally {
        $legacyDatabase->close();
    }

    $backupContents = file_get_contents($backupPath);
    unlink($backupPath);

    $this->post(route('transactions.import'), [
        'backup' => UploadedFile::fake()->createWithContent('legacy.sqlite', $backupContents),
    ])
        ->assertRedirect(route('transactions.index'))
        ->assertSessionHas('success', 'Backup berhasil digabungkan. 1 transaksi baru ditambahkan.');

    $this->assertDatabaseHas('car_transactions', [
        'plate_number' => 'LEGACY BACKUP',
    ]);
});

test('it imports transactions from SQLite backups with newly generated IDs', function () {
    $existingTransaction = CarTransaction::create([
        'plate_number' => 'EXISTING',
        'brand' => 'Honda',
        'model' => 'Civic',
        'year' => 2021,
        'transmission' => 'Automatic',
        'color' => 'Putih',
        'buy_date' => '2026-01-01',
        'buy_price' => 100000000,
    ]);
    $existingTransaction->id = 100000;
    $existingTransaction->save();

    $sourceTransaction = CarTransaction::create([
        'plate_number' => 'B 1234 CD',
        'brand' => 'Toyota',
        'model' => 'Innova',
        'year' => 2020,
        'transmission' => 'Automatic',
        'color' => 'Hitam',
        'buy_date' => '2026-10-17',
        'buy_price' => 125000000,
        'tax_items' => [['name' => 'Pajak', 'cost' => 1250000]],
        'leasing_options' => [['name' => 'BCA', 'disbursement' => 90000000, 'dp' => 35000000]],
    ]);

    $backupPath = app(TransactionBackupService::class)->createBackup();
    $backupContents = file_get_contents($backupPath);
    $sourceId = $sourceTransaction->id;
    $sourceTransaction->delete();

    try {
        $response = $this->post(route('transactions.import'), [
            'backup' => UploadedFile::fake()->createWithContent('transactions.sqlite', $backupContents),
        ]);
    } finally {
        unlink($backupPath);
    }

    $response->assertRedirect(route('transactions.index'))
        ->assertSessionHas('success', 'Backup berhasil digabungkan. 2 transaksi baru ditambahkan.');

    $importedTransaction = CarTransaction::query()->where('plate_number', 'B 1234 CD')->sole();
    $importedExistingTransaction = CarTransaction::query()
        ->where('plate_number', 'EXISTING')
        ->where('id', '!=', $existingTransaction->id)
        ->sole();

    $this->assertModelExists($existingTransaction);
    $this->assertModelExists($importedTransaction);
    $this->assertModelExists($importedExistingTransaction);
    expect($importedTransaction->id)->not->toBe($sourceId)
        ->and((float) $importedTransaction->buy_price)->toBe(125000000.0)
        ->and($importedTransaction->tax_items)->toBe([['name' => 'Pajak', 'cost' => 1250000]])
        ->and($importedTransaction->leasing_options)->toBe([['name' => 'BCA', 'disbursement' => 90000000, 'dp' => 35000000]]);

    $this->assertDatabaseCount('car_transactions', 3);
});

test('it rejects invalid SQLite backups without changing existing transactions', function () {
    $existingTransaction = CarTransaction::create([
        'plate_number' => 'KEEP',
        'brand' => 'Honda',
        'model' => 'Civic',
        'year' => 2021,
        'transmission' => 'Automatic',
        'color' => 'Putih',
        'buy_date' => '2026-01-01',
        'buy_price' => 100000000,
    ]);

    $response = $this->from(route('transactions.index'))->post(route('transactions.import'), [
        'backup' => UploadedFile::fake()->createWithContent('broken.sqlite', 'not a SQLite database'),
    ]);

    $response->assertRedirect(route('transactions.index'))
        ->assertSessionHasErrors('backup')
        ->assertSessionHasErrors([
            'backup' => 'File yang dipilih bukan backup SQLite yang valid.',
        ]);

    $this->assertModelExists($existingTransaction);
    $this->assertDatabaseCount('car_transactions', 1);
});
