<?php

namespace App\Policies;

use App\Models\Tenants\User;
use App\Models\Tenants\Word;

class WordPolicy
{
    /**
     * Determine if the user can view any words.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view words (students see only published ones via service layer)
        return true;
    }

    /**
     * Determine if the user can view the word.
     */
    public function view(User $user, Word $word): bool
    {
        // Students can only view published words
        if ($user->isStudent()) {
            return $word->status === 'published';
        }

        // Admins and team members can view all words
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can create words.
     */
    public function create(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can update the word.
     */
    public function update(User $user, Word $word): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can delete the word.
     */
    public function delete(User $user, Word $word): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can publish the word.
     */
    public function publish(User $user, Word $word): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can unpublish the word.
     */
    public function unpublish(User $user, Word $word): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can bulk import words.
     */
    public function bulkImport(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can bulk export words.
     */
    public function bulkExport(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can manage word translations.
     */
    public function manageTranslations(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can manage word audio.
     */
    public function manageAudio(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }
}
