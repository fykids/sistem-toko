<?php

use App\Models\Buyer;
use App\Models\CarTransaction;
use App\Models\SaleNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function createSaleNoteVehicle(array $attributes = []): CarTransaction
{
    return CarTransaction::create(array_merge([
        'plate_number' => 'B 9876 NOTE',
        'brand' => 'Honda',
        'model' => 'HR-V',
        'year' => 2023,
        'transmission' => 'Automatic',
        'color' => 'Putih',
        'buy_date' => '2026-10-01',
        'buy_price' => 60000000,
    ], $attributes));
}

function validSaleNotePayload(CarTransaction $vehicle, array $attributes = []): array
{
    return array_merge([
        'car_transaction_id' => $vehicle->id,
        'buyer_name' => 'Sari Pembeli',
        'buyer_nik' => '3174012345678901',
        'buyer_phone' => '081234567890',
        'buyer_address' => 'Bandung',
        'deal_price' => 90000000,
        'payment_method' => 'Transfer',
        'sell_date' => '2026-10-22',
        'notes' => 'Nota penjualan independen',
    ], $attributes);
}

function createSaleNote(CarTransaction $vehicle, array $attributes = []): SaleNote
{
    return $vehicle->saleNote()->create(array_merge(
        validSaleNotePayload($vehicle),
        ['created_by' => User::factory()->create()->id],
        $attributes,
    ));
}

it('stores an independent sale note without changing the existing sales data', function () {
    $vehicle = createSaleNoteVehicle();
    $admin = User::factory()->create();

    $response = $this->actingAs($admin)
        ->post(route('nota-penjualan.store'), validSaleNotePayload($vehicle));

    $response->assertRedirect(route('nota-penjualan.index'))
        ->assertSessionHas('success');

    $note = SaleNote::query()->sole();

    expect($note->car_transaction_id)->toBe($vehicle->id)
        ->and((float) $note->deal_price)->toBe(90000000.0)
        ->and($vehicle->refresh()->sell_date)->toBeNull()
        ->and((float) $vehicle->selling_price)->toBe(0.0)
        ->and(Buyer::query()->count())->toBe(0);
});

it('defaults payment method and enforces the required vehicle and creator relationships', function () {
    $vehicle = createSaleNoteVehicle();
    $creator = User::factory()->create();

    $saleNote = $vehicle->saleNote()->create([
        'buyer_name' => 'Sari Pembeli',
        'deal_price' => 90000000,
        'sell_date' => '2026-10-22',
        'created_by' => $creator->id,
    ]);
    $saleNote->refresh();

    expect($saleNote->payment_method)->toBe('cash')
        ->and($saleNote->carTransaction->is($vehicle))->toBeTrue()
        ->and($saleNote->creator->is($creator))->toBeTrue();
});

it('shows only permitted financial columns to admins in the isolated note list', function () {
    $vehicle = createSaleNoteVehicle();
    createSaleNote($vehicle);

    $this->actingAs(User::factory()->create())
        ->get(route('nota-penjualan.index'))
        ->assertOk()
        ->assertSee('Sari Pembeli')
        ->assertSee('Harga deal')
        ->assertDontSee('Modal Beli')
        ->assertDontSee('Total Modal')
        ->assertDontSee('Laba Jual')
        ->assertDontSee('Laba Stor')
        ->assertDontSee('Laba Deal')
        ->assertDontSee('60,000,000');
});

it('shows financial details to masters in the isolated note list', function () {
    $vehicle = createSaleNoteVehicle(['setor_price' => 95000000]);
    createSaleNote($vehicle);

    $this->actingAs(User::factory()->master()->create())
        ->get(route('nota-penjualan.index'))
        ->assertOk()
        ->assertSee('Modal Beli')
        ->assertSee('Total Modal')
        ->assertSee('Laba Jual')
        ->assertSee('Laba Stor')
        ->assertSee('Laba Deal')
        ->assertSee('60,000,000');
});

it('hides financial data from admins on note detail pages', function () {
    $vehicle = createSaleNoteVehicle();
    $saleNote = createSaleNote($vehicle);

    $this->actingAs(User::factory()->create())
        ->get(route('nota-penjualan.show', $saleNote))
        ->assertOk()
        ->assertSee('Sari Pembeli')
        ->assertDontSee('Modal beli')
        ->assertDontSee('Total modal')
        ->assertDontSee('Laba jual')
        ->assertDontSee('60,000,000');
});

it('renders a separate sale-note form without leasing fields for both roles', function () {
    $vehicle = createSaleNoteVehicle();

    $this->actingAs(User::factory()->create())
        ->get(route('nota-penjualan.create'))
        ->assertOk()
        ->assertSee('transaction-form--sale-note')
        ->assertSee('transaction-form--admin')
        ->assertSee('name="deal_price"', false)
        ->assertDontSee('Pilihan Leasing')
        ->assertDontSee('name="leasing_options');

    $this->actingAs(User::factory()->master()->create())
        ->get(route('nota-penjualan.create'))
        ->assertOk()
        ->assertSee('transaction-form--master')
        ->assertSee('Ringkasan margin')
        ->assertDontSee('Pilihan Leasing');
});

it('escapes buyer notes displayed on the detail page', function () {
    $vehicle = createSaleNoteVehicle();
    $saleNote = createSaleNote($vehicle, [
        'notes' => '<script>alert("xss")</script>',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('nota-penjualan.show', $saleNote))
        ->assertOk()
        ->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert("xss")</script>', false);
});

it('rejects an incomplete note without creating a record', function () {
    $vehicle = createSaleNoteVehicle();

    $this->actingAs(User::factory()->create())
        ->post(route('nota-penjualan.store'), [])
        ->assertSessionHasErrors([
            'car_transaction_id',
            'buyer_name',
            'deal_price',
            'payment_method',
            'sell_date',
        ]);

    expect(SaleNote::query()->count())->toBe(0)
        ->and($vehicle->fresh()->sell_date)->toBeNull();
});

it('prevents creating more than one note for a vehicle', function () {
    $vehicle = createSaleNoteVehicle();
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('nota-penjualan.store'), validSaleNotePayload($vehicle))
        ->assertRedirect(route('nota-penjualan.index'));

    $this->post(route('nota-penjualan.store'), validSaleNotePayload($vehicle, [
        'buyer_name' => 'Pembeli Lain',
    ]))
        ->assertSessionHasErrors('car_transaction_id');

    expect(SaleNote::query()->count())->toBe(1);
});

it('allows admin and master to update notes without touching legacy sale fields', function () {
    $vehicle = createSaleNoteVehicle();
    $note = createSaleNote($vehicle);

    $this->actingAs(User::factory()->create())
        ->put(route('nota-penjualan.update', $note), [
            'buyer_name' => 'Sari Utami',
            'buyer_nik' => null,
            'buyer_phone' => '081111111111',
            'buyer_address' => 'Jakarta',
            'deal_price' => 95000000,
            'payment_method' => 'Tunai',
            'sell_date' => '2026-10-23',
            'notes' => 'Diubah tanpa mengganggu modul lama',
        ])
        ->assertRedirect(route('nota-penjualan.show', $note))
        ->assertSessionHas('success');

    expect($note->refresh()->buyer_name)->toBe('Sari Utami')
        ->and((float) $note->deal_price)->toBe(95000000.0)
        ->and($vehicle->refresh()->sell_date)->toBeNull()
        ->and((float) $vehicle->selling_price)->toBe(0.0);
});

it('keeps the legacy dashboard sales section separate from sale notes and Jual', function () {
    expect(Route::has('sales.index'))->toBeFalse()
        ->and(route('nota-penjualan.index'))->toEndWith('/nota-penjualan');

    $this->actingAs(User::factory()->create())
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertSee('id="penjualan"', false)
        ->assertSee(route('dashboard').'#penjualan', false)
        ->assertSee(route('jual.index'), false)
        ->assertSee(route('nota-penjualan.index'), false)
        ->assertDontSee('/sales', false);

    $this->get(route('nota-penjualan.index'))
        ->assertOk()
        ->assertSee(route('nota-penjualan.create'), false);
});

it('keeps a legacy buyer receipt and a new sale note separate for the same vehicle', function () {
    $vehicle = createSaleNoteVehicle([
        'sell_date' => '2026-10-20',
        'selling_price' => 85000000,
    ]);
    Buyer::create([
        'car_transaction_id' => $vehicle->id,
        'buyer_name' => 'Pembeli Modul Lama',
        'sell_price_deal' => 85000000,
        'payment_method' => 'Transfer',
        'sell_date' => '2026-10-20',
    ]);

    $this->actingAs(User::factory()->create())
        ->post(route('nota-penjualan.store'), validSaleNotePayload($vehicle))
        ->assertRedirect(route('nota-penjualan.index'));

    expect(Buyer::query()->count())->toBe(1)
        ->and(SaleNote::query()->count())->toBe(1)
        ->and($vehicle->refresh()->sell_date)->toBe('2026-10-20')
        ->and((float) $vehicle->selling_price)->toBe(85000000.0);
});

it('requires an authorized showroom role to view sale notes', function () {
    $this->actingAs(User::factory()->create(['role' => 'viewer']))
        ->get(route('nota-penjualan.index'))
        ->assertForbidden();
});

it('requires authentication to access sale notes', function () {
    $this->get(route('nota-penjualan.index'))
        ->assertRedirect('/login');
});
