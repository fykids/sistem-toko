<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('masters and admins open SPA account settings from the legacy URL', function (string $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertRedirect('/app/settings');
})->with(['master', 'admin']);

test('users can update their profile without changing the existing password', function () {
    $user = User::factory()->master()->create([
        'name' => 'Nama Lama',
        'username' => 'old_user',
        'password' => 'existing-password',
    ]);

    $this->actingAs($user)
        ->put(route('profile.update'), [
            'name' => 'Nama Baru',
            'username' => 'new_user',
            'current_password' => '',
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('success', 'Pengaturan akun berhasil diperbarui.');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Nama Baru',
        'username' => 'new_user',
    ]);
    expect(Hash::check('existing-password', $user->fresh()->password))->toBeTrue();
});

test('users can keep their existing username when updating their profile', function () {
    $user = User::factory()->create([
        'name' => 'Nama Lama',
        'username' => 'same_user',
    ]);

    $this->actingAs($user)
        ->put(route('profile.update'), [
            'name' => 'Nama Baru',
            'username' => 'same_user',
        ])
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh()->name)->toBe('Nama Baru')
        ->and($user->fresh()->username)->toBe('same_user');
});

test('users can change their password after confirming their current password', function () {
    $user = User::factory()->create(['password' => 'existing-password']);

    $this->actingAs($user)
        ->put(route('profile.update'), [
            'name' => $user->name,
            'username' => $user->username,
            'current_password' => 'existing-password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('success', 'Pengaturan akun berhasil diperbarui.');

    expect(Hash::check('new-secure-password', $user->fresh()->password))->toBeTrue();
});

test('users cannot change their password with an incorrect current password', function () {
    $user = User::factory()->create(['password' => 'existing-password']);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->put(route('profile.update'), [
            'name' => $user->name,
            'username' => $user->username,
            'current_password' => 'incorrect-password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('existing-password', $user->fresh()->password))->toBeTrue();
});

test('users must confirm a new password before it is saved', function () {
    $user = User::factory()->create(['password' => 'existing-password']);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->put(route('profile.update'), [
            'name' => $user->name,
            'username' => $user->username,
            'current_password' => 'existing-password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'different-password',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors('password');

    expect(Hash::check('existing-password', $user->fresh()->password))->toBeTrue();
});

test('users cannot change their username to one owned by another account', function () {
    $user = User::factory()->create(['username' => 'user_account']);
    User::factory()->create(['username' => 'taken_account']);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->put(route('profile.update'), [
            'name' => $user->name,
            'username' => 'taken_account',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors('username');

    expect($user->fresh()->username)->toBe('user_account');
});

test('guests are redirected from account settings', function () {
    $this->get(route('profile.edit'))->assertRedirect(route('login'));

    $this->put(route('profile.update'), [
        'name' => 'Nama',
        'username' => 'user_account',
    ])->assertRedirect(route('login'));
});
