<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create system-wide roles first (no tenant_id)
        $this->createSystemRoles();

        // Create tenant-specific roles for each tenant
        $tenants = Tenant::all();
        foreach ($tenants as $tenant) {
            $this->createTenantRoles($tenant);
        }
    }

    /**
     * Create system-wide roles.
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
     * Create tenant-specific roles.
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

        // Teacher
        $teacher = Role::firstOrCreate([
            'slug' => 'teacher',
            'tenant_id' => $tenant->id,
        ], [
            'name' => 'Teacher',
            'description' => 'Teacher role for ' . $tenant->name,
            'is_system' => false,
        ]);

        // Student
        $student = Role::firstOrCreate([
            'slug' => 'student',
            'tenant_id' => $tenant->id,
        ], [
            'name' => 'Student',
            'description' => 'Student role for ' . $tenant->name,
            'is_system' => false,
        ]);

        // Create tenant-specific permissions
        $this->createTenantPermissions($tenant, $tenantAdmin, $teacher, $student);
    }

    /**
     * Create tenant-specific permissions and assign to roles.
     */
    private function createTenantPermissions(Tenant $tenant, Role $tenantAdmin, Role $teacher, Role $student): void
    {
        // Define permissions with their role assignments
        $permissions = [
            // Tenant Admin permissions
            'manage-tenant-users' => [
                'description' => 'Manage users within tenant',
                'roles' => [$tenantAdmin],
            ],
            'manage-tenant-settings' => [
                'description' => 'Manage tenant settings',
                'roles' => [$tenantAdmin],
            ],
            'view-tenant-analytics' => [
                'description' => 'View tenant analytics and reports',
                'roles' => [$tenantAdmin],
            ],
            
            // Content management permissions
            'manage-learning-paths' => [
                'description' => 'Create, edit, and delete learning paths',
                'roles' => [$tenantAdmin, $teacher],
            ],
            'manage-units' => [
                'description' => 'Create, edit, and delete units',
                'roles' => [$tenantAdmin, $teacher],
            ],
            'manage-lessons' => [
                'description' => 'Create, edit, and delete lessons',
                'roles' => [$tenantAdmin, $teacher],
            ],
            'manage-exercises' => [
                'description' => 'Create, edit, and delete exercises',
                'roles' => [$tenantAdmin, $teacher],
            ],
            'manage-vocabulary' => [
                'description' => 'Manage vocabulary items',
                'roles' => [$tenantAdmin, $teacher],
            ],
            'manage-media' => [
                'description' => 'Upload and manage media files',
                'roles' => [$tenantAdmin, $teacher],
            ],
            
            // View permissions
            'view-learning-paths' => [
                'description' => 'View learning paths',
                'roles' => [$tenantAdmin, $teacher, $student],
            ],
            'view-lessons' => [
                'description' => 'View lessons',
                'roles' => [$tenantAdmin, $teacher, $student],
            ],
            'view-exercises' => [
                'description' => 'View and complete exercises',
                'roles' => [$tenantAdmin, $teacher, $student],
            ],
            
            // Student-specific permissions
            'track-progress' => [
                'description' => 'Track learning progress',
                'roles' => [$student],
            ],
            'submit-exercises' => [
                'description' => 'Submit exercise attempts',
                'roles' => [$student],
            ],
            
            // Teacher-specific permissions
            'view-student-progress' => [
                'description' => 'View student progress and analytics',
                'roles' => [$tenantAdmin, $teacher],
            ],
            'grade-exercises' => [
                'description' => 'Grade student exercise submissions',
                'roles' => [$tenantAdmin, $teacher],
            ],
        ];

        foreach ($permissions as $slug => $config) {
            $permission = Permission::firstOrCreate([
                'slug' => $slug,
            ], [
                'name' => ucwords(str_replace('-', ' ', $slug)),
                'description' => $config['description'],
            ]);

            // Assign permission to specified roles
            foreach ($config['roles'] as $role) {
                $role->permissions()->syncWithoutDetaching([$permission->id]);
            }
        }
    }
}
