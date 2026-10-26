<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectMarketingToCatalog
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        if ($user->role === 'marketing' && ! $request->routeIs('marketing.stok.*')) {
            return redirect()->route('marketing.stok.index');
        }

        if ($user->role !== 'marketing' && $request->routeIs('marketing.stok.*')) {
            abort(403);
        }

        return $next($request);
    }
}
