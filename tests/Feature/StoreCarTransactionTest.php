<?php

use App\Models\CarTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->master()->create());
});

test('the transaction creation route serves the SPA shell', function () {
    $this->get('/app/transactions/create')
        ->assertOk()
        ->assertSee('id="app"', false)
        ->assertSee('assets/spa-', false);
});

test('the transaction creation SPA route serves the shared shell', function () {
    $this->get('/app/transactions/create')
        ->assertOk()
        ->assertSee('id="app"', false)
        ->assertSee('window.spaUser', false);
});

test('the transaction form deep link resolves to the SPA shell', function () {
    $this->get('/app/transactions/create')
        ->assertOk()
        ->assertSee('id="app"', false);
});

test('it requires and stores a planned payment date when a payment balance remains', function () {
    $payload = [
        'plate_number' => 'B 1234 CD',
        'nama_penjual' => 'Penjual Uji',
        'brand' => 'TOYOTA',
        'model' => 'INNOVA',
        'year' => 2014,
        'transmission' => 'MANUAL',
        'color' => 'HITAM',
        'buy_date' => '2026-10-12',
        'buy_price' => 500000,
        'selling_price' => 1000000,
        'down_payment' => 250000,
        'settlement_amount' => 100000,
        'leasing_disbursement' => 0,
    ];

    $this->from(route('transactions.create'))
        ->post(route('transactions.store'), $payload)
        ->assertRedirect(route('transactions.create'))
        ->assertSessionHasErrors('planned_payment_date');

    expect(CarTransaction::query()->count())->toBe(0);

    $this->post(route('transactions.store'), [
        ...$payload,
        'planned_payment_date' => '2026-11-20',
    ])->assertRedirect(route('transactions.index'));

    $transaction = CarTransaction::query()->sole();

    expect($transaction->planned_payment_date)->toBe('2026-11-20')
        ->and((float) $transaction->remaining_payment)->toBe(650000.0);

    $this->post(route('transactions.store'), [
        ...$payload,
        'plate_number' => 'B 5678 EF',
        'down_payment' => 1000000,
    ])->assertRedirect(route('transactions.index'));

    $fullyPaidTransaction = CarTransaction::query()
        ->where('plate_number', 'B 5678 EF')
        ->sole();

    expect($fullyPaidTransaction->planned_payment_date)->toBeNull()
        ->and((float) $fullyPaidTransaction->remaining_payment)->toBe(0.0);
});

test('the transaction form uses the compiled SPA entry point', function () {
    $this->get('/app/transactions/create')
        ->assertOk()
        ->assertSee('assets/spa-', false)
        ->assertSee('id="app"', false);
});

test('the transaction form SPA page remains available for expense entry', function () {
    $this->get('/app/transactions/create')
        ->assertOk()
        ->assertSee('id="app"', false);

    expect(Schema::hasColumn('car_transactions', 'intertine_expenses'))->toBeTrue()
        ->and(Schema::hasColumn('car_transactions', 'total_intertine_expense'))->toBeTrue()
        ->and(Schema::hasColumn('car_transactions', 'internal_expenses'))->toBeFalse()
        ->and(Schema::hasColumn('car_transactions', 'total_internal_expense'))->toBeFalse();
});

test('transaction list and create routes enter their SPA pages', function () {
    $this->get(route('transactions.index'))->assertRedirect('/app/dashboard');
    $this->get(route('transactions.create'))->assertRedirect('/app/transactions/create');
    $this->get('/app/dashboard')->assertOk()->assertSee('id="app"', false);
    $this->get('/app/transactions/create')->assertOk()->assertSee('id="app"', false);
});

test('the dashboard API separates inventory and sales and reports sold totals', function () {
    CarTransaction::create([
        'plate_number' => 'B 1111 STK',
        'brand' => 'Toyota',
        'model' => 'Avanza',
        'year' => 2021,
        'transmission' => 'Automatic',
        'color' => 'Hitam',
        'buy_date' => '2026-09-01',
        'buy_price' => 50000000,
    ]);

    CarTransaction::create([
        'plate_number' => 'B 2222 SLD',
        'brand' => 'Honda',
        'model' => 'Civic',
        'year' => 2022,
        'transmission' => 'Automatic',
        'color' => 'Putih',
        'buy_date' => '2026-08-01',
        'sell_date' => '2026-09-15',
        'buy_price' => 100000000,
        'selling_price' => 120000000,
    ]);

    $this->getJson(route('api.spa.dashboard', ['view' => 'inventory']))
        ->assertOk()
        ->assertJsonPath('data.0.plate_number', 'B 1111 STK')
        ->assertJsonPath('data.0.status', 'stock')
        ->assertJsonCount(1, 'data');

    $this->getJson(route('api.spa.dashboard', ['view' => 'sales']))
        ->assertOk()
        ->assertJsonPath('data.0.plate_number', 'B 2222 SLD')
        ->assertJsonPath('data.0.status', 'sold')
        ->assertJsonPath('metrics.sold_revenue', 120000000)
        ->assertJsonPath('metrics.sold_capital', 100000000);
});

test('the purchase transaction form route serves the SPA mount point', function () {
    $this->get('/app/transactions/create')
        ->assertOk()
        ->assertSee('id="app"', false);
});

test('the transaction creation SPA route remains available for optional tax data', function () {
    $this->get('/app/transactions/create')
        ->assertOk()
        ->assertSee('id="app"', false);
});

test('transaction form fields are rendered by the client-side SPA', function () {
    $response = $this->get('/app/transactions/create');

    $response->assertOk()
        ->assertSee('id="app"', false)
        ->assertDontSee('name="plate_number"', false)
        ->assertDontSee('name="buy_price"', false);
});

test('it stores leasing simulations and restores their values when editing a transaction', function () {
    $leasingOptions = [
        [
            'name' => 'BCA',
            'disbursement' => 93000000,
            'selling_price' => 150000000,
            'dp' => 57000000,
            'installment_3yr' => 2300000,
            'installment_4yr' => 2150000,
            'installment_5yr' => 2000000,
        ],
        [
            'name' => 'GARUDA FINANCE',
            'disbursement' => 85000000,
            'selling_price' => 150000000,
            'dp' => 65000000,
            'installment_3yr' => 2100000,
            'installment_4yr' => 1950000,
            'installment_5yr' => 1800000,
        ],
    ];

    $response = $this->post(route('transactions.store'), [
        'plate_number' => 'B 1234 CD',
        'nama_penjual' => 'Penjual Uji',
        'brand' => 'TOYOTA',
        'model' => 'INNOVA',
        'year' => 2014,
        'transmission' => 'MANUAL',
        'color' => 'HITAM',
        'buy_date' => '2026-10-12',
        'buy_price' => 500000,
        'leasing_options' => [...$leasingOptions, ['name' => '']],
    ]);

    $response->assertRedirect(route('transactions.index'));

    $transaction = CarTransaction::query()->firstOrFail();
    $this->assertModelExists($transaction);
    expect($transaction->leasing_options)->toBe($leasingOptions);

    $this->getJson(route('api.spa.transactions.show', $transaction))
        ->assertOk()
        ->assertJsonPath('data.leasing_options', $leasingOptions);
});

test('transaction form route loads its compiled SPA page for leasing inputs', function () {
    $this->get('/app/transactions/create')
        ->assertOk()
        ->assertSee('assets/spa-', false)
        ->assertSee('id="app"', false);
});

test('transaction API returns custom leasing names as JSON data', function () {
    $transaction = CarTransaction::create([
        'plate_number' => 'B 5678 SEC',
        'brand' => 'Honda',
        'model' => 'Civic',
        'year' => 2022,
        'transmission' => 'Automatic',
        'color' => 'Putih',
        'buy_date' => '2026-10-01',
        'leasing_options' => [['name' => '<script>alert(1)</script>']],
    ]);

    $this->getJson(route('api.spa.transactions.show', $transaction))
        ->assertOk()
        ->assertJsonPath('data.leasing_options.0.name', '<script>alert(1)</script>');
});

test('transaction creation shell does not server-render form values', function () {
    $this->get('/app/transactions/create')
        ->assertOk()
        ->assertSee('id="app"', false)
        ->assertDontSee('name="plate_number"', false);
});

test('it rejects negative leasing amounts without saving a transaction', function () {
    $response = $this->from(route('transactions.create'))->post(route('transactions.store'), [
        'plate_number' => 'B 1234 CD',
        'nama_penjual' => 'Penjual Uji',
        'brand' => 'TOYOTA',
        'model' => 'INNOVA',
        'year' => 2014,
        'transmission' => 'MANUAL',
        'color' => 'HITAM',
        'buy_date' => '2026-10-12',
        'buy_price' => 500000,
        'leasing_options' => [
            ['name' => 'BCA', 'dp' => -1],
        ],
    ]);

    $response->assertRedirect(route('transactions.create'))
        ->assertSessionHasErrors('leasing_options.0.dp');
    $this->assertDatabaseCount('car_transactions', 0);
});

test('it rejects unrecognized leasing fields without saving a transaction', function () {
    $response = $this->from(route('transactions.create'))->post(route('transactions.store'), [
        'plate_number' => 'B 1234 CD',
        'nama_penjual' => 'Penjual Uji',
        'brand' => 'TOYOTA',
        'model' => 'INNOVA',
        'year' => 2014,
        'transmission' => 'MANUAL',
        'color' => 'HITAM',
        'buy_date' => '2026-10-12',
        'buy_price' => 500000,
        'leasing_options' => [
            ['name' => 'BCA', 'dp' => 1000000, 'total_capital' => 999999999],
        ],
    ]);

    $response->assertRedirect(route('transactions.create'))
        ->assertSessionHasErrors('leasing_options.0');
    $this->assertDatabaseCount('car_transactions', 0);
});

test('it rejects leasing information beyond the per-transaction limit', function () {
    $leasingOptions = collect(range(1, 51))
        ->map(fn ($index) => ['name' => "Leasing {$index}"])
        ->all();

    $response = $this->from(route('transactions.create'))->post(route('transactions.store'), [
        'plate_number' => 'B 1234 CD',
        'nama_penjual' => 'Penjual Uji',
        'brand' => 'TOYOTA',
        'model' => 'INNOVA',
        'year' => 2014,
        'transmission' => 'MANUAL',
        'color' => 'HITAM',
        'buy_date' => '2026-10-12',
        'buy_price' => 500000,
        'leasing_options' => $leasingOptions,
    ]);

    $response->assertRedirect(route('transactions.create'))
        ->assertSessionHasErrors('leasing_options');
    $this->assertDatabaseCount('car_transactions', 0);
});

test('the SPA transaction form route is available for calculated fields', function () {
    $this->get('/app/transactions/create')
        ->assertOk()
        ->assertSee('id="app"', false);
});

test('it ignores the untouched empty repair row when saving a transaction', function () {
    $response = $this->post(route('transactions.store'), [
        'plate_number' => 'B 1234 CD',
        'nama_penjual' => 'Penjual Uji',
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
        'nama_penjual' => 'Penjual Uji',
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

test('it stores zero for empty numeric inputs and costs on populated expense rows', function () {
    $response = $this->post(route('transactions.store'), [
        'plate_number' => 'B 1234 CD',
        'nama_penjual' => 'Penjual Uji',
        'brand' => 'TOYOTA',
        'model' => 'INNOVA',
        'year' => '',
        'transmission' => 'MANUAL',
        'color' => 'HITAM',
        'buy_date' => '2026-10-12',
        'buy_price' => '',
        'mileage_km' => '',
        'additional_repairs' => [
            ['name' => 'Perbaikan jok', 'date' => '2026-10-11', 'cost' => '', 'payment_status' => 'lunas'],
        ],
        'tax_items' => [
            ['name' => 'Pajak', 'date' => '2026-10-10', 'cost' => ''],
        ],
        'intertine_expenses' => [
            ['name' => 'KR Jual', 'cost' => ''],
        ],
    ]);

    $response->assertRedirect(route('transactions.index'));

    $transaction = CarTransaction::query()->firstOrFail();
    $numericFields = [
        'year',
        'mileage_km',
        'buy_price',
        'purchase_operational',
        'other_costs',
        'selling_price',
        'down_payment',
        'settlement_amount',
        'leasing_disbursement',
        'setor_price',
        'opening_price',
        'leasing_ripan',
        'insurance_ripan',
        'booster_ripan',
    ];

    $this->assertDatabaseHas('car_transactions', ['id' => $transaction->id] + array_fill_keys($numericFields, 0));

    $mileageColumn = collect(Schema::getColumns('car_transactions'))
        ->firstWhere('name', 'mileage_km');

    expect($transaction->additional_repairs[0]['cost'])->toBe(0)
        ->and($transaction->tax_items[0]['cost'])->toBe(0)
        ->and($transaction->intertine_expenses[0]['cost'])->toBe(0)
        ->and($mileageColumn['nullable'])->toBeFalse()
        ->and((int) $mileageColumn['default'])->toBe(0);
});

test('it stores zero when the primary tax cost and date are left empty', function () {
    $response = $this->post(route('transactions.store'), [
        'plate_number' => 'B 1234 CD',
        'nama_penjual' => 'Penjual Uji',
        'brand' => 'TOYOTA',
        'model' => 'INNOVA',
        'year' => 2014,
        'transmission' => 'MANUAL',
        'color' => 'HITAM',
        'buy_date' => '2026-10-12',
        'buy_price' => 500000,
        'tax_items' => [
            ['name' => 'Pajak', 'date' => '', 'cost' => ''],
        ],
    ]);

    $response->assertRedirect(route('transactions.index'));

    $transaction = CarTransaction::query()->firstOrFail();
    expect($transaction->tax_items)->toBe([
        ['name' => 'Pajak', 'date' => null, 'cost' => 0],
    ]);
});

test('it stores payment dates and displays them on the transaction receipt', function () {
    $response = $this->post(route('transactions.store'), [
        'plate_number' => 'B 1234 CD',
        'nama_penjual' => 'Penjual Uji',
        'brand' => 'TOYOTA',
        'model' => 'INNOVA',
        'year' => 2014,
        'transmission' => 'MANUAL',
        'color' => 'HITAM',
        'buy_date' => '2026-10-12',
        'buy_price' => 500000,
        'down_payment' => 100000,
        'down_payment_date' => '2026-10-13',
        'settlement_amount' => 200000,
        'settlement_date' => '2026-10-14',
        'leasing_disbursement' => 150000,
        'leasing_disbursement_date' => '2026-10-15',
    ]);

    $response->assertRedirect(route('transactions.index'));

    $transaction = CarTransaction::query()->firstOrFail();
    $this->assertDatabaseHas('car_transactions', [
        'id' => $transaction->id,
        'down_payment_date' => '2026-10-13',
        'settlement_date' => '2026-10-14',
        'leasing_disbursement_date' => '2026-10-15',
    ]);

    $this->getJson(route('api.spa.transactions.show', $transaction))
        ->assertOk()
        ->assertJsonPath('data.down_payment_date', '2026-10-13')
        ->assertJsonPath('data.settlement_date', '2026-10-14')
        ->assertJsonPath('data.leasing_disbursement_date', '2026-10-15');
});

test('the transaction detail API returns the sale price references', function () {
    $transaction = CarTransaction::create([
        'plate_number' => 'B 1234 CD',
        'brand' => 'TOYOTA',
        'model' => 'INNOVA',
        'year' => 2014,
        'transmission' => 'MANUAL',
        'color' => 'HITAM',
        'buy_date' => '2026-10-12',
        'buy_price' => 500000,
        'setor_price' => 1000000,
        'opening_price' => 1200000,
        'selling_price' => 1100000,
        'leasing_ripan' => 20000,
        'insurance_ripan' => 10000,
        'booster_ripan' => 5000,
    ]);

    $this->getJson(route('api.spa.transactions.show', $transaction))
        ->assertOk()
        ->assertJsonPath('data.setor_price', 1000000)
        ->assertJsonPath('data.opening_price', 1200000)
        ->assertJsonPath('data.selling_price', 1100000)
        ->assertJsonPath('data.leasing_ripan', 20000)
        ->assertJsonPath('data.insurance_ripan', 10000)
        ->assertJsonPath('data.booster_ripan', 5000);
});

test('the transaction detail API returns leasing prices as vehicle data', function () {
    $leasingOptions = [
        [
            'name' => 'BCA',
            'disbursement' => 93000000,
            'selling_price' => 150000000,
            'dp' => 57000000,
            'installment_3yr' => 2300000,
            'installment_4yr' => 2150000,
            'installment_5yr' => 2000000,
        ],
        [
            'name' => 'MUF',
            'disbursement' => null,
            'selling_price' => null,
            'dp' => null,
            'installment_3yr' => null,
            'installment_4yr' => null,
            'installment_5yr' => null,
        ],
    ];
    $transaction = CarTransaction::create([
        'plate_number' => 'B 1234 CD',
        'brand' => 'TOYOTA',
        'model' => 'INNOVA',
        'year' => 2014,
        'transmission' => 'MANUAL',
        'color' => 'HITAM',
        'buy_date' => '2026-10-12',
        'buy_price' => 500000,
        'leasing_options' => $leasingOptions,
    ]);

    $this->getJson(route('api.spa.transactions.show', $transaction))
        ->assertOk()
        ->assertJsonPath('data.leasing_options', $leasingOptions);
});

test('it shows an empty state when a transaction has no leasing prices', function () {
    $transaction = CarTransaction::create([
        'plate_number' => 'B 1234 CD',
        'brand' => 'TOYOTA',
        'model' => 'INNOVA',
        'year' => 2014,
        'transmission' => 'MANUAL',
        'color' => 'HITAM',
        'buy_date' => '2026-10-12',
        'buy_price' => 500000,
    ]);

    $this->getJson(route('api.spa.transactions.show', $transaction))
        ->assertOk()
        ->assertJsonPath('data.leasing_options', null);
});

test('it saves manual intertine expense fields and calculates the net profit', function () {
    $response = $this->post(route('transactions.store'), [
        'plate_number' => 'B 1234 CD',
        'nama_penjual' => 'Penjual Uji',
        'brand' => 'TOYOTA',
        'model' => 'INNOVA',
        'year' => 2014,
        'transmission' => 'MANUAL',
        'color' => 'HITAM',
        'buy_date' => '2026-10-12',
        'buy_price' => 500000,
        'selling_price' => 1000000,
        'planned_payment_date' => '2026-11-20',
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
        'nama_penjual' => 'Penjual Uji',
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
        'planned_payment_date' => '2026-11-20',
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

    $this->getJson(route('api.spa.transactions.show', $transaction))
        ->assertOk()
        ->assertJsonPath('data.additional_repairs.0.name', 'Ketok Pintu')
        ->assertJsonPath('data.additional_repairs.0.date', '2026-10-11')
        ->assertJsonPath('data.additional_repairs.0.cost', 200000)
        ->assertJsonPath('data.additional_repairs.0.payment_status', 'panjar')
        ->assertJsonPath('data.additional_repairs.1.payment_note', 'Menunggu tagihan')
        ->assertJsonPath('data.tax_items.0.name', 'Pajak Tahunan')
        ->assertJsonPath('data.tax_items.1.payment_status', 'belum_lunas')
        ->assertJsonPath('data.chassis_number', 'FM3245JHK56854')
        ->assertJsonPath('data.engine_number', 'FM2001531')
        ->assertJsonPath('data.mileage_km', 125000);

    $this->get("/app/transactions/{$transaction->id}/edit")
        ->assertOk()
        ->assertSee('id="app"', false);
});

test('it calculates sale profit from selling price minus total capital and adds all ripan', function () {
    $response = $this->post(route('transactions.store'), [
        'plate_number' => 'B 1234 CD',
        'nama_penjual' => 'Penjual Uji',
        'brand' => 'TOYOTA',
        'model' => 'INNOVA',
        'year' => 2014,
        'transmission' => 'MANUAL',
        'color' => 'HITAM',
        'buy_date' => '2026-10-12',
        'buy_price' => 400000,
        'selling_price' => 1200000,
        'planned_payment_date' => '2026-11-20',
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

    $this->getJson(route('api.spa.transactions.show', $transaction))
        ->assertOk()
        ->assertJsonPath('data.gross_profit', 800000)
        ->assertJsonPath('data.net_profit', 720000)
        ->assertJsonPath('data.leasing_ripan', 100000)
        ->assertJsonPath('data.insurance_ripan', 20000)
        ->assertJsonPath('data.booster_ripan', 30000);

    $this->get("/app/transactions/{$transaction->id}/edit")
        ->assertOk()
        ->assertSee('id="app"', false);
});

test('it displays transaction values under the matching list columns', function () {
    $this->post(route('transactions.store'), [
        'plate_number' => 'B 9876 XYZ',
        'nama_penjual' => 'Penjual Uji',
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
        'planned_payment_date' => '2026-11-20',
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
        ->assertRedirect('/app/dashboard');

    $this->getJson(route('api.spa.dashboard'))
        ->assertOk()
        ->assertJsonPath('data.0.plate_number', 'B 9876 XYZ')
        ->assertJsonPath('data.0.selling_price', 600000000)
        ->assertJsonPath('data.0.setor_price', 550000000)
        ->assertJsonPath('data.0.opening_price', 575000000)
        ->assertJsonPath('data.0.gross_profit', 95750000)
        ->assertJsonPath('data.0.setor_profit', 45750000)
        ->assertJsonPath('data.0.total_profit', 131750000)
        ->assertJsonPath('data.0.can_update', true)
        ->assertJsonPath('data.0.can_delete', true);
});

test('it stores the fixed tax fields as separate tax items and includes them in the capital total', function () {
    $response = $this->post(route('transactions.store'), [
        'plate_number' => 'B 1234 CD',
        'nama_penjual' => 'Penjual Uji',
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
        'planned_payment_date' => '2026-11-20',
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
        'nama_penjual' => 'Penjual Uji',
        'brand' => 'TOYOTA',
        'model' => 'INNOVA',
        'year' => 2014,
        'transmission' => 'MANUAL',
        'color' => 'HITAM',
        'buy_date' => '2026-10-12',
        'buy_price' => 100000,
        'selling_price' => 300000,
        'planned_payment_date' => '2026-11-20',
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
        'nama_penjual' => 'Penjual Uji',
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
        'nama_penjual' => 'Penjual Uji',
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

test('it rejects values outside the database column ranges without saving a transaction', function () {
    $this->from(route('transactions.create'))
        ->post(route('transactions.store'), [
            'plate_number' => 'B 1234 RANGE',
            'nama_penjual' => 'Penjual Uji',
            'brand' => 'TOYOTA',
            'model' => 'INNOVA',
            'year' => 1900,
            'transmission' => 'MANUAL',
            'color' => 'HITAM',
            'buy_date' => '2026-10-12',
            'buy_price' => 10000000000000,
            'mileage_km' => 4294967296,
        ])
        ->assertRedirect(route('transactions.create'))
        ->assertSessionHasErrors(['year', 'buy_price', 'mileage_km']);

    $this->assertDatabaseCount('car_transactions', 0);
});

test('it rejects calculated transaction totals beyond decimal column precision', function () {
    $this->from(route('transactions.create'))
        ->post(route('transactions.store'), [
            'plate_number' => 'B 1234 TOTAL',
            'nama_penjual' => 'Penjual Uji',
            'brand' => 'TOYOTA',
            'model' => 'INNOVA',
            'year' => 2014,
            'transmission' => 'MANUAL',
            'color' => 'HITAM',
            'buy_date' => '2026-10-12',
            'buy_price' => 9000000000000,
            'additional_repairs' => [
                [
                    'name' => 'Perbaikan',
                    'date' => '2026-10-12',
                    'cost' => 1000000000000,
                    'payment_status' => 'lunas',
                ],
            ],
        ])
        ->assertRedirect(route('transactions.create'))
        ->assertSessionHasErrors('buy_price');

    $this->assertDatabaseCount('car_transactions', 0);
});
