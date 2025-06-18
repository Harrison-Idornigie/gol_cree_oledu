<?php

namespace App\Policies;

use App\Models\Tenants\User;
use App\Models\Tenants\LearningPath;

class LearningPathPolicy
{
    /**
     * Determine if the user can view any learning paths.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view learning paths
    }

    /**
     * Determine if the user can view the learning path.
     */
    public function view(User $user, LearningPath $learningPath): bool
    {
        // Students can only view published learning paths
        if ($user->isStudent()) {
            return $learningPath->is_published ?? false;
        }

        // Admins and team members can view all learning paths
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can create learning paths.
     */
    public function create(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can update the learning path.
     */
    public function update(User $user, LearningPath $learningPath): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can delete the learning path.
     */
    public function delete(User $user, LearningPath $learningPath): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can publish the learning path.
     */
    public function publish(User $user, LearningPath $learningPath): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can unpublish the learning path.
     */
    public function unpublish(User $user, LearningPath $learningPath): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can enroll in the learning path.
     */
    public function enroll(User $user, LearningPath $learningPath): bool
    {
        // Students can enroll in published learning paths
        return $user->isStudent() && ($learningPath->is_published ?? false);
    }

    /**
     * Determine if the user can unenroll from the learning path.
     */
    public function unenroll(User $user, LearningPath $learningPath): bool
    {
        // Students can unenroll from learning paths they're enrolled in
        return $user->isStudent();
    }

    /**
     * Determine if the user can manage units in the learning path.
     */
    public function manageUnits(User $user, LearningPath $learningPath): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can view learning path analytics.
     */
    public function viewAnalytics(User $user, LearningPath $learningPath): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can duplicate the learning path.
     */
    public function duplicate(User $user, LearningPath $learningPath): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can reorder learning paths.
     */
    public function reorder(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }
}
