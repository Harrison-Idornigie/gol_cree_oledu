<?php

namespace Tests\Feature\Tenant\Admin;

use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class TenantAdminRoleControllerTest extends TenantTestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        $this->tenant = $this->createTestTenant();
        $this->initializeTenantContext($this->tenant);
        $this->adminUser = $this->createTenantAdmin();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /**
     * Test getting all roles
     */
    public function test_get_roles_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/roles");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Roles retrieved successfully.'
            ]);
    }

    /**
     * Test creating a new role
     */
    public function test_create_role_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $roleData = [
            'name' => 'test-role',
            'display_name' => 'Test Role',
            'description' => 'A test role for testing purposes'
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/tenant-admin/roles", $roleData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Role created successfully.'
            ]);
    }

    /**
     * Test creating role with invalid data
     */
    public function test_create_role_with_invalid_data()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->postJson("/api/{$this->tenant->slug}/tenant-admin/roles", [
            // Missing required fields
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test getting specific role
     */
    public function test_get_role_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $roleId = 'test-role-id';
        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/roles/{$roleId}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Role retrieved successfully.'
            ]);
    }

    /**
     * Test updating role
     */
    public function test_update_role_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $roleId = 'test-role-id';
        $updateData = [
            'display_name' => 'Updated Test Role',
            'description' => 'Updated description'
        ];

        $response = $this->putJson("/api/{$this->tenant->slug}/tenant-admin/roles/{$roleId}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Role updated successfully.'
            ]);
    }

    /**
     * Test deleting role
     */
    public function test_delete_role_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $roleId = 'test-role-id';
        $response = $this->deleteJson("/api/{$this->tenant->slug}/tenant-admin/roles/{$roleId}");

        $response->assertStatus(204);
    }

    /**
     * Test updating role permissions
     */
    public function test_update_role_permissions_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $roleId = 'test-role-id';
        $permissionsData = [
            'permissions' => ['create-content', 'edit-content', 'view-analytics']
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/tenant-admin/roles/{$roleId}/permissions", $permissionsData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Role permissions updated successfully.'
            ]);
    }

    /**
     * Test assigning users to role
     */
    public function test_assign_users_to_role_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $roleId = 'test-role-id';
        $usersData = [
            'user_ids' => [1, 2, 3]
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/tenant-admin/roles/{$roleId}/users", $usersData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Users assigned to role successfully.'
            ]);
    }

    /**
     * Test removing users from role
     */
    public function test_remove_users_from_role_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $roleId = 'test-role-id';
        $usersData = [
            'user_ids' => [1, 2]
        ];

        $response = $this->deleteJson("/api/{$this->tenant->slug}/tenant-admin/roles/{$roleId}/users", $usersData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Users removed from role successfully.'
            ]);
    }

    /**
     * Test role management requires authentication
     */
    public function test_role_management_requires_authentication()
    {
        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/roles");
        $response->assertStatus(401);
    }

    /**
     * Test role management requires admin permission
     */
    public function test_role_management_requires_admin_permission()
    {
        $studentUser = $this->createTenantStudent();
        Sanctum::actingAs($studentUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/roles");
        $response->assertStatus(403);
    }

    /**
     * Test get nonexistent role
     */
    public function test_get_nonexistent_role()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/roles/nonexistent");
        $response->assertStatus(404);
    }

    /**
     * Test delete nonexistent role
     */
    public function test_delete_nonexistent_role()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->deleteJson("/api/{$this->tenant->slug}/tenant-admin/roles/nonexistent");
        $response->assertStatus(404);
    }

    /**
     * Test expected role response structure when implemented
     */
    public function test_expected_role_response_structure()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/roles");

        $response->assertStatus(200);

        // When implemented, should contain these fields
        $expectedStructure = [
            'success',
            'message',
            'data' => [
                // Expected role data structure
                // 'roles' => [
                //     [
                //         'id' => 'string',
                //         'name' => 'string',
                //         'display_name' => 'string',
                //         'description' => 'string',
                //         'permissions' => [],
                //         'users_count' => 'integer'
                //     ]
                // ]
            ]
        ];

        $response->assertJsonStructure(['success', 'message', 'data']);
    }

    /**
     * Helper methods
     */
    protected function createTenantAdmin(array $attributes = []): User
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return User::factory()->create(array_merge([
                'email' => 'admin@test.com',
                'membership' => 'admin',
                'email_verified_at' => now(),
            ], $attributes));
        });
    }

    protected function createTenantStudent(array $attributes = []): User
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return User::factory()->create(array_merge([
                'email' => 'student@test.com',
                'membership' => 'student',
                'email_verified_at' => now(),
            ], $attributes));
        });
    }

    protected function initializeTenantContext(Tenant $tenant): void
    {
        // Already handled by createTestTenant with enhanced seeding
    }
}
