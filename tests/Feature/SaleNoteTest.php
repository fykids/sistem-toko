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

it('stores a sale note and synchronizes the vehicle sale fields', function () {
    $vehicle = createSaleNoteVehicle();
    $admin = User::factory()->create();

    $response = $this->actingAs($admin)
        ->post(route('nota-penjualan.store'), validSaleNotePayload($vehicle));

    $response->assertRedirect(route('nota-penjualan.index'))
        ->assertSessionHas('success');

    $note = SaleNote::query()->sole();

    expect($note->car_transaction_id)->toBe($vehicle->id)
        ->and($note->nama_konsumen)->toBe('Sari Pembeli')
        ->and($vehicle->refresh()->sell_date)->toBe('2026-10-22')
        ->and((float) $vehicle->selling_price)->toBe(90000000.0)
        ->and($note->created_by)->toBe($admin->id);
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

it('keeps sale-note storage limited to the buyer identity and vehicle relationship', function () {
    expect(Schema::getColumnListing('sale_notes'))->toBe([
        'id',
        'car_transaction_id',
        'nama_konsumen',
        'created_by',
        'created_at',
        'updated_at',
    ]);
});

it('shows only permitted financial columns to admins in the isolated note list', function () {
    $vehicle = createSaleNoteVehicle();
    $admin = User::factory()->create();
    createSaleNote($vehicle, ['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->get(route('nota-penjualan.index'))
        ->assertOk()
        ->assertSee('Sari Pembeli')
        ->assertSee('Harga jual')
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
    $admin = User::factory()->create();
    $saleNote = createSaleNote($vehicle, ['created_by' => $admin->id]);

    $this->actingAs($admin)
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
        ->assertSee('name="payment_type"', false)
        ->assertSee('name="payment_amount"', false)
        ->assertDontSee('Pilihan Leasing')
        ->assertDontSee('name="leasing_options');

    $this->actingAs(User::factory()->master()->create())
        ->get(route('nota-penjualan.create'))
        ->assertOk()
        ->assertSee('transaction-form--master')
        ->assertDontSee('Pilihan Leasing');
});

it('escapes a buyer name displayed on the detail page', function () {
    $vehicle = createSaleNoteVehicle();
    $admin = User::factory()->create();
    $saleNote = createSaleNote($vehicle, [
        'nama_konsumen' => '<script>alert("xss")</script>',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
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
            'nama_konsumen',
            'payment_type',
            'payment_amount',
            'sell_date',
            'intertine_expenses',
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
        'nama_konsumen' => 'Pembeli Lain',
    ]))
        ->assertSessionHasErrors('car_transaction_id');

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

it('keeps the legacy dashboard sales section separate from sale notes and Jual', function () {
    expect(Route::has('sales.index'))->toBeFalse()
        ->and(route('nota-penjualan.index'))->toEndWith('/nota-penjualan');

    $this->actingAs(User::factory()->create())
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertSee('id="penjualan"', false)
        ->assertSee(route('dashboard').'#penjualan', false)
        ->assertSee(route('jual.create'), false)
        ->assertSee(route('jual.index'), false)
        ->assertSee('Laporan Penjualan')
        ->assertDontSee('/sales', false);

    $this->get(route('nota-penjualan.index'))
        ->assertOk()
        ->assertSee('Laporan Penjualan');
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
        ->get(route('dashboard', [
            'view' => 'reports',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]));

    $response->assertOk()
        ->assertSee('name="start_date"', false)
        ->assertSee('name="end_date"', false)
        ->assertSee('value="2026-10-01"', false)
        ->assertSee('value="2026-10-31"', false)
        ->assertSee('class="stat-label">Kendaraan tercatat</span>', false)
        ->assertSee('class="stat-label">Tersedia di stok</span>', false)
        ->assertSee('class="stat-label">Total modal kendaraan</span>', false)
        ->assertSee('class="showroom-report-label">Kendaraan terjual</span>', false)
        ->assertSee('B 1001 RANGE')
        ->assertDontSee('B 1002 RANGE')
        ->assertDontSee('B 1003 RANGE')
        ->assertSee('Filter')
        ->assertSee('Reset');

    $responseContent = $response->getContent();
    $reportSectionStart = strpos($responseContent, '<section class="showroom-report-grid"');
    $reportSectionEnd = strpos($responseContent, '</section>', $reportSectionStart);
    $reportSection = substr($responseContent, $reportSectionStart, $reportSectionEnd - $reportSectionStart);
    $statsSectionStart = strpos($responseContent, '<section class="stats-grid"');
    $statsSectionEnd = strpos($responseContent, '</section>', $statsSectionStart);
    $statsSection = substr($responseContent, $statsSectionStart, $statsSectionEnd - $statsSectionStart);

    expect($reportSection)->toContain('<strong>1</strong>')
        ->and($reportSection)->toContain('Rp 10,000,000')
        ->and($reportSection)->toContain('Rp 6,000,000')
        ->and($reportSection)->toContain('Rp 3,600,000')
        ->and($reportSection)->not->toContain('Rp 15,000,000')
        ->and($statsSection)->toContain('<strong class="stat-value">2</strong>')
        ->and($statsSection)->toContain('<strong class="stat-value">1</strong>')
        ->and($statsSection)->toContain('Rp 13,000,000')
        ->and($inPeriod->sell_date)->toBe('2026-10-01')
        ->and($outsidePeriod->sell_date)->toBe('2026-11-01')
        ->and($inPeriodStock->sell_date)->toBeNull();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('<strong class="stat-value">4</strong>', false)
        ->assertSee('Rp 30,000,000')
        ->assertSee('Rp 25,000,000');
});

it('paginates dashboard results by ten while retaining search, view, and period filters', function () {
    foreach (range(1, 11) as $number) {
        createSaleNoteVehicle([
            'plate_number' => sprintf('B %04d PAGE', $number),
        ]);
    }

    $this->actingAs(User::factory()->master()->create())
        ->get(route('dashboard', [
            'view' => 'inventory',
            'q' => 'PAGE',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]))
        ->assertOk()
        ->assertSee('Menampilkan 1-10')
        ->assertSee('dari 11 kendaraan')
        ->assertSee('q=PAGE', false)
        ->assertSee('view=inventory', false)
        ->assertSee('start_date=2026-10-01', false)
        ->assertSee('end_date=2026-10-31', false)
        ->assertSee('page=2', false);

    $pageTwo = $this->get(route('dashboard', [
        'view' => 'inventory',
        'q' => 'PAGE',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-31',
        'page' => 2,
    ]));

    $pageTwo->assertOk()
        ->assertSee('Menampilkan 11-11')
        ->assertSee('dari 11 kendaraan');

    expect(substr_count($pageTwo->getContent(), 'data-status="stock"'))->toBe(1);
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
        ->get(route('nota-penjualan.index', ['q' => 'PAGE']))
        ->assertOk()
        ->assertSee('page=2', false)
        ->assertSee('q=PAGE', false);

    expect(substr_count($firstPage->getContent(), 'data-label="Nama konsumen"'))->toBe(10);

    $pageTwo = $this->get(route('nota-penjualan.index', ['q' => 'PAGE', 'page' => 2]));

    $pageTwo->assertOk();

    expect(substr_count($pageTwo->getContent(), 'data-label="Nama konsumen"'))->toBe(1);
});

it('rejects a report period whose end date precedes its start date', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard', [
            'start_date' => '2026-10-31',
            'end_date' => '2026-10-01',
        ]))
        ->assertSessionHasErrors('end_date');
});

it('rejects vehicles already marked sold without querying legacy buyers', function () {
    $vehicle = createSaleNoteVehicle([
        'sell_date' => '2026-10-20',
        'selling_price' => 85000000,
    ]);
    Schema::drop('buyers');

    $this->actingAs(User::factory()->create())
        ->post(route('nota-penjualan.store'), validSaleNotePayload($vehicle))
        ->assertSessionHasErrors('car_transaction_id');

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
