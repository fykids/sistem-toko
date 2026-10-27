<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInitialMasterRequest;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InitialMasterSetupController extends Controller
{
    public function create(): View
    {
        return view('auth.setup-master');
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
}
