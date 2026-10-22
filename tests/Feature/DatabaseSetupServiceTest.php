<?php

use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\DatabaseSetupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

it('creates and migrates a missing SQLite database before handling requests', function () {
    $databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sistemtoko-startup-'.bin2hex(random_bytes(8)).DIRECTORY_SEPARATOR.'database.sqlite';

    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => $databasePath,
        'session.driver' => 'database',
    ]);
    DB::purge('sqlite');

    try {
        (new AppServiceProvider(app()))->boot(app(DatabaseSetupService::class));

        $this->from(route('login'))->post(route('login.store'), [
            'username' => 'missing_user',
            'password' => 'incorrect-password',
        ])->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk();

        expect(file_exists($databasePath))->toBeTrue()
            ->and(DB::table('sessions')->exists())->toBeTrue();
    } finally {
        DB::purge('sqlite');
        @unlink($databasePath);
        @unlink($databasePath.'-shm');
        @unlink($databasePath.'-wal');
        @rmdir(dirname($databasePath));
    }
});

it('runs pending migrations without deleting existing database records', function () {
    $databasePath = tempnam(sys_get_temp_dir(), 'sistemtoko-migration-');
    config(['database.connections.sqlite.database' => $databasePath]);
    DB::purge('sqlite');

    try {
        $databaseSetupService = app(DatabaseSetupService::class);
        $databaseSetupService->setup();

        DB::table('users')->insert([
            'name' => 'Existing User',
            'username' => 'existing_user',
            'password' => 'hashed-password',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $migrationCount = DB::table('migrations')->count();

        $databaseSetupService->setup();

        expect(DB::table('users')->where('username', 'existing_user')->exists())->toBeTrue()
            ->and(DB::table('migrations')->count())->toBe($migrationCount);
    } finally {
        DB::purge('sqlite');
        @unlink($databasePath);
        @unlink($databasePath.'-shm');
        @unlink($databasePath.'-wal');
    }
});

it('replaces existing user emails with unique usernames during migration', function () {
    $databasePath = tempnam(sys_get_temp_dir(), 'sistemtoko-usernames-');
    config(['database.connections.sqlite.database' => $databasePath]);
    DB::purge('sqlite');

    try {
        expect(Artisan::call('migrate', [
            '--force' => true,
            '--path' => 'database/migrations/0001_01_01_000000_create_users_table.php',
        ]))->toBe(0);

        DB::table('users')->insert([
            [
                'name' => 'Jane A',
                'email' => 'jane@example.com',
                'password' => 'hashed-password',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Jane B',
                'email' => 'jane@another-domain.com',
                'password' => 'hashed-password',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        expect(Artisan::call('migrate', [
            '--force' => true,
            '--path' => 'database/migrations/2026_10_22_105615_replace_user_email_with_username.php',
        ]))->toBe(0)
            ->and(DB::table('users')->orderBy('id')->pluck('username')->all())
            ->toBe(['jane', 'jane_1'])
            ->and(Schema::hasColumn('users', 'email'))->toBeFalse()
            ->and(Schema::hasColumn('users', 'email_verified_at'))->toBeFalse()
            ->and(Schema::hasTable('password_reset_tokens'))->toBeFalse();
    } finally {
        DB::purge('sqlite');
        @unlink($databasePath);
        @unlink($databasePath.'-shm');
        @unlink($databasePath.'-wal');
    }
});

it('logs and defers migrations when SQLite is locked', function () {
    $databasePath = tempnam(sys_get_temp_dir(), 'sistemtoko-locked-');
    config(['database.connections.sqlite.database' => $databasePath]);
    DB::purge('sqlite');

    Artisan::shouldReceive('call')
        ->once()
        ->with('migrate', ['--force' => true])
        ->andThrow(new RuntimeException('database is locked'));
    Log::shouldReceive('warning')
        ->once()
        ->with('SQLite migrations were deferred because the database is locked.', [
            'exception' => RuntimeException::class,
        ]);

    try {
        app(DatabaseSetupService::class)->setup();
    } finally {
        DB::purge('sqlite');
        @unlink($databasePath);
        @unlink($databasePath.'-shm');
        @unlink($databasePath.'-wal');
    }
});

it('uses file sessions when SQLite migrations do not create the sessions table', function () {
    $databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sistemtoko-session-'.bin2hex(random_bytes(8)).DIRECTORY_SEPARATOR.'database.sqlite';
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => $databasePath,
        'session.driver' => 'database',
    ]);
    DB::purge('sqlite');

    Artisan::shouldReceive('call')
        ->once()
        ->with('migrate', ['--force' => true])
        ->andReturn(1);
    Artisan::shouldReceive('output')
        ->once()
        ->andReturn('migration failed');
    Log::shouldReceive('error')
        ->once()
        ->with('SQLite database migrations failed.', [
            'exit_code' => 1,
            'output' => 'migration failed',
        ]);

    try {
        (new AppServiceProvider(app()))->boot(app(DatabaseSetupService::class));

        expect(config('session.driver'))->toBe('file');
    } finally {
        DB::purge('sqlite');
        @unlink($databasePath);
        @unlink($databasePath.'-shm');
        @unlink($databasePath.'-wal');
        @rmdir(dirname($databasePath));
    }
});
