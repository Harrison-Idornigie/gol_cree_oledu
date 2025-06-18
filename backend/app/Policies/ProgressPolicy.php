<?php

namespace App\Policies;

use App\Models\Tenants\User;
use App\Models\Tenants\Progress;

class ProgressPolicy
{
    /**
     * Determine if the user can view any progress records.
     */
    public function viewAny(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam() || $user->isStudent();
    }

    /**
     * Determine if the user can view the progress record.
     */
    public function view(User $user, Progress $progress): bool
    {
        // Students can view their own progress
        if ($user->isStudent() && $user->id === $progress->user_id) {
            return true;
        }

        // Admins and team members can view all progress
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can create progress records.
     */
    public function create(User $user): bool
    {
        // Progress is typically created automatically by the system
        // But students can create their own progress records
        return true;
    }

    /**
     * Determine if the user can update the progress record.
     */
    public function update(User $user, Progress $progress): bool
    {
        // Students can update their own progress
        if ($user->isStudent() && $user->id === $progress->user_id) {
            return true;
        }

        // Admins can update any progress
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can delete the progress record.
     */
    public function delete(User $user, Progress $progress): bool
    {
        // Only admins can delete progress records
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can reset progress.
     */
    public function reset(User $user, Progress $progress): bool
    {
        // Students can reset their own progress
        if ($user->isStudent() && $user->id === $progress->user_id) {
            return true;
        }

        // Admins can reset any progress
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can view progress analytics.
     */
    public function viewAnalytics(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can export progress data.
     */
    public function exportData(User $user): bool
    {
        return $user->isTenantAdmin();
    }
}
