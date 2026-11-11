<?php

use App\Models\CarTransaction;
use App\Models\SaleNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function spaTestVehicle(array $attributes = []): CarTransaction
{
    return CarTransaction::create(array_merge([
        'plate_number' => 'B 1234 SPA',
        'nama_penjual' => 'Penjual SPA',
        'seller_name' => 'Penjual SPA',
        'brand' => 'Toyota',
        'model' => 'Avanza',
        'year' => 2024,
        'transmission' => 'Automatic',
        'color' => 'Hitam',
        'buy_date' => '2026-10-01',
        'buy_price' => 50000000,
    ], $attributes));
}

it('creates and returns transactions through the SPA API with sensitive values restricted', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('api.spa.transactions.store'), [
            'plate_number' => 'B 5678 SPA',
            'nama_penjual' => 'Penjual SPA',
            'brand' => 'Honda',
            'model' => 'Jazz',
            'year' => 2023,
            'transmission' => 'Automatic',
            'color' => 'Silver',
            'buy_date' => '2026-10-02',
            'buy_price' => 80000000,
        ])
        ->assertCreated()
        ->assertJsonPath('message', 'Data transaksi berhasil disimpan.');

    $transaction = CarTransaction::query()->where('plate_number', 'B 5678 SPA')->sole();

    expect((float) $transaction->buy_price)->toBe(0.0);

    $this->getJson(route('api.spa.transactions.show', $transaction))
        ->assertOk()
        ->assertJsonPath('data.plate_number', 'B 5678 SPA')
        ->assertJsonMissingPath('data.buy_price')
        ->assertJsonMissingPath('data.total_capital');
});

it('validates SPA transaction submissions and requires authentication', function () {
    $this->postJson(route('api.spa.transactions.store'), [])
        ->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->postJson(route('api.spa.transactions.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['plate_number', 'nama_penjual', 'brand', 'buy_date']);
});

it('allows only a master to delete a transaction through the SPA API', function () {
    $transaction = spaTestVehicle();

    $this->actingAs(User::factory()->create())
        ->deleteJson(route('api.spa.transactions.destroy', $transaction))
        ->assertForbidden();

    $this->assertModelExists($transaction);

    $this->actingAs(User::factory()->master()->create())
        ->deleteJson(route('api.spa.transactions.destroy', $transaction))
        ->assertOk()
        ->assertJsonPath('message', 'Data transaksi berhasil dihapus.');

    $this->assertModelMissing($transaction);
});

it('creates and lists a Jual transaction through the SPA API', function () {
    $vehicle = spaTestVehicle();
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->postJson(route('api.spa.jual.store'), [
            'car_transaction_id' => $vehicle->id,
            'nama_konsumen' => 'Pembeli SPA',
            'selling_price' => 60000000,
            'down_payment' => 60000000,
            'down_payment_date' => '2026-11-01',
            'settlement_amount' => 0,
            'leasing_disbursement' => 0,
            'sell_date' => '2026-11-01',
        ])
        ->assertCreated()
        ->assertJsonPath('message', 'Data Jual berhasil disimpan.');

    $saleNote = SaleNote::query()->sole();

    expect($saleNote->nama_konsumen)->toBe('Pembeli SPA')
        ->and($vehicle->refresh()->sell_date)->toBe('2026-11-01');

    $this->getJson(route('api.spa.jual.index'))
        ->assertOk()
        ->assertJsonPath('data.0.nama_konsumen', 'Pembeli SPA')
        ->assertJsonPath('data.0.car_transaction.plate_number', 'B 1234 SPA')
        ->assertJsonPath('can_view_sensitive_data', false)
        ->assertJsonMissingPath('data.0.car_transaction.buy_price');
});

it('does not expose dedicated sale-note create endpoints through the SPA API', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/api/spa/sale-notes/create')
        ->assertNotFound();

    $this->postJson('/api/spa/sale-notes', [])
        ->assertStatus(405);
});

it('rejects invalid buyer details when creating a Jual transaction', function () {
    $vehicle = spaTestVehicle(['plate_number' => 'B 1122 NOTE']);
    $payload = [
        'car_transaction_id' => $vehicle->id,
        'nama_konsumen' => 'Konsumen Nota SPA',
        'buyer_nik' => '123',
        'buyer_phone' => str_repeat('1', 31),
        'buyer_address' => str_repeat('a', 2001),
        'selling_price' => 70000000,
        'down_payment' => 70000000,
        'down_payment_date' => '2026-11-02',
        'settlement_amount' => 0,
        'leasing_disbursement' => 0,
        'sell_date' => '2026-11-02',
    ];

    $this->actingAs(User::factory()->create())
        ->postJson(route('api.spa.jual.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['buyer_nik', 'buyer_phone', 'buyer_address']);

    $this->assertDatabaseMissing('sale_notes', ['car_transaction_id' => $vehicle->id]);
});
