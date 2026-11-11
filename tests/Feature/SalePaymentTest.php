<?php

use App\Models\CarTransaction;
use App\Models\SaleNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createSalePaymentTestNote(User $creator, array $vehicleOverrides = []): SaleNote
{
    $vehicle = CarTransaction::query()->create(array_replace([
        'plate_number' => 'B 4455 PAY',
        'brand' => 'Toyota',
        'model' => 'Yaris',
        'year' => 2024,
        'transmission' => 'Automatic',
        'color' => 'Silver',
        'buy_date' => '2026-10-01',
        'buy_price' => 65000000,
        'sell_date' => '2026-10-20',
        'selling_price' => 92000000,
        'down_payment' => 10000000,
        'settlement_amount' => 0,
        'leasing_disbursement' => 0,
        'planned_payment_date' => '2026-11-20',
    ], $vehicleOverrides));

    return $vehicle->saleNote()->create([
        'nama_konsumen' => 'Dewi Pembeli',
        'created_by' => $creator->id,
    ]);
}

it('filters sale notes into unpaid leasing and paid groups', function () {
    $master = User::factory()->master()->create();
    $unpaidNote = createSalePaymentTestNote($master, ['plate_number' => 'B 1111 DUE']);
    $leasingNote = createSalePaymentTestNote($master, [
        'plate_number' => 'B 2222 LEA',
        'down_payment' => 0,
        'leasing_disbursement' => 80000000,
    ]);
    $paidNote = createSalePaymentTestNote($master, [
        'plate_number' => 'B 3333 PAID',
        'down_payment' => 92000000,
    ]);

    $this->actingAs($master)
        ->getJson(route('api.spa.jual.index', ['status' => 'unpaid']))
        ->assertOk()
        ->assertJsonPath('data.0.id', $unpaidNote->id)
        ->assertJsonPath('status', 'unpaid')
        ->assertJsonCount(1, 'data');

    $this->getJson(route('api.spa.jual.index', ['status' => 'leasing']))
        ->assertOk()
        ->assertJsonPath('data.0.id', $leasingNote->id)
        ->assertJsonPath('status', 'leasing')
        ->assertJsonCount(1, 'data');

    $this->getJson(route('api.spa.jual.index', ['status' => 'paid']))
        ->assertOk()
        ->assertJsonPath('data.0.id', $paidNote->id)
        ->assertJsonPath('status', 'paid')
        ->assertJsonCount(1, 'data');
});

it('loads a sale detail page when no payment history has been recorded', function () {
    $creator = User::factory()->create();
    $saleNote = createSalePaymentTestNote($creator);

    $this->actingAs($creator)
        ->getJson(route('api.spa.jual.show', $saleNote))
        ->assertOk()
        ->assertJsonPath('data.car_transaction.plate_number', 'B 4455 PAY')
        ->assertJsonPath('data.payments', []);
});

it('records a partial payment and updates the remaining balance', function () {
    $creator = User::factory()->create();
    $saleNote = createSalePaymentTestNote($creator);
    $actor = User::factory()->master()->create();

    $this->actingAs($actor)
        ->postJson(route('api.spa.jual.payments.store', $saleNote), [
            'amount' => '20.000.000',
            'payment_method' => 'bank_transfer',
            'payment_date' => '2026-10-25',
            'notes' => "Referensi TRX-2026-10 <script>alert('x')</script>",
        ])->assertCreated()
        ->assertJsonPath('message', 'Pelunasan berhasil dicatat.');

    $vehicle = $saleNote->carTransaction()->firstOrFail();
    $this->assertDatabaseHas('sale_payments', [
        'sale_note_id' => $saleNote->id,
        'amount' => 20000000,
        'payment_method' => 'bank_transfer',
        'payment_date' => '2026-10-25 00:00:00',
        'notes' => "Referensi TRX-2026-10 <script>alert('x')</script>",
        'recorded_by' => $actor->id,
    ]);

    expect((float) $vehicle->fresh()->settlement_amount)->toBe(20000000.0)
        ->and((float) $vehicle->fresh()->remaining_payment)->toBe(62000000.0)
        ->and($vehicle->fresh()->planned_payment_date)->toBe('2026-11-20');

    $this->getJson(route('api.spa.jual.show', $saleNote))
        ->assertOk()
        ->assertJsonPath('data.payments.0.notes', "Referensi TRX-2026-10 <script>alert('x')</script>")
        ->assertJsonPath('data.payments.0.amount', '20000000.00');
});

it('classifies a pending payment recorded through leasing as in progress', function () {
    $creator = User::factory()->create();
    $saleNote = createSalePaymentTestNote($creator);

    $this->actingAs($creator)
        ->postJson(route('api.spa.jual.payments.store', $saleNote), [
            'amount' => 1000000,
            'payment_method' => 'leasing',
            'payment_date' => '2026-10-26',
        ])->assertCreated();

    $this->getJson(route('api.spa.jual.index', ['status' => 'leasing']))
        ->assertOk()
        ->assertJsonPath('data.0.payment_status', 'leasing');

    $this->getJson(route('api.spa.jual.index', ['status' => 'unpaid']))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('marks a transaction paid and clamps its remaining balance after an overpayment', function () {
    $creator = User::factory()->create();
    $saleNote = createSalePaymentTestNote($creator);

    $this->actingAs($creator)
        ->getJson(route('api.spa.jual.show', $saleNote))
        ->assertOk()
        ->assertJsonPath('data.car_transaction.plate_number', 'B 4455 PAY')
        ->assertJsonPath('data.nama_konsumen', 'Dewi Pembeli')
        ->assertJsonPath('data.car_transaction.remaining_payment', 82000000);

    $this->postJson(route('api.spa.jual.payments.store', $saleNote), [
        'amount' => 90000000,
        'payment_method' => 'cash',
        'payment_date' => '2026-10-26',
    ])->assertCreated();

    expect((float) $saleNote->carTransaction->fresh()->remaining_payment)->toBe(0.0)
        ->and($saleNote->carTransaction->fresh()->planned_payment_date)->toBeNull()
        ->and($saleNote->fresh()->paymentStatus())->toBe('paid');
});

it('prevents another admin from viewing or recording a sale payment', function () {
    $creator = User::factory()->create();
    $saleNote = createSalePaymentTestNote($creator);
    $otherAdmin = User::factory()->create();

    $this->actingAs($otherAdmin)
        ->getJson(route('api.spa.jual.show', $saleNote))
        ->assertForbidden();

    $this->postJson(route('api.spa.jual.payments.store', $saleNote), [
        'amount' => 1000000,
        'payment_method' => 'cash',
        'payment_date' => '2026-10-26',
    ])
        ->assertForbidden();

    $this->assertDatabaseCount('sale_payments', 0);
    expect((float) $saleNote->carTransaction->fresh()->settlement_amount)->toBe(0.0);
});

it('rejects invalid and unnecessary payment entries', function () {
    $creator = User::factory()->create();
    $saleNote = createSalePaymentTestNote($creator);

    $this->actingAs($creator)
        ->post(route('jual.payments.store', $saleNote), [
            'amount' => 0,
            'payment_method' => 'unknown',
            'payment_date' => 'invalid-date',
        ])
        ->assertSessionHasErrors(['amount', 'payment_method', 'payment_date']);

    $saleNote->carTransaction->update(['down_payment' => 92000000]);

    $this->post(route('jual.payments.store', $saleNote), [
        'amount' => 1,
        'payment_method' => 'cash',
        'payment_date' => '2026-10-26',
    ])
        ->assertSessionHasErrors('amount');

    $this->assertDatabaseCount('sale_payments', 0);
});

it('rejects a payment amount beyond the database decimal range', function () {
    $creator = User::factory()->create();
    $saleNote = createSalePaymentTestNote($creator);

    $this->actingAs($creator)
        ->from(route('jual.payments.create', $saleNote))
        ->post(route('jual.payments.store', $saleNote), [
            'amount' => 10000000000000,
            'payment_method' => 'cash',
            'payment_date' => '2026-10-26',
        ])
        ->assertRedirect(route('jual.payments.create', $saleNote))
        ->assertSessionHasErrors('amount');

    $this->assertDatabaseCount('sale_payments', 0);
    expect((float) $saleNote->carTransaction->fresh()->settlement_amount)->toBe(0.0);
});

it('requires authentication to access the payment form', function () {
    $saleNote = createSalePaymentTestNote(User::factory()->create());

    $this->get(route('jual.payments.create', $saleNote))
        ->assertRedirect('/login');
});
