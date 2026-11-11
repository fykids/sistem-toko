<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInitialMasterRequest;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class InitialMasterSetupController extends Controller
{
    public function create(): Response
    {
        return response()->view('spa.app')->header('Cache-Control', 'no-store, private');
    }

    public function store(StoreInitialMasterRequest $request, UserService $userService): RedirectResponse
    {
        $master = $userService->createInitialMaster(
            $request->safe()->only(['name', 'username', 'password']),
        );

        if ($master === null) {
            return redirect()->route('login');
        }

        Auth::login($master);
        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        return redirect()->route('dashboard');
    }

    public function storeApi(
        StoreInitialMasterRequest $request,
        UserService $userService,
    ): JsonResponse {
        $master = $userService->createInitialMaster(
            $request->safe()->only(['name', 'username', 'password']),
        );

        if ($master === null) {
            return response()->json([
                'message' => 'Akun Master telah dibuat. Silakan masuk.',
            ], 409);
        }

        Auth::login($master);
        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        return response()->json(['redirect' => '/app/dashboard'], 201);
    }
}
