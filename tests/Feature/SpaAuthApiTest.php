<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

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

it('directs login to initial setup when there is no master account', function () {
    $this->postJson(route('api.spa.login'), [
        'username' => 'admin',
        'password' => 'password',
    ])->assertStatus(409)
        ->assertJsonPath('redirect', '/setup-master');
});

it('creates and authenticates the first master through the API', function () {
    $this->postJson(route('api.spa.setup-master'), [
        'name' => 'Master Showroom',
        'username' => 'master_toko',
        'password' => 'a-unique-master-secret',
        'password_confirmation' => 'a-unique-master-secret',
    ])->assertCreated()
        ->assertJsonPath('redirect', '/app/dashboard');

    $master = User::query()->where('username', 'master_toko')->sole();

    expect($master->role)->toBe('master')
        ->and(Hash::check('a-unique-master-secret', $master->password))->toBeTrue();

    $this->assertAuthenticatedAs($master);
});

it('rejects short initial master passwords without creating an account', function () {
    $this->postJson(route('api.spa.setup-master'), [
        'name' => 'Master Showroom',
        'username' => 'master_toko',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    expect(User::query()->count())->toBe(0);
});

it('logs out through the SPA endpoint and invalidates the session', function () {
    $user = User::factory()->master()->create();
    $this->actingAs($user);

    $this->postJson(route('api.spa.logout'))
        ->assertNoContent();

    $this->assertGuest();
});
