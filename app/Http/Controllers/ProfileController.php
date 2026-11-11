<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function show(Request $request): UserResource
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return new UserResource($user);
    }

    public function edit(): RedirectResponse
    {
        return redirect('/app/settings');
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

    public function updateApi(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $profile = $request->safe()->only(['name', 'username']);

        if (filled($request->validated('password'))) {
            $profile['password'] = Hash::make($request->validated('password'));
        }

        $user->fill($profile)->save();

        return (new UserResource($user->refresh()))->response();
    }
}
