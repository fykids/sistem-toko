<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('returns a paginated safe account list to the master', function () {
    $master = User::factory()->master()->create();
    User::factory()->create(['name' => 'Akun Dicari']);
    $this->actingAs($master);

    $this->getJson(route('api.spa.users.index', ['q' => 'Dicari']))
        ->assertOk()
        ->assertJsonPath('summary.search', 'Dicari')
        ->assertJsonPath('summary.master_count', 1)
        ->assertJsonPath('data.0.name', 'Akun Dicari')
        ->assertJsonMissingPath('data.0.password')
        ->assertJsonMissingPath('data.0.remember_token');
});

it('creates an account through the API and returns its safe resource', function () {
    $this->actingAs(User::factory()->master()->create());

    $this->postJson(route('api.spa.users.store'), [
        'name' => 'Admin Baru',
        'username' => 'admin_baru',
        'password' => 'secure-password',
        'role' => 'admin',
    ])->assertCreated()
        ->assertJsonPath('data.username', 'admin_baru')
        ->assertJsonMissingPath('data.password');

    $user = User::query()->where('username', 'admin_baru')->sole();

    expect($user->role)->toBe('admin')
        ->and(Hash::check('secure-password', $user->password))->toBeTrue();
});

it('rejects invalid account data without creating an account', function () {
    $this->actingAs(User::factory()->master()->create());

    $this->postJson(route('api.spa.users.store'), [
        'name' => '',
        'username' => 'invalid name',
        'password' => 'short',
        'role' => 'unknown',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'username', 'password', 'role']);

    expect(User::query()->count())->toBe(1);
});

it('updates an account while preserving its password when the password is blank', function () {
    $master = User::factory()->master()->create();
    $account = User::factory()->create(['password' => 'original-password']);
    $this->actingAs($master);

    $this->putJson(route('api.spa.users.update', $account), [
        'name' => 'Nama Baru',
        'username' => $account->username,
        'password' => '',
        'role' => 'marketing',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Nama Baru')
        ->assertJsonPath('data.role', 'marketing')
        ->assertJsonMissingPath('data.password');

    expect(Hash::check('original-password', $account->refresh()->password))->toBeTrue();
});

it('deletes an account through the API', function () {
    $master = User::factory()->master()->create();
    $account = User::factory()->create();
    $this->actingAs($master);

    $this->deleteJson(route('api.spa.users.destroy', $account))
        ->assertNoContent();

    $this->assertModelMissing($account);
});

it('forbids an admin from reading or changing user accounts', function () {
    $account = User::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->getJson(route('api.spa.users.index'))->assertForbidden();
    $this->postJson(route('api.spa.users.store'), [])->assertForbidden();
    $this->putJson(route('api.spa.users.update', $account), [])->assertForbidden();
    $this->deleteJson(route('api.spa.users.destroy', $account))->assertForbidden();
});

it('returns the authenticated profile and updates it with password validation', function () {
    $user = User::factory()->create(['password' => 'old-password']);
    $this->actingAs($user);

    $this->getJson(route('api.spa.profile.show'))
        ->assertOk()
        ->assertJsonPath('data.username', $user->username)
        ->assertJsonMissingPath('data.password');

    $this->putJson(route('api.spa.profile.update'), [
        'name' => 'Nama Profil',
        'username' => $user->username,
        'current_password' => 'old-password',
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Nama Profil')
        ->assertJsonMissingPath('data.password');

    expect(Hash::check('new-secure-password', $user->refresh()->password))->toBeTrue();
});

it('returns 401 when a guest requests the profile API', function () {
    $this->getJson(route('api.spa.profile.show'))
        ->assertUnauthorized();
});
