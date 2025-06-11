<?php

namespace Tests\Feature\Admin;

use App\Models\Tenants\Permission;
use App\Models\Tenants\Membership;
use App\Models\Tenants\User;

class AdminMembershipPermissionTest extends AdminTestCase
{
    public function test_admin_can_list_memberships()
    {
        Membership::create(['name' => 'team']);
        Membership::create(['name' => 'moderator']);

        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/memberships');

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

    public function test_admin_can_create_membership()
    {
        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/memberships', [
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

        $this->assertDatabaseHas('memberships', [
            'name' => 'content_creator'
        ]);

        // Check if permissions were created and assigned
        $membership = Membership::where('name', 'content_creator')->first();
        $this->assertTrue($membership->permissions->contains('name', 'create_content'));
        $this->assertTrue($membership->permissions->contains('name', 'edit_content'));
    }

    public function test_admin_can_update_membership()
    {
        $membership = Membership::create([
            'name' => 'editor',
            'description' => 'Content editor'
        ]);

        $response = $this->actingAsAdmin()
            ->putJson("/api/admin/memberships/{$membership->id}", [
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

        $this->assertDatabaseHas('memberships', [
            'name' => 'senior_editor',
            'description' => 'Senior content editor'
        ]);
    }

    public function test_admin_cannot_delete_admin_membership()
    {
        $adminMembership = Membership::where('name', 'admin')->first();

        $response = $this->actingAsAdmin()
            ->deleteJson("/api/admin/memberships/{$adminMembership->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('memberships', ['name' => 'admin']);
    }

    public function test_admin_can_update_membership_permissions()
    {
        $membership = Membership::create([
            'name' => 'reviewer',
            'description' => 'Content reviewer'
        ]);

        Permission::create(['name' => 'review_content']);
        Permission::create(['name' => 'approve_content']);

        $response = $this->actingAsAdmin()
            ->postJson("/api/admin/memberships/{$membership->id}/permissions", [
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

        $this->assertTrue($membership->fresh()->permissions->contains('name', 'review_content'));
        $this->assertTrue($membership->fresh()->permissions->contains('name', 'approve_content'));
    }

    public function test_admin_can_sync_membership_permissions()
    {
        $membership = Membership::create(['name' => 'manager']);

        // Create initial permissions
        Permission::create(['name' => 'view_reports']);
        Permission::create(['name' => 'edit_settings']);
        $membership->permissions()->attach(Permission::where('name', 'view_reports')->first());

        // Sync new permissions
        $response = $this->actingAsAdmin()
            ->postJson("/api/admin/memberships/{$membership->id}/permissions", [
                'permissions' => ['edit_settings'],
                'sync' => true
            ]);

        $response->assertOk();

        $updatedMembership = $membership->fresh();
        $this->assertFalse($updatedMembership->permissions->contains('name', 'view_reports'));
        $this->assertTrue($updatedMembership->permissions->contains('name', 'edit_settings'));
    }

    public function test_cannot_create_duplicate_membership()
    {
        Membership::create(['name' => 'moderator']);

        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/memberships', [
                'name' => 'moderator',
                'description' => 'Content moderator'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_admin_can_view_membership_details()
    {
        $membership = Membership::create([
            'name' => 'contributor',
            'description' => 'Content contributor'
        ]);

        Permission::create(['name' => 'create_content']);
        $membership->permissions()->attach(Permission::where('name', 'create_content')->first());

        $response = $this->actingAsAdmin()
            ->getJson("/api/admin/memberships/{$membership->id}");

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

    public function test_membership_hierarchy_validation()
    {
        $adminMembership = Membership::where('name', 'admin')->first();

        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/memberships', [
                'name' => 'super_admin',
                'permissions' => $adminMembership->permissions->pluck('name')->toArray()
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot create membership with higher privileges than admin'
            ]);
    }
}