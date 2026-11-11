<?php

use App\Models\CarTransaction;
use App\Models\SaleNote;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function createJualVehicle(): CarTransaction
{
    return CarTransaction::create([
        'plate_number' => 'B 1122 JUAL',
        'brand' => 'Toyota',
        'model' => 'Yaris',
        'year' => 2024,
        'transmission' => 'Automatic',
        'color' => 'Silver',
        'buy_date' => '2026-10-01',
        'buy_price' => 65000000,
    ]);
}

function validJualPayload(CarTransaction $vehicle, array $overrides = []): array
{
    return array_merge([
        'car_transaction_id' => $vehicle->id,
        'nama_konsumen' => 'Dewi Jual',
        'buyer_nik' => null,
        'buyer_phone' => null,
        'buyer_address' => null,
        'selling_price' => 92000000,
        'down_payment' => 0,
        'down_payment_date' => null,
        'settlement_amount' => 0,
        'settlement_date' => null,
        'leasing_disbursement' => 0,
        'leasing_disbursement_date' => null,
        'sell_date' => '2026-10-23',
        'planned_payment_date' => '2026-11-23',
    ], $overrides);
}

function createJualSaleNote(
    CarTransaction $vehicle,
    User $creator,
    array $overrides = [],
): SaleNote {
    $attributes = validJualPayload($vehicle, $overrides);
    $vehicle->update([
        'sell_date' => $attributes['sell_date'],
        'selling_price' => $attributes['selling_price'],
        'down_payment' => $attributes['down_payment'],
        'settlement_amount' => $attributes['settlement_amount'],
        'leasing_disbursement' => $attributes['leasing_disbursement'],
        'planned_payment_date' => $attributes['planned_payment_date'],
    ]);

    return $vehicle->saleNote()->create([
        'nama_konsumen' => $attributes['nama_konsumen'],
        'buyer_nik' => $attributes['buyer_nik'],
        'buyer_phone' => $attributes['buyer_phone'],
        'buyer_address' => $attributes['buyer_address'],
        'created_by' => $creator->id,
    ]);
}

it('registers Jual as a separate SPA module and removes the former sales endpoint', function () {
    expect(route('jual.index'))->toEndWith('/jual')
        ->and(route('jual.create'))->toEndWith('/jual/create')
        ->and(Route::has('sales.index'))->toBeFalse()
        ->and(route('nota-penjualan.index'))->toEndWith('/nota-penjualan')
        ->and(route('dashboard').'#penjualan')->toEndWith('#penjualan');

    $this->actingAs(User::factory()->create())
        ->get(route('transactions.index'))
        ->assertRedirect('/app/dashboard');

    $this->get(route('jual.index'))->assertRedirect('/app/jual');
    $this->get('/sales')->assertNotFound();
    $this->getJson(route('api.spa.jual.index'))->assertOk();
});

it('provides Jual list and role-specific form data through the SPA API', function () {
    createJualVehicle();

    $this->actingAs(User::factory()->create())
        ->getJson(route('api.spa.jual.index'))
        ->assertOk()
        ->assertJsonPath('status', 'all');

    $this->getJson(route('api.spa.jual.create'))
        ->assertOk()
        ->assertJsonPath('data.can_view_sensitive_data', false)
        ->assertJsonPath('data.has_available_vehicles', true)
        ->assertJsonPath('data.vehicles.0.plate_number', 'B 1122 JUAL');

    $this->actingAs(User::factory()->master()->create())
        ->getJson(route('api.spa.jual.create'))
        ->assertOk()
        ->assertJsonPath('data.can_view_sensitive_data', true);
});

it('shows only the selected vehicle details from stock without exposing unrelated values', function () {
    $vehicle = createJualVehicle();
    $vehicle->update([
        'leasing_options' => [[
            'name' => 'BCA',
            'disbursement' => 80000000,
            'selling_price' => 0,
            'dp' => 0,
            'installment_3yr' => 3500000,
            'installment_4yr' => 2900000,
            'installment_5yr' => 2500000,
        ]],
        'additional_repairs' => [[
            'name' => 'Tune up',
            'date' => '2026-10-10',
            'cost' => 1250000,
            'payment_status' => 'Lunas',
        ]],
        'tax_items' => [[
            'name' => 'Pajak',
            'date' => '2026-11-10',
            'cost' => 750000,
        ]],
        'intertine_expenses' => [['name' => 'Komisi', 'cost' => 900000]],
        'setor_price' => 110000000,
        'opening_price' => 105000000,
        'booster_ripan' => 3000000,
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('api.spa.jual.create', ['vehicle' => $vehicle->id]))
        ->assertOk()
        ->assertJsonPath('data.selected_vehicle.plate_number', 'B 1122 JUAL')
        ->assertJsonPath('data.selected_vehicle.brand', 'Toyota')
        ->assertJsonMissingPath('data.selected_vehicle.leasing_options')
        ->assertJsonMissingPath('data.selected_vehicle.additional_repairs')
        ->assertJsonMissingPath('data.selected_vehicle.tax_items')
        ->assertJsonMissingPath('data.selected_vehicle.intertine_expenses')
        ->assertJsonMissingPath('data.selected_vehicle.setor_price')
        ->assertJsonMissingPath('data.selected_vehicle.booster_ripan');

    $this->actingAs(User::factory()->master()->create())
        ->getJson(route('api.spa.jual.create', ['vehicle' => $vehicle->id]))
        ->assertOk()
        ->assertJsonPath('data.selected_vehicle.plate_number', 'B 1122 JUAL')
        ->assertJsonMissingPath('data.selected_vehicle.buy_price')
        ->assertJsonMissingPath('data.selected_vehicle.booster_ripan');
});

it('renders every available vehicle while preserving the requested vehicle preview', function () {
    $vehicles = collect(range(1, 12))->map(function (int $number): CarTransaction {
        return CarTransaction::query()->create([
            'plate_number' => sprintf('B %04d RAM', $number),
            'brand' => 'Toyota',
            'model' => 'Yaris',
            'year' => 2024,
            'transmission' => 'Automatic',
            'color' => 'Silver',
            'buy_date' => '2026-10-01',
            'buy_price' => 65000000,
        ]);
    });
    $selectedVehicle = $vehicles->last();

    $response = $this->actingAs(User::factory()->create())
        ->getJson(route('api.spa.jual.create', ['vehicle' => $selectedVehicle->id]));

    $response->assertOk()
        ->assertJsonCount(12, 'data.vehicles')
        ->assertJsonPath('data.selected_vehicle.id', $selectedVehicle->id);

    expect(collect($response->json('data.vehicles'))->pluck('plate_number'))
        ->toContain($vehicles->first()->plate_number)
        ->toContain($selectedVehicle->plate_number);
});

it('renders the jual form when buyers has no vehicle relationship column', function () {
    $vehicle = createJualVehicle();
    Schema::drop('buyers');
    Schema::create('buyers', function (Blueprint $table): void {
        $table->id();
    });

    $this->actingAs(User::factory()->create())
        ->getJson(route('api.spa.jual.create'))
        ->assertOk()
        ->assertJsonPath('data.vehicles.0.plate_number', $vehicle->plate_number);
});

it('shows sensitive financial columns only to masters in the jual list', function () {
    $vehicle = createJualVehicle();
    $creator = User::factory()->create();
    createJualSaleNote($vehicle, $creator);

    $this->actingAs(User::factory()->create())
        ->getJson(route('api.spa.jual.index'))
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->actingAs($creator)
        ->getJson(route('api.spa.jual.index'))
        ->assertOk()
        ->assertJsonPath('data.0.nama_konsumen', 'Dewi Jual')
        ->assertJsonPath('can_view_sensitive_data', false)
        ->assertJsonMissingPath('data.0.car_transaction.buy_price');

    $this->actingAs(User::factory()->master()->create())
        ->getJson(route('api.spa.jual.index'))
        ->assertOk()
        ->assertJsonPath('can_view_sensitive_data', true)
        ->assertJsonPath('data.0.car_transaction.buy_price', 65000000);
});

it('stores a jual record and updates vehicle stock and sale details atomically', function () {
    $vehicle = createJualVehicle();
    $admin = User::factory()->create();

    $response = $this->actingAs($admin)
        ->postJson(route('api.spa.jual.store'), validJualPayload($vehicle));

    $saleNote = SaleNote::query()->sole();
    $response->assertCreated()
        ->assertJsonPath('data.id', $saleNote->id);

    $this->assertDatabaseHas('sale_notes', [
        'id' => $saleNote->id,
        'car_transaction_id' => $vehicle->id,
        'nama_konsumen' => 'Dewi Jual',
        'created_by' => $admin->id,
    ]);

    expect($vehicle->refresh()->sell_date)->toBe('2026-10-23')
        ->and((float) $vehicle->selling_price)->toBe(92000000.0);

    $this->getJson(route('api.spa.jual.create'))
        ->assertOk()
        ->assertJsonPath('data.has_available_vehicles', false)
        ->assertJsonCount(0, 'data.vehicles');
    $this->getJson('/api/spa/sale-notes/create')
        ->assertNotFound();

    $this->getJson(route('api.spa.jual.show', $saleNote))
        ->assertOk()
        ->assertJsonPath('data.nama_konsumen', 'Dewi Jual')
        ->assertJsonPath('data.car_transaction.plate_number', 'B 1122 JUAL');

    $this->getJson(route('api.spa.dashboard', ['view' => 'sales']))
        ->assertOk()
        ->assertJsonPath('data.0.plate_number', 'B 1122 JUAL')
        ->assertJsonPath('data.0.status', 'sold');
});

it('stores all received payment amounts and preserves vehicle costs omitted from the form', function () {
    $vehicle = createJualVehicle();
    $vehicle->update([
        'selling_price' => 92000000,
        'intertine_expenses' => [['name' => 'Biaya lama', 'cost' => 900000]],
    ]);
    $admin = User::factory()->create();

    $response = $this->actingAs($admin)
        ->post(route('jual.store'), validJualPayload($vehicle, [
            'down_payment' => 10000000,
            'down_payment_date' => '2026-10-24',
            'buyer_nik' => '3174012345678901',
            'buyer_phone' => '081234567890',
            'buyer_address' => 'Jl. Merdeka No. 1',
        ]));

    $saleNote = SaleNote::query()->sole();
    $response->assertRedirect(route('jual.show', $saleNote));
    $vehicle->refresh();

    expect((float) $vehicle->selling_price)->toBe(92000000.0)
        ->and((float) $vehicle->down_payment)->toBe(10000000.0)
        ->and($vehicle->down_payment_date)->toBe('2026-10-24')
        ->and((float) $vehicle->remaining_payment)->toBe(82000000.0)
        ->and($vehicle->planned_payment_date)->toBe('2026-11-23')
        ->and($vehicle->intertine_expenses)->toBe([['name' => 'Biaya lama', 'cost' => 900000]])
        ->and($saleNote->buyer_nik)->toBe('3174012345678901')
        ->and($saleNote->buyer_phone)->toBe('081234567890')
        ->and($saleNote->buyer_address)->toBe('Jl. Merdeka No. 1');
});

it('stores all actual payment dates with the corresponding amounts', function () {
    $vehicle = createJualVehicle();

    $response = $this->actingAs(User::factory()->create())
        ->post(route('jual.store'), validJualPayload($vehicle, [
            'selling_price' => '100.000.000',
            'down_payment' => '20.000.000',
            'down_payment_date' => '2026-10-24',
            'settlement_amount' => '5.000.000',
            'settlement_date' => '2026-10-25',
            'leasing_disbursement' => '30.000.000',
            'leasing_disbursement_date' => '2026-10-26',
            'planned_payment_date' => '2026-11-26',
            'buyer_nik' => '3174012345678901',
            'buyer_phone' => '081234567890',
            'buyer_address' => 'Jl. Merdeka No. 1',
        ]));

    $saleNote = SaleNote::query()->sole();
    $response->assertRedirect(route('jual.show', $saleNote));

    expect((float) $vehicle->fresh()->selling_price)->toBe(100000000.0)
        ->and((float) $vehicle->fresh()->down_payment)->toBe(20000000.0)
        ->and($vehicle->fresh()->down_payment_date)->toBe('2026-10-24')
        ->and((float) $vehicle->fresh()->settlement_amount)->toBe(5000000.0)
        ->and($vehicle->fresh()->settlement_date)->toBe('2026-10-25')
        ->and((float) $vehicle->fresh()->leasing_disbursement)->toBe(30000000.0)
        ->and($vehicle->fresh()->leasing_disbursement_date)->toBe('2026-10-26')
        ->and((float) $vehicle->fresh()->remaining_payment)->toBe(45000000.0)
        ->and($vehicle->fresh()->planned_payment_date)->toBe('2026-11-26');

    $this->getJson(route('api.spa.jual.show', $saleNote))
        ->assertOk()
        ->assertJsonPath('data.buyer_nik', '3174012345678901')
        ->assertJsonPath('data.buyer_phone', '081234567890')
        ->assertJsonPath('data.buyer_address', 'Jl. Merdeka No. 1')
        ->assertJsonPath('data.car_transaction.settlement_date', '2026-10-25')
        ->assertJsonPath('data.car_transaction.leasing_disbursement_date', '2026-10-26')
        ->assertJsonPath('data.car_transaction.planned_payment_date', '2026-11-26');
});

it('requires a planned payment date while any balance remains', function () {
    $vehicle = createJualVehicle();

    $this->actingAs(User::factory()->create())
        ->post(route('jual.store'), validJualPayload($vehicle, [
            'planned_payment_date' => null,
            'down_payment' => 10000000,
        ]))
        ->assertSessionHasErrors('planned_payment_date');

    expect(SaleNote::query()->count())->toBe(0)
        ->and($vehicle->fresh()->sell_date)->toBeNull();
});

it('does not require or retain a planned payment date once the sale is fully paid', function () {
    $vehicle = createJualVehicle();

    $this->actingAs(User::factory()->create())
        ->post(route('jual.store'), validJualPayload($vehicle, [
            'selling_price' => 92000000,
            'down_payment' => 20000000,
            'down_payment_date' => '2026-10-23',
            'settlement_amount' => 72000000,
            'settlement_date' => '2026-10-24',
            'planned_payment_date' => null,
        ]))
        ->assertRedirect(route('jual.show', SaleNote::query()->sole()));

    expect((float) $vehicle->fresh()->remaining_payment)->toBe(0.0)
        ->and($vehicle->fresh()->planned_payment_date)->toBeNull();
});

it('requires a real payment date when a received payment amount is positive', function () {
    $vehicle = createJualVehicle();

    $this->actingAs(User::factory()->create())
        ->post(route('jual.store'), validJualPayload($vehicle, [
            'down_payment' => 10000000,
            'down_payment_date' => null,
        ]))
        ->assertSessionHasErrors('down_payment_date');

    expect(SaleNote::query()->count())->toBe(0)
        ->and($vehicle->fresh()->sell_date)->toBeNull();
});

it('rejects consumer identity data with an invalid national identification number', function () {
    $vehicle = createJualVehicle();

    $this->actingAs(User::factory()->create())
        ->post(route('jual.store'), validJualPayload($vehicle, [
            'buyer_nik' => '12345',
        ]))
        ->assertSessionHasErrors('buyer_nik');

    expect(SaleNote::query()->count())->toBe(0)
        ->and($vehicle->fresh()->sell_date)->toBeNull();
});

it('accepts a nominal above the former validation ceiling', function () {
    $vehicle = createJualVehicle();
    $admin = User::factory()->create();

    $response = $this->actingAs($admin)
        ->post(route('jual.store'), validJualPayload($vehicle, [
            'selling_price' => '2000000000000',
            'down_payment' => '1000000000000',
            'down_payment_date' => '2026-10-23',
        ]));

    $saleNote = SaleNote::query()->sole();
    $response->assertRedirect(route('jual.show', $saleNote));

    expect((float) $vehicle->fresh()->selling_price)->toBe(2000000000000.0)
        ->and((float) $vehicle->fresh()->down_payment)->toBe(1000000000000.0)
        ->and((float) $vehicle->fresh()->remaining_payment)->toBe(1000000000000.0);
});

it('rejects a sale nominal that exceeds decimal column precision', function () {
    $vehicle = createJualVehicle();

    $this->actingAs(User::factory()->create())
        ->from(route('jual.create'))
        ->post(route('jual.store'), validJualPayload($vehicle, [
            'selling_price' => '10000000000000',
            'down_payment' => '0',
        ]))
        ->assertRedirect(route('jual.create'))
        ->assertSessionHasErrors('selling_price');

    expect(SaleNote::query()->count())->toBe(0)
        ->and($vehicle->fresh()->sell_date)->toBeNull();
});

it('provides existing Jual values to the SPA edit form', function () {
    $vehicle = createJualVehicle();
    $creator = User::factory()->create();
    $saleNote = createJualSaleNote($vehicle, $creator);

    $this->actingAs($creator)
        ->getJson(route('api.spa.jual.show', $saleNote))
        ->assertOk()
        ->assertJsonPath('data.nama_konsumen', 'Dewi Jual')
        ->assertJsonPath('data.car_transaction.selling_price', 92000000)
        ->assertJsonPath('data.car_transaction.planned_payment_date', '2026-11-23');
});

it('updates buyer identity and the canonical vehicle sale attributes', function () {
    $vehicle = createJualVehicle();
    $creator = User::factory()->create();
    $saleNote = createJualSaleNote($vehicle, $creator);

    $this->actingAs($creator)
        ->put(route('jual.update', $saleNote), [
            'nama_konsumen' => 'Dewi Utami',
            'buyer_nik' => '3174012345678901',
            'buyer_phone' => '081234567890',
            'buyer_address' => 'Jl. Merdeka No. 1',
            'selling_price' => 94000000,
            'down_payment' => 0,
            'down_payment_date' => null,
            'settlement_amount' => 0,
            'settlement_date' => null,
            'leasing_disbursement' => 0,
            'leasing_disbursement_date' => null,
            'sell_date' => '2026-10-24',
            'planned_payment_date' => '2026-11-24',
        ])
        ->assertRedirect(route('jual.show', $saleNote))
        ->assertSessionHas('success');

    expect($saleNote->refresh()->nama_konsumen)->toBe('Dewi Utami')
        ->and($saleNote->buyer_nik)->toBe('3174012345678901')
        ->and($saleNote->buyer_phone)->toBe('081234567890')
        ->and($saleNote->buyer_address)->toBe('Jl. Merdeka No. 1')
        ->and($vehicle->refresh()->sell_date)->toBe('2026-10-24')
        ->and((float) $vehicle->selling_price)->toBe(94000000.0)
        ->and($vehicle->planned_payment_date)->toBe('2026-11-24');
});

it('deletes a jual note without deleting its vehicle', function () {
    $vehicle = createJualVehicle();
    $creator = User::factory()->create();
    $saleNote = createJualSaleNote($vehicle, $creator);

    $this->actingAs($creator)
        ->delete(route('jual.destroy', $saleNote))
        ->assertRedirect(route('jual.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('sale_notes', ['id' => $saleNote->id]);
    $this->assertDatabaseHas('car_transactions', ['id' => $vehicle->id]);
    expect($vehicle->refresh()->sell_date)->toBeNull()
        ->and((float) $vehicle->selling_price)->toBe(0.0);

    $this->getJson(route('api.spa.jual.create'))
        ->assertOk()
        ->assertJsonPath('data.vehicles.0.plate_number', 'B 1122 JUAL');
});

it('prevents an admin from viewing another admins sale record', function () {
    $vehicle = createJualVehicle();
    $creator = User::factory()->create();
    $saleNote = createJualSaleNote($vehicle, $creator);
    $otherAdmin = User::factory()->create();

    $this->actingAs($otherAdmin)
        ->getJson(route('api.spa.jual.index'))
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->getJson(route('api.spa.jual.show', $saleNote))->assertForbidden();
    $this->get(route('jual.edit', $saleNote))->assertForbidden();
    $this->getJson(route('api.spa.sale-notes.index'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
    $this->getJson(route('api.spa.sale-notes.show', $saleNote))->assertForbidden();

    $this->actingAs(User::factory()->master()->create())
        ->getJson(route('api.spa.sale-notes.index'))
        ->assertOk()
        ->assertJsonPath('data.0.nama_konsumen', 'Dewi Jual');
});

it('does not grant jual access to roles outside admin and master', function () {
    $this->actingAs(User::factory()->create(['role' => 'viewer']))
        ->get(route('jual.index'))
        ->assertForbidden();
});

it('requires authentication for jual routes', function () {
    $this->get(route('jual.index'))
        ->assertRedirect('/login');
});
