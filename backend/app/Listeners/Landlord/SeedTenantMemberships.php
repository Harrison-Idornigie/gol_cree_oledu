<?php

namespace App\Listeners\Landlord;

use App\Events\Landlord\TenantSeedingRequested;
use App\Models\Tenants\Membership;
use App\Models\Tenants\Permission;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Seed Tenant Memberships Listener
 * 
 * Handles seeding of memberships and permissions for new tenants.
 * This listener is modular and can be easily extended or replaced.
 */
class SeedTenantMemberships
{
    /**
     * Handle the event.
     */
    public function handle(TenantSeedingRequested $event): void
    {
        // Skip if memberships seeding is disabled
        if (!$event->shouldSeed('memberships')) {
            return;
        }

        $tenant = $event->tenant;
        $adminUser = $event->adminUser;

        try {
            // Set tenant context
            app()->instance('current_tenant', $tenant);

            // Seed permissions first
            $this->seedPermissions($tenant);
            
            // Seed memberships and assign permissions
            $this->seedMemberships($tenant);
            
            // Assign admin membership to admin user
            $this->assignAdminMembership($adminUser, $tenant);

            Log::info('Tenant memberships seeded successfully', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name
            ]);

        } catch (Exception $e) {
            Log::error('Failed to seed tenant memberships', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Don't throw - let other seeders continue
        }
    }

    /**
     * Seed permissions for the tenant
     */
    protected function seedPermissions($tenant): void
    {
        $permissions = config('tenant.default_permissions', []);

        foreach ($permissions as $permissionData) {
            Permission::firstOrCreate([
                'slug' => $permissionData['slug'],
                'tenant_id' => $tenant->id
            ], [
                'name' => $permissionData['name'],
                'description' => $permissionData['description'],
                'category' => $permissionData['category'],
            ]);
        }
    }

    /**
     * Seed memberships for the tenant
     */
    protected function seedMemberships($tenant): void
    {
        $memberships = config('tenant.default_memberships', []);

        foreach ($memberships as $membershipData) {
            $membership = Membership::firstOrCreate([
                'slug' => $membershipData['slug'],
                'tenant_id' => $tenant->id
            ], [
                'name' => $membershipData['name'],
                'description' => $membershipData['description'],
                'is_system' => $membershipData['is_system'] ?? true,
            ]);

            // Assign permissions to membership
            if (!empty($membershipData['permissions'])) {
                $permissionIds = Permission::where('tenant_id', $tenant->id)
                    ->whereIn('slug', $membershipData['permissions'])
                    ->pluck('id');

                $membership->permissions()->sync($permissionIds);
            }
        }
    }

    /**
     * Assign admin membership to the admin user
     */
    protected function assignAdminMembership($adminUser, $tenant): void
    {
        $tenantAdminMembership = Membership::where('tenant_id', $tenant->id)
            ->where('slug', 'tenant-admin')
            ->first();

        if ($tenantAdminMembership && !$adminUsermemberships()->where('membership_id', $tenantAdminMembership->id)->exists()) {
            $adminUsermemberships()->attach($tenantAdminMembership);
        }
    }
}
