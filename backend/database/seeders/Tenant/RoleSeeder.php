<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenants\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Super Administrator',
                'slug' => 'super-admin',
                'description' => 'Full system access with all permissions',
                'is_system' => true,
                'metadata' => [
                    'color' => '#dc2626',
                    'priority' => 1,
                    'inherited_from' => 'membership'
                ]
            ],
            [
                'name' => 'Administrator',
                'slug' => 'admin',
                'description' => 'Administrative access with management permissions',
                'is_system' => true,
                'metadata' => [
                    'color' => '#ea580c',
                    'priority' => 2,
                    'inherited_from' => 'membership'
                ]
            ],
            [
                'name' => 'Tenant Administrator',
                'slug' => 'tenant-admin',
                'description' => 'Tenant-level administrative access',
                'is_system' => true,
                'metadata' => [
                    'color' => '#d97706',
                    'priority' => 3,
                    'inherited_from' => 'membership'
                ]
            ],
            [
                'name' => 'Team Member',
                'slug' => 'team',
                'description' => 'Content creation and management access',
                'is_system' => true,
                'metadata' => [
                    'color' => '#059669',
                    'priority' => 4,
                    'inherited_from' => 'membership'
                ]
            ],
            [
                'name' => 'Student',
                'slug' => 'student',
                'description' => 'Learning and progress tracking access',
                'is_system' => true,
                'metadata' => [
                    'color' => '#2563eb',
                    'priority' => 5,
                    'inherited_from' => 'membership'
                ]
            ],
            [
                'name' => 'Content Creator',
                'slug' => 'content-creator',
                'description' => 'Create and edit learning content',
                'is_system' => false,
                'metadata' => [
                    'color' => '#7c3aed',
                    'priority' => 10
                ]
            ],
            [
                'name' => 'Content Reviewer',
                'slug' => 'content-reviewer',
                'description' => 'Review and approve content changes',
                'is_system' => false,
                'metadata' => [
                    'color' => '#be185d',
                    'priority' => 11
                ]
            ],
            [
                'name' => 'Analytics Viewer',
                'slug' => 'analytics-viewer',
                'description' => 'View analytics and reports',
                'is_system' => false,
                'metadata' => [
                    'color' => '#0891b2',
                    'priority' => 12
                ]
            ],
            [
                'name' => 'User Manager',
                'slug' => 'user-manager',
                'description' => 'Manage user accounts and permissions',
                'is_system' => false,
                'metadata' => [
                    'color' => '#65a30d',
                    'priority' => 13
                ]
            ]
        ];

        foreach ($roles as $roleData) {
            Role::createOrUpdate($roleData['name'], $roleData['slug'], [
                'description' => $roleData['description'],
                'is_system' => $roleData['is_system'],
                'metadata' => $roleData['metadata']
            ]);
        }
    }
}