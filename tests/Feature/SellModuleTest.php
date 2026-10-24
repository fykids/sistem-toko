<?php

use App\Models\CarTransaction;
use App\Models\SaleNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

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
        'buyer_name' => 'Dewi Jual',
        'buyer_nik' => '3174012345678901',
        'buyer_phone' => '081234567890',
        'buyer_address' => 'Surabaya',
        'deal_price' => 92000000,
        'payment_method' => 'Tunai',
        'sell_date' => '2026-10-23',
        'notes' => 'Data Jual tersendiri',
    ], $overrides);
}

it('registers jual independently and removes the former sales endpoint', function () {
    expect(route('jual.index'))->toEndWith('/jual')
        ->and(route('jual.create'))->toEndWith('/jual/create')
        ->and(Route::has('sales.index'))->toBeFalse()
        ->and(route('nota-penjualan.index'))->toEndWith('/nota-penjualan')
        ->and(route('dashboard').'#penjualan')->toEndWith('#penjualan');

    $this->actingAs(User::factory()->create())
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertSee(route('dashboard').'#penjualan', false)
        ->assertSee(route('jual.index'), false)
        ->assertSee(route('nota-penjualan.index'), false);

    $this->get('/sales')->assertNotFound();
});

it('renders the independent jual list and role-specific form without leasing', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('jual.index'))
        ->assertOk()
        ->assertSee('Menu Jual')
        ->assertSee(route('jual.create'), false);

    $this->get(route('jual.create'))
        ->assertOk()
        ->assertSee('jual-form')
        ->assertSee('transaction-form--admin')
        ->assertSee('name="deal_price"', false)
        ->assertDontSee('Pilihan Leasing')
        ->assertDontSee('leasing_options');

    $this->actingAs(User::factory()->master()->create())
        ->get(route('jual.create'))
        ->assertOk()
        ->assertSee('transaction-form--master')
        ->assertSee('Ringkasan finansial Master')
        ->assertDontSee('Pilihan Leasing');
});

it('shows sensitive financial columns only to masters in the jual list', function () {
    $vehicle = createJualVehicle();
    $creator = User::factory()->master()->create();
    $vehicle->saleNote()->create(array_merge(
        validJualPayload($vehicle),
        ['created_by' => $creator->id],
    ));

    $this->actingAs(User::factory()->create())
        ->get(route('jual.index'))
        ->assertOk()
        ->assertSee('Dewi Jual')
        ->assertDontSee('Modal beli')
        ->assertDontSee('Total modal')
        ->assertDontSee('Laba deal')
        ->assertDontSee('65,000,000');

    $this->actingAs($creator)
        ->get(route('jual.index'))
        ->assertOk()
        ->assertSee('Modal beli')
        ->assertSee('Total modal')
        ->assertSee('Laba deal')
        ->assertSee('65,000,000');
});

it('stores a jual record and displays its detail without changing legacy sales attributes', function () {
    $vehicle = createJualVehicle();
    $admin = User::factory()->create();

    $response = $this->actingAs($admin)
        ->post(route('jual.store'), validJualPayload($vehicle));

    $saleNote = SaleNote::query()->sole();
    $response->assertRedirect(route('jual.show', $saleNote))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('sale_notes', [
        'id' => $saleNote->id,
        'car_transaction_id' => $vehicle->id,
        'buyer_name' => 'Dewi Jual',
        'created_by' => $admin->id,
    ]);

    expect($vehicle->refresh()->sell_date)->toBeNull()
        ->and((float) $vehicle->selling_price)->toBe(0.0);

    $this->get(route('jual.show', $saleNote))
        ->assertOk()
        ->assertSee('Dewi Jual')
        ->assertSee('B 1122 JUAL');
});

it('prepopulates an existing jual note when its edit form is opened', function () {
    $vehicle = createJualVehicle();
    $creator = User::factory()->create();
    $saleNote = $vehicle->saleNote()->create(array_merge(
        validJualPayload($vehicle),
        ['created_by' => $creator->id],
    ));

    $this->actingAs($creator)
        ->get(route('jual.edit', $saleNote))
        ->assertOk()
        ->assertSee('value="Dewi Jual"', false)
        ->assertSee('value="92000000.00"', false)
        ->assertSee(route('jual.update', $saleNote), false);
});

it('updates a jual note without changing legacy vehicle sale attributes', function () {
    $vehicle = createJualVehicle();
    $creator = User::factory()->create();
    $saleNote = $vehicle->saleNote()->create(array_merge(
        validJualPayload($vehicle),
        ['created_by' => $creator->id],
    ));

    $this->actingAs($creator)
        ->put(route('jual.update', $saleNote), [
            'buyer_name' => 'Dewi Utami',
            'buyer_nik' => null,
            'buyer_phone' => '081111111111',
            'buyer_address' => 'Malang',
            'deal_price' => 94000000,
            'payment_method' => 'Transfer',
            'sell_date' => '2026-10-24',
            'notes' => 'Nota diperbarui',
        ])
        ->assertRedirect(route('jual.show', $saleNote))
        ->assertSessionHas('success');

    expect($saleNote->refresh()->buyer_name)->toBe('Dewi Utami')
        ->and((float) $saleNote->deal_price)->toBe(94000000.0)
        ->and($vehicle->refresh()->sell_date)->toBeNull()
        ->and((float) $vehicle->selling_price)->toBe(0.0);
});

it('deletes a jual note without deleting its vehicle', function () {
    $vehicle = createJualVehicle();
    $creator = User::factory()->create();
    $saleNote = $vehicle->saleNote()->create(array_merge(
        validJualPayload($vehicle),
        ['created_by' => $creator->id],
    ));

    $this->actingAs($creator)
        ->delete(route('jual.destroy', $saleNote))
        ->assertRedirect(route('jual.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('sale_notes', ['id' => $saleNote->id]);
    $this->assertDatabaseHas('car_transactions', ['id' => $vehicle->id]);
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
