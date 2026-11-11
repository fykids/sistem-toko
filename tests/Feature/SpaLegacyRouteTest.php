<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects legacy module pages to SPA routes', function (string $legacyPath, string $spaPath) {
    $this->actingAs(User::factory()->master()->create())
        ->get($legacyPath)
        ->assertRedirect($spaPath);
})->with([
    'transaction list' => ['/transactions?view=sales', '/app/dashboard?view=sales'],
    'transaction form' => ['/transactions/create?source=stock', '/app/transactions/create?source=stock'],
    'Jual list' => ['/jual?status=paid', '/app/jual?status=paid'],
    'Jual form' => ['/jual/create?vehicle=9', '/app/jual/create?vehicle=9'],
    'sale note list' => ['/nota-penjualan', '/app/nota-penjualan'],
]);

it('does not redirect a removed legacy sale-note create route to the SPA', function () {
    $this->actingAs(User::factory()->master()->create())
        ->get('/nota-penjualan/create?vehicle=9')
        ->assertNotFound();
});

it('serves the SPA shell for deep transaction and dashboard routes', function () {
    $this->actingAs(User::factory()->master()->create())
        ->get('/app/transactions/15/edit')
        ->assertOk()
        ->assertViewIs('spa.app');

    $this->get('/app/dashboard')
        ->assertOk()
        ->assertViewIs('spa.app');
});
