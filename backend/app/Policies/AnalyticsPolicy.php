<?php

namespace App\Policies;

use App\Models\Tenants\User;

class AnalyticsPolicy
{
    /**
     * Determine if the user can view analytics overview.
     */
    public function viewOverview(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can view detailed analytics.
     */
    public function viewDetailed(User $user): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can view student progress analytics.
     */
    public function viewStudentProgress(User $user): bool
    {
        // Students can view their own progress
        if ($user->isStudent()) {
            return true;
        }

        // Admins and team members can view all student progress
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can view content effectiveness analytics.
     */
    public function viewContentEffectiveness(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can view engagement analytics.
     */
    public function viewEngagement(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can view learning analytics.
     */
    public function viewLearningAnalytics(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can export analytics data.
     */
    public function exportData(User $user): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can configure analytics settings.
     */
    public function configureSettings(User $user): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can view system health metrics.
     */
    public function viewSystemHealth(User $user): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can view performance metrics.
     */
    public function viewPerformanceMetrics(User $user): bool
    {
        return $user->isTenantAdmin();
    }
}
