<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenants\Permission;
use App\Models\Tenants\Role;
use App\Models\Landlord\Tenant;
use Illuminate\Database\Seeder;

class TenantRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create system-wide memberships first (no tenant_id)
        $this->createSystemRoles();

        // Create tenant-specific memberships for each tenant
        $tenants = Tenant::all();
        foreach ($tenants as $tenant) {
            $this->createTenantRoles($tenant);
        }
    }

    /**
     * Create system-wide memberships.
     */
    private function createSystemRoles(): void
    {
        // Super Admin - can manage everything across all tenants
        $superAdmin = Role::firstOrCreate([
            'slug' => 'super-admin',
            'tenant_id' => null,
        ], [
            'name' => 'Super Administrator',
            'description' => 'System administrator with access to all tenants',
            'is_system' => true,
        ]);

        // Create system-wide permissions
        $systemPermissions = [
            'manage-tenants' => 'Manage tenants (create, update, delete)',
            'view-all-tenants' => 'View all tenants',
            'switch-tenant-context' => 'Switch between tenant contexts',
            'manage-system-settings' => 'Manage system-wide settings',
        ];

        foreach ($systemPermissions as $slug => $description) {
            $permission = Permission::firstOrCreate([
                'slug' => $slug,
            ], [
                'name' => ucwords(str_replace('-', ' ', $slug)),
                'description' => $description,
            ]);

            $superAdmin->permissions()->syncWithoutDetaching([$permission->id]);
        }
    }

    /**
     * Create tenant-specific memberships.
     */
    private function createTenantRoles(Tenant $tenant): void
    {
        // Tenant Administrator
        $tenantAdmin = Role::firstOrCreate([
            'slug' => 'tenant-admin',
            'tenant_id' => $tenant->id,
        ], [
            'name' => 'Tenant Administrator',
            'description' => 'Administrator for ' . $tenant->name,
            'is_system' => false,
        ]);

        // Team
        $team = Role::firstOrCreate([
            'slug' => 'team',
            'tenant_id' => $tenant->id,
        ], [
            'name' => 'Team',
            'description' => 'Team membership for ' . $tenant->name,
            'is_system' => false,
        ]);

        // Student
        $student = Role::firstOrCreate([
            'slug' => 'student',
            'tenant_id' => $tenant->id,
        ], [
            'name' => 'Student',
            'description' => 'Student membership for ' . $tenant->name,
            'is_system' => false,
        ]);

        // Create tenant-specific permissions
        $this->createTenantPermissions($tenant, $tenantAdmin, $team, $student);
    }

    /**
     * Create tenant-specific permissions and assign to memberships.
     */
    private function createTenantPermissions(Tenant $tenant, Role $tenantAdmin, Role $team, Role $student): void
    {
        // Define permissions with their membership assignments
        $permissions = [
            // Tenant Admin permissions
            'manage-tenant-users' => [
                'description' => 'Manage users within tenant',
                'memberships' => [$tenantAdmin],
            ],
            'manage-tenant-settings' => [
                'description' => 'Manage tenant settings',
                'memberships' => [$tenantAdmin],
            ],
            'view-tenant-analytics' => [
                'description' => 'View tenant analytics and reports',
                'memberships' => [$tenantAdmin],
            ],
            
            // Content management permissions
            'manage-learning-paths' => [
                'description' => 'Create, edit, and delete learning paths',
                'memberships' => [$tenantAdmin, $team],
            ],
            'manage-units' => [
                'description' => 'Create, edit, and delete units',
                'memberships' => [$tenantAdmin, $team],
            ],
            'manage-lessons' => [
                'description' => 'Create, edit, and delete lessons',
                'memberships' => [$tenantAdmin, $team],
            ],
            'manage-exercises' => [
                'description' => 'Create, edit, and delete exercises',
                'memberships' => [$tenantAdmin, $team],
            ],
            'manage-vocabulary' => [
                'description' => 'Manage vocabulary items',
                'memberships' => [$tenantAdmin, $team],
            ],
            'manage-media' => [
                'description' => 'Upload and manage media files',
                'memberships' => [$tenantAdmin, $team],
            ],
            
            // View permissions
            'view-learning-paths' => [
                'description' => 'View learning paths',
                'memberships' => [$tenantAdmin, $team, $student],
            ],
            'view-lessons' => [
                'description' => 'View lessons',
                'memberships' => [$tenantAdmin, $team, $student],
            ],
            'view-exercises' => [
                'description' => 'View and complete exercises',
                'memberships' => [$tenantAdmin, $team, $student],
            ],
            
            // Student-specific permissions
            'track-progress' => [
                'description' => 'Track learning progress',
                'memberships' => [$student],
            ],
            'submit-exercises' => [
                'description' => 'Submit exercise attempts',
                'memberships' => [$student],
            ],
            
            // Team-specific permissions
            'view-student-progress' => [
                'description' => 'View student progress and analytics',
                'memberships' => [$tenantAdmin, $team],
            ],
            'grade-exercises' => [
                'description' => 'Grade student exercise submissions',
                'memberships' => [$tenantAdmin, $team],
            ],
        ];

        foreach ($permissions as $slug => $config) {
            $permission = Permission::firstOrCreate([
                'slug' => $slug,
            ], [
                'name' => ucwords(str_replace('-', ' ', $slug)),
                'description' => $config['description'],
            ]);

            // Assign permission to specified memberships
            foreach ($config['memberships'] as $membership) {
                $membership->permissions()->syncWithoutDetaching([$permission->id]);
            }
        }
    }
}
