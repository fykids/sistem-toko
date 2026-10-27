<?php

namespace App\Http\Middleware;

use App\Services\UserService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectGuestsToMasterSetup
{
    public function __construct(private UserService $userService) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() === null) {
            if (! $this->userService->hasMasterAccount()) {
                return redirect()->route('setup-master.create');
            }

            if (! $request->routeIs('login', 'login.store')) {
                return redirect()->guest(route('login'));
            }
        }

        return $next($request);
    }
}
