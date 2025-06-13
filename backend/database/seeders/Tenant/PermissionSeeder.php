<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenants\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // System Management
            [
                'name' => 'Manage System',
                'slug' => 'system.manage',
                'group' => 'system',
                'description' => 'Full system administration access',
                'is_system' => true
            ],
            [
                'name' => 'Manage Tenants',
                'slug' => 'tenants.manage',
                'group' => 'system',
                'description' => 'Manage tenant organizations',
                'is_system' => true
            ],

            // User Management
            [
                'name' => 'Manage Users',
                'slug' => 'users.manage',
                'group' => 'users',
                'description' => 'Create, edit, and delete user accounts',
                'is_system' => true
            ],
            [
                'name' => 'View Users',
                'slug' => 'users.view',
                'group' => 'users',
                'description' => 'View user accounts and profiles',
                'is_system' => false
            ],
            [
                'name' => 'Invite Users',
                'slug' => 'users.invite',
                'group' => 'users',
                'description' => 'Send user invitations',
                'is_system' => false
            ],

            // Content Management
            [
                'name' => 'Manage Content',
                'slug' => 'content.manage',
                'group' => 'content',
                'description' => 'Full content management access',
                'is_system' => true
            ],
            [
                'name' => 'Create Content',
                'slug' => 'content.create',
                'group' => 'content',
                'description' => 'Create new learning content',
                'is_system' => false
            ],
            [
                'name' => 'Edit Content',
                'slug' => 'content.edit',
                'group' => 'content',
                'description' => 'Edit existing learning content',
                'is_system' => false
            ],
            [
                'name' => 'View Content',
                'slug' => 'content.view',
                'group' => 'content',
                'description' => 'View learning content',
                'is_system' => false
            ],
            [
                'name' => 'Delete Content',
                'slug' => 'content.delete',
                'group' => 'content',
                'description' => 'Delete learning content',
                'is_system' => false
            ],
            [
                'name' => 'Approve Content',
                'slug' => 'content.approve',
                'group' => 'content',
                'description' => 'Approve content for publication',
                'is_system' => false
            ],

            // Word Management
            [
                'name' => 'Manage Words',
                'slug' => 'words.manage',
                'group' => 'words',
                'description' => 'Full word management access',
                'is_system' => true
            ],
            [
                'name' => 'Create Words',
                'slug' => 'words.create',
                'group' => 'words',
                'description' => 'Create new vocabulary words',
                'is_system' => false
            ],
            [
                'name' => 'Edit Words',
                'slug' => 'words.edit',
                'group' => 'words',
                'description' => 'Edit existing vocabulary words',
                'is_system' => false
            ],
            [
                'name' => 'Delete Words',
                'slug' => 'words.delete',
                'group' => 'words',
                'description' => 'Delete vocabulary words',
                'is_system' => false
            ],

            // Lesson Management
            [
                'name' => 'Manage Lessons',
                'slug' => 'lessons.manage',
                'group' => 'lessons',
                'description' => 'Full lesson management access',
                'is_system' => true
            ],
            [
                'name' => 'Create Lessons',
                'slug' => 'lessons.create',
                'group' => 'lessons',
                'description' => 'Create new lessons',
                'is_system' => false
            ],
            [
                'name' => 'Edit Lessons',
                'slug' => 'lessons.edit',
                'group' => 'lessons',
                'description' => 'Edit existing lessons',
                'is_system' => false
            ],
            [
                'name' => 'Delete Lessons',
                'slug' => 'lessons.delete',
                'group' => 'lessons',
                'description' => 'Delete lessons',
                'is_system' => false
            ],

            // Exercise Management
            [
                'name' => 'Create Exercises',
                'slug' => 'exercises.create',
                'group' => 'exercises',
                'description' => 'Create new exercises',
                'is_system' => false
            ],
            [
                'name' => 'Edit Exercises',
                'slug' => 'exercises.edit',
                'group' => 'exercises',
                'description' => 'Edit existing exercises',
                'is_system' => false
            ],
            [
                'name' => 'Delete Exercises',
                'slug' => 'exercises.delete',
                'group' => 'exercises',
                'description' => 'Delete exercises',
                'is_system' => false
            ],
            [
                'name' => 'Attempt Exercises',
                'slug' => 'exercises.attempt',
                'group' => 'exercises',
                'description' => 'Attempt and complete exercises',
                'is_system' => false
            ],

            // Settings Management
            [
                'name' => 'Manage Settings',
                'slug' => 'settings.manage',
                'group' => 'settings',
                'description' => 'Manage system and tenant settings',
                'is_system' => true
            ],
            [
                'name' => 'View Settings',
                'slug' => 'settings.view',
                'group' => 'settings',
                'description' => 'View system and tenant settings',
                'is_system' => false
            ],

            // Analytics and Reports
            [
                'name' => 'View Analytics',
                'slug' => 'analytics.view',
                'group' => 'analytics',
                'description' => 'View analytics and statistics',
                'is_system' => false
            ],
            [
                'name' => 'View Reports',
                'slug' => 'reports.view',
                'group' => 'analytics',
                'description' => 'View detailed reports',
                'is_system' => false
            ],
            [
                'name' => 'Export Reports',
                'slug' => 'reports.export',
                'group' => 'analytics',
                'description' => 'Export reports and data',
                'is_system' => false
            ],

            // Progress and Learning
            [
                'name' => 'Track Progress',
                'slug' => 'progress.track',
                'group' => 'learning',
                'description' => 'Track learning progress',
                'is_system' => false
            ],
            [
                'name' => 'View Progress',
                'slug' => 'progress.view',
                'group' => 'learning',
                'description' => 'View learning progress',
                'is_system' => false
            ],

            // Profile Management
            [
                'name' => 'Edit Profile',
                'slug' => 'profile.edit',
                'group' => 'profile',
                'description' => 'Edit own user profile',
                'is_system' => false
            ],

            // Legacy Membership Permissions (for backward compatibility)
            [
                'name' => 'Admin Access',
                'slug' => 'admin',
                'group' => 'membership',
                'description' => 'Admin level access (legacy)',
                'is_system' => true
            ],
            [
                'name' => 'Tenant Admin Access',
                'slug' => 'tenant-admin',
                'group' => 'membership',
                'description' => 'Tenant admin level access (legacy)',
                'is_system' => true
            ],
            [
                'name' => 'Team Access',
                'slug' => 'team',
                'group' => 'membership',
                'description' => 'Team level access (legacy)',
                'is_system' => true
            ]
        ];

        foreach ($permissions as $permissionData) {
            Permission::createOrUpdate(
                $permissionData['name'],
                $permissionData['slug'],
                [
                    'group' => $permissionData['group'],
                    'description' => $permissionData['description'],
                    'is_system' => $permissionData['is_system']
                ]
            );
        }
    }
}