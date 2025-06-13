<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenants\Role;
use App\Models\Tenants\Permission;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define role-permission mappings
        $rolePermissions = [
            'super-admin' => [
                // Full system access
                'system.manage', 'tenants.manage', 'users.manage', 'content.manage',
                'words.manage', 'lessons.manage', 'settings.manage', 'analytics.view',
                'reports.view', 'reports.export', 'admin', 'tenant-admin', 'team'
            ],
            'admin' => [
                // Administrative access
                'users.manage', 'content.manage', 'words.manage', 'lessons.manage',
                'exercises.create', 'exercises.edit', 'exercises.delete',
                'settings.manage', 'analytics.view', 'reports.view', 'admin', 'team'
            ],
            'tenant-admin' => [
                // Tenant-level admin
                'users.view', 'users.invite', 'content.view', 'content.create',
                'content.edit', 'settings.view', 'analytics.view', 'reports.view',
                'tenant-admin'
            ],
            'team' => [
                // Content creators and managers
                'content.create', 'content.edit', 'content.view', 'words.create',
                'words.edit', 'lessons.create', 'lessons.edit', 'exercises.create',
                'exercises.edit', 'team'
            ],
            'student' => [
                // Learning access
                'content.view', 'exercises.attempt', 'progress.track',
                'progress.view', 'profile.edit'
            ],
            'content-creator' => [
                // Specialized content creation
                'content.create', 'content.edit', 'content.view',
                'words.create', 'words.edit', 'lessons.create', 'lessons.edit',
                'exercises.create', 'exercises.edit'
            ],
            'content-reviewer' => [
                // Content review and approval
                'content.view', 'content.edit', 'content.approve',
                'words.edit', 'lessons.edit', 'exercises.edit'
            ],
            'analytics-viewer' => [
                // Analytics and reporting
                'analytics.view', 'reports.view', 'reports.export',
                'users.view', 'content.view'
            ],
            'user-manager' => [
                // User management
                'users.view', 'users.invite', 'users.manage',
                'analytics.view'
            ]
        ];

        foreach ($rolePermissions as $roleSlug => $permissionSlugs) {
            $role = Role::where('slug', $roleSlug)->first();
            
            if (!$role) {
                continue;
            }

            foreach ($permissionSlugs as $permissionSlug) {
                $permission = Permission::where('slug', $permissionSlug)->first();
                
                if ($permission) {
                    $role->grantPermission($permission);
                }
            }
        }
    }
}