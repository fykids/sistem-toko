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

test('login page does not create a default master account', function () {
    $this->get(route('login'))->assertOk();

    expect(User::query()->count())->toBe(0);
});

test('login page does not promote an existing username to master', function () {
    $existingUser = User::factory()->create([
        'username' => 'master',
        'password' => 'existing-password',
    ]);

    $this->get(route('login'))->assertOk();

    $existingUser->refresh();

    expect(User::query()->where('username', 'master')->count())->toBe(1)
        ->and($existingUser->role)->toBe('admin')
        ->and(Hash::check('existing-password', $existingUser->password))->toBeTrue()
        ->and(Hash::check('master123', $existingUser->password))->toBeFalse();
});

test('an initial master account can be provisioned with an operator-supplied password', function () {
    $this->artisan('app:create-master-user')
        ->expectsQuestion('Name', 'Master Showroom')
        ->expectsQuestion('Username', 'master_toko')
        ->expectsQuestion('Password (minimum 12 characters)', 'a-unique-master-secret')
        ->expectsQuestion('Confirm password', 'a-unique-master-secret')
        ->assertExitCode(0);

    $master = User::query()->where('username', 'master_toko')->sole();

    expect($master->role)->toBe('master')
        ->and(Hash::check('a-unique-master-secret', $master->password))->toBeTrue()
        ->and(Hash::check('master123', $master->password))->toBeFalse();
});

test('initial master provisioning rejects passwords shorter than 12 characters', function () {
    $this->artisan('app:create-master-user')
        ->expectsQuestion('Name', 'Master Showroom')
        ->expectsQuestion('Username', 'master_toko')
        ->expectsQuestion('Password (minimum 12 characters)', 'short')
        ->expectsQuestion('Confirm password', 'short')
        ->assertExitCode(1);

    expect(User::query()->count())->toBe(0);
});

test('initial master provisioning refuses to create another master account', function () {
    User::factory()->master()->create();

    $this->artisan('app:create-master-user')
        ->assertExitCode(1);

    expect(User::query()->where('role', 'master')->count())->toBe(1);
});

test('valid master credentials redirect to the admin dashboard instead of an intended route', function () {
    $user = User::factory()->master()->create([
        'username' => 'master_toko',
        'password' => 'password',
    ]);

    $this->get(route('transactions.index'))
        ->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'username' => 'master_toko',
        'password' => 'password',
    ])->assertRedirect(route('dashboard'))
        ->assertSessionMissing('url.intended');

    $this->assertAuthenticatedAs($user);
});

test('logging in as another role after logout clears stale intended routes and refreshes the login form', function () {
    User::factory()->marketing()->create([
        'username' => 'marketing_user',
        'password' => 'password',
    ]);
    $master = User::factory()->master()->create([
        'username' => 'master_toko',
        'password' => 'password',
    ]);

    $initialLoginForm = $this->get(route('login'))->assertOk();
    preg_match('/name="_token" value="([^"]+)"/', $initialLoginForm->getContent(), $initialToken);

    $this->get(route('marketing.stok.index'))
        ->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'username' => 'marketing_user',
        'password' => 'password',
    ])->assertRedirect(route('marketing.stok.index'));

    $this->withSession(['url.intended' => route('marketing.stok.index')])
        ->post(route('logout'))
        ->assertStatus(303)
        ->assertRedirect(route('login'))
        ->assertSessionMissing('url.intended');

    $this->assertGuest();

    $freshLoginForm = $this->get(route('login'))->assertOk();
    preg_match('/name="_token" value="([^"]+)"/', $freshLoginForm->getContent(), $freshToken);

    expect($freshLoginForm->headers->get('Cache-Control'))->toBe('no-store, private')
        ->and($initialToken[1] ?? null)->not->toBeNull()
        ->and($freshToken[1] ?? null)->not->toBe($initialToken[1] ?? null);

    $this->post(route('login.store'), [
        'username' => 'master_toko',
        'password' => 'password',
    ])->assertRedirect(route('dashboard'))
        ->assertSessionMissing('url.intended');

    $this->assertAuthenticatedAs($master);
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
