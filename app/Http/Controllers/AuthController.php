<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\RoleDashboardResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(private RoleDashboardResolver $roleDashboardResolver) {}

    public function create(): Response
    {
        return response()->view('auth.login')->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            return back()
                ->withErrors(['username' => 'Username atau password tidak sesuai.'])
                ->onlyInput('username');
        }

        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $routeName = $this->roleDashboardResolver->routeNameFor($user);

        abort_if($routeName === null, 403);

        return redirect()->route($routeName);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->forget('url.intended');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->setStatusCode(303)
            ->with('success', 'Anda berhasil logout.');
    }
}
