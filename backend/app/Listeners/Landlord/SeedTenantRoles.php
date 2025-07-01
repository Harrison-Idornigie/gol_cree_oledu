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
 * Handles seeding of default roles and permissions for new tenants.
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

        try {
            // Switch to tenant context for all database operations
            $tenant->run(function () use ($tenant) {
                // Seed default roles and permissions
                $this->seedRoles($tenant);
            });

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
     * Seed roles and permissions for the tenant
     */
    protected function seedRoles($tenant): void
    {
        Log::info('Starting tenant roles seeding', [
            'tenant_id' => $tenant->id
        ]);

        // Seed default permissions first
        $this->seedDefaultPermissions();
        
        // Then seed default roles
        $this->seedDefaultRoles();

        Log::info('Completed tenant roles seeding', [
            'tenant_id' => $tenant->id
        ]);
    }

    /**
     * Seed default permissions for the tenant
     */
    protected function seedDefaultPermissions(): void
    {
        $permissions = [
            // System permissions
            ['name' => 'System Management', 'slug' => 'system.manage', 'group' => 'system', 'description' => 'Full system access'],
            ['name' => 'Tenant Management', 'slug' => 'tenants.manage', 'group' => 'system', 'description' => 'Manage tenants'],
            ['name' => 'User Management', 'slug' => 'users.manage', 'group' => 'admin', 'description' => 'Manage users'],
            
            // Content permissions
            ['name' => 'Content Management', 'slug' => 'content.manage', 'group' => 'content', 'description' => 'Full content management'],
            ['name' => 'Content Creation', 'slug' => 'content.create', 'group' => 'content', 'description' => 'Create content'],
            ['name' => 'Content View', 'slug' => 'content.view', 'group' => 'content', 'description' => 'View content'],
            
            // Language permissions
            ['name' => 'Word Management', 'slug' => 'words.manage', 'group' => 'language', 'description' => 'Manage words and Guidebook'],
            ['name' => 'Lesson Management', 'slug' => 'lessons.manage', 'group' => 'language', 'description' => 'Manage lessons'],
            
            // Student permissions
            ['name' => 'Progress Tracking', 'slug' => 'progress.track', 'group' => 'student', 'description' => 'Track learning progress'],
            ['name' => 'Exercise Attempts', 'slug' => 'exercises.attempt', 'group' => 'student', 'description' => 'Attempt exercises'],
            
            // Analytics permissions
            ['name' => 'Analytics View', 'slug' => 'analytics.view', 'group' => 'analytics', 'description' => 'View analytics'],
            ['name' => 'Reports View', 'slug' => 'reports.view', 'group' => 'analytics', 'description' => 'View reports'],
            
            // Settings permissions
            ['name' => 'Settings Management', 'slug' => 'settings.manage', 'group' => 'admin', 'description' => 'Manage settings'],
        ];

        foreach ($permissions as $permissionData) {
            Permission::firstOrCreate([
                'slug' => $permissionData['slug'],
                'tenant_id' => tenant()->id
            ], $permissionData);
        }
    }

    /**
     * Seed default roles for the tenant
     */
    protected function seedDefaultRoles(): void
    {
        $roles = [
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'description' => 'Full administrative access',
                'is_system' => true,
                'permissions' => [
                    'system.manage', 'tenants.manage', 'users.manage',
                    'content.manage', 'words.manage', 'lessons.manage',
                    'analytics.view', 'reports.view', 'settings.manage'
                ]
            ],
            [
                'name' => 'Tenant Admin',
                'slug' => 'tenant-admin',
                'description' => 'Tenant administrator with limited system access',
                'is_system' => true,
                'permissions' => [
                    'users.manage', 'content.view', 'settings.manage',
                    'analytics.view', 'reports.view'
                ]
            ],
            [
                'name' => 'Team Member',
                'slug' => 'team',
                'description' => 'Content creation and management team member',
                'is_system' => true,
                'permissions' => [
                    'content.create', 'words.manage', 'lessons.manage'
                ]
            ],
            [
                'name' => 'Student',
                'slug' => 'student',
                'description' => 'Student with learning access',
                'is_system' => true,
                'permissions' => [
                    'content.view', 'progress.track', 'exercises.attempt'
                ]
            ]
        ];

        foreach ($roles as $roleData) {
            $role = Role::firstOrCreate([
                'slug' => $roleData['slug'],
                'tenant_id' => tenant()->id
            ], [
                'name' => $roleData['name'],
                'description' => $roleData['description'],
                'is_system' => $roleData['is_system']
            ]);

            // Attach permissions to the role
            if (isset($roleData['permissions'])) {
                $permissions = Permission::whereIn('slug', $roleData['permissions'])
                    ->where('tenant_id', tenant()->id)
                    ->get();
                
                foreach ($permissions as $permission) {
                    $role->permissions()->syncWithoutDetaching([$permission->id => [
                        'is_denied' => false,
                        'conditions' => null
                    ]]);
                }
            }
        }
    }
}