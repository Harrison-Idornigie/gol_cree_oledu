<?php

namespace App\Policies;

use App\Models\Tenants\User;

class UserPolicy
{
    /**
     * Determine if the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can view the user profile.
     */
    public function view(User $user, User $targetUser): bool
    {
        // Users can view their own profile
        if ($user->id === $targetUser->id) {
            return true;
        }

        // Admins and team members can view user profiles
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can create users.
     */
    public function create(User $user): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can update the user.
     */
    public function update(User $user, User $targetUser): bool
    {
        // Users can update their own profile
        if ($user->id === $targetUser->id) {
            return true;
        }

        // Tenant admins can update any user in their tenant
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can delete the user.
     */
    public function delete(User $user, User $targetUser): bool
    {
        // Users cannot delete themselves
        if ($user->id === $targetUser->id) {
            return false;
        }

        // Only tenant admins can delete users
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can manage user roles.
     */
    public function manageRoles(User $user): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can assign roles to users.
     */
    public function assignRole(User $user, User $targetUser): bool
    {
        // Cannot assign roles to themselves
        if ($user->id === $targetUser->id) {
            return false;
        }

        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can view user progress.
     */
    public function viewProgress(User $user, User $targetUser): bool
    {
        // Users can view their own progress
        if ($user->id === $targetUser->id) {
            return true;
        }

        // Admins and team members can view user progress
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can invite new users.
     */
    public function invite(User $user): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can suspend users.
     */
    public function suspend(User $user, User $targetUser): bool
    {
        // Cannot suspend themselves
        if ($user->id === $targetUser->id) {
            return false;
        }

        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can reactivate users.
     */
    public function reactivate(User $user, User $targetUser): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can view user analytics.
     */
    public function viewAnalytics(User $user): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can export user data.
     */
    public function exportData(User $user): bool
    {
        return $user->isTenantAdmin();
    }
}
