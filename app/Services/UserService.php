<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService
{
    /**
     * @param  array{name: string, email: string, password: string, role: string}  $data
     */
    public function createUser(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = new User;
            $user->forceFill([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
            ])->save();

            return $user;
        });
    }

    /**
     * @param  array{name: string, email: string, password?: string|null, role: string}  $data
     */
    public function updateUser(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $masterCount = User::query()
                ->where('role', 'master')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id'])
                ->count();
            $managedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($managedUser->role === 'master' && $data['role'] === 'admin' && $masterCount <= 1) {
                throw ValidationException::withMessages([
                    'role' => 'Sistem harus mempertahankan minimal satu akun Master.',
                ]);
            }

            $attributes = [
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $data['role'],
            ];

            if (filled($data['password'] ?? null)) {
                $attributes['password'] = Hash::make($data['password']);
            }

            $managedUser->forceFill($attributes)->save();

            return $managedUser;
        });
    }

    public function deleteUser(User $user): bool
    {
        return DB::transaction(function () use ($user): bool {
            $masterCount = User::query()
                ->where('role', 'master')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id'])
                ->count();
            $managedUser = User::query()->lockForUpdate()->find($user->getKey());

            if (
                ! $managedUser
                || Auth::id() === $managedUser->getKey()
                || ($managedUser->role === 'master' && $masterCount <= 1)
            ) {
                return false;
            }

            return (bool) $managedUser->delete();
        });
    }
}
