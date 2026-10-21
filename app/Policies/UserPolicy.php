<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isMaster($user);
    }

    public function create(User $user): bool
    {
        return $this->isMaster($user);
    }

    public function update(User $user, User $managedUser): bool
    {
        return $this->isMaster($user);
    }

    public function delete(User $user, User $managedUser): bool
    {
        if (! $this->isMaster($user) || $user->is($managedUser)) {
            return false;
        }

        return $managedUser->role !== 'master'
            || User::query()->where('role', 'master')->count() > 1;
    }

    private function isMaster(User $user): bool
    {
        return $user->role === 'master';
    }
}
