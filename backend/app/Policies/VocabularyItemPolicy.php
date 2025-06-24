<?php

namespace App\Policies;

use App\Models\Tenants\VocabularyItem;
use App\Models\Tenants\User;

class VocabularyItemPolicy
{
    /**
     * Determine whether the user can view any vocabulary items.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view vocabulary items
    }

    /**
     * Determine whether the user can view the vocabulary item.
     */
    public function view(User $user, VocabularyItem $vocabularyItem): bool
    {
        // Students can only view vocabulary items from lessons they have access to
        if ($user->isStudent()) {
            return true; // TODO: Add lesson access check when services are implemented
        }

        // Admins and team members can view all vocabulary items
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine whether the user can create vocabulary items.
     */
    public function create(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine whether the user can update the vocabulary item.
     */
    public function update(User $user, VocabularyItem $vocabularyItem): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine whether the user can delete the vocabulary item.
     */
    public function delete(User $user, VocabularyItem $vocabularyItem): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine whether the user can practice/interact with vocabulary items.
     */
    public function practice(User $user, VocabularyItem $vocabularyItem): bool
    {
        // Students can practice vocabulary items from accessible lessons
        if ($user->isStudent()) {
            return true; // TODO: Add lesson access check when services are implemented
        }

        // Admins and team members can also practice for testing
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine whether the user can restore the vocabulary item.
     */
    public function restore(User $user, VocabularyItem $vocabularyItem): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can permanently delete the vocabulary item.
     */
    public function forceDelete(User $user, VocabularyItem $vocabularyItem): bool
    {
        return $user->isTenantAdmin();
    }
}
