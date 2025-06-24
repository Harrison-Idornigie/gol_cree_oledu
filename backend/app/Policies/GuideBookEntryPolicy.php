<?php

namespace App\Policies;

use App\Models\Tenants\User;
use App\Models\Tenants\GuideBookEntry;

class GuideBookEntryPolicy
{
    /**
     * Determine if the user can view any guide book entries.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view guide entries
    }

    /**
     * Determine if the user can view the guide book entry.
     */
    public function view(User $user, GuideBookEntry $entry): bool
    {
        // Students can only view published entries
        if ($user->isStudent()) {
            return $entry->status === 'published';
        }

        // Admins and team members can view all entries
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can create guide book entries.
     */
    public function create(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can update the guide book entry.
     */
    public function update(User $user, GuideBookEntry $entry): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can delete the guide book entry.
     */
    public function delete(User $user, GuideBookEntry $entry): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can publish the guide book entry.
     */
    public function publish(User $user, GuideBookEntry $entry): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can unpublish the guide book entry.
     */
    public function unpublish(User $user, GuideBookEntry $entry): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can manage guide book entry categories.
     */
    public function manageCategories(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can duplicate the guide book entry.
     */
    public function duplicate(User $user, GuideBookEntry $entry): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can reorder guide book entries.
     */
    public function reorder(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }
}
