<?php

namespace App\Services\Auth;

use App\Models\Landlord\Tenant;
use App\Models\Landlord\UserTenantAssociation;
use App\Models\Tenants\User;
use Illuminate\Support\Facades\Log;

/**
 * User Tenant Synchronization Service
 * 
 * Keeps the central user_tenant_associations table in sync with
 * actual user data in tenant databases.
 */
class UserTenantSyncService
{
    /**
     * Sync user association when user is created/updated in tenant
     */
    public function syncUserToTenant(string $email, Tenant $tenant, array $userData): void
    {
        try {
            UserTenantAssociation::syncFromTenant($email, $tenant, $userData);
            
            // Clear cache for this user
            UserTenantAssociation::clearUserCache($email);
            
            Log::info('User tenant association synced', [
                'email' => $email,
                'tenant_slug' => $tenant->slug,
                'membership' => $userData['membership'] ?? null
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to sync user tenant association', [
                'email' => $email,
                'tenant_slug' => $tenant->slug,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Remove user association when user is deleted from tenant
     */
    public function removeUserFromTenant(string $email, string $tenantSlug): void
    {
        try {
            UserTenantAssociation::removeFromTenant($email, $tenantSlug);
            
            // Clear cache for this user
            UserTenantAssociation::clearUserCache($email);
            
            Log::info('User tenant association removed', [
                'email' => $email,
                'tenant_slug' => $tenantSlug
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to remove user tenant association', [
                'email' => $email,
                'tenant_slug' => $tenantSlug,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Sync all users from a specific tenant (for data migration)
     */
    public function syncTenantUsers(Tenant $tenant): array
    {
        $stats = ['synced' => 0, 'errors' => 0];
        
        try {
            $tenant->run(function () use ($tenant, &$stats) {
                $users = User::all();
                
                foreach ($users as $user) {
                    try {
                        UserTenantAssociation::syncFromTenant($user->email, $tenant, [
                            'membership' => $user->membership,
                            'permissions' => $user->permissions ?? [],
                            'is_active' => true
                        ]);
                        
                        $stats['synced']++;
                    } catch (\Exception $e) {
                        $stats['errors']++;
                        Log::error('Failed to sync individual user', [
                            'email' => $user->email,
                            'tenant_slug' => $tenant->slug,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            });
        } catch (\Exception $e) {
            Log::error('Failed to sync tenant users', [
                'tenant_slug' => $tenant->slug,
                'error' => $e->getMessage()
            ]);
        }
        
        return $stats;
    }

    /**
     * Full system sync - migrate all existing users to central mapping
     * 
     * ⚠️  WARNING: This is expensive! Use only for initial migration.
     */
    public function syncAllTenants(): array
    {
        $overallStats = ['tenants_processed' => 0, 'total_synced' => 0, 'total_errors' => 0];
        
        $tenants = Tenant::where('status', 'active')->get();
        
        foreach ($tenants as $tenant) {
            Log::info('Starting sync for tenant', ['tenant_slug' => $tenant->slug]);
            
            $stats = $this->syncTenantUsers($tenant);
            
            $overallStats['tenants_processed']++;
            $overallStats['total_synced'] += $stats['synced'];
            $overallStats['total_errors'] += $stats['errors'];
            
            Log::info('Completed sync for tenant', [
                'tenant_slug' => $tenant->slug,
                'synced' => $stats['synced'],
                'errors' => $stats['errors']
            ]);
        }
        
        return $overallStats;
    }

    /**
     * Verify sync integrity - check for inconsistencies
     */
    public function verifySyncIntegrity(string $email): array
    {
        $centralAssociations = UserTenantAssociation::forEmail($email)->active()->get();
        $actualTenants = collect();
        
        // Check each tenant database
        foreach ($centralAssociations as $association) {
            try {
                $association->tenant->run(function () use ($email, $association, &$actualTenants) {
                    $user = User::where('email', $email)->first();
                    if ($user) {
                        $actualTenants->push([
                            'tenant_slug' => $association->tenant_slug,
                            'central_membership' => $association->membership,
                            'actual_membership' => $user->membership,
                            'matches' => $association->membership === $user->membership
                        ]);
                    }
                });
            } catch (\Exception $e) {
                Log::warning('Failed to verify tenant during integrity check', [
                    'email' => $email,
                    'tenant_slug' => $association->tenant_slug,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return [
            'email' => $email,
            'central_associations' => $centralAssociations->count(),
            'verified_tenants' => $actualTenants->count(),
            'mismatches' => $actualTenants->where('matches', false)->count(),
            'details' => $actualTenants->toArray()
        ];
    }

    /**
     * Clean up orphaned associations (tenants that no longer exist)
     */
    public function cleanupOrphanedAssociations(): int
    {
        $activeTenantIds = Tenant::where('status', 'active')->pluck('id');
        
        $orphanedCount = UserTenantAssociation::whereNotIn('tenant_id', $activeTenantIds)->count();
        
        if ($orphanedCount > 0) {
            UserTenantAssociation::whereNotIn('tenant_id', $activeTenantIds)->delete();
            
            Log::info('Cleaned up orphaned user tenant associations', [
                'count' => $orphanedCount
            ]);
        }
        
        return $orphanedCount;
    }
}
