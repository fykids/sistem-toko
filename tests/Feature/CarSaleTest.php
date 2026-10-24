<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

it('does not register the removed sales endpoints', function () {
    expect(Route::has('sales.index'))->toBeFalse()
        ->and(Route::has('sales.create'))->toBeFalse()
        ->and(Route::has('sales.store'))->toBeFalse();

    $this->actingAs(User::factory()->create())
        ->get('/sales')
        ->assertNotFound();
});

it('keeps the legacy dashboard sales anchor and the independent jual module', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('dashboard').'#penjualan', false)
        ->assertSee(route('jual.index'), false);

    $this->get(route('jual.index'))->assertOk();
});
