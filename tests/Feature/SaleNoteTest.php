<?php

use App\Models\CarTransaction;
use App\Models\SaleNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

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
        'nama_konsumen' => 'Sari Pembeli',
        'payment_type' => 'selling_price',
        'payment_amount' => 90000000,
        'sell_date' => '2026-10-22',
        'intertine_expenses' => [
            ['name' => 'KR Jual', 'cost' => 0],
            ['name' => 'OP Survei', 'cost' => 0],
            ['name' => 'Fee CMO', 'cost' => 0],
        ],
    ], $attributes);
}

function createSaleNote(CarTransaction $vehicle, array $attributes = []): SaleNote
{
    $attributes = array_merge(validSaleNotePayload($vehicle), $attributes);
    $creatorId = $attributes['created_by'] ?? User::factory()->create()->id;
    $vehicle->update([
        'sell_date' => $attributes['sell_date'],
        $attributes['payment_type'] => $attributes['payment_amount'],
    ]);

    return $vehicle->saleNote()->create([
        'nama_konsumen' => $attributes['nama_konsumen'],
        'created_by' => $creatorId,
    ]);
}

it('does not expose legacy sale-note create or store routes', function () {
    $this->actingAs(User::factory()->create())
        ->get('/nota-penjualan/create')
        ->assertNotFound();

    $this->post('/nota-penjualan', [])
        ->assertStatus(405);

    expect(Route::has('nota-penjualan.create'))->toBeFalse()
        ->and(Route::has('nota-penjualan.store'))->toBeFalse();
});

it('enforces the required vehicle and creator relationships', function () {
    $vehicle = createSaleNoteVehicle();
    $creator = User::factory()->create();
    $vehicle->update([
        'sell_date' => '2026-10-22',
        'selling_price' => 90000000,
    ]);

    $saleNote = $vehicle->saleNote()->create([
        'nama_konsumen' => 'Sari Pembeli',
        'created_by' => $creator->id,
    ]);
    $saleNote->refresh();

    expect($saleNote->carTransaction->is($vehicle))->toBeTrue()
        ->and($saleNote->creator->is($creator))->toBeTrue();
});

it('stores consumer contact details with the sale note and vehicle relationship', function () {
    expect(Schema::getColumnListing('sale_notes'))->toBe([
        'id',
        'car_transaction_id',
        'nama_konsumen',
        'created_by',
        'created_at',
        'updated_at',
        'buyer_nik',
        'buyer_phone',
        'buyer_address',
    ]);
});

it('omits sensitive financial fields from an admins sale-note API list', function () {
    $vehicle = createSaleNoteVehicle();
    $admin = User::factory()->create();
    createSaleNote($vehicle, ['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->getJson(route('api.spa.sale-notes.index'))
        ->assertOk()
        ->assertJsonPath('can_view_sensitive_data', false)
        ->assertJsonPath('data.0.nama_konsumen', 'Sari Pembeli')
        ->assertJsonMissingPath('data.0.car_transaction.buy_price')
        ->assertJsonMissingPath('data.0.car_transaction.total_capital');
});

it('includes permitted financial fields in a masters sale-note API list', function () {
    $vehicle = createSaleNoteVehicle(['setor_price' => 95000000]);
    createSaleNote($vehicle);

    $this->actingAs(User::factory()->master()->create())
        ->getJson(route('api.spa.sale-notes.index'))
        ->assertOk()
        ->assertJsonPath('can_view_sensitive_data', true)
        ->assertJsonPath('data.0.car_transaction.buy_price', 60000000)
        ->assertJsonPath('data.0.car_transaction.total_capital', 60000000);
});

it('omits sensitive financial fields from an admins sale-note detail API response', function () {
    $vehicle = createSaleNoteVehicle();
    $admin = User::factory()->create();
    $saleNote = createSaleNote($vehicle, ['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->getJson(route('api.spa.sale-notes.show', $saleNote))
        ->assertOk()
        ->assertJsonPath('data.nama_konsumen', 'Sari Pembeli')
        ->assertJsonPath('data.can_view_sensitive_data', false)
        ->assertJsonMissingPath('data.car_transaction.buy_price')
        ->assertJsonMissingPath('data.car_transaction.total_capital');
});

it('does not expose dedicated sale-note create endpoints to admin or master', function () {
    foreach ([User::factory()->create(), User::factory()->master()->create()] as $user) {
        $this->actingAs($user)
            ->getJson('/api/spa/sale-notes/create')
            ->assertNotFound();

        $this->postJson('/api/spa/sale-notes', [])
            ->assertStatus(405);
    }

    expect(Route::has('api.spa.sale-notes.create'))->toBeFalse()
        ->and(Route::has('api.spa.sale-notes.store'))->toBeFalse();
});

it('serializes an untrusted buyer name as data for escaped Vue interpolation', function () {
    $vehicle = createSaleNoteVehicle();
    $admin = User::factory()->create();
    $saleNote = createSaleNote($vehicle, [
        'nama_konsumen' => '<script>alert("xss")</script>',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->getJson(route('api.spa.sale-notes.show', $saleNote))
        ->assertOk()
        ->assertJsonPath('data.nama_konsumen', '<script>alert("xss")</script>');
});

it('does not persist incomplete legacy note submissions after the form is removed', function () {
    $vehicle = createSaleNoteVehicle();

    $this->actingAs(User::factory()->create())
        ->post('/nota-penjualan', [])
        ->assertStatus(405);

    expect(SaleNote::query()->count())->toBe(0)
        ->and($vehicle->fresh()->sell_date)->toBeNull();
});

it('prevents creating more than one note for a vehicle through Jual', function () {
    $vehicle = createSaleNoteVehicle();
    $admin = User::factory()->create();
    $payload = [
        'car_transaction_id' => $vehicle->id,
        'nama_konsumen' => 'Sari Pembeli',
        'selling_price' => 90000000,
        'down_payment' => 90000000,
        'down_payment_date' => '2026-10-22',
        'settlement_amount' => 0,
        'leasing_disbursement' => 0,
        'sell_date' => '2026-10-22',
    ];

    $this->actingAs($admin)
        ->postJson(route('api.spa.jual.store'), $payload)
        ->assertCreated();

    $this->postJson(route('api.spa.jual.store'), array_merge($payload, [
        'nama_konsumen' => 'Pembeli Lain',
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('car_transaction_id');

    expect(SaleNote::query()->count())->toBe(1);
});

it('updates the canonical vehicle sale fields when a note is edited', function () {
    $vehicle = createSaleNoteVehicle();
    $admin = User::factory()->create();
    $note = createSaleNote($vehicle, ['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->put(route('nota-penjualan.update', $note), [
            'nama_konsumen' => 'Sari Utami',
            'payment_type' => 'selling_price',
            'payment_amount' => 95000000,
            'sell_date' => '2026-10-23',
            'intertine_expenses' => [
                ['name' => 'KR Jual', 'cost' => 0],
                ['name' => 'OP Survei', 'cost' => 0],
                ['name' => 'Fee CMO', 'cost' => 0],
            ],
        ])
        ->assertRedirect(route('nota-penjualan.show', $note))
        ->assertSessionHas('success');

    expect($note->refresh()->nama_konsumen)->toBe('Sari Utami')
        ->and($vehicle->refresh()->sell_date)->toBe('2026-10-23')
        ->and((float) $vehicle->selling_price)->toBe(95000000.0);
});

it('keeps sale-note and Jual APIs separate from the removed sales endpoint', function () {
    expect(Route::has('sales.index'))->toBeFalse()
        ->and(route('nota-penjualan.index'))->toEndWith('/nota-penjualan');

    $this->actingAs(User::factory()->create())
        ->getJson(route('api.spa.dashboard'))
        ->assertOk();

    $this->get(route('nota-penjualan.index'))
        ->assertRedirect('/app/nota-penjualan');

    $this->getJson(route('api.spa.sale-notes.index'))->assertOk();
    $this->getJson(route('api.spa.jual.index'))->assertOk();
});

it('filters report totals and report rows by inclusive sale date range', function () {
    $inPeriod = createSaleNoteVehicle([
        'plate_number' => 'B 1001 RANGE',
        'buy_date' => '2026-09-15',
        'buy_price' => 6000000,
        'sell_date' => '2026-10-01',
        'selling_price' => 10000000,
    ]);
    $outsidePeriod = createSaleNoteVehicle([
        'plate_number' => 'B 1002 RANGE',
        'buy_date' => '2026-11-01',
        'buy_price' => 8000000,
        'sell_date' => '2026-11-01',
        'selling_price' => 15000000,
    ]);
    $inPeriodStock = createSaleNoteVehicle([
        'plate_number' => 'B 1003 RANGE',
        'buy_date' => '2026-10-15',
        'buy_price' => 7000000,
    ]);
    createSaleNoteVehicle([
        'plate_number' => 'B 1004 RANGE',
        'buy_date' => '2026-11-15',
        'buy_price' => 9000000,
    ]);

    $response = $this->actingAs(User::factory()->master()->create())
        ->getJson(route('api.spa.dashboard', [
            'view' => 'reports',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]));

    $response->assertOk()
        ->assertJsonPath('filters.view', 'reports')
        ->assertJsonPath('filters.start_date', '2026-10-01')
        ->assertJsonPath('filters.end_date', '2026-10-31')
        ->assertJsonPath('metrics.report_sold_count', 1)
        ->assertJsonPath('metrics.report_sold_revenue', 10000000)
        ->assertJsonPath('metrics.report_sold_capital', 6000000)
        ->assertJsonPath('data.0.plate_number', 'B 1001 RANGE')
        ->assertJsonMissing(['plate_number' => 'B 1002 RANGE'])
        ->assertJsonMissing(['plate_number' => 'B 1003 RANGE']);

    $this->getJson(route('api.spa.dashboard'))
        ->assertOk()
        ->assertJsonPath('metrics.report_transaction_count', 4)
        ->assertJsonPath('metrics.report_stock_count', 2)
        ->assertJsonPath('metrics.report_recorded_capital', 30000000);

    expect($inPeriod->sell_date)->toBe('2026-10-01')
        ->and($outsidePeriod->sell_date)->toBe('2026-11-01')
        ->and($inPeriodStock->sell_date)->toBeNull();
});

it('paginates dashboard results by ten while retaining search, view, and period filters', function () {
    foreach (range(1, 11) as $number) {
        createSaleNoteVehicle([
            'plate_number' => sprintf('B %04d PAGE', $number),
        ]);
    }

    $this->actingAs(User::factory()->master()->create())
        ->getJson(route('api.spa.dashboard', [
            'view' => 'inventory',
            'q' => 'PAGE',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]))
        ->assertOk()
        ->assertJsonPath('filters.view', 'inventory')
        ->assertJsonPath('filters.search', 'PAGE')
        ->assertJsonPath('meta.total', 11)
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('meta.last_page', 2);

    $pageTwo = $this->getJson(route('api.spa.dashboard', [
        'view' => 'inventory',
        'q' => 'PAGE',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-31',
        'page' => 2,
    ]));

    $pageTwo->assertOk()
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.plate_number', 'B 0001 PAGE');

});

it('paginates sale notes by ten and retains query parameters in page links', function () {
    $creator = User::factory()->master()->create();

    foreach (range(1, 11) as $number) {
        $vehicle = createSaleNoteVehicle([
            'plate_number' => sprintf('B %04d NOTE', $number),
            'sell_date' => sprintf('2026-10-%02d', $number),
        ]);
        createSaleNote($vehicle, ['created_by' => $creator->id]);
    }

    $firstPage = $this->actingAs($creator)
        ->getJson(route('api.spa.sale-notes.index', ['q' => 'PAGE']))
        ->assertOk()
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.total', 11)
        ->assertJsonPath('meta.last_page', 2);

    expect(count($firstPage->json('data')))->toBe(10);

    $pageTwo = $this->getJson(route('api.spa.sale-notes.index', ['q' => 'PAGE', 'page' => 2]));
    $pageTwo->assertOk()
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonCount(1, 'data');
});

it('rejects a report period whose end date precedes its start date', function () {
    $this->actingAs(User::factory()->create())
        ->getJson(route('api.spa.dashboard', [
            'start_date' => '2026-10-31',
            'end_date' => '2026-10-01',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('end_date');
});

it('rejects vehicles already marked sold through Jual without querying legacy buyers', function () {
    $vehicle = createSaleNoteVehicle([
        'sell_date' => '2026-10-20',
        'selling_price' => 85000000,
    ]);
    Schema::drop('buyers');

    $this->actingAs(User::factory()->create())
        ->postJson(route('api.spa.jual.store'), [
            'car_transaction_id' => $vehicle->id,
            'nama_konsumen' => 'Sari Pembeli',
            'selling_price' => 90000000,
            'down_payment' => 90000000,
            'down_payment_date' => '2026-10-22',
            'settlement_amount' => 0,
            'leasing_disbursement' => 0,
            'sell_date' => '2026-10-22',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('car_transaction_id');

    expect(SaleNote::query()->count())->toBe(0)
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
