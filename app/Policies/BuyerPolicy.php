<?php

namespace App\Policies;

use App\Models\Buyer;
use App\Models\User;

class BuyerPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManageSales($user);
    }

    public function view(User $user, Buyer $buyer): bool
    {
        return $this->canManageSales($user);
    }

    public function create(User $user): bool
    {
        return $this->canManageSales($user);
    }

    public function update(User $user, Buyer $buyer): bool
    {
        return $this->canManageSales($user);
    }

    public function delete(User $user, Buyer $buyer): bool
    {
        return false;
    }

    private function canManageSales(User $user): bool
    {
        return in_array($user->role, ['master', 'admin'], true);
    }
}
