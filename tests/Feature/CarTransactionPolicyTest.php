<?php

use App\Models\CarTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('master can view sensitive data and manage transactions', function () {
    $master = User::factory()->master()->create();
    $transaction = CarTransaction::make();

    expect($master->can('viewSensitiveData', CarTransaction::class))->toBeTrue()
        ->and($master->can('create', CarTransaction::class))->toBeTrue()
        ->and($master->can('update', $transaction))->toBeTrue()
        ->and($master->can('delete', $transaction))->toBeTrue();
});

test('admin can view and update transactions but cannot view sensitive data or delete them', function () {
    $admin = User::factory()->create();
    $transaction = CarTransaction::make();

    expect($admin->can('viewSensitiveData', CarTransaction::class))->toBeFalse()
        ->and($admin->can('create', CarTransaction::class))->toBeTrue()
        ->and($admin->can('view', $transaction))->toBeTrue()
        ->and($admin->can('update', $transaction))->toBeTrue()
        ->and($admin->can('delete', $transaction))->toBeFalse();
});

test('users without a transaction management role cannot view transaction details', function () {
    $user = User::factory()->create(['role' => 'viewer']);
    $transaction = CarTransaction::create([
        'plate_number' => 'B 2580 VIEW',
        'brand' => 'Honda',
        'model' => 'Jazz',
        'year' => 2022,
        'transmission' => 'Automatic',
        'color' => 'Silver',
        'buy_date' => '2026-10-01',
    ]);

    $this->actingAs($user)
        ->get(route('transactions.show', $transaction))
        ->assertForbidden();
});

test('master receives delete permission for transactions in the SPA API', function () {
    $transaction = CarTransaction::create([
        'plate_number' => 'B 7312 MST',
        'brand' => 'Toyota',
        'model' => 'Yaris',
        'year' => 2023,
        'transmission' => 'Automatic',
        'color' => 'Merah',
        'buy_date' => '2026-10-01',
    ]);
    $this->actingAs(User::factory()->master()->create());

    $this->getJson(route('api.spa.dashboard'))
        ->assertOk()
        ->assertJsonPath('data.0.can_delete', true);
});

test('transaction routes require an authenticated user', function () {
    $this->get(route('transactions.index'))
        ->assertRedirect('/login');
});

test('admin dashboard and transaction details omit sensitive financial values', function () {
    $this->travelTo('2026-10-22');

    $transaction = CarTransaction::create([
        'plate_number' => 'B 9876 SEC',
        'brand' => 'Toyota',
        'model' => 'Avanza',
        'year' => 2022,
        'transmission' => 'Automatic',
        'color' => 'Hitam',
        'buy_date' => '2026-10-01',
        'tax_items' => [
            ['name' => 'Pajak', 'date' => '2026-10-22', 'cost' => 500000],
        ],
        'buy_price' => 987654321,
        'selling_price' => 123456789,
    ]);
    $this->actingAs(User::factory()->create());

    $this->getJson(route('api.spa.dashboard'))
        ->assertOk()
        ->assertJsonPath('metrics.can_view_sensitive_data', false)
        ->assertJsonPath('data.0.plate_number', 'B 9876 SEC')
        ->assertJsonPath('data.0.tax_status.label', 'Hidup')
        ->assertJsonPath('data.0.showroom_age_status.days', 21)
        ->assertJsonMissingPath('data.0.buy_price')
        ->assertJsonMissingPath('data.0.total_capital');

    $this->getJson(route('api.spa.transactions.show', $transaction))
        ->assertOk()
        ->assertJsonPath('data.plate_number', 'B 9876 SEC')
        ->assertJsonPath('data.can_view_sensitive_data', false)
        ->assertJsonMissingPath('data.buy_price')
        ->assertJsonMissingPath('data.total_capital');
});

it('classifies tax status on the due date and showroom age at each threshold', function (
    ?int $taxDateOffset,
    string $taxLabel,
    string $taxBadgeClass,
    ?int $showroomDays,
    string $showroomBadgeClass,
) {
    $this->travelTo('2026-10-22');

    $taxDate = $taxDateOffset === null
        ? null
        : now()->addDays($taxDateOffset)->toDateString();
    $transaction = CarTransaction::make([
        'buy_date' => $showroomDays === null
            ? null
            : now()->subDays($showroomDays)->toDateString(),
        'tax_items' => [['name' => 'Pajak', 'date' => $taxDate]],
    ]);

    expect($transaction->taxStatus()['label'])->toBe($taxLabel)
        ->and($transaction->taxStatus()['badgeClass'])->toBe($taxBadgeClass)
        ->and($transaction->showroomAgeStatus()['days'])->toBe($showroomDays)
        ->and($transaction->showroomAgeStatus()['badgeClass'])->toBe($showroomBadgeClass);
})->with([
    'tax is active on its due date and 25 showroom days are safe' => [
        0,
        'Hidup',
        'status-badge--success',
        25,
        'status-badge--success',
    ],
    'tax is active before its due date and 26 showroom days need attention' => [
        1,
        'Hidup',
        'status-badge--success',
        26,
        'status-badge--warning',
    ],
    '45 showroom days need attention' => [
        0,
        'Hidup',
        'status-badge--success',
        45,
        'status-badge--warning',
    ],
    'tax is expired after its due date and 46 showroom days are important' => [
        -1,
        'Mati',
        'status-badge--danger',
        46,
        'status-badge--danger',
    ],
    'missing tax date is inactive and a vehicle bought today has zero days' => [
        null,
        'Mati',
        'status-badge--danger',
        0,
        'status-badge--success',
    ],
    'missing buy date has no showroom age and neutral styling' => [
        0,
        'Hidup',
        'status-badge--success',
        null,
        'status-badge--neutral',
    ],
]);

test('admin-created transactions ignore any submitted purchase amount', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('transactions.store'), [
        'plate_number' => 'B 1234 ADM',
        'brand' => 'Honda',
        'model' => 'Civic',
        'year' => 2021,
        'transmission' => 'Automatic',
        'color' => 'Putih',
        'buy_date' => '2026-10-01',
        'buy_price' => 7654321,
        'leasing_ripan' => 450000,
        'insurance_ripan' => 150000,
        'booster_ripan' => 250000,
        'nama_penjual' => 'Penjual Kendaraan',
    ])->assertRedirect(route('transactions.index'));

    $this->assertDatabaseHas('car_transactions', [
        'plate_number' => 'B 1234 ADM',
        'buy_price' => 0,
        'leasing_ripan' => 0,
        'insurance_ripan' => 0,
        'booster_ripan' => 0,
    ]);
});

test('admin transaction form route enters the SPA with restricted financial access', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/app/transactions/create')
        ->assertOk()
        ->assertSee('id="app"', false);

    $this->getJson(route('api.spa.dashboard'))
        ->assertOk()
        ->assertJsonPath('metrics.can_view_sensitive_data', false);
});

test('master transaction form route enters the SPA with sensitive financial access', function () {
    $this->actingAs(User::factory()->master()->create());

    $this->get('/app/transactions/create')
        ->assertOk()
        ->assertSee('id="app"', false);

    $this->getJson(route('api.spa.dashboard'))
        ->assertOk()
        ->assertJsonPath('metrics.can_view_sensitive_data', true);
});

test('admin can edit vehicle data without changing restricted finances but cannot delete transactions or use backup routes', function () {
    $transaction = CarTransaction::create([
        'plate_number' => 'B 4567 ADM',
        'brand' => 'Honda',
        'model' => 'Civic',
        'year' => 2021,
        'transmission' => 'Automatic',
        'color' => 'Putih',
        'buy_date' => '2026-10-01',
        'buy_price' => 100000000,
        'leasing_ripan' => 450000,
        'insurance_ripan' => 150000,
        'booster_ripan' => 250000,
        'purchase_operational' => 500000,
        'setor_price' => 120000000,
        'opening_price' => 130000000,
        'selling_price' => 125000000,
        'additional_repairs' => [
            [
                'name' => 'Tune up',
                'date' => '2026-10-02',
                'cost' => 750000,
                'payment_status' => 'lunas',
            ],
        ],
        'intertine_expenses' => [
            ['name' => 'KR Jual', 'cost' => 175000],
        ],
    ]);
    $this->actingAs(User::factory()->create());

    $this->getJson(route('api.spa.transactions.show', $transaction))
        ->assertOk()
        ->assertJsonPath('data.plate_number', 'B 4567 ADM')
        ->assertJsonPath('data.brand', 'Honda')
        ->assertJsonPath('data.setor_price', null)
        ->assertJsonPath('data.selling_price', null)
        ->assertJsonMissingPath('data.additional_repairs')
        ->assertJsonMissingPath('data.buy_price')
        ->assertJsonMissingPath('data.leasing_ripan');

    $this->put(route('transactions.update', $transaction), [
        'plate_number' => 'B 4567 ADM',
        'nama_penjual' => 'Penjual Admin',
        'brand' => 'Honda',
        'model' => 'Accord',
        'year' => 2021,
        'transmission' => 'Automatic',
        'color' => 'Putih',
        'buy_date' => '2026-10-01',
        'buy_price' => 0,
        'leasing_ripan' => 0,
        'insurance_ripan' => 0,
        'booster_ripan' => 0,
    ])->assertRedirect(route('transactions.index'));

    $this->delete(route('transactions.destroy', $transaction))
        ->assertForbidden();

    $this->get(route('transactions.export'))
        ->assertForbidden();

    $this->post(route('transactions.import'))
        ->assertForbidden();

    $this->assertDatabaseHas('car_transactions', [
        'id' => $transaction->id,
        'model' => 'Accord',
        'buy_price' => 100000000,
        'leasing_ripan' => 450000,
        'insurance_ripan' => 150000,
        'booster_ripan' => 250000,
    ]);
});
