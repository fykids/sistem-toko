<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('guests can render the login form', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('name="email"', false)
        ->assertSee('name="password"', false)
        ->assertSee(route('login.store'), false);
});

test('valid credentials start a session and redirect to the intended route', function () {
    $user = User::factory()->master()->create([
        'email' => 'master@toko.com',
        'password' => 'password',
    ]);

    $this->get(route('transactions.index'))
        ->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'email' => 'master@toko.com',
        'password' => 'password',
    ])->assertRedirect(route('transactions.index'));

    $this->assertAuthenticatedAs($user);
});

test('invalid credentials return a generic login error and preserve the email', function () {
    User::factory()->create([
        'email' => 'admin@toko.com',
        'password' => 'password',
    ]);

    $this->from(route('login'))->post(route('login.store'), [
        'email' => 'admin@toko.com',
        'password' => 'incorrect-password',
    ])->assertRedirect(route('login'))
        ->assertSessionHasErrors([
            'email' => 'Email atau password tidak sesuai.',
        ])
        ->assertSessionHas('_old_input.email', 'admin@toko.com');

    $this->assertGuest();
});

test('invalid email input is rejected before attempting authentication', function () {
    $this->from(route('login'))->post(route('login.store'), [
        'email' => 'not-an-email',
        'password' => 'password',
    ])->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('login attempts are rate limited by email and client address', function () {
    foreach (range(1, 5) as $attempt) {
        $this->from(route('login'))->post(route('login.store'), [
            'email' => 'unknown@toko.com',
            'password' => 'incorrect-password',
        ])->assertRedirect(route('login'));
    }

    $this->post(route('login.store'), [
        'email' => 'unknown@toko.com',
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
        'email' => 'master@toko.com',
        'password' => 'password',
    ]);

    $this->get('/')
        ->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'email' => 'master@toko.com',
        'password' => 'password',
    ])->assertRedirect('/');

    $response = $this->get('/')->assertOk();
    $html = (string) $response->getContent();

    expect(substr_count($html, 'class="showroom-topbar"'))->toBe(1)
        ->and(substr_count($html, 'data-sidebar-toggle'))->toBe(1)
        ->and(substr_count($html, 'class="showroom-topbar-account"'))->toBe(1)
        ->and(substr_count($html, 'class="session-toolbar"'))->toBe(0);
});

test('the local user seeder creates master and admin test accounts', function () {
    $this->seed();

    $master = User::query()->where('email', 'master@toko.com')->sole();
    $admin = User::query()->where('email', 'admin@toko.com')->sole();

    expect($master->role)->toBe('master')
        ->and($admin->role)->toBe('admin')
        ->and(Hash::check('password', $master->password))->toBeTrue()
        ->and(Hash::check('password', $admin->password))->toBeTrue();
});
