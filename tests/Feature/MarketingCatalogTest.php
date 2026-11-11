<?php

use App\Models\CarTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createMarketingCatalogVehicle(array $attributes = []): CarTransaction
{
    $plateNumber = 'B '.str_pad((string) (CarTransaction::query()->count() + 1), 4, '0', STR_PAD_LEFT).' MKT';

    return CarTransaction::query()->create(array_merge([
        'plate_number' => $plateNumber,
        'brand' => 'Toyota',
        'model' => 'Avanza',
        'year' => 2022,
        'transmission' => 'Manual',
        'color' => 'Hitam',
        'buy_date' => '2026-01-15',
        'buy_price' => 100000000,
    ], $attributes));
}

test('marketing login redirects to the read-only stock catalog and blocks internal routes', function () {
    User::factory()->master()->create();
    $marketingUser = User::factory()->marketing()->create([
        'username' => 'marketing_user',
        'password' => 'password',
    ]);

    $this->post(route('login.store'), [
        'username' => 'marketing_user',
        'password' => 'password',
    ])->assertRedirect(route('marketing.stok.index'));

    $this->assertAuthenticatedAs($marketingUser);

    $this->get(route('dashboard'))->assertRedirect(route('marketing.stok.index'));
    $this->get(route('jual.create'))->assertRedirect(route('marketing.stok.index'));
    $this->get(route('users.index'))->assertRedirect(route('marketing.stok.index'));
    $this->post(route('jual.store'))->assertRedirect(route('marketing.stok.index'));

    $this->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('marketing catalog lists only available vehicles and paginates ten per page', function () {
    $marketingUser = User::factory()->marketing()->create();
    $this->actingAs($marketingUser);

    foreach (range(1, 11) as $number) {
        createMarketingCatalogVehicle([
            'plate_number' => sprintf('B %04d MKT', $number),
            'model' => sprintf('Avanza %02d', $number),
        ]);
    }

    $soldVehicle = createMarketingCatalogVehicle([
        'plate_number' => 'B 9999 SOLD',
        'model' => 'Sold Vehicle',
        'sell_date' => '2026-03-01',
        'selling_price' => 120000000,
    ]);

    $this->get(route('marketing.stok.index'))
        ->assertRedirect('/app/marketing');

    $this->getJson(route('api.spa.marketing.vehicles.index'))
        ->assertOk()
        ->assertJsonPath('summary.available_count', 11)
        ->assertJsonPath('data.0.plate_number', 'B 0011 MKT')
        ->assertJsonCount(10, 'data')
        ->assertJsonMissing(['plate_number' => $soldVehicle->plate_number]);

    $this->getJson(route('api.spa.marketing.vehicles.index', ['page' => 2]))
        ->assertOk()
        ->assertJsonPath('data.0.plate_number', 'B 0001 MKT')
        ->assertJsonCount(1, 'data');
});

test('marketing can search, filter transmission and filter the year range', function () {
    $marketingUser = User::factory()->marketing()->create();
    $this->actingAs($marketingUser);

    createMarketingCatalogVehicle([
        'plate_number' => 'D 1234 HND',
        'brand' => 'Honda',
        'model' => 'Brio',
        'year' => 2021,
        'transmission' => 'Automatic',
    ]);
    createMarketingCatalogVehicle([
        'plate_number' => 'D 5678 MT',
        'brand' => 'Honda',
        'model' => 'Brio Manual',
        'year' => 2019,
        'transmission' => 'Manual',
    ]);
    createMarketingCatalogVehicle([
        'plate_number' => 'B 1111 TYT',
        'brand' => 'Toyota',
        'model' => 'Innova',
        'year' => 2023,
        'transmission' => 'Automatic',
    ]);

    $this->getJson(route('api.spa.marketing.vehicles.index', [
        'q' => 'honda',
        'transmission' => 'otomatis',
        'year_from' => 2020,
        'year_to' => 2022,
    ]))
        ->assertOk()
        ->assertJsonPath('data.0.plate_number', 'D 1234 HND')
        ->assertJsonCount(1, 'data');

    $this->getJson(route('api.spa.marketing.vehicles.index', ['transmission' => 'manual']))
        ->assertOk()
        ->assertJsonPath('data.0.plate_number', 'D 5678 MT')
        ->assertJsonCount(1, 'data');

    $this->getJson(route('api.spa.marketing.vehicles.index', ['year_to' => 2020]))
        ->assertOk()
        ->assertJsonPath('data.0.plate_number', 'D 5678 MT')
        ->assertJsonCount(1, 'data');
});

test('marketing can view available vehicle specifications and leasing simulations', function () {
    $marketingUser = User::factory()->marketing()->create([
        'name' => 'Marketing Showroom',
    ]);
    $this->actingAs($marketingUser);

    $vehicle = createMarketingCatalogVehicle([
        'plate_number' => 'B 4321 MKT',
        'chassis_number' => 'MHKA1AA1234567890',
        'engine_number' => '1NR123456',
        'mileage_km' => 42000,
        'owner_name' => 'Pemilik Pertama',
        'opening_price' => 135000000,
        'tax_items' => [
            ['name' => 'Pajak', 'date' => now()->addYear()->toDateString()],
        ],
        'leasing_options' => [[
            'name' => 'BCA Finance',
            'disbursement' => 90000000,
            'selling_price' => 135000000,
            'dp' => 45000000,
            'installment_3yr' => 3200000,
            'installment_4yr' => 2600000,
            'installment_5yr' => 2200000,
        ]],
    ]);

    $this->getJson(route('api.spa.marketing.vehicles.show', $vehicle))
        ->assertOk()
        ->assertJsonPath('data.brand', 'Toyota')
        ->assertJsonPath('data.model', 'Avanza')
        ->assertJsonPath('data.chassis_number', 'MHKA1AA1234567890')
        ->assertJsonPath('data.mileage_km', 42000)
        ->assertJsonPath('data.owner_name', 'Pemilik Pertama')
        ->assertJsonPath('data.tax_status.label', 'Hidup')
        ->assertJsonPath('data.opening_price', 135000000)
        ->assertJsonPath('data.leasing_options.0.name', 'BCA Finance')
        ->assertJsonPath('data.leasing_options.0.installment_3yr', 3200000)
        ->assertJsonMissingPath('data.total_capital')
        ->assertJsonMissingPath('data.gross_profit');

    $this->get(route('marketing.stok.show', $vehicle))
        ->assertRedirect('/app/marketing/'.$vehicle->id);
});

test('marketing cannot view a sold vehicle and other roles return to their dashboard', function () {
    $marketingUser = User::factory()->marketing()->create();
    $soldVehicle = createMarketingCatalogVehicle([
        'sell_date' => '2026-03-01',
        'selling_price' => 120000000,
    ]);

    $this->actingAs($marketingUser)
        ->getJson(route('api.spa.marketing.vehicles.show', $soldVehicle))
        ->assertNotFound();

    $this->actingAs(User::factory()->create())
        ->get(route('marketing.stok.index'))
        ->assertRedirect(route('dashboard'));
});

test('marketing receives the Vue shell for catalog deep links', function () {
    $this->actingAs(User::factory()->marketing()->create());

    $this->get('/app/marketing')
        ->assertOk()
        ->assertSee('id="app"', false)
        ->assertSee('assets/spa-', false);
});

test('marketing catalog rejects invalid year ranges', function () {
    $this->actingAs(User::factory()->marketing()->create());

    $this->from(route('marketing.stok.index'))->get(route('marketing.stok.index', [
        'year_from' => 2024,
        'year_to' => 2020,
    ]))
        ->assertRedirect(route('marketing.stok.index'))
        ->assertSessionHasErrors('year_to');
});

test('master can create an account with the marketing role', function () {
    $this->actingAs(User::factory()->master()->create());

    $this->get(route('users.index'))
        ->assertRedirect('/app/users');

    $this->post(route('users.store'), [
        'name' => 'Marketing Baru',
        'username' => 'marketing_baru',
        'password' => 'secure-password',
        'role' => 'marketing',
    ])->assertRedirect(route('users.index'));

    $marketingUser = User::query()->where('username', 'marketing_baru')->sole();

    expect($marketingUser->role)->toBe('marketing');
});
