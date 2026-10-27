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
        ->assertSee('Showroom')
        ->assertSee('Tambah akun')
        ->assertSee('data-app-menu-toggle', false)
        ->assertSee('id="app-navigation"', false)
        ->assertSee('data-profile-menu', false)
        ->assertDontSee('data-profile-menu-database', false)
        ->assertSee('data-sidebar-database', false)
        ->assertSee(route('dashboard').'#database', false)
        ->assertSee('class="showroom-content users-page"', false)
        ->assertSee('class="showroom-dashboard-heading"', false)
        ->assertSee('class="panel showroom-inventory users-table-card"', false)
        ->assertSee('class="toolbar"', false)
        ->assertSee('class="showroom-primary-action"', false)
        ->assertSee('data-user-search', false)
        ->assertSee('Menampilkan 1 pengguna')
        ->assertSee('Daftar Pengguna')
        ->assertSee('Tanggal dibuat')
        ->assertSee(route('dashboard').'#inventaris', false)
        ->assertSee(route('dashboard').'#laporan', false);

    $this->post(route('users.store'), [
        'name' => 'Admin Baru',
        'username' => 'admin_baru',
        'password' => 'secure-password',
        'role' => 'admin',
    ])->assertRedirect(route('users.index'))
        ->assertSessionHas('success', 'Akun berhasil dibuat.');

    $user = User::query()->where('username', 'admin_baru')->sole();

    expect($user->role)->toBe('admin')
        ->and(Hash::check('secure-password', $user->password))->toBeTrue();

    $this->get(route('users.index'))
        ->assertOk()
        ->assertSee('role="status"', false)
        ->assertSee('Akun berhasil dibuat.');
});

test('master user list paginates ten matching accounts and retains the search query', function () {
    $master = User::factory()->master()->create();
    User::factory()
        ->count(11)
        ->sequence(fn ($sequence) => ['name' => sprintf('Needle Account %02d', $sequence->index + 1)])
        ->create();
    $this->actingAs($master);

    $response = $this->get(route('users.index', ['q' => 'Needle']));

    $response->assertOk()
        ->assertSee('value="Needle"', false)
        ->assertSee('page=2', false)
        ->assertSee('q=Needle', false);

    expect(substr_count($response->getContent(), 'data-user-row'))->toBe(10);

    $pageTwo = $this->get(route('users.index', ['q' => 'Needle', 'page' => 2]));

    $pageTwo->assertOk()
        ->assertSee('Needle Account 11');

    expect(substr_count($pageTwo->getContent(), 'data-user-row'))->toBe(1);
});

test('master can update account details and leave its password unchanged', function () {
    $master = User::factory()->master()->create();
    $managedUser = User::factory()->create([
        'password' => 'old-password',
    ]);
    $this->actingAs($master);

    $this->put(route('users.update', $managedUser), [
        'name' => 'Nama Diperbarui',
        'username' => $managedUser->username,
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
        'username' => 'no_access',
        'password' => 'secure-password',
        'role' => 'master',
    ])->assertForbidden();

    $this->put(route('users.update', $managedUser), [
        'name' => 'Tidak Diizinkan',
        'username' => $managedUser->username,
        'password' => '',
        'role' => 'admin',
    ])->assertForbidden();

    $this->delete(route('users.destroy', $managedUser))->assertForbidden();
});

test('only masters see user and database management in the showroom sidebar', function () {
    $this->actingAs(User::factory()->master()->create())
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertSee(route('users.index'), false)
        ->assertDontSee('data-profile-menu-database', false)
        ->assertSee('data-sidebar-database', false)
        ->assertSee('data-database-modal', false);

    $this->actingAs(User::factory()->create())
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertDontSee(route('users.index'), false)
        ->assertDontSee('data-profile-menu-database', false)
        ->assertDontSee('data-sidebar-database', false)
        ->assertDontSee('data-database-modal', false);
});

test('final master cannot be demoted to admin', function () {
    $master = User::factory()->master()->create();
    $this->actingAs($master);

    $this->from(route('users.index'))->followingRedirects()->put(route('users.update', $master), [
        'name' => $master->name,
        'username' => $master->username,
        'password' => '',
        'role' => 'admin',
        '_form' => 'edit',
        '_user_id' => $master->id,
    ])->assertOk()
        ->assertSee('role="alert"', false)
        ->assertSee('Sistem harus mempertahankan minimal satu akun Master.');

    expect($master->refresh()->role)->toBe('master');
});

test('unauthenticated users are redirected away from user management', function () {
    $this->get(route('users.index'))->assertRedirect(route('login'));
});
