<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\RoleDashboardResolver;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(private RoleDashboardResolver $roleDashboardResolver) {}

    public function create(): Response
    {
        return response()->view('spa.app')->header('Cache-Control', 'no-store, private');
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

    public function loginApi(Request $request, UserService $userService): JsonResponse
    {
        if (! $userService->hasMasterAccount()) {
            return response()->json([
                'message' => 'Akun Master belum tersedia.',
                'redirect' => '/setup-master',
            ], 409);
        }

        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Username atau password tidak sesuai.',
                'errors' => ['username' => ['Username atau password tidak sesuai.']],
            ], 422);
        }

        $request->session()->regenerate();
        $request->session()->forget('url.intended');
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $routeName = $this->roleDashboardResolver->routeNameFor($user);

        abort_if($routeName === null, 403);

        return response()->json([
            'redirect' => $routeName === 'marketing.stok.index'
                ? '/app/marketing'
                : '/app/dashboard',
        ]);
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

    public function logoutApi(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->forget('url.intended');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(null, 204);
    }
}
