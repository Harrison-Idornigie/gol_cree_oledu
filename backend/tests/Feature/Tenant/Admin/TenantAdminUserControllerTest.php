<?php

namespace Tests\Feature\Tenant\Admin;

use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class TenantAdminUserControllerTest extends TenantTestCase
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
     * Test getting all users in tenant
     */
    public function test_get_users_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/users");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Users retrieved successfully.'
            ]);
    }

    /**
     * Test getting teams in tenant
     */
    public function test_get_teams_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/users/teams");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Team members retrieved successfully.'
            ]);
    }

    /**
     * Test getting students in tenant
     */
    public function test_get_students_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/users/students");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Students retrieved successfully.'
            ]);
    }

    /**
     * Test sending user invite
     */
    public function test_send_invite_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->postJson("/api/{$this->tenant->slug}/tenant-admin/users/invite", [
            'email' => 'newuser@test.com',
            'membership_type' => 'student',
            'first_name' => 'New',
            'last_name' => 'User'
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Invite sent successfully.'
            ]);
    }

    /**
     * Test send invite with invalid email
     */
    public function test_send_invite_with_invalid_email()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->postJson("/api/{$this->tenant->slug}/tenant-admin/users/invite", [
            'email' => 'invalid-email',
            'membership_type' => 'student',
            'first_name' => 'New',
            'last_name' => 'User'
        ]);

        // Should validate email format when implemented
        $response->assertStatus(422);
    }

    /**
     * Test send invite with missing required fields
     */
    public function test_send_invite_with_missing_fields()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->postJson("/api/{$this->tenant->slug}/tenant-admin/users/invite", [
            'email' => 'newuser@test.com'
            // Missing membership_type, first_name, last_name
        ]);

        // Should validate required fields when implemented
        $response->assertStatus(422);
    }

    /**
     * Test cancel invite
     */
    public function test_cancel_invite_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $inviteId = 'test-invite-id';
        $response = $this->deleteJson("/api/{$this->tenant->slug}/tenant-admin/users/invite/{$inviteId}");

        $response->assertStatus(204);
    }

    /**
     * Test resend invite
     */
    public function test_resend_invite_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $inviteId = 'test-invite-id';
        $response = $this->postJson("/api/{$this->tenant->slug}/tenant-admin/users/invite/{$inviteId}/resend");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Invite resent successfully.'
            ]);
    }

    /**
     * Test update user role
     */
    public function test_update_user_role_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);
        $testUser = $this->createTenantStudent();

        $response = $this->patchJson("/api/{$this->tenant->slug}/tenant-admin/users/{$testUser->id}/role", [
            'membership_type' => 'team'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'User role updated successfully.'
            ]);
    }

    /**
     * Test update user status
     */
    public function test_update_user_status_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);
        $testUser = $this->createTenantStudent();

        $response = $this->patchJson("/api/{$this->tenant->slug}/tenant-admin/users/{$testUser->id}/status", [
            'status' => 'suspended'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'User status updated successfully.'
            ]);
    }

    /**
     * Test get user activity
     */
    public function test_get_user_activity_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);
        $testUser = $this->createTenantStudent();

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/users/{$testUser->id}/activity");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'User activity retrieved successfully.'
            ]);
    }

    /**
     * Test user management requires authentication
     */
    public function test_user_management_requires_authentication()
    {
        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/users");
        $response->assertStatus(401);
    }

    /**
     * Test user management requires admin permission
     */
    public function test_user_management_requires_admin_permission()
    {
        $studentUser = $this->createTenantStudent();
        Sanctum::actingAs($studentUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/users");
        $response->assertStatus(403);
    }

    /**
     * Test get users with pagination
     */
    public function test_get_users_with_pagination()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/users?page=1&per_page=10");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);
    }

    /**
     * Test get users with search filter
     */
    public function test_get_users_with_search()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/users?search=test@example.com");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);
    }

    /**
     * Test get users with membership filter
     */
    public function test_get_users_with_membership_filter()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/users?membership=student");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);
    }

    /**
     * Test update nonexistent user
     */
    public function test_update_nonexistent_user()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->patchJson("/api/{$this->tenant->slug}/tenant-admin/users/999999/role", [
            'membership_type' => 'team'
        ]);

        $response->assertStatus(404);
    }

    /**
     * Test expected user data structure when implemented
     */
    public function test_expected_user_response_structure()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/users");

        $response->assertStatus(200);

        // When implemented, should contain these fields
        $expectedStructure = [
            'success',
            'message',
            'data' => [
                // Expected user data structure
                // 'users' => [],
                // 'pagination' => [],
                // 'filters' => []
            ]
        ];

        $response->assertJsonStructure(['success', 'message', 'data']);
    }

    /**
     * Helper methods
     */
    protected function createTenantAdmin(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'admin@test.com',
            'membership' => 'admin',
            'email_verified_at' => now(),
        ], $attributes));
    }

    protected function createTenantStudent(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'student@test.com',
            'membership' => 'student',
            'email_verified_at' => now(),
        ], $attributes));
    }

    protected function initializeTenantContext(Tenant $tenant): void
    {
        tenancy()->initialize($tenant);
        $this->artisan('migrate:fresh', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
        ]);
    }
}
