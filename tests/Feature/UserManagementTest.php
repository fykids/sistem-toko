<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('master can list users and create an account with an explicitly selected role', function () {
    $this->actingAs(User::factory()->master()->create());

    $this->get(route('users.index'))
        ->assertOk()
        ->assertSee('Manajemen Akun')
        ->assertSee('Tambah akun');

    $this->post(route('users.store'), [
        'name' => 'Admin Baru',
        'email' => 'admin-baru@example.com',
        'password' => 'secure-password',
        'role' => 'admin',
    ])->assertRedirect(route('users.index'))
        ->assertSessionHas('success', 'Akun berhasil dibuat.');

    $user = User::query()->where('email', 'admin-baru@example.com')->sole();

    expect($user->role)->toBe('admin')
        ->and(Hash::check('secure-password', $user->password))->toBeTrue();
});

test('master can update account details and leave its password unchanged', function () {
    $master = User::factory()->master()->create();
    $managedUser = User::factory()->create([
        'password' => 'old-password',
    ]);
    $this->actingAs($master);

    $this->put(route('users.update', $managedUser), [
        'name' => 'Nama Diperbarui',
        'email' => $managedUser->email,
        'password' => '',
        'role' => 'admin',
        '_form' => 'edit',
        '_user_id' => $managedUser->id,
    ])->assertRedirect(route('users.index'))
        ->assertSessionHas('success', 'Akun berhasil diperbarui.');

    $managedUser->refresh();

    expect($managedUser->name)->toBe('Nama Diperbarui')
        ->and(Hash::check('old-password', $managedUser->password))->toBeTrue();
});

test('master can delete another user but cannot delete itself or the final master', function () {
    $master = User::factory()->master()->create();
    $admin = User::factory()->create();
    $this->actingAs($master);

    $this->delete(route('users.destroy', $admin))
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success', 'Akun berhasil dihapus.');

    $this->assertDatabaseMissing('users', ['id' => $admin->id]);

    $this->delete(route('users.destroy', $master))->assertForbidden();

    $otherMaster = User::factory()->master()->create();
    $this->delete(route('users.destroy', $otherMaster))
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success', 'Akun berhasil dihapus.');

    $this->assertModelExists($master);
    $this->assertDatabaseMissing('users', ['id' => $otherMaster->id]);
});

test('admin receives forbidden responses for user management endpoints', function () {
    $admin = User::factory()->create();
    $managedUser = User::factory()->create();
    $this->actingAs($admin);

    $this->get(route('users.index'))->assertForbidden();

    $this->post(route('users.store'), [
        'name' => 'Tidak Diizinkan',
        'email' => 'no-access@example.com',
        'password' => 'secure-password',
        'role' => 'master',
    ])->assertForbidden();

    $this->put(route('users.update', $managedUser), [
        'name' => 'Tidak Diizinkan',
        'email' => $managedUser->email,
        'password' => '',
        'role' => 'admin',
    ])->assertForbidden();

    $this->delete(route('users.destroy', $managedUser))->assertForbidden();
});

test('final master cannot be demoted to admin', function () {
    $master = User::factory()->master()->create();
    $this->actingAs($master);

    $this->from(route('users.index'))->put(route('users.update', $master), [
        'name' => $master->name,
        'email' => $master->email,
        'password' => '',
        'role' => 'admin',
        '_form' => 'edit',
        '_user_id' => $master->id,
    ])->assertRedirect(route('users.index'))
        ->assertSessionHasErrors('role');

    expect($master->refresh()->role)->toBe('master');
});

test('unauthenticated users are redirected away from user management', function () {
    $this->get(route('users.index'))->assertRedirect(route('login'));
});
