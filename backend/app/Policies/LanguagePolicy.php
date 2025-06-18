<?php

namespace App\Policies;

use App\Models\Tenants\User;
use App\Models\Tenants\Language;

class LanguagePolicy
{
    /**
     * Determine if the user can view any languages.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view languages
    }

    /**
     * Determine if the user can view the language.
     */
    public function view(User $user, Language $language): bool
    {
        return true; // All authenticated users can view individual languages
    }

    /**
     * Determine if the user can create languages.
     */
    public function create(User $user): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can update the language.
     */
    public function update(User $user, Language $language): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can delete the language.
     */
    public function delete(User $user, Language $language): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can manage language settings.
     */
    public function manageSettings(User $user, Language $language): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can activate the language.
     */
    public function activate(User $user, Language $language): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can deactivate the language.
     */
    public function deactivate(User $user, Language $language): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can view language analytics.
     */
    public function viewAnalytics(User $user, Language $language): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can manage language content.
     */
    public function manageContent(User $user, Language $language): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }
}
