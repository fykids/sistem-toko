<?php

use App\Http\Middleware\RedirectToRoleDashboard;
use App\Models\User;
use App\Services\RoleDashboardResolver;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo(function (Request $request): string {
            $user = $request->user();

            if (! $user instanceof User) {
                abort(403);
            }

            $routeName = app(RoleDashboardResolver::class)->routeNameFor($user);

            abort_if($routeName === null, 403);

            return route($routeName);
        });
        $middleware->alias([
            'role.dashboard' => RedirectToRoleDashboard::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
