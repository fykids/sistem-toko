<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\RoleDashboardResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectToRoleDashboard
{
    public function __construct(private RoleDashboardResolver $roleDashboardResolver) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        if (in_array($user->role, $roles, true)) {
            return $next($request);
        }

        $routeName = $this->roleDashboardResolver->routeNameFor($user);

        abort_if($routeName === null, 403);

        return redirect()->route($routeName);
    }
}
