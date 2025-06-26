<?php

namespace App\Policies;

use App\Models\Tenants\User;
use App\Models\Tenants\AdminInvite;

class AdminInvitePolicy
{
    /**
     * Determine whether the user can create admin invites.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can view admin invites.
     */
    public function view(User $user): bool
    {
        return $user->isAdmin() || $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can update admin invites.
     */
    public function update(User $user, AdminInvite $adminInvite): bool
    {
        return $user->isAdmin() || $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can delete admin invites.
     */
    public function delete(User $user, AdminInvite $adminInvite): bool
    {
        return $user->isAdmin() || $user->isTenantAdmin();
    }
}
