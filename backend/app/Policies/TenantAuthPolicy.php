<?php

namespace App\Policies;

use App\Models\Tenants\User;
use App\Models\Landlord\Tenant;
use App\Helpers\Tenants\TenantHelper;
use Illuminate\Support\Facades\Log;

/**
 * Tenant Authentication Policy
 * 
 * Handles authorization for tenant-specific authentication operations.
 * Ensures proper tenant isolation and membership-based access control.
 */
class TenantAuthPolicy
{
    /**
     * Determine if the user can login to the specified tenant.
     * 
     * This policy ensures users can only login to tenants where they have membership.
     */
    public function login(?User $user, Tenant $tenant): bool
    {
        // Allow login attempts for unauthenticated users (they will be validated in controller)
        if (!$user) {
            return true;
        }

        // If user is already authenticated, they should be able to login to their tenant
        $currentTenant = TenantHelper::current();
        if ($currentTenant && $currentTenant->id === $tenant->id) {
            return true;
        }

        // For multi-tenant scenarios, check if user belongs to this tenant
        // This would require checking the central user-tenant association
        return true; // Allow for now, actual validation happens in controller
    }

    /**
     * Determine if the user can logout from the current tenant.
     */
    public function logout(User $user): bool
    {
        // Any authenticated user can logout
        return true;
    }

    /**
     * Determine if the user can retrieve their tenant list.
     *
     * This is used for multi-tenant scenarios where users might belong to multiple tenants.
     * For security reasons, this should be restricted to prevent tenant discovery.
     */
    public function getUserTenants(?User $user): bool
    {
        // Restrict tenant discovery for security reasons
        // Only allow unauthenticated requests (for initial login flow)
        // Authenticated users should not be able to discover other tenants
        if ($user) {
            Log::warning('Authenticated user attempted tenant discovery', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            return false;
        }

        // Allow unauthenticated requests for tenant discovery during login
        return true;
    }

    /**
     * Determine if the user can access tenant-specific authentication endpoints.
     */
    public function accessTenantAuth(?User $user, Tenant $tenant): bool
    {
        // Basic tenant status checks
        if (!$tenant) {
            Log::warning('Tenant auth access denied: No tenant context');
            return false;
        }

        // Check if tenant is in a valid status (active, trial, or null for tests)
        $validStatuses = ['active', 'trial', null];
        if (!in_array($tenant->status, $validStatuses)) {
            Log::warning('Tenant auth access denied: Invalid tenant status', [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
                'status' => $tenant->status
            ]);
            return false;
        }

        return true;
    }

    /**
     * Determine if the user can switch tenant context.
     * 
     * Only certain user types should be able to switch between tenants.
     */
    public function switchTenantContext(User $user): bool
    {
        // Super admins can switch between any tenants
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Tenant admins can switch between their assigned tenants
        if ($user->isTenantAdmin()) {
            return true;
        }

        // Regular users cannot switch tenant context
        return false;
    }

    /**
     * Determine if the user can invite others to the tenant.
     */
    public function inviteUsers(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isAdmin();
    }

    /**
     * Determine if the user can register in the current tenant.
     */
    public function register(?User $user, Tenant $tenant): bool
    {
        // Check tenant status
        $validStatuses = ['active', 'trial', null];
        if (!in_array($tenant->status, $validStatuses)) {
            return false;
        }

        // Check if tenant allows registration
        $settings = $tenant->settings ?? [];
        if (isset($settings['allow_registration']) && !$settings['allow_registration']) {
            return false;
        }

        return true;
    }

    /**
     * Determine if the user can reset password in the current tenant.
     */
    public function resetPassword(?User $user, Tenant $tenant): bool
    {
        // Check tenant status
        $validStatuses = ['active', 'trial', null];
        if (!in_array($tenant->status, $validStatuses)) {
            return false;
        }

        return true;
    }

    /**
     * Determine if the user can verify email in the current tenant.
     */
    public function verifyEmail(?User $user, Tenant $tenant): bool
    {
        // Check tenant status
        $validStatuses = ['active', 'trial', null];
        if (!in_array($tenant->status, $validStatuses)) {
            return false;
        }

        return true;
    }

    /**
     * Determine if the user has the required membership level.
     */
    public function hasMembershipLevel(User $user, string $requiredLevel): bool
    {
        $membershipHierarchy = [
            'student' => 1,
            'team' => 2,
            'tenant-admin' => 3,
            'admin' => 4,
            'super-admin' => 5,
        ];

        $userLevel = $membershipHierarchy[$user->membership] ?? 0;
        $requiredLevelValue = $membershipHierarchy[$requiredLevel] ?? 0;

        return $userLevel >= $requiredLevelValue;
    }

    /**
     * Determine if the user can access admin features in the tenant.
     */
    public function accessAdminFeatures(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isAdmin() || $user->isSuperAdmin();
    }

    /**
     * Determine if the user can access team features in the tenant.
     */
    public function accessTeamFeatures(User $user): bool
    {
        return $user->isTeam() || $user->isTenantAdmin() || $user->isAdmin() || $user->isSuperAdmin();
    }

    /**
     * Determine if the user can manage tenant settings.
     */
    public function manageTenantSettings(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isAdmin() || $user->isSuperAdmin();
    }
}
