<?php

namespace App\Policies;

use App\Models\CarTransaction;
use App\Models\User;

class CarTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManageTransactions($user);
    }

    public function view(User $user, CarTransaction $carTransaction): bool
    {
        return $this->canManageTransactions($user);
    }

    public function viewSensitiveData(User $user): bool
    {
        return $user->role === 'master';
    }

    public function create(User $user): bool
    {
        return $this->canManageTransactions($user);
    }

    public function update(User $user, CarTransaction $carTransaction): bool
    {
        return $this->viewSensitiveData($user);
    }

    public function delete(User $user, CarTransaction $carTransaction): bool
    {
        return $this->viewSensitiveData($user);
    }

    public function restore(User $user, CarTransaction $carTransaction): bool
    {
        return $this->viewSensitiveData($user);
    }

    public function forceDelete(User $user, CarTransaction $carTransaction): bool
    {
        return $this->viewSensitiveData($user);
    }

    private function canManageTransactions(User $user): bool
    {
        return in_array($user->role, ['master', 'admin'], true);
    }
}
