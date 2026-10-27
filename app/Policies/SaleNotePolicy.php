<?php

namespace App\Policies;

use App\Models\SaleNote;
use App\Models\User;

class SaleNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManageSaleNotes($user);
    }

    public function view(User $user, SaleNote $saleNote): bool
    {
        return $this->canManageSaleNotes($user)
            && ($user->role === 'master' || $saleNote->created_by === $user->id);
    }

    public function create(User $user): bool
    {
        return $this->canManageSaleNotes($user);
    }

    public function update(User $user, SaleNote $saleNote): bool
    {
        return $this->view($user, $saleNote);
    }

    public function recordPayment(User $user, SaleNote $saleNote): bool
    {
        return $this->update($user, $saleNote);
    }

    public function delete(User $user, SaleNote $saleNote): bool
    {
        return $this->view($user, $saleNote);
    }

    private function canManageSaleNotes(User $user): bool
    {
        return in_array($user->role, ['master', 'admin'], true);
    }
}
