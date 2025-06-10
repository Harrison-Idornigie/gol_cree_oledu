<?php

namespace App\Listeners\Landlord;

use App\Events\Landlord\TenantSeedingRequested;
use App\Models\Tenants\Role;
use App\Models\Tenants\Permission;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Seed Tenant Roles Listener
 * 
 * Handles seeding of roles and permissions for new tenants.
 * This listener is modular and can be easily extended or replaced.
 */
class SeedTenantRoles
{
    /**
     * Handle the event.
     */
    public function handle(TenantSeedingRequested $event): void
    {
        // Skip if roles seeding is disabled
        if (!$event->shouldSeed('roles')) {
            return;
        }

        $tenant = $event->tenant;
        $adminUser = $event->adminUser;

        try {
            // Set tenant context
            app()->instance('current_tenant', $tenant);

            // Seed permissions first
            $this->seedPermissions($tenant);
            
            // Seed roles and assign permissions
            $this->seedRoles($tenant);
            
            // Assign admin role to admin user
            $this->assignAdminRole($adminUser, $tenant);

            Log::info('Tenant roles seeded successfully', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name
            ]);

        } catch (Exception $e) {
            Log::error('Failed to seed tenant roles', [
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
     * Seed roles for the tenant
     */
    protected function seedRoles($tenant): void
    {
        $roles = config('tenant.default_roles', []);

        foreach ($roles as $roleData) {
            $role = Role::firstOrCreate([
                'slug' => $roleData['slug'],
                'tenant_id' => $tenant->id
            ], [
                'name' => $roleData['name'],
                'description' => $roleData['description'],
                'is_system' => $roleData['is_system'] ?? true,
            ]);

            // Assign permissions to role
            if (!empty($roleData['permissions'])) {
                $permissionIds = Permission::where('tenant_id', $tenant->id)
                    ->whereIn('slug', $roleData['permissions'])
                    ->pluck('id');

                $role->permissions()->sync($permissionIds);
            }
        }
    }

    /**
     * Assign admin role to the admin user
     */
    protected function assignAdminRole($adminUser, $tenant): void
    {
        $tenantAdminRole = Role::where('tenant_id', $tenant->id)
            ->where('slug', 'tenant-admin')
            ->first();

        if ($tenantAdminRole && !$adminUser->roles()->where('role_id', $tenantAdminRole->id)->exists()) {
            $adminUser->roles()->attach($tenantAdminRole);
        }
    }
}
