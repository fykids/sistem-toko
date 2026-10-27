<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('settings.index');
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $profile = $request->safe()->only(['name', 'username']);

        if (filled($request->validated('password'))) {
            $profile['password'] = Hash::make($request->validated('password'));
        }

        $user->fill($profile)->save();

        return redirect()->route('profile.edit')->with('success', 'Pengaturan akun berhasil diperbarui.');
    }
}
