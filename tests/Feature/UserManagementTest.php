<?php

use App\Models\CarTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('master account management opens the SPA and preserves account creation', function () {
    $this->actingAs(User::factory()->master()->create());

    $this->get(route('users.index'))
        ->assertRedirect('/app/users');

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
        ->assertRedirect('/app/users');
});

test('master user list paginates ten matching accounts and retains the search query', function () {
    $master = User::factory()->master()->create();
    User::factory()
        ->count(11)
        ->sequence(fn ($sequence) => ['name' => sprintf('Needle Account %02d', $sequence->index + 1)])
        ->create();
    $this->actingAs($master);

    $this->getJson(route('api.spa.users.index', ['q' => 'Needle']))
        ->assertOk()
        ->assertJsonPath('summary.search', 'Needle')
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('links.next', route('api.spa.users.index', ['q' => 'Needle', 'page' => 2]));

    $this->getJson(route('api.spa.users.index', ['q' => 'Needle', 'page' => 2]))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Needle Account 11')
        ->assertJsonCount(1, 'data');
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

test('deleting a sale note creator preserves the sale note and clears its creator reference', function () {
    $master = User::factory()->master()->create();
    $creator = User::factory()->create();
    $vehicle = CarTransaction::query()->create([
        'plate_number' => 'B 1234 USER',
        'brand' => 'Toyota',
        'model' => 'Yaris',
        'year' => 2024,
        'transmission' => 'Automatic',
        'color' => 'Silver',
        'buy_date' => '2026-10-01',
        'buy_price' => 65000000,
        'sell_date' => '2026-10-20',
        'selling_price' => 92000000,
    ]);
    $saleNote = $vehicle->saleNote()->create([
        'nama_konsumen' => 'Pembeli',
        'created_by' => $creator->id,
    ]);

    $this->actingAs($master)
        ->delete(route('users.destroy', $creator))
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success', 'Akun berhasil dihapus.');

    $this->assertModelExists($saleNote);
    $this->assertDatabaseHas('sale_notes', [
        'id' => $saleNote->id,
        'created_by' => null,
    ]);
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

test('the SPA shell exposes role metadata for management navigation', function () {
    $this->actingAs(User::factory()->master()->create())
        ->get('/app/dashboard')
        ->assertOk()
        ->assertSee('\u0022role\u0022:\u0022master\u0022', false)
        ->assertSee('id="app"', false);

    $this->actingAs(User::factory()->create())
        ->get('/app/dashboard')
        ->assertOk()
        ->assertSee('\u0022role\u0022:\u0022admin\u0022', false)
        ->assertSee('id="app"', false);

    $this->getJson(route('api.spa.users.index'))->assertForbidden();
});

test('the account API rejects demoting the final master to admin', function () {
    $master = User::factory()->master()->create();
    $this->actingAs($master);

    $this->putJson(route('api.spa.users.update', $master), [
        'name' => $master->name,
        'username' => $master->username,
        'password' => '',
        'role' => 'admin',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('role');

    expect($master->refresh()->role)->toBe('master');
});

test('unauthenticated users are redirected away from user management', function () {
    $this->get(route('users.index'))->assertRedirect(route('login'));
});
