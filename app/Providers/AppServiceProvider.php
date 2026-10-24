<?php

namespace App\Providers;

use App\Models\Buyer;
use App\Models\CarTransaction;
use App\Models\User;
use App\Policies\BuyerPolicy;
use App\Policies\CarTransactionPolicy;
use App\Policies\UserPolicy;
use App\Services\DatabaseSetupService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(DatabaseSetupService $databaseSetupService): void
    {
        $consoleCommand = $_SERVER['argv'][1] ?? null;
        $isMigrationCommand = $this->app->runningInConsole()
            && is_string($consoleCommand)
            && str_starts_with($consoleCommand, 'migrate');
        $defaultConnection = config('database.default');

        $databaseSetupService->setup(runMigrations: ! $isMigrationCommand);

        if (
            config("database.connections.{$defaultConnection}.driver") === 'sqlite'
            && config('session.driver') === 'database'
        ) {
            try {
                $sessionConnection = config('session.connection') ?: $defaultConnection;

                if (! Schema::connection($sessionConnection)->hasTable(config('session.table'))) {
                    config(['session.driver' => 'file']);
                }
            } catch (Throwable $exception) {
                Log::error('Unable to verify the SQLite sessions table; using file sessions.', [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);

                config(['session.driver' => 'file']);
            }
        }

        Gate::policy(CarTransaction::class, CarTransactionPolicy::class);
        Gate::policy(Buyer::class, BuyerPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        RateLimiter::for('login', function (Request $request): Limit {
            $username = Str::lower($request->string('username')->toString());

            return Limit::perMinute(5)->by(Str::transliterate($username.'|'.$request->ip()));
        });
    }
}
