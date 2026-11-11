<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns validation errors for an invalid catalog year range', function () {
    $this->actingAs(User::factory()->marketing()->create());

    $this->getJson(route('api.spa.marketing.vehicles.index', [
        'year_from' => 2024,
        'year_to' => 2020,
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors('year_to');
});

it('redirects non-marketing users away from the marketing API', function () {
    $this->actingAs(User::factory()->create());

    $this->getJson(route('api.spa.marketing.vehicles.index'))
        ->assertRedirect(route('dashboard'));
});

it('returns 401 when a guest requests the marketing catalog API', function () {
    $this->getJson(route('api.spa.marketing.vehicles.index'))
        ->assertUnauthorized();
});
