<?php

namespace App\Policies;

use App\Models\Tenants\User;
use App\Models\Tenants\UserLanguage;

class UserLanguagePolicy
{
    /**
     * Determine if the user can view any user languages.
     */
    public function viewAny(User $user): bool
    {
        // Students can only view their own language selections
        // Team members and admins can view all
        return $user->isStudent() || $user->isTeam() || $user->isTenantAdmin();
    }

    /**
     * Determine if the user can view the user language.
     */
    public function view(User $user, UserLanguage $userLanguage): bool
    {
        // Students can only view their own language selections
        if ($user->isStudent()) {
            return $userLanguage->user_id === $user->id;
        }

        // Team members and admins can view any user language
        return $user->isTeam() || $user->isTenantAdmin();
    }

    /**
     * Determine if the user can create user languages.
     */
    public function create(User $user): bool
    {
        // Students can create their own language selections
        // Team members and admins can create for any user
        return $user->isStudent() || $user->isTeam() || $user->isTenantAdmin();
    }

    /**
     * Determine if the user can update the user language.
     */
    public function update(User $user, UserLanguage $userLanguage): bool
    {
        // Students can only update their own language selections
        if ($user->isStudent()) {
            return $userLanguage->user_id === $user->id;
        }

        // Team members and admins can update any user language
        return $user->isTeam() || $user->isTenantAdmin();
    }

    /**
     * Determine if the user can delete the user language.
     */
    public function delete(User $user, UserLanguage $userLanguage): bool
    {
        // Students can only delete their own language selections
        if ($user->isStudent()) {
            return $userLanguage->user_id === $user->id;
        }

        // Team members and admins can delete any user language
        return $user->isTeam() || $user->isTenantAdmin();
    }

    /**
     * Determine if the user can restore the user language.
     */
    public function restore(User $user, UserLanguage $userLanguage): bool
    {
        return $this->delete($user, $userLanguage);
    }

    /**
     * Determine if the user can permanently delete the user language.
     */
    public function forceDelete(User $user, UserLanguage $userLanguage): bool
    {
        // Only admins can permanently delete
        return $user->isTenantAdmin();
    }
}
