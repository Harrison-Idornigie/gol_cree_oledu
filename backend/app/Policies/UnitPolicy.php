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
        // Students can view units, but only published ones
        // Admins and team members can view all units
        return true;
    }

    /**
     * Determine whether the user can view the unit.
     */
    public function view(User $user, Unit $unit): bool
    {
        // Students can only view published units
        if ($user->isStudent()) {
            return $unit->status === 'published';
        }

        // Admins and team members can view all units
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine whether the user can create units.
     */
    public function create(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine whether the user can update the unit.
     */
    public function update(User $user, Unit $unit): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine whether the user can delete the unit.
     */
    public function delete(User $user, Unit $unit): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine whether the user can restore the unit.
     */
    public function restore(User $user, Unit $unit): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can permanently delete the unit.
     */
    public function forceDelete(User $user, Unit $unit): bool
    {
        return $user->isTenantAdmin();
    }
}
