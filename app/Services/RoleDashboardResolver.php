<?php

namespace App\Services;

use App\Models\User;

class RoleDashboardResolver
{
    public function routeNameFor(User $user): ?string
    {
        return match ($user->role) {
            'master', 'admin' => 'dashboard',
            'marketing' => 'marketing.stok.index',
            default => null,
        };
    }
}
