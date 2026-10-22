<?php

namespace App\Services;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class DatabaseSetupService
{
    public function __construct(private Migrator $migrator) {}

    public function setup(bool $runMigrations = true): void
    {
        $connectionName = config('database.default');
        $connection = config("database.connections.{$connectionName}");

        if (! is_array($connection) || ($connection['driver'] ?? null) !== 'sqlite') {
            return;
        }

        $databasePath = $connection['database'] ?? null;

        if (! is_string($databasePath) || $databasePath === ':memory:') {
            Log::error('SQLite database setup requires a persistent database path.');

            return;
        }

        try {
            File::ensureDirectoryExists(dirname($databasePath));

            if (! File::exists($databasePath) && File::put($databasePath, '') === false) {
                throw new RuntimeException('Unable to create the SQLite database file.');
            }

            if (! $runMigrations || ! $this->hasPendingMigrations()) {
                return;
            }

            $exitCode = Artisan::call('migrate', ['--force' => true]);

            if ($exitCode !== 0) {
                $output = Artisan::output();

                if (str_contains(strtolower($output), 'locked')) {
                    Log::warning('SQLite migrations were deferred because the database is locked.', [
                        'exit_code' => $exitCode,
                    ]);

                    return;
                }

                Log::error('SQLite database migrations failed.', [
                    'exit_code' => $exitCode,
                    'output' => $output,
                ]);
            }
        } catch (Throwable $exception) {
            $exceptionMessage = strtolower($exception->getMessage());

            if (str_contains($exceptionMessage, 'locked')) {
                Log::warning('SQLite migrations were deferred because the database is locked.', [
                    'exception' => $exception::class,
                ]);

                return;
            }

            Log::error('SQLite database setup failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'path' => $databasePath,
            ]);
        }
    }

    private function hasPendingMigrations(): bool
    {
        $migrationPaths = array_merge($this->migrator->paths(), [database_path('migrations')]);
        $migrationFiles = $this->migrator->getMigrationFiles($migrationPaths);
        $repository = $this->migrator->getRepository();

        if (! $repository->repositoryExists()) {
            return $migrationFiles !== [];
        }

        return array_diff(array_keys($migrationFiles), $repository->getRan()) !== [];
    }
}
