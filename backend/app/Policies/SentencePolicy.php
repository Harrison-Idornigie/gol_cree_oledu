<?php

namespace App\Policies;

use App\Models\Tenants\User;
use App\Models\Tenants\Sentence;

class SentencePolicy
{
    /**
     * Determine if the user can view any sentences.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view sentences
    }

    /**
     * Determine if the user can view the sentence.
     */
    public function view(User $user, Sentence $sentence): bool
    {
        return true; // All authenticated users can view individual sentences
    }

    /**
     * Determine if the user can create sentences.
     */
    public function create(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can update the sentence.
     */
    public function update(User $user, Sentence $sentence): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can delete the sentence.
     */
    public function delete(User $user, Sentence $sentence): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can publish the sentence.
     */
    public function publish(User $user, Sentence $sentence): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can unpublish the sentence.
     */
    public function unpublish(User $user, Sentence $sentence): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can bulk import sentences.
     */
    public function bulkImport(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can bulk export sentences.
     */
    public function bulkExport(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can manage sentence translations.
     */
    public function manageTranslations(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can manage sentence audio.
     */
    public function manageAudio(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }
}
