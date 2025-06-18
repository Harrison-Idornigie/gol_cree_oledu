<?php

namespace App\Policies;

use App\Models\Tenants\User;
use App\Models\Tenants\BulkOperation;

class BulkOperationPolicy
{
    /**
     * Determine if the user can view any bulk operations.
     */
    public function viewAny(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can view the bulk operation.
     */
    public function view(User $user, BulkOperation $bulkOperation): bool
    {
        // Users can view their own bulk operations
        if ($user->id === $bulkOperation->user_id) {
            return true;
        }

        // Tenant admins can view all bulk operations in their tenant
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can create bulk operations.
     */
    public function create(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can perform bulk imports.
     */
    public function import(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can perform bulk exports.
     */
    public function export(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can cancel bulk operations.
     */
    public function cancel(User $user, BulkOperation $bulkOperation): bool
    {
        // Users can cancel their own bulk operations
        if ($user->id === $bulkOperation->user_id) {
            return true;
        }

        // Tenant admins can cancel any bulk operation in their tenant
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can delete bulk operations.
     */
    public function delete(User $user, BulkOperation $bulkOperation): bool
    {
        // Only completed or failed operations can be deleted
        if (!in_array($bulkOperation->status, ['completed', 'failed'])) {
            return false;
        }

        // Users can delete their own bulk operations
        if ($user->id === $bulkOperation->user_id) {
            return true;
        }

        // Tenant admins can delete any bulk operation in their tenant
        return $user->isTenantAdmin();
    }
}
