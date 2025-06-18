<?php

namespace App\Policies;

use App\Models\Tenants\User;

class SystemHealthPolicy
{
    /**
     * Determine if the user can view basic health status.
     */
    public function viewBasicHealth(User $user): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can view detailed health metrics.
     */
    public function viewDetailedHealth(User $user): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can view system performance metrics.
     */
    public function viewPerformanceMetrics(User $user): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can view database health.
     */
    public function viewDatabaseHealth(User $user): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can view cache health.
     */
    public function viewCacheHealth(User $user): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can view storage health.
     */
    public function viewStorageHealth(User $user): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can view external service health.
     */
    public function viewExternalServices(User $user): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can configure health monitoring.
     */
    public function configureMonitoring(User $user): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can view error logs.
     */
    public function viewErrorLogs(User $user): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can export health reports.
     */
    public function exportReports(User $user): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can trigger health checks.
     */
    public function triggerHealthChecks(User $user): bool
    {
        return $user->isTenantAdmin();
    }
}
