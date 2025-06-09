<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    /**
     * Determine if the user can view any tenants.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine if the user can view the tenant.
     */
    public function view(User $user, Tenant $tenant): bool
    {
        // Super admins can view any tenant
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Tenant admins can view their own tenant
        if ($user->isTenantAdmin() && $user->tenant_id === $tenant->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can create tenants.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine if the user can update the tenant.
     */
    public function update(User $user, Tenant $tenant): bool
    {
        // Super admins can update any tenant
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Tenant admins can update their own tenant (limited fields)
        if ($user->isTenantAdmin() && $user->tenant_id === $tenant->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can delete the tenant.
     */
    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine if the user can manage tenant users.
     */
    public function manageUsers(User $user, Tenant $tenant): bool
    {
        // Super admins can manage users in any tenant
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Tenant admins can manage users in their own tenant
        if ($user->isTenantAdmin() && $user->tenant_id === $tenant->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can manage tenant content.
     */
    public function manageContent(User $user, Tenant $tenant): bool
    {
        // Super admins can manage content in any tenant
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Tenant admins and teachers can manage content in their own tenant
        if (($user->isTenantAdmin() || $user->isTeacher()) && $user->tenant_id === $tenant->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can view tenant statistics.
     */
    public function viewStatistics(User $user, Tenant $tenant): bool
    {
        // Super admins can view statistics for any tenant
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Tenant admins can view statistics for their own tenant
        if ($user->isTenantAdmin() && $user->tenant_id === $tenant->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can switch tenant context.
     */
    public function switchContext(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
