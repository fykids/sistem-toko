<?php

use App\Models\CarTransaction;
use App\Models\User;
use Database\Seeders\CarTransactionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it adds ten available inventory vehicles without creating sales data', function () {
    $seeder = app(CarTransactionSeeder::class);
    $seeder->run();
    $seeder->run();

    $this->assertDatabaseCount('car_transactions', 10);
    $this->assertDatabaseCount('sale_notes', 0);
    $this->assertDatabaseCount('buyers', 0);
    $this->assertDatabaseCount('users', 0);

    $transactions = CarTransaction::query()->get();

    expect($transactions)->toHaveCount(10);

    foreach ($transactions as $transaction) {
        expect($transaction->sell_date)->toBeNull()
            ->and($transaction->selling_price)->toBeNull();
    }

    $avanza = $transactions->firstWhere('plate_number', 'BD 1201 AA');

    expect($avanza)->not->toBeNull()
        ->and((float) $avanza->total_repair_cost)->toBe(2500000.0)
        ->and((float) $avanza->total_capital)->toBe(131550000.0);
});

test('the ten seeded vehicles fill one page in the inventory view', function () {
    app(CarTransactionSeeder::class)->run();

    $this->actingAs(User::factory()->master()->create())
        ->get(route('transactions.index', ['view' => 'inventory']))
        ->assertOk()
        ->assertViewHas('transactions', fn ($transactions): bool => $transactions->perPage() === 10
            && $transactions->count() === 10
            && $transactions->total() === 10
            && ! $transactions->hasMorePages());
});
