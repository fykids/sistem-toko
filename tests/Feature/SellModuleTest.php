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
        'selling_price' => 92000000,
        'payment_type' => 'down_payment',
        'payment_amount' => 0,
        'sell_date' => '2026-10-23',
        'intertine_expenses' => [
            ['name' => 'KR Jual', 'cost' => 0],
            ['name' => 'OP Survei', 'cost' => 0],
            ['name' => 'Fee CMO', 'cost' => 0],
        ],
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
        $attributes['payment_type'] => $attributes['payment_amount'],
    ]);

    return $vehicle->saleNote()->create([
        'nama_konsumen' => $attributes['nama_konsumen'],
        'created_by' => $creator->id,
    ]);
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
        ->assertSee(route('jual.create'), false)
        ->assertSee(route('jual.index'), false)
        ->assertSee('Input / Transaksi Jual')
        ->assertSee('Laporan Penjualan');

    $this->get('/sales')->assertNotFound();
});

it('renders the independent jual list and role-specific form without leasing', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('jual.index'))
        ->assertOk()
        ->assertSee('Menu Jual')
        ->assertSee(route('jual.create'), false);

    $response = $this->get(route('jual.create'));

    $response
        ->assertOk()
        ->assertSee('jual-form')
        ->assertSee('transaction-form--admin')
        ->assertSee('class="section-heading"', false)
        ->assertSee('class="section-content', false)
        ->assertSee('class="btn btn-accent"', false)
        ->assertSee('class="btn btn-secondary"', false)
        ->assertSee('name="payment_type"', false)
        ->assertSee('name="payment_amount"', false)
        ->assertSee('name="selling_price"', false)
        ->assertSee('DP (Rp)')
        ->assertSee('Pencairan Leasing (Rp)')
        ->assertSee('Pelunasan (Rp)')
        ->assertSee('class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end"', false)
        ->assertSee('name="payment_date"', false)
        ->assertSee('Tanggal Pembayaran')
        ->assertDontSee('Harga Jual (Rp)</option>')
        ->assertSee('grid grid-cols-12 gap-4 items-start', false)
        ->assertSee('sell-module-layout', false)
        ->assertSee('sell-module-main', false)
        ->assertSee('lg:col-span-5', false)
        ->assertSee('sell-module-leasing-card', false)
        ->assertSee('lg:col-span-7', false)
        ->assertSee('sell-module-leasing-table', false)
        ->assertSee('leasing-custom-table', false)
        ->assertSee('name="intertine_expenses[0][cost]"', false)
        ->assertDontSee('Pilihan Leasing')
        ->assertDontSee('leasing_options');

    expect(substr_count($response->getContent(), 'class="form-section"'))->toBe(1);

    $this->actingAs(User::factory()->master()->create())
        ->get(route('jual.create'))
        ->assertOk()
        ->assertSee('transaction-form--master')
        ->assertDontSee('Pilihan Leasing');
});

it('shows selected vehicle details and section two and three costs without exposing master-only values', function () {
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
        ->get(route('jual.create', ['vehicle' => $vehicle->id]))
        ->assertOk()
        ->assertSee('Preview rincian mobil terpilih')
        ->assertSee('Informasi Pembayaran Leasing')
        ->assertSee('BCA')
        ->assertSee('80,000,000')
        ->assertSee('3,500,000')
        ->assertSee('data-leasing-value="dp"', false)
        ->assertDontSee('name="leasing_options[', false)
        ->assertSee('B 1122 JUAL')
        ->assertSee('Rincian biaya perbaikan')
        ->assertSee('Tune up')
        ->assertSee('Rp 1,250,000')
        ->assertSee('Rincian pajak')
        ->assertSee('Pajak')
        ->assertSee('Rincian biaya intertine')
        ->assertSee('Komisi')
        ->assertSee('Harga setor')
        ->assertSee('Rp 110,000,000')
        ->assertDontSee('Ripan booster')
        ->assertDontSee('Rp 3,000,000');

    $this->actingAs(User::factory()->master()->create())
        ->get(route('jual.create', ['vehicle' => $vehicle->id]))
        ->assertOk()
        ->assertSee('Modal beli')
        ->assertSee('Ripan booster')
        ->assertSee('Rp 3,000,000');
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
        ->get(route('jual.create', ['vehicle' => $selectedVehicle->id]));

    $response->assertOk()
        ->assertSee($vehicles->first()->plate_number)
        ->assertSee($selectedVehicle->plate_number);

    expect(substr_count($response->getContent(), 'data-vehicle-preview='))
        ->toBe($vehicles->count())
        ->and(substr_count($response->getContent(), $selectedVehicle->plate_number))
        ->toBeGreaterThan(1);
});

it('renders the jual form when buyers has no vehicle relationship column', function () {
    $vehicle = createJualVehicle();
    Schema::drop('buyers');
    Schema::create('buyers', function (Blueprint $table): void {
        $table->id();
    });

    $this->actingAs(User::factory()->create())
        ->get(route('jual.create'))
        ->assertOk()
        ->assertSee($vehicle->plate_number);
});

it('shows sensitive financial columns only to masters in the jual list', function () {
    $vehicle = createJualVehicle();
    $creator = User::factory()->create();
    createJualSaleNote($vehicle, $creator);

    $this->actingAs(User::factory()->create())
        ->get(route('jual.index'))
        ->assertOk()
        ->assertDontSee('Dewi Jual')
        ->assertDontSee('Modal beli')
        ->assertDontSee('Total modal')
        ->assertDontSee('Laba deal')
        ->assertDontSee('65,000,000');

    $this->actingAs($creator)
        ->get(route('jual.index'))
        ->assertOk()
        ->assertSee('Dewi Jual')
        ->assertDontSee('Modal beli')
        ->assertDontSee('65,000,000');

    $this->actingAs(User::factory()->master()->create())
        ->get(route('jual.index'))
        ->assertOk()
        ->assertSee('Modal beli')
        ->assertSee('Total modal')
        ->assertSee('Laba deal')
        ->assertSee('65,000,000');
});

it('stores a jual record and updates vehicle stock and sale details atomically', function () {
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
        'nama_konsumen' => 'Dewi Jual',
        'created_by' => $admin->id,
    ]);

    expect($vehicle->refresh()->sell_date)->toBe('2026-10-23')
        ->and((float) $vehicle->selling_price)->toBe(92000000.0);

    $this->get(route('jual.create'))
        ->assertOk()
        ->assertDontSee('B 1122 JUAL');
    $this->get(route('nota-penjualan.create'))
        ->assertOk()
        ->assertDontSee('B 1122 JUAL');

    $this->get(route('jual.show', $saleNote))
        ->assertOk()
        ->assertSee('Dewi Jual')
        ->assertSee('B 1122 JUAL');

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('B 1122 JUAL')
        ->assertSee('Terjual');
});

it('updates the selected payment field and saves the three sales cost categories', function () {
    $vehicle = createJualVehicle();
    $vehicle->update(['selling_price' => 92000000]);
    $admin = User::factory()->create();

    $response = $this->actingAs($admin)
        ->post(route('jual.store'), validJualPayload($vehicle, [
            'payment_type' => 'down_payment',
            'payment_amount' => 10000000,
            'payment_date' => '2026-10-24',
            'intertine_expenses' => [
                ['name' => 'KR Jual', 'cost' => 1500000],
                ['name' => 'OP Survei', 'cost' => 750000],
                ['name' => 'Fee CMO', 'cost' => 500000],
            ],
        ]));

    $saleNote = SaleNote::query()->sole();
    $response->assertRedirect(route('jual.show', $saleNote));
    $vehicle->refresh();

    expect((float) $vehicle->selling_price)->toBe(92000000.0)
        ->and((float) $vehicle->down_payment)->toBe(10000000.0)
        ->and($vehicle->down_payment_date)->toBe('2026-10-24')
        ->and((float) $vehicle->remaining_payment)->toBe(82000000.0)
        ->and($vehicle->intertine_expenses)->toBe([
            ['name' => 'KR Jual', 'cost' => 1500000],
            ['name' => 'OP Survei', 'cost' => 750000],
            ['name' => 'Fee CMO', 'cost' => 500000],
        ]);
});

it('stores a selected payment date in the matching vehicle field', function (string $paymentType, string $dateField) {
    $vehicle = createJualVehicle();

    $response = $this->actingAs(User::factory()->create())
        ->post(route('jual.store'), validJualPayload($vehicle, [
            'payment_type' => $paymentType,
            'payment_amount' => 1000000,
            'payment_date' => '2026-10-24',
        ]));

    $saleNote = SaleNote::query()->sole();
    $response->assertRedirect(route('jual.show', $saleNote));

    expect($vehicle->fresh()->getAttribute($dateField))->toBe('2026-10-24');
})->with([
    'leasing disbursement' => ['leasing_disbursement', 'leasing_disbursement_date'],
    'settlement' => ['settlement_amount', 'settlement_date'],
]);

it('rejects an unapproved payment field without changing the vehicle', function () {
    $vehicle = createJualVehicle();

    $this->actingAs(User::factory()->create())
        ->post(route('jual.store'), validJualPayload($vehicle, [
            'payment_type' => 'buy_price',
            'payment_amount' => 1,
        ]))
        ->assertSessionHasErrors('payment_type');

    expect(SaleNote::query()->count())->toBe(0)
        ->and((float) $vehicle->fresh()->buy_price)->toBe(65000000.0)
        ->and($vehicle->fresh()->sell_date)->toBeNull();
});

it('accepts a nominal above the former validation ceiling', function () {
    $vehicle = createJualVehicle();
    $admin = User::factory()->create();

    $response = $this->actingAs($admin)
        ->post(route('jual.store'), validJualPayload($vehicle, [
            'selling_price' => '20000000000000',
            'payment_type' => 'down_payment',
            'payment_amount' => '10000000000000',
            'intertine_expenses' => [
                ['name' => 'KR Jual', 'cost' => '10000000000000'],
                ['name' => 'OP Survei', 'cost' => 0],
                ['name' => 'Fee CMO', 'cost' => 0],
            ],
        ]));

    $saleNote = SaleNote::query()->sole();
    $response->assertRedirect(route('jual.show', $saleNote));

    expect((float) $vehicle->fresh()->selling_price)->toBe(20000000000000.0)
        ->and((float) $vehicle->fresh()->down_payment)->toBe(10000000000000.0)
        ->and((float) $vehicle->fresh()->remaining_payment)->toBe(10000000000000.0)
        ->and((float) $vehicle->fresh()->intertine_expenses[0]['cost'])->toBe(10000000000000.0);
});

it('requires each sales cost category to be entered only once', function () {
    $vehicle = createJualVehicle();

    $this->actingAs(User::factory()->create())
        ->post(route('jual.store'), validJualPayload($vehicle, [
            'intertine_expenses' => [
                ['name' => 'KR Jual', 'cost' => 100000],
                ['name' => 'OP Survei', 'cost' => 200000],
                ['name' => 'KR Jual', 'cost' => 300000],
            ],
        ]))
        ->assertSessionHasErrors('intertine_expenses.2.name');

    expect(SaleNote::query()->count())->toBe(0)
        ->and($vehicle->fresh()->sell_date)->toBeNull();
});

it('prepopulates an existing jual note when its edit form is opened', function () {
    $vehicle = createJualVehicle();
    $creator = User::factory()->create();
    $saleNote = createJualSaleNote($vehicle, $creator);

    $this->actingAs($creator)
        ->get(route('jual.edit', $saleNote))
        ->assertOk()
        ->assertSee('value="Dewi Jual"', false)
        ->assertSee('value="92000000"', false)
        ->assertSee(route('jual.update', $saleNote), false);
});

it('updates buyer identity and the canonical vehicle sale attributes', function () {
    $vehicle = createJualVehicle();
    $creator = User::factory()->create();
    $saleNote = createJualSaleNote($vehicle, $creator);

    $this->actingAs($creator)
        ->put(route('jual.update', $saleNote), [
            'nama_konsumen' => 'Dewi Utami',
            'selling_price' => 94000000,
            'payment_type' => 'down_payment',
            'payment_amount' => 0,
            'sell_date' => '2026-10-24',
            'intertine_expenses' => [
                ['name' => 'KR Jual', 'cost' => 0],
                ['name' => 'OP Survei', 'cost' => 0],
                ['name' => 'Fee CMO', 'cost' => 0],
            ],
        ])
        ->assertRedirect(route('jual.show', $saleNote))
        ->assertSessionHas('success');

    expect($saleNote->refresh()->nama_konsumen)->toBe('Dewi Utami')
        ->and($vehicle->refresh()->sell_date)->toBe('2026-10-24')
        ->and((float) $vehicle->selling_price)->toBe(94000000.0);
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

    $this->get(route('jual.create'))
        ->assertOk()
        ->assertSee('B 1122 JUAL');
});

it('prevents an admin from viewing another admins sale record', function () {
    $vehicle = createJualVehicle();
    $creator = User::factory()->create();
    $saleNote = createJualSaleNote($vehicle, $creator);
    $otherAdmin = User::factory()->create();

    $this->actingAs($otherAdmin)
        ->get(route('jual.index'))
        ->assertOk()
        ->assertDontSee('Dewi Jual');

    $this->get(route('jual.show', $saleNote))->assertForbidden();
    $this->get(route('jual.edit', $saleNote))->assertForbidden();
    $this->get(route('nota-penjualan.index'))
        ->assertOk()
        ->assertDontSee('Dewi Jual');
    $this->get(route('nota-penjualan.show', $saleNote))->assertForbidden();

    $this->actingAs(User::factory()->master()->create())
        ->get(route('nota-penjualan.index'))
        ->assertOk()
        ->assertSee('Dewi Jual');
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
