<?php

use App\Models\CarTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('it renders repair items with a next index after the highest existing index', function () {
    $response = $this->withSession([
        '_old_input' => [
            'additional_repairs' => [
                0 => ['name' => 'Ketok Pintu', 'date' => '2026-10-11', 'cost' => 200000, 'payment_status' => 'lunas'],
                2 => ['name' => 'Perbaikan Jok', 'date' => '2026-10-12', 'cost' => 100000, 'payment_status' => 'panjar'],
            ],
        ],
    ])->get(route('transactions.create'));

    $response->assertOk()
        ->assertSee('name="additional_repairs[0][name]"', false)
        ->assertSee('name="additional_repairs[2][name]"', false)
        ->assertSee('data-next-index="3"', false);
});

test('it renders an empty repair row by default', function () {
    $response = $this->get(route('transactions.create'));

    $response->assertOk()
        ->assertSee('name="additional_repairs[0][name]"', false)
        ->assertSee('name="additional_repairs[0][date]"', false)
        ->assertSee('name="additional_repairs[0][cost]"', false)
        ->assertSee('name="additional_repairs[0][payment_status]"', false)
        ->assertSee('id="repair-name-0"', false)
        ->assertSee('id="repair-name-__INDEX__"', false)
        ->assertSee('id="add-repair"', false)
        ->assertSee('class="repair-form-labels"', false);

    $content = $response->getContent();
    $templateStart = strpos($content, '<template id="repair-item-template">');
    $repairTemplate = substr($content, $templateStart, strpos($content, '</template>', $templateStart) - $templateStart);

    expect(substr_count($content, 'id="add-repair"'))->toBe(1)
        ->and($content)->not->toContain('class="icon-button add-item"')
        ->and(substr_count($content, 'class="repair-form-labels"'))->toBe(1)
        ->and(strpos($repairTemplate, 'name="additional_repairs[__INDEX__][cost]"'))
        ->toBeLessThan(strpos($repairTemplate, 'name="additional_repairs[__INDEX__][date]"'));
});

test('it renders fixed intertine expense fields and totals', function () {
    $response = $this->get(route('transactions.create'));

    $response->assertOk()
        ->assertSee('id="intertine-expenses"', false)
        ->assertSee('name="intertine_expenses[0][name]"', false)
        ->assertSee('name="intertine_expenses[0][cost]"', false)
        ->assertSee('value="KR Jual"', false)
        ->assertSee('value="OP Survei"', false)
        ->assertSee('value="Fee CMO"', false)
        ->assertSee('label for="intertine-expense-cost-0">KR Jual</label>', false)
        ->assertSee('label for="intertine-expense-cost-1">OP Survei</label>', false)
        ->assertSee('label for="intertine-expense-cost-2">Fee CMO</label>', false)
        ->assertSee('class="form-group intertine-expense-item"', false)
        ->assertDontSee('name="intertine_expenses[0][date]"', false)
        ->assertSee('id="calculated-showroom-fee"', false)
        ->assertSee('id="calculated-total-intertine-expenses"', false)
        ->assertSee('id="calculated-net-profit"', false)
        ->assertDontSee('Pengeluaran Internal');

    $content = $response->getContent();
    $salesSectionStart = strpos($content, '<section class="form-section sales-section">');
    $costSectionStart = strpos($content, '<section class="form-section costs-section">');

    expect($salesSectionStart)->not->toBeFalse()
        ->and($costSectionStart)->not->toBeFalse()
        ->and(substr($content, $salesSectionStart, $costSectionStart - $salesSectionStart))
        ->toContain('id="intertine-expenses"')
        ->toContain('id="calculated-total-intertine-expenses"')
        ->toContain('id="calculated-net-profit"');

    expect(Schema::hasColumn('car_transactions', 'intertine_expenses'))->toBeTrue()
        ->and(Schema::hasColumn('car_transactions', 'total_intertine_expense'))->toBeTrue()
        ->and(Schema::hasColumn('car_transactions', 'internal_expenses'))->toBeFalse()
        ->and(Schema::hasColumn('car_transactions', 'total_internal_expense'))->toBeFalse();
});

test('it renders the dashboard action without a global header', function () {
    $response = $this->get(route('transactions.index'));

    $response->assertOk()
        ->assertDontSee('<header class="app-header"', false)
        ->assertSee('class="btn dashboard-create"', false)
        ->assertSee('Transaksi baru', false);

    expect(substr_count($response->getContent(), 'href="' . route('transactions.create') . '"'))->toBe(1);

    $this->get(route('transactions.create'))
        ->assertOk()
        ->assertDontSee('class="dashboard-create"', false);
});

test('it keeps purchase fields mapped to separate stored values', function () {
    $response = $this->get(route('transactions.create'));

    $response->assertOk()
        ->assertSee('name="other_costs"', false)
        ->assertSee('name="tax_items[0][cost]"', false)
        ->assertSee('name="tax_items[1][cost]"', false)
        ->assertSee('name="tax_items[2][cost]"', false)
        ->assertSee('name="tax_items[0][date]"', false)
        ->assertSee('name="tax_items[1][date]"', false);

    $content = $response->getContent();
    expect(substr_count($content, 'name="buy_price"'))->toBe(1)
        ->and(substr_count($content, 'name="buy_date"'))->toBe(1)
        ->and(substr_count($content, 'name="purchase_operational"'))->toBe(1);
});

test('it renders read-only setor profit and percentage calculations', function () {
    $response = $this->get(route('transactions.create'));

    $response->assertOk()
        ->assertSee('id="calculated-total-capital"', false)
        ->assertSee('Total pengeluaran')
        ->assertSee('id="calculated-setor-profit"', false)
        ->assertSee('id="calculated-setor-profit-percentage"', false)
        ->assertSee('class="setor-price-inputs"', false)
        ->assertSee('Harga setor (Rp)')
        ->assertSee('class="form-group opening-price-group"', false)
        ->assertSee('name="opening_price"', false)
        ->assertSee('class="opening-price-inputs"', false)
        ->assertSee('id="calculated-opening-profit"', false)
        ->assertSee('id="calculated-opening-profit-percentage"', false)
        ->assertSee('class="form-group selling-price-group"', false)
        ->assertSee('class="selling-price-inputs"', false)
        ->assertSee('id="calculated-sale-profit"', false)
        ->assertSee('id="sell-date"', false)
        ->assertDontSee('<label for="calculated-sale-profit">')
        ->assertDontSee('<label for="sell-date">')
        ->assertSee('aria-label="Selisih harga setor dengan modal (Rupiah)"', false)
        ->assertSee('aria-label="Persentase selisih harga setor dari modal"', false);
});

test('it ignores the untouched empty repair row when saving a transaction', function () {
    $response = $this->post(route('transactions.store'), [
        'plate_number' => 'B 1234 CD',
        'brand' => 'TOYOTA',
        'model' => 'INNOVA',
        'year' => 2014,
        'transmission' => 'MANUAL',
        'color' => 'HITAM',
        'buy_date' => '2026-10-12',
        'buy_price' => 500000,
        'additional_repairs' => [
            ['name' => '', 'date' => '', 'cost' => '', 'payment_status' => ''],
        ],
    ]);

    $response->assertRedirect(route('transactions.index'));

    $transaction = CarTransaction::query()->firstOrFail();
    expect($transaction->additional_repairs)->toBe([]);
});

test('it ignores untouched empty intertine expense rows when saving a transaction', function () {
    $response = $this->post(route('transactions.store'), [
        'plate_number' => 'B 1234 CD',
        'brand' => 'TOYOTA',
        'model' => 'INNOVA',
        'year' => 2014,
        'transmission' => 'MANUAL',
        'color' => 'HITAM',
        'buy_date' => '2026-10-12',
        'buy_price' => 500000,
        'intertine_expenses' => [
            ['name' => '', 'cost' => '', 'date' => ''],
        ],
    ]);

    $response->assertRedirect(route('transactions.index'));

    expect(CarTransaction::query()->firstOrFail()->intertine_expenses)->toBe([]);
});

test('it saves manual intertine expense fields and calculates the net profit', function () {
    $response = $this->post(route('transactions.store'), [
        'plate_number' => 'B 1234 CD',
        'brand' => 'TOYOTA',
        'model' => 'INNOVA',
        'year' => 2014,
        'transmission' => 'MANUAL',
        'color' => 'HITAM',
        'buy_date' => '2026-10-12',
        'buy_price' => 500000,
        'selling_price' => 1000000,
        'intertine_expenses' => [
            ['name' => 'KR Jual', 'cost' => 20000],
            ['name' => 'OP Survei', 'cost' => 10000],
            ['name' => 'Fee CMO', 'cost' => 5000],
        ],
    ]);

    $response->assertRedirect(route('transactions.index'));

    $transaction = CarTransaction::query()->firstOrFail();
    expect(array_column($transaction->intertine_expenses, 'name'))->toBe([
        'KR Jual',
        'OP Survei',
        'Fee CMO',
    ])->and((float) array_sum(array_column($transaction->intertine_expenses, 'cost')))->toBe(35000.0)
        ->and(array_filter($transaction->intertine_expenses, fn (array $expense): bool => array_key_exists('date', $expense)))->toBe([])
        ->and((float) $transaction->showroom_fee)->toBe(50000.0)
        ->and((float) $transaction->total_intertine_expense)->toBe(85000.0)
        ->and((float) $transaction->net_profit)->toBe(415000.0);
});

test('it stores custom repair items and includes them in transaction totals', function () {
    $response = $this->post(route('transactions.store'), [
        'plate_number' => 'B 1234 CD',
        'chassis_number' => 'FM3245JHK56854',
        'engine_number' => 'FM2001531',
        'mileage_km' => 125000,
        'brand' => 'TOYOTA',
        'model' => 'INNOVA',
        'year' => 2014,
        'transmission' => 'MANUAL',
        'color' => 'HITAM',
        'buy_date' => '2026-10-12',
        'buy_price' => 500000,
        'other_costs' => 50000,
        'selling_price' => 1000000,
        'down_payment' => 250000,
        'settlement_amount' => 100000,
        'leasing_disbursement' => 500000,
        'total_capital' => 1,
        'gross_profit' => 1,
        'net_profit' => 1,
        'remaining_payment' => 123,
        'tax_items' => [
            ['name' => 'Pajak Tahunan', 'date' => '2026-10-10', 'cost' => 50000, 'payment_status' => 'lunas'],
            ['name' => 'Balik Nama', 'date' => '2026-10-11', 'cost' => 25000, 'payment_status' => 'belum_lunas'],
        ],
        'intertine_expenses' => [
            ['name' => 'Biaya Iklan', 'date' => '2026-10-12', 'cost' => 30000],
            ['name' => 'Cuci Mobil', 'date' => '2026-10-12', 'cost' => 10000],
        ],
        'additional_repairs' => [
            ['name' => 'Ketok Pintu', 'date' => '2026-10-11', 'cost' => 200000, 'payment_status' => 'panjar'],
            ['name' => 'Perbaikan Jok', 'date' => '2026-10-12', 'cost' => 100000, 'payment_status' => 'lainnya', 'payment_note' => 'Menunggu tagihan'],
        ],
    ]);

    $response->assertRedirect(route('transactions.index'));

    $transaction = CarTransaction::query()->firstOrFail();
    expect((float) $transaction->total_repair_cost)->toBe(300000.0)
        ->and((float) $transaction->other_costs)->toBe(50000.0)
        ->and((float) $transaction->total_capital)->toBe(925000.0)
        ->and((float) $transaction->total_intertine_expense)->toBe(47500.0)
        ->and((float) $transaction->net_profit)->toBe(27500.0)
        ->and((float) $transaction->remaining_payment)->toBe(150000.0)
        ->and((float) $transaction->gross_profit)->toBe(75000.0)
        ->and((float) $transaction->settlement_amount)->toBe(100000.0)
        ->and((float) $transaction->leasing_disbursement)->toBe(500000.0)
        ->and(array_column($transaction->additional_repairs, 'name'))
        ->toBe(['Ketok Pintu', 'Perbaikan Jok'])
        ->and($transaction->additional_repairs[0]['date'])->toBe('2026-10-11')
        ->and($transaction->additional_repairs[1]['payment_note'])->toBe('Menunggu tagihan')
        ->and(array_column($transaction->tax_items, 'name'))->toBe(['Pajak Tahunan', 'Balik Nama']);

    expect(Schema::hasColumn('car_transactions', 'battery_cost'))->toBeFalse()
        ->and(Schema::hasColumn('car_transactions', 'undercarriage_cost'))->toBeFalse()
        ->and(Schema::hasColumn('car_transactions', 'bumper_paint_cost'))->toBeFalse()
        ->and(Schema::hasColumn('car_transactions', 'oil_change_cost'))->toBeFalse();

    $this->get(route('transactions.show', $transaction))
        ->assertOk()
        ->assertSee('Ketok Pintu')
        ->assertSee('KR Beli')
        ->assertSee('2026-10-11')
        ->assertSee('200,000')
        ->assertSee('Panjar')
        ->assertSee('Menunggu tagihan')
        ->assertSee('Pajak Tahunan')
        ->assertSee('Belum lunas')
        ->assertSee('FM3245JHK56854')
        ->assertSee('FM2001531')
        ->assertSee('125,000');

    $this->get(route('transactions.edit', $transaction))
        ->assertOk()
        ->assertSee('name="tax_items[0][name]"', false)
        ->assertSee('name="tax_items[3][name]"', false)
        ->assertSee('value="Pajak Tahunan"', false)
        ->assertSee('value="Balik Nama"', false)
        ->assertSee('name="tax_items[0][cost]"', false)
        ->assertSee('value="50000"', false);
});

test('it calculates sale profit from selling price minus total capital and adds all ripan', function () {
    $response = $this->post(route('transactions.store'), [
        'plate_number' => 'B 1234 CD',
        'brand' => 'TOYOTA',
        'model' => 'INNOVA',
        'year' => 2014,
        'transmission' => 'MANUAL',
        'color' => 'HITAM',
        'buy_date' => '2026-10-12',
        'buy_price' => 400000,
        'selling_price' => 1200000,
        'setor_price' => 1000000,
        'opening_price' => 1500000,
        'leasing_ripan' => 100000,
        'insurance_ripan' => 20000,
        'booster_ripan' => 30000,
    ]);

    $response->assertRedirect(route('transactions.index'));

    $transaction = CarTransaction::query()->firstOrFail();
    expect((float) $transaction->setor_price)->toBe(1000000.0)
        ->and((float) $transaction->opening_price)->toBe(1500000.0)
        ->and((float) $transaction->leasing_ripan)->toBe(100000.0)
        ->and((float) $transaction->insurance_ripan)->toBe(20000.0)
        ->and((float) $transaction->booster_ripan)->toBe(30000.0)
        ->and($transaction->calculatedSaleProfit())->toBe(800000.0)
        ->and($transaction->calculatedTotalProfit())->toBe(950000.0)
        ->and((float) $transaction->gross_profit)->toBe(800000.0)
        ->and((float) $transaction->net_profit)->toBe(720000.0);

    $this->get(route('transactions.show', $transaction))
        ->assertOk()
        ->assertSee('Kalkulasi Laba Penjualan dan Ripan')
        ->assertSee('Total laba baru')
        ->assertSee('Harga jual − Total modal')
        ->assertSee('950,000');

    $this->get(route('transactions.edit', $transaction))
        ->assertOk()
        ->assertDontSee('class="marketing-table"', false)
        ->assertSee('Harga setor')
        ->assertSee('Harga buka')
        ->assertSee('name="setor_price"', false)
        ->assertSee('value="1000000"', false)
        ->assertSee('name="opening_price"', false)
        ->assertSee('value="1500000"', false)
        ->assertSee('name="leasing_ripan"', false)
        ->assertSee('name="insurance_ripan"', false)
        ->assertSee('name="booster_ripan"', false);
});

test('it displays transaction values under the matching list columns', function () {
    $this->post(route('transactions.store'), [
        'plate_number' => 'B 9876 XYZ',
        'manager_name' => 'Rina Manager',
        'chassis_number' => 'CHASSIS-9876',
        'engine_number' => 'ENGINE-9876',
        'mileage_km' => 98765,
        'brand' => 'HONDA',
        'model' => 'CIVIC',
        'year' => 2020,
        'transmission' => 'AUTOMATIC',
        'color' => 'SILVER',
        'owner_name' => 'Nama STNK Uji',
        'buy_date' => '2026-10-01',
        'sell_date' => '2026-10-15',
        'buy_price' => 500000000,
        'purchase_operational' => 1000000,
        'other_costs' => 2000000,
        'setor_price' => 550000000,
        'opening_price' => 575000000,
        'selling_price' => 600000000,
        'down_payment' => 100000000,
        'settlement_amount' => 200000000,
        'leasing_disbursement' => 250000000,
        'leasing_ripan' => 11000000,
        'insurance_ripan' => 12000000,
        'booster_ripan' => 13000000,
        'tax_items' => [
            ['name' => 'Pajak List Uji', 'date' => '2026-10-02', 'cost' => 500000, 'payment_status' => 'lunas'],
        ],
        'additional_repairs' => [
            ['name' => 'Perbaikan List Uji', 'date' => '2026-10-03', 'cost' => 750000, 'payment_status' => 'panjar'],
        ],
        'intertine_expenses' => [
            ['name' => 'KR Jual', 'cost' => 100000],
            ['name' => 'OP Survei', 'cost' => 200000],
            ['name' => 'Fee CMO', 'cost' => 300000],
        ],
    ])->assertRedirect(route('transactions.index'));

    $this->get(route('transactions.index'))
        ->assertOk()
        ->assertSee('class="data-table transaction-list-table"', false)
        ->assertSee('<th>Kendaraan</th>', false)
        ->assertSee('<th>Status</th>', false)
        ->assertSee('<th>Tanggal beli</th>', false)
        ->assertSee('<th class="numeric">Modal beli</th>', false)
        ->assertSee('<th class="numeric">Total modal</th>', false)
        ->assertSee('<th class="numeric">Harga jual</th>', false)
        ->assertSee('<th class="numeric">Harga stor</th>', false)
        ->assertSee('<th class="numeric">Harga deal</th>', false)
        ->assertSee('<th class="numeric">Laba jual</th>', false)
        ->assertSee('<th class="numeric">Laba stor</th>', false)
        ->assertSee('<th class="numeric">Laba deal</th>', false)
        ->assertSee('<th>Aksi</th>', false)
        ->assertSee('aria-label="Lihat nota B 9876 XYZ"', false)
        ->assertSee('aria-label="Edit transaksi B 9876 XYZ"', false)
        ->assertSee('aria-label="Hapus transaksi B 9876 XYZ"', false)
        ->assertSee('B 9876 XYZ')
        ->assertSee('600,000,000')
        ->assertSee('550,000,000')
        ->assertSee('575,000,000')
        ->assertSee('95,750,000')
        ->assertSee('45,750,000')
        ->assertSee('131,750,000');
});

test('it stores the fixed tax fields as separate tax items and includes them in the capital total', function () {
    $response = $this->post(route('transactions.store'), [
        'plate_number' => 'B 1234 CD',
        'brand' => 'TOYOTA',
        'model' => 'INNOVA',
        'year' => 2014,
        'transmission' => 'MANUAL',
        'color' => 'HITAM',
        'buy_date' => '2026-10-12',
        'buy_price' => 100000.01,
        'purchase_operational' => 5000.02,
        'other_costs' => 6000.03,
        'selling_price' => 200000.36,
        'tax_items' => [
            0 => ['name' => 'Pajak', 'date' => '2026-10-10', 'cost' => 10000.04],
            1 => ['name' => 'BBN', 'date' => '2026-10-11', 'cost' => 20000.05],
            2 => ['name' => 'ETLE', 'cost' => 30000.06],
        ],
        'intertine_expenses' => [
            ['name' => 'Administrasi', 'date' => '2026-10-12', 'cost' => 0.01],
        ],
    ]);

    $response->assertRedirect(route('transactions.index'));

    $transaction = CarTransaction::query()->firstOrFail();
    expect(array_column($transaction->tax_items, 'name'))->toBe(['Pajak', 'BBN', 'ETLE'])
        ->and((float) $transaction->total_capital)->toBe(171000.21)
        ->and((float) $transaction->gross_profit)->toBe(29000.15)
        ->and((float) $transaction->showroom_fee)->toBe(2900.02)
        ->and((float) $transaction->total_intertine_expense)->toBe(2900.03)
        ->and((float) $transaction->net_profit)->toBe(26100.12);
});

test('it recalculates showroom fee when an existing transaction is updated', function () {
    $transaction = CarTransaction::create([
        'plate_number' => 'B 1234 CD',
        'brand' => 'TOYOTA',
        'model' => 'INNOVA',
        'year' => 2014,
        'transmission' => 'MANUAL',
        'color' => 'HITAM',
        'buy_date' => '2026-10-12',
        'buy_price' => 100000,
        'selling_price' => 200000,
    ]);

    expect((float) $transaction->showroom_fee)->toBe(10000.0);

    $response = $this->put(route('transactions.update', $transaction), [
        'plate_number' => 'B 1234 CD',
        'brand' => 'TOYOTA',
        'model' => 'INNOVA',
        'year' => 2014,
        'transmission' => 'MANUAL',
        'color' => 'HITAM',
        'buy_date' => '2026-10-12',
        'buy_price' => 100000,
        'selling_price' => 300000,
    ]);

    $response->assertRedirect(route('transactions.index'));
    expect((float) $transaction->refresh()->showroom_fee)->toBe(20000.0)
        ->and((float) $transaction->net_profit)->toBe(180000.0);
});

test('it preserves existing tax items when the fixed cost fields are submitted on edit', function () {
    $transaction = CarTransaction::create([
        'plate_number' => 'B 1234 CD',
        'brand' => 'TOYOTA',
        'model' => 'INNOVA',
        'year' => 2014,
        'transmission' => 'MANUAL',
        'color' => 'HITAM',
        'buy_date' => '2026-10-12',
        'buy_price' => 100000,
        'tax_items' => [
            ['name' => 'Pajak Tahunan', 'date' => '2026-10-10', 'cost' => 50000, 'payment_status' => 'lunas'],
        ],
    ]);

    $response = $this->put(route('transactions.update', $transaction), [
        'plate_number' => 'B 1234 CD',
        'brand' => 'TOYOTA',
        'model' => 'INNOVA',
        'year' => 2014,
        'transmission' => 'MANUAL',
        'color' => 'HITAM',
        'buy_date' => '2026-10-12',
        'buy_price' => 100000,
        'other_costs' => 0,
        'tax_items' => [
            0 => ['name' => 'Pajak Tahunan', 'date' => '2026-10-10', 'cost' => 50000, 'payment_status' => 'lunas'],
            1 => ['name' => 'BBN', 'cost' => '', 'date' => '', 'payment_status' => '', 'payment_note' => ''],
            2 => ['name' => 'ETLE', 'cost' => '', 'payment_status' => '', 'payment_note' => ''],
            3 => ['name' => 'Balik Nama', 'date' => '2026-10-11', 'cost' => 25000, 'payment_status' => 'belum_lunas'],
        ],
    ]);

    $response->assertRedirect(route('transactions.index'));
    expect($transaction->refresh()->tax_items)->toHaveCount(2)
        ->and($transaction->tax_items[0]['name'])->toBe('Pajak Tahunan')
        ->and($transaction->tax_items[3]['name'])->toBe('Balik Nama')
        ->and((float) $transaction->total_capital)->toBe(175000.0);
});

test('it rejects additional repair items without a valid cost', function () {
    $response = $this->from(route('transactions.create'))->post(route('transactions.store'), [
        'plate_number' => 'B 1234 CD',
        'brand' => 'TOYOTA',
        'model' => 'INNOVA',
        'year' => 2014,
        'transmission' => 'MANUAL',
        'color' => 'HITAM',
        'buy_date' => '2026-10-12',
        'buy_price' => 500000,
        'additional_repairs' => [
            ['name' => 'Ketok Pintu', 'date' => '2026-10-11', 'cost' => -1, 'payment_status' => 'lunas'],
        ],
    ]);

    $response->assertRedirect(route('transactions.create'));
    $response->assertSessionHasErrors('additional_repairs.0.cost');
    expect(CarTransaction::query()->count())->toBe(0);
});

test('it rejects unrecognized keys in repeatable transaction items', function () {
    $response = $this->from(route('transactions.create'))->post(route('transactions.store'), [
        'plate_number' => 'B 1234 CD',
        'brand' => 'TOYOTA',
        'model' => 'INNOVA',
        'year' => 2014,
        'transmission' => 'MANUAL',
        'color' => 'HITAM',
        'buy_date' => '2026-10-12',
        'buy_price' => 500000,
        'additional_repairs' => [
            [
                'name' => 'Ketok Pintu',
                'date' => '2026-10-11',
                'cost' => 200000,
                'payment_status' => 'lunas',
                'unexpected' => 'unvalidated data',
            ],
        ],
    ]);

    $response->assertRedirect(route('transactions.create'))
        ->assertSessionHasErrors('additional_repairs.0');

    expect(CarTransaction::query()->count())->toBe(0);
});

test('it stores additional repairs with other payment status and no note', function () {
    $response = $this->post(route('transactions.store'), [
        'plate_number' => 'B 1234 CD',
        'brand' => 'TOYOTA',
        'model' => 'INNOVA',
        'year' => 2014,
        'transmission' => 'MANUAL',
        'color' => 'HITAM',
        'buy_date' => '2026-10-12',
        'buy_price' => 500000,
        'additional_repairs' => [
            ['name' => 'Ketok Pintu', 'date' => '2026-10-11', 'cost' => 200000, 'payment_status' => 'lainnya'],
        ],
    ]);

    $response->assertRedirect(route('transactions.index'));

    $transaction = CarTransaction::query()->firstOrFail();
    expect($transaction->additional_repairs[0]['payment_status'])->toBe('lainnya')
        ->and($transaction->additional_repairs[0]['payment_note'] ?? null)->toBeNull();
});
