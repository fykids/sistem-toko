<?php

use App\Models\CarTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns dashboard data without sensitive values to an admin', function () {
    CarTransaction::create([
        'plate_number' => 'B 1234 SPA',
        'brand' => 'Toyota',
        'model' => 'Avanza',
        'year' => 2024,
        'transmission' => 'Automatic',
        'color' => 'Hitam',
        'buy_date' => '2026-10-01',
        'buy_price' => 50000000,
        'selling_price' => 65000000,
    ]);
    $this->actingAs(User::factory()->create());

    $this->getJson(route('api.spa.dashboard', ['view' => 'inventory']))
        ->assertOk()
        ->assertJsonPath('filters.view', 'inventory')
        ->assertJsonPath('permissions.can_view_sensitive_data', false)
        ->assertJsonPath('data.0.plate_number', 'B 1234 SPA')
        ->assertJsonPath('data.0.status', 'stock')
        ->assertJsonMissingPath('data.0.buy_price')
        ->assertJsonMissingPath('data.0.total_capital')
        ->assertJsonMissingPath('metrics');
});

it('returns sensitive transaction values and permissions to the master without dashboard metrics', function () {
    CarTransaction::create([
        'plate_number' => 'B 5678 SPA',
        'brand' => 'Honda',
        'model' => 'Jazz',
        'year' => 2023,
        'transmission' => 'Automatic',
        'color' => 'Silver',
        'buy_date' => '2026-10-01',
        'buy_price' => 40000000,
        'selling_price' => 55000000,
        'sell_date' => '2026-10-20',
    ]);
    $this->actingAs(User::factory()->master()->create());

    $this->getJson(route('api.spa.dashboard', ['view' => 'sales']))
        ->assertOk()
        ->assertJsonPath('permissions.can_view_sensitive_data', true)
        ->assertJsonPath('data.0.status', 'sold')
        ->assertJsonPath('data.0.buy_price', 40000000)
        ->assertJsonPath('data.0.total_capital', 40000000)
        ->assertJsonMissingPath('metrics');
});

it('returns 401 when a guest requests the dashboard API', function () {
    $this->getJson(route('api.spa.dashboard'))
        ->assertUnauthorized();
});

it('returns 403 when a user without dashboard permission requests the API', function () {
    $this->actingAs(User::factory()->marketing()->create());

    $this->getJson(route('api.spa.dashboard'))
        ->assertForbidden();
});

it('returns 422 when dashboard filters are invalid', function () {
    $this->actingAs(User::factory()->create());

    $this->getJson(route('api.spa.dashboard', ['view' => 'unknown']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('view');
});

it('serves the Vue shell for deep links inside the SPA', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/app/dashboard')
        ->assertOk()
        ->assertSee('id="app"', false)
        ->assertSee('assets/spa-', false);
});
