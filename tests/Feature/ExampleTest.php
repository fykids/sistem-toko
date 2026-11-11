<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->master()->create());
});

test('the application root redirects into the SPA', function () {
    $this->get('/')
        ->assertRedirect('/app/dashboard');

    $this->get('/app/dashboard')
        ->assertOk()
        ->assertSee('id="app"', false);
});
