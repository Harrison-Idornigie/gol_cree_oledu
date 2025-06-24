<?php

namespace App\Policies;

use App\Models\Tenants\Unit;
use App\Models\Tenants\User;

class UnitPolicy
{
    /**
     * Determine whether the user can view any units.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'team', 'content_creator']);
    }

    /**
     * Determine whether the user can view the unit.
     */
    public function view(User $user, Unit $unit): bool
    {
        return $user->hasAnyRole(['admin', 'team', 'content_creator']);
    }

    /**
     * Determine whether the user can create units.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'team', 'content_creator']);
    }

    /**
     * Determine whether the user can update the unit.
     */
    public function update(User $user, Unit $unit): bool
    {
        return $user->hasAnyRole(['admin', 'team', 'content_creator']);
    }

    /**
     * Determine whether the user can delete the unit.
     */
    public function delete(User $user, Unit $unit): bool
    {
        return $user->hasAnyRole(['admin', 'team', 'content_creator']);
    }

    /**
     * Determine whether the user can restore the unit.
     */
    public function restore(User $user, Unit $unit): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the unit.
     */
    public function forceDelete(User $user, Unit $unit): bool
    {
        return $user->hasRole('admin');
    }
}
