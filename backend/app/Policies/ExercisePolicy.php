<?php

namespace App\Policies;

use App\Models\Tenants\User;
use App\Models\Tenants\Exercise;

class ExercisePolicy
{
    /**
     * Determine if the user can view any exercises.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view exercises
    }

    /**
     * Determine if the user can view the exercise.
     */
    public function view(User $user, Exercise $exercise): bool
    {
        // Students can only view published exercises
        if ($user->isStudent()) {
            return $exercise->status === 'published';
        }

        // Admins and team members can view all exercises
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can create exercises.
     */
    public function create(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can update the exercise.
     */
    public function update(User $user, Exercise $exercise): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can delete the exercise.
     */
    public function delete(User $user, Exercise $exercise): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can publish the exercise.
     */
    public function publish(User $user, Exercise $exercise): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can attempt the exercise.
     */
    public function attempt(User $user, Exercise $exercise): bool
    {
        // All users can attempt published exercises
        return $exercise->status === 'published';
    }

    /**
     * Determine if the user can view exercise attempts.
     */
    public function viewAttempts(User $user, Exercise $exercise): bool
    {
        // Students can view their own attempts
        if ($user->isStudent()) {
            return true; // This will be filtered by user in the controller
        }

        // Admins and team members can view all attempts
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can view exercise analytics.
     */
    public function viewAnalytics(User $user, Exercise $exercise): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can duplicate the exercise.
     */
    public function duplicate(User $user, Exercise $exercise): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can reorder exercises.
     */
    public function reorder(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }
}
