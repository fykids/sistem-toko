<?php

use App\Models\CarTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->master()->create());
});

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

test('the transaction creation page uses the global topbar and drawer without changing the form', function () {
    $this->get(route('transactions.create'))
        ->assertOk()
        ->assertSee('<header class="showroom-topbar">', false)
        ->assertSee('data-app-menu-toggle', false)
        ->assertSee('aria-controls="app-navigation"', false)
        ->assertSee('id="app-navigation"', false)
        ->assertSee('Transaksi Baru')
        ->assertSee('Logout')
        ->assertSee('class="transaction-form transaction-form--master"', false);
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
    $vehicleSectionStart = strpos($content, '<section class="form-section vehicle-section">');
    $costSectionStart = strpos($content, '<section class="form-section costs-section">');
    $marketingSectionStart = strpos($content, '<section class="form-section marketing-section">');
    $salesSectionStart = strpos($content, '<section class="form-section sales-section">');
    $salesSectionEnd = strpos($content, '</section>', $salesSectionStart);
    $salesSection = substr($content, $salesSectionStart, $salesSectionEnd - $salesSectionStart);

    expect($vehicleSectionStart)->not->toBeFalse()
        ->and($costSectionStart)->toBeGreaterThan($vehicleSectionStart)
        ->and($marketingSectionStart)->toBeGreaterThan($costSectionStart)
        ->and($salesSectionStart)->toBeGreaterThan($marketingSectionStart)
        ->and($salesSection)
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
        ->assertSee('class="showroom-primary-action"', false)
        ->assertSee('Transaksi baru', false);

    expect(substr_count($response->getContent(), 'href="'.route('transactions.create').'"'))->toBe(1);

    $this->get(route('transactions.create'))
        ->assertOk()
        ->assertDontSee('class="showroom-primary-action"', false);
});

test('it separates inventory and sales views and reports sold vehicle totals', function () {
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

    $response = $this->get(route('transactions.index'));

    $response->assertOk()
        ->assertSee('data-dashboard-nav="inventory"', false)
        ->assertSee('data-dashboard-nav="sales"', false)
        ->assertSee('data-dashboard-nav="reports"', false)
        ->assertSee('id="inventaris"', false)
        ->assertSee('id="penjualan"', false)
        ->assertSee('id="laporan"', false)
        ->assertSee('data-status="stock"', false)
        ->assertSee('data-status="sold"', false)
        ->assertSee('Kendaraan terjual')
        ->assertSee('Rp 120,000,000')
        ->assertSee('Rp 100,000,000')
        ->assertSee('Rp 18,000,000');
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

test('it renders the optional primary tax cost empty with a zero placeholder', function () {
    $response = $this->get(route('transactions.create'));

    $response->assertOk();
    preg_match('/<input id="tax-price"[^>]*>/', $response->getContent(), $matches);

    expect($matches)->not->toBeEmpty()
        ->and($matches[0])->toContain('placeholder="Rp 0"')
        ->not->toContain('value="0"')
        ->not->toContain('required');
});

test('it renders empty vehicle and sales fields with illustrative placeholders', function () {
    $response = $this->get(route('transactions.create'));

    $response->assertOk()
        ->assertSee('id="leasing-vehicle-name">Lengkapi data kendaraan</strong>', false);

    $content = $response->getContent();
    $placeholders = [
        'plate_number' => 'Contoh: B 1234 CD',
        'brand' => 'Contoh: Toyota',
        'model' => 'Contoh: Innova Diesel G',
        'year' => 'Contoh: 2014',
        'transmission' => 'Contoh: Manual',
        'color' => 'Contoh: Hitam',
        'chassis_number' => 'Masukkan nomor rangka',
        'engine_number' => 'Masukkan nomor mesin',
        'mileage_km' => 'Contoh: 85000',
        'owner_name' => 'Nama pemilik pada STNK',
        'manager_name' => 'Nama penanggung jawab',
        'buy_price' => 'Rp 0',
        'purchase_operational' => 'Rp 0',
        'other_costs' => 'Rp 0',
        'tax_items[0][cost]' => 'Rp 0',
        'tax_items[1][cost]' => 'Rp 0',
        'tax_items[2][cost]' => 'Rp 0',
        'additional_repairs[0][name]' => 'Nama perbaikan',
        'additional_repairs[0][cost]' => 'Rp 0',
        'setor_price' => 'Rp 0',
        'opening_price' => 'Rp 0',
        'selling_price' => 'Rp 0',
        'down_payment' => 'Rp 0',
        'settlement_amount' => 'Rp 0',
        'leasing_disbursement' => 'Rp 0',
        'leasing_ripan' => 'Rp 0',
        'insurance_ripan' => 'Rp 0',
        'booster_ripan' => 'Rp 0',
        'intertine_expenses[0][cost]' => 'Rp 0',
        'intertine_expenses[1][cost]' => 'Rp 0',
        'intertine_expenses[2][cost]' => 'Rp 0',
        'leasing_options[0][disbursement]' => 'Rp 0',
    ];

    foreach ($placeholders as $name => $placeholder) {
        preg_match('/<input\b(?=[^>]*\bname="'.preg_quote($name, '/').'")[^>]*>/s', $content, $matches);

        expect($matches)->not->toBeEmpty()
            ->and($matches[0])->toContain('value=""')
            ->toContain('placeholder="'.$placeholder.'"');
    }
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
    ];

    $response = $this->post(route('transactions.store'), [
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

    $response->assertRedirect(route('transactions.index'));

    $transaction = CarTransaction::query()->firstOrFail();
    $this->assertModelExists($transaction);
    expect($transaction->leasing_options)->toBe($leasingOptions);

    $this->get(route('transactions.edit', $transaction))
        ->assertOk()
        ->assertSee('name="leasing_options[0][disbursement]"', false)
        ->assertSee('value="93000000"', false);
});

test('it renders every leasing option inside a dedicated form card', function () {
    $response = $this->get(route('transactions.create'));

    $response->assertOk()
        ->assertSee('class="form-section leasing-card-wrapper"', false)
        ->assertSee('<h2 id="leasing-card-title">Pilihan leasing</h2>', false)
        ->assertSee('class="section-number">05</span>', false)
        ->assertSee('BCA', false)
        ->assertSee('MUF', false)
        ->assertSee('BUANA', false)
        ->assertSee('ADIRA', false)
        ->assertSee('SINAR MAS', false)
        ->assertSee('WORI', false)
        ->assertSee('WOM', false)
        ->assertSee('id="leasing-vehicle-name"', false)
        ->assertSee('Lengkapi data kendaraan', false)
        ->assertSee('data-leasing-disbursement', false)
        ->assertSee('data-leasing-selling-price', false)
        ->assertSee('data-leasing-dp', false)
        ->assertSee('readonly', false)
        ->assertSee('name="leasing_options[6][installment_5yr]"', false)
        ->assertDontSee('Simulasi pembiayaan')
        ->assertDontSee('Data kendaraan akan ditampilkan di sini');
});

test('it renders the leasing vehicle summary from old vehicle input', function () {
    $response = $this->withSession([
        '_old_input' => [
            'brand' => 'HONDA',
            'model' => 'CIVIC',
            'year' => '2022',
            'transmission' => 'AUTOMATIC',
            'color' => 'PUTIH',
            'plate_number' => 'B 5678 EF',
        ],
    ])->get(route('transactions.create'));

    $response->assertOk()
        ->assertSee('HONDA CIVIC 2022 AUTOMATIC PUTIH B 5678 EF', false);
});

test('it rejects negative leasing amounts without saving a transaction', function () {
    $response = $this->from(route('transactions.create'))->post(route('transactions.store'), [
        'plate_number' => 'B 1234 CD',
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
        ->assertSee('name="down_payment_date"', false)
        ->assertSee('name="settlement_date"', false)
        ->assertSee('name="leasing_disbursement_date"', false)
        ->assertDontSee('<label for="calculated-sale-profit">')
        ->assertDontSee('<label for="sell-date">')
        ->assertSee('aria-label="Selisih harga setor dengan modal (Rupiah)"', false)
        ->assertSee('aria-label="Persentase selisih harga setor dari modal"', false)
        ->assertSee('aria-label="Persentase selisih harga buka dari modal"', false);
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

test('it stores zero for empty numeric inputs and costs on populated expense rows', function () {
    $response = $this->post(route('transactions.store'), [
        'plate_number' => 'B 1234 CD',
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

    $this->get(route('transactions.show', $transaction))
        ->assertOk()
        ->assertSee('2026-10-13')
        ->assertSee('2026-10-14')
        ->assertSee('2026-10-15');
});

test('it renders the receipt sections in order and displays the selling price references', function () {
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

    $response = $this->get(route('transactions.show', $transaction));

    $response->assertOk()
        ->assertSee('Harga Setor')
        ->assertSee('Harga Buka')
        ->assertSee('Kalkulasi Laba Penjualan dan Ripan')
        ->assertSee('Pengeluaran Intertine')
        ->assertSee('1,000,000')
        ->assertSee('1,200,000')
        ->assertSee('500,000')
        ->assertSee('635,000');

    $content = $response->getContent();
    $sectionPositions = [
        strpos($content, 'class="receipt-body"'),
        strpos($content, 'class="receipt-price-reference"'),
        strpos($content, 'class="receipt-dark-section"'),
        strpos($content, 'class="receipt-profit receipt-profit-calculation"'),
        strpos($content, 'class="receipt-profit receipt-profit-summary"'),
    ];

    expect($sectionPositions)->not->toContain(false)
        ->and($sectionPositions[0])->toBeLessThan($sectionPositions[1])
        ->and($sectionPositions[1])->toBeLessThan($sectionPositions[2])
        ->and($sectionPositions[2])->toBeLessThan($sectionPositions[3])
        ->and($sectionPositions[3])->toBeLessThan($sectionPositions[4]);
});

test('it displays leasing prices beside the receipt as separate vehicle information', function () {
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

    $response = $this->get(route('transactions.show', $transaction));

    $response->assertOk()
        ->assertSee('page-shell--receipt', false)
        ->assertSee('class="vehicle-detail-layout"', false)
        ->assertSee('class="vehicle-leasing-panel"', false)
        ->assertSee('Informasi pelengkap kendaraan')
        ->assertSee('Harga Leasing')
        ->assertSee('BCA')
        ->assertSee('93,000,000')
        ->assertSee('2,300,000')
        ->assertSee('MUF')
        ->assertDontSee('Belum ada detail harga leasing untuk kendaraan ini.')
        ->assertDontSee('receipt-leasing', false)
        ->assertDontSee('receipt-leasing-scroll', false);

    preg_match('/<tr>\s*<th scope="row">MUF<\/th>(.*?)<\/tr>/s', $response->getContent(), $emptyOptionRow);

    expect($emptyOptionRow)->not->toBeEmpty()
        ->and(preg_match_all('/<td[^>]*>\s*-\s*<\/td>/s', $emptyOptionRow[1]))->toBe(6);
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

    $this->get(route('transactions.show', $transaction))
        ->assertOk()
        ->assertSee('Belum ada detail harga leasing untuk kendaraan ini.');
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
        ->assertSee('aria-label="Lihat detail B 9876 XYZ"', false)
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
