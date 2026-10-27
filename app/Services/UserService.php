<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function hasMasterAccount(): bool
    {
        return User::query()->where('role', 'master')->exists();
    }

    public function resetMasterPassword(): ?User
    {
        return DB::transaction(function (): ?User {
            $master = User::query()
                ->where('role', 'master')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($master === null) {
                return null;
            }

            $master->forceFill([
                'password' => Hash::make('123456'),
            ])->save();

            return $master;
        });
    }

    /**
     * @param  array{name: string, username: string, password: string}  $data
     */
    public function createInitialMaster(array $data): ?User
    {
        $lock = Cache::lock('initial-master-account', 30);

        if (! $lock->get()) {
            return null;
        }

        try {
            return DB::transaction(function () use ($data): ?User {
                if ($this->hasMasterAccount()) {
                    return null;
                }

                $user = new User;
                $user->forceFill([
                    'name' => $data['name'],
                    'username' => $data['username'],
                    'password' => Hash::make($data['password']),
                    'role' => 'master',
                ])->save();

                return $user;
            });
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array{name: string, username: string, password: string, role: string}  $data
     */
    public function createUser(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = new User;
            $user->forceFill([
                'name' => $data['name'],
                'username' => $data['username'],
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
            ])->save();

            return $user;
        });
    }

    /**
     * @param  array{name: string, username: string, password?: string|null, role: string}  $data
     */
    public function updateUser(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $masterCount = User::query()
                ->where('role', 'master')
                ->lockForUpdate()
                ->count();
            $managedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($managedUser->role === 'master' && $data['role'] === 'admin' && $masterCount <= 1) {
                throw ValidationException::withMessages([
                    'role' => 'Sistem harus mempertahankan minimal satu akun Master.',
                ]);
            }

            $attributes = [
                'name' => $data['name'],
                'username' => $data['username'],
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
                ->lockForUpdate()
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
