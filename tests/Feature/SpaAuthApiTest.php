<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('authenticates with valid credentials and returns the role-based SPA destination', function () {
    User::factory()->master()->create();
    User::factory()->marketing()->create([
        'username' => 'marketing_user',
        'password' => 'secure-password',
    ]);

    $this->withSession(['url.intended' => '/transactions'])
        ->postJson(route('api.spa.login'), [
            'username' => 'marketing_user',
            'password' => 'secure-password',
        ])
        ->assertOk()
        ->assertJsonPath('redirect', '/app/marketing')
        ->assertSessionMissing('url.intended');

    $this->assertAuthenticated();
});

it('returns a generic validation response for invalid credentials', function () {
    User::factory()->master()->create();

    $this->postJson(route('api.spa.login'), [
        'username' => 'unknown_user',
        'password' => 'wrong-password',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'Username atau password tidak sesuai.')
        ->assertJsonValidationErrors('username');

    $this->assertGuest();
});

it('rejects login when no seeded account exists', function () {
    $this->postJson(route('api.spa.login'), [
        'username' => 'admin',
        'password' => 'password',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'Username atau password tidak sesuai.')
        ->assertJsonValidationErrors('username');

    $this->assertGuest();
});

it('returns 404 for public Master setup endpoints without creating an account', function () {
    $this->get('/api/spa/setup-master')->assertNotFound();
    $this->postJson('/api/spa/setup-master', [
        'name' => 'Master Showroom',
        'username' => 'master_toko',
        'password' => 'a-unique-master-secret',
        'password_confirmation' => 'a-unique-master-secret',
    ])->assertNotFound();

    expect(User::query()->count())->toBe(0);
});

it('logs out through the SPA endpoint and invalidates the session', function () {
    $user = User::factory()->master()->create();
    $this->actingAs($user);

    $this->postJson(route('api.spa.logout'))
        ->assertNoContent();

    $this->assertGuest();
});
