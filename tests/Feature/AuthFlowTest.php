<?php

use App\Models\CarTransaction;
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

test('guests receive the Vue shell for the login page', function () {
    User::factory()->master()->create();

    $this->get(route('login'))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertSee('id="app"', false)
        ->assertSee('assets/spa-', false);
});

test('guests go directly to login and cannot access Master setup routes', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('id="app"', false)
        ->assertSee('assets/spa-', false);

    $this->get('/')->assertRedirect(route('login'));
    $this->get('/setup-master')->assertNotFound();
    $this->post(route('login.store'), [
        'username' => 'admin',
        'password' => 'password',
    ])->assertRedirect();

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

test('a master cannot authenticate with the legacy shared emergency password', function () {
    User::factory()->master()->create([
        'username' => 'master_toko',
        'password' => 'a-unique-master-secret',
    ]);

    $this->post(route('login.store'), [
        'username' => 'master_toko',
        'password' => '123456',
    ])->assertRedirect()
        ->assertSessionHasErrors('username');

    $this->assertGuest();
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

test('password reset only changes the first master password and preserves application data', function () {
    $master = User::factory()->master()->create([
        'username' => 'master_first',
        'password' => 'original-master-password',
    ]);
    $otherMaster = User::factory()->master()->create([
        'username' => 'master_second',
        'password' => 'another-master-password',
    ]);
    $otherUser = User::factory()->create([
        'username' => 'admin_account',
        'password' => 'admin-password',
    ]);
    $transaction = CarTransaction::create([
        'plate_number' => 'B 1234 TST',
        'brand' => 'Toyota',
        'model' => 'Avanza',
        'year' => 2022,
        'transmission' => 'Automatic',
        'color' => 'Hitam',
        'buy_date' => '2026-09-01',
        'buy_price' => 50000000,
        'selling_price' => 60000000,
    ]);

    $userCount = User::query()->count();
    $transactionCount = CarTransaction::query()->count();

    $this->artisan('app:reset-master-password')
        ->expectsQuestion('New password (minimum 12 characters)', 'a-new-master-password')
        ->expectsQuestion('Confirm new password', 'a-new-master-password')
        ->expectsOutput('Password akun Master berhasil direset.')
        ->assertExitCode(0);

    $master->refresh();
    $otherMaster->refresh();
    $otherUser->refresh();
    $transaction->refresh();

    expect(Hash::check('a-new-master-password', $master->password))->toBeTrue()
        ->and(Hash::check('original-master-password', $master->password))->toBeFalse()
        ->and(Hash::check('another-master-password', $otherMaster->password))->toBeTrue()
        ->and(Hash::check('admin-password', $otherUser->password))->toBeTrue()
        ->and($transaction->plate_number)->toBe('B 1234 TST')
        ->and($transaction->selling_price)->toBe(60000000)
        ->and(User::query()->count())->toBe($userCount)
        ->and(CarTransaction::query()->count())->toBe($transactionCount);

    $this->post(route('login.store'), [
        'username' => 'master_first',
        'password' => 'a-new-master-password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($master);
});

test('password reset rejects a password shorter than 12 characters', function () {
    $master = User::factory()->master()->create([
        'password' => 'original-master-password',
    ]);

    $this->artisan('app:reset-master-password')
        ->expectsQuestion('New password (minimum 12 characters)', 'short')
        ->expectsQuestion('Confirm new password', 'short')
        ->assertExitCode(1);

    expect(Hash::check('original-master-password', $master->refresh()->password))->toBeTrue()
        ->and(Hash::check('short', $master->password))->toBeFalse();
});

test('emergency password reset reports failure when no master account exists', function () {
    $this->artisan('app:reset-master-password')
        ->expectsOutput('A Master account does not exist.')
        ->assertExitCode(1);

    expect(User::query()->count())->toBe(0)
        ->and(CarTransaction::query()->count())->toBe(0);
});

test('public Master setup routes stay unavailable', function () {
    $this->get('/setup-master')->assertNotFound();
    $this->post('/setup-master', [
        'name' => 'Master Showroom',
        'username' => 'master_toko',
        'password' => 'a-unique-master-secret',
        'password_confirmation' => 'a-unique-master-secret',
    ])->assertNotFound();

    expect(User::query()->count())->toBe(0);
    $this->assertGuest();
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
    preg_match('/name="csrf-token" content="([^"]+)"/', $initialLoginForm->getContent(), $initialToken);

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
    preg_match('/name="csrf-token" content="([^"]+)"/', $freshLoginForm->getContent(), $freshToken);

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
    User::factory()->master()->create();
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
    User::factory()->master()->create();

    $this->from(route('login'))->post(route('login.store'), [
        'username' => '',
        'password' => 'password',
    ])->assertRedirect(route('login'))
        ->assertSessionHasErrors('username');

    $this->assertGuest();
});

test('login attempts are rate limited by username and client address', function () {
    User::factory()->master()->create();

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
    $user = User::factory()->master()->create();
    $this->actingAs($user);

    $this->post(route('logout'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('success', 'Anda berhasil logout.');

    $this->assertGuest();

    $this->get(route('login'))->assertOk();
});

test('the SPA shell exposes the authenticated user to Vue', function () {
    $user = User::factory()->master()->create([
        'name' => 'Akun Master',
    ]);
    $this->actingAs($user);

    $this->get('/app/dashboard')
        ->assertOk()
        ->assertSee('window.spaUser', false)
        ->assertSee('Akun Master')
        ->assertSee('\u0022role\u0022:\u0022master\u0022', false)
        ->assertSee('id="app"', false);
});

test('successful login enters the SPA dashboard shell', function () {
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

    $this->get('/')->assertRedirect('/app/dashboard');

    $this->get('/app/dashboard')
        ->assertOk()
        ->assertSee('id="app"', false)
        ->assertSee('window.spaUser', false)
        ->assertSee('assets/spa-', false);
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
