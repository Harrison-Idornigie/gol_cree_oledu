<?php

namespace App\Policies;

use App\Models\Tenants\User;
use App\Models\Tenants\Lesson;

class LessonPolicy
{
    /**
     * Determine if the user can view any lessons.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view lessons
    }

    /**
     * Determine if the user can view the lesson.
     */
    public function view(User $user, Lesson $lesson): bool
    {
        // Students can only view published lessons
        if ($user->isStudent()) {
            return $lesson->is_published ?? false;
        }

        // Admins and team members can view all lessons
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can create lessons.
     */
    public function create(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can update the lesson.
     */
    public function update(User $user, Lesson $lesson): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can delete the lesson.
     */
    public function delete(User $user, Lesson $lesson): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can publish the lesson.
     */
    public function publish(User $user, Lesson $lesson): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can unpublish the lesson.
     */
    public function unpublish(User $user, Lesson $lesson): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can manage lesson exercises.
     */
    public function manageExercises(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can view lesson analytics.
     */
    public function viewAnalytics(User $user, Lesson $lesson): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can duplicate the lesson.
     */
    public function duplicate(User $user, Lesson $lesson): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can reorder lessons.
     */
    public function reorder(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can submit lesson for review.
     */
    public function submitForReview(User $user, Lesson $lesson): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }
}
