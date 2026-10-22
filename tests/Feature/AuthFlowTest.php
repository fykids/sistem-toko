<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('users are identified by unique usernames instead of email addresses', function () {
    expect(Schema::hasColumn('users', 'username'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'email'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'email_verified_at'))->toBeFalse()
        ->and(Schema::hasTable('password_reset_tokens'))->toBeFalse();
});

test('guests can render the login form', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('name="username"', false)
        ->assertSee('name="password"', false)
        ->assertSee(route('login.store'), false);
});

test('login page creates a master account that can authenticate with the default credentials', function () {
    $this->get(route('login'))->assertOk();

    $master = User::query()->where('username', 'master')->sole();

    expect($master->role)->toBe('master')
        ->and(Hash::check('master123', $master->password))->toBeTrue();

    $this->post(route('login.store'), [
        'username' => 'master',
        'password' => 'master123',
    ])->assertRedirect('/');

    $this->assertAuthenticatedAs($master);
});

test('login page promotes the existing default username instead of inserting a duplicate', function () {
    $existingUser = User::factory()->create([
        'username' => 'master',
        'password' => 'existing-password',
    ]);

    $this->get(route('login'))->assertOk();

    $existingUser->refresh();

    expect(User::query()->where('username', 'master')->count())->toBe(1)
        ->and($existingUser->role)->toBe('master')
        ->and(Hash::check('existing-password', $existingUser->password))->toBeTrue()
        ->and(Hash::check('master123', $existingUser->password))->toBeFalse();
});

test('valid credentials start a session and redirect to the intended route', function () {
    $user = User::factory()->master()->create([
        'username' => 'master_toko',
        'password' => 'password',
    ]);

    $this->get(route('transactions.index'))
        ->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'username' => 'master_toko',
        'password' => 'password',
    ])->assertRedirect(route('transactions.index'));

    $this->assertAuthenticatedAs($user);
});

test('invalid credentials return a generic login error and preserve the username', function () {
    User::factory()->create([
        'username' => 'admin',
        'password' => 'password',
    ]);

    $this->from(route('login'))->post(route('login.store'), [
        'username' => 'admin',
        'password' => 'incorrect-password',
    ])->assertRedirect(route('login'))
        ->assertSessionHasErrors([
            'username' => 'Username atau password tidak sesuai.',
        ])
        ->assertSessionHas('_old_input.username', 'admin');

    $this->assertGuest();
});

test('missing username is rejected before attempting authentication', function () {
    $this->from(route('login'))->post(route('login.store'), [
        'username' => '',
        'password' => 'password',
    ])->assertRedirect(route('login'))
        ->assertSessionHasErrors('username');

    $this->assertGuest();
});

test('login attempts are rate limited by username and client address', function () {
    foreach (range(1, 5) as $attempt) {
        $this->from(route('login'))->post(route('login.store'), [
            'username' => 'unknown_user',
            'password' => 'incorrect-password',
        ])->assertRedirect(route('login'));
    }

    $this->post(route('login.store'), [
        'username' => 'unknown_user',
        'password' => 'incorrect-password',
    ])->assertTooManyRequests();
});

test('authenticated users can log out and return to the login page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post(route('logout'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('success', 'Anda berhasil logout.');

    $this->assertGuest();

    $this->get(route('login'))->assertOk();
});

test('the shared layout shows the authenticated user role and logout action', function () {
    $user = User::factory()->master()->create([
        'name' => 'Akun Master',
    ]);
    $this->actingAs($user);

    $this->get(route('transactions.index'))
        ->assertOk()
        ->assertSee('Akun Master')
        ->assertSee('Master')
        ->assertSee(route('logout'), false)
        ->assertSee('Logout');
});

test('successful login to the dashboard root renders a single navbar', function () {
    User::factory()->master()->create([
        'username' => 'master_toko',
        'password' => 'password',
    ]);

    $this->get('/')
        ->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'username' => 'master_toko',
        'password' => 'password',
    ])->assertRedirect('/');

    $response = $this->get('/')->assertOk();
    $html = (string) $response->getContent();

    expect(substr_count($html, 'class="showroom-topbar"'))->toBe(1)
        ->and(substr_count($html, 'data-app-menu-toggle'))->toBe(1)
        ->and(substr_count($html, 'class="showroom-topbar-account"'))->toBe(1)
        ->and(substr_count($html, 'data-profile-menu>'))->toBe(1)
        ->and(substr_count($html, 'data-profile-menu-database'))->toBe(0)
        ->and(substr_count($html, 'data-profile-menu-settings'))->toBe(1)
        ->and(substr_count($html, 'data-confirm-logout'))->toBe(1)
        ->and(substr_count($html, route('profile.edit')))->toBe(1)
        ->and(substr_count($html, 'class="session-toolbar"'))->toBe(0);
});

test('the local user seeder creates master and admin test accounts', function () {
    $this->seed();

    $master = User::query()->where('username', 'master')->sole();
    $admin = User::query()->where('username', 'admin')->sole();

    expect($master->role)->toBe('master')
        ->and($admin->role)->toBe('admin')
        ->and(Hash::check('password', $master->password))->toBeTrue()
        ->and(Hash::check('password', $admin->password))->toBeTrue();
});
