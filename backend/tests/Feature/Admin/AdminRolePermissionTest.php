<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\Permission;
use App\Models\Tenants\User;

class AdminRolePermissionTest extends AdminTestCase
{
    public function test_admin_can_list_roles()
    {
        Role::create(['name' => 'team']);
        Role::create(['name' => 'moderator']);

        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/roles');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    [
                        'name' => 'admin'
                    ],
                    [
                        'name' => 'team'
                    ],
                    [
                        'name' => 'moderator'
                    ]
                ]
            ]);
    }

    public function test_admin_can_create_role()
    {
        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/roles', [
                'name' => 'content_creator',
                'description' => 'Can create and edit content',
                'permissions' => [
                    'create_content',
                    'edit_content'
                ]
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'content_creator',
                    'description' => 'Can create and edit content'
                ]
            ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'content_creator'
        ]);

        // Check if permissions were created and assigned
        $role = Role::where('name', 'content_creator')->first();
        $this->assertTrue($role->permissions->contains('name', 'create_content'));
        $this->assertTrue($role->permissions->contains('name', 'edit_content'));
    }

    public function test_admin_can_update_role()
    {
        $role = Role::create([
            'name' => 'editor',
            'description' => 'Content editor'
        ]);

        $response = $this->actingAsAdmin()
            ->putJson("/api/admin/roles/{$role->id}", [
                'name' => 'senior_editor',
                'description' => 'Senior content editor',
                'permissions' => ['edit_content', 'delete_content']
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'senior_editor',
                    'description' => 'Senior content editor'
                ]
            ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'senior_editor',
            'description' => 'Senior content editor'
        ]);
    }

    public function test_admin_cannot_delete_admin_role()
    {
        $adminRole = Role::where('name', 'admin')->first();

        $response = $this->actingAsAdmin()
            ->deleteJson("/api/admin/roles/{$adminRole->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('roles', ['name' => 'admin']);
    }

    public function test_admin_can_update_role_permissions()
    {
        $role = Role::create([
            'name' => 'reviewer',
            'description' => 'Content reviewer'
        ]);

        Permission::create(['name' => 'review_content']);
        Permission::create(['name' => 'approve_content']);

        $response = $this->actingAsAdmin()
            ->postJson("/api/admin/roles/{$role->id}/permissions", [
                'permissions' => ['review_content', 'approve_content']
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'reviewer',
                    'permissions' => [
                        ['name' => 'review_content'],
                        ['name' => 'approve_content']
                    ]
                ]
            ]);

        $this->assertTrue($role->fresh()->permissions->contains('name', 'review_content'));
        $this->assertTrue($role->fresh()->permissions->contains('name', 'approve_content'));
    }

    public function test_admin_can_sync_role_permissions()
    {
        $role = Role::create(['name' => 'manager']);

        // Create initial permissions
        Permission::create(['name' => 'view_reports']);
        Permission::create(['name' => 'edit_settings']);
        $role->permissions()->attach(Permission::where('name', 'view_reports')->first());

        // Sync new permissions
        $response = $this->actingAsAdmin()
            ->postJson("/api/admin/roles/{$role->id}/permissions", [
                'permissions' => ['edit_settings'],
                'sync' => true
            ]);

        $response->assertOk();

        $updatedRole = $role->fresh();
        $this->assertFalse($updatedRole->permissions->contains('name', 'view_reports'));
        $this->assertTrue($updatedRole->permissions->contains('name', 'edit_settings'));
    }

    public function test_cannot_create_duplicate_role()
    {
        Role::create(['name' => 'moderator']);

        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/roles', [
                'name' => 'moderator',
                'description' => 'Content moderator'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_admin_can_view_role_details()
    {
        $role = Role::create([
            'name' => 'contributor',
            'description' => 'Content contributor'
        ]);

        Permission::create(['name' => 'create_content']);
        $role->permissions()->attach(Permission::where('name', 'create_content')->first());

        $response = $this->actingAsAdmin()
            ->getJson("/api/admin/roles/{$role->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'contributor',
                    'description' => 'Content contributor',
                    'permissions' => [
                        ['name' => 'create_content']
                    ]
                ]
            ]);
    }

    public function test_role_hierarchy_validation()
    {
        $adminRole = Role::where('name', 'admin')->first();

        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/roles', [
                'name' => 'super_admin',
                'permissions' => $adminRole->permissions->pluck('name')->toArray()
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot create role with higher privileges than admin'
            ]);
    }
}