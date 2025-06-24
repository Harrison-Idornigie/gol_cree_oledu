<?php

namespace Tests\Feature\Tenant\Admin;

use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class TenantAdminAuditControllerTest extends TenantTestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        $this->tenant = $this->createTestTenant();
        $this->adminUser = $this->createTenantAdmin();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /**
     * Test getting audit logs
     */
    public function test_get_audit_logs_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/audit-logs");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Audit logs retrieved successfully.'
            ]);
    }

    /**
     * Test getting audit logs with pagination
     */
    public function test_get_audit_logs_with_pagination()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/audit-logs?page=1&per_page=10");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);
    }

    /**
     * Test getting audit logs with date filter
     */
    public function test_get_audit_logs_with_date_filter()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/audit-logs?date_from=2025-01-01&date_to=2025-12-31");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);
    }

    /**
     * Test getting audit logs with user filter
     */
    public function test_get_audit_logs_with_user_filter()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/audit-logs?user_id=1");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);
    }

    /**
     * Test getting audit logs with action filter
     */
    public function test_get_audit_logs_with_action_filter()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/audit-logs?action=created");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);
    }

    /**
     * Test exporting audit logs
     */
    public function test_export_audit_logs_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/audit-logs/export");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Audit logs export initiated successfully.'
            ]);
    }

    /**
     * Test exporting audit logs with format
     */
    public function test_export_audit_logs_with_format()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/audit-logs/export?format=csv");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);
    }

    /**
     * Test getting specific audit log
     */
    public function test_get_specific_audit_log_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $logId = 'test-log-id';
        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/audit-logs/{$logId}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Audit log retrieved successfully.'
            ]);
    }

    /**
     * Test getting audit logs summary
     */
    public function test_get_audit_logs_summary_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/audit-logs/summary");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Audit summary retrieved successfully.'
            ]);
    }

    /**
     * Test audit logs require authentication
     */
    public function test_audit_logs_require_authentication()
    {
        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/audit-logs");
        $response->assertStatus(401);
    }

    /**
     * Test audit logs require admin permission
     */
    public function test_audit_logs_require_admin_permission()
    {
        $studentUser = $this->createTenantStudent();
        Sanctum::actingAs($studentUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/audit-logs");
        $response->assertStatus(403);
    }

    /**
     * Test team user cannot access audit logs
     */
    public function test_team_user_cannot_access_audit_logs()
    {
        $teamUser = $this->createTenantTeam();
        Sanctum::actingAs($teamUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/audit-logs");
        $response->assertStatus(403);
    }

    /**
     * Test get nonexistent audit log
     */
    public function test_get_nonexistent_audit_log()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/audit-logs/nonexistent");
        $response->assertStatus(404);
    }

    /**
     * Test audit logs with invalid date format
     */
    public function test_audit_logs_with_invalid_date_format()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/audit-logs?date_from=invalid-date");

        $response->assertStatus(422);
    }

    /**
     * Test expected audit log response structure when implemented
     */
    public function test_expected_audit_log_response_structure()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/audit-logs");

        $response->assertStatus(200);

        // When implemented, should contain these fields
        $expectedStructure = [
            'success',
            'message',
            'data' => [
                // Expected audit log data structure
                // 'logs' => [
                //     [
                //         'id' => 'string',
                //         'user_id' => 'integer',
                //         'user_name' => 'string',
                //         'action' => 'string',
                //         'resource_type' => 'string',
                //         'resource_id' => 'string',
                //         'changes' => [],
                //         'ip_address' => 'string',
                //         'user_agent' => 'string',
                //         'created_at' => 'string'
                //     ]
                // ],
                // 'pagination' => [],
                // 'filters' => []
            ]
        ];

        $response->assertJsonStructure(['success', 'message', 'data']);
    }

    /**
     * Test expected audit summary response structure when implemented
     */
    public function test_expected_audit_summary_response_structure()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/audit-logs/summary");

        $response->assertStatus(200);

        // When implemented, should contain these fields
        $expectedStructure = [
            'success',
            'message',
            'data' => [
                // Expected audit summary data structure
                // 'activity_summary' => [],
                // 'most_active_users' => [],
                // 'recent_significant_changes' => [],
                // 'security_events' => []
            ]
        ];

        $response->assertJsonStructure(['success', 'message', 'data']);
    }

    /**
     * Helper methods
     */
    private function createTenantAdmin(): User
    {
        return $this->runInTenantContext($this->tenant, function () {
            return User::factory()->create([
                'email' => 'admin@test.com',
                'membership_type' => 'tenant-admin',
                'email_verified_at' => now(),
            ]);
        });
    }

    private function createTenantStudent(): User
    {
        return $this->runInTenantContext($this->tenant, function () {
            return User::factory()->create([
                'email' => 'student@test.com',
                'membership_type' => 'student',
                'email_verified_at' => now(),
            ]);
        });
    }

    private function createTenantTeam(): User
    {
        return $this->runInTenantContext($this->tenant, function () {
            return User::factory()->create([
                'email' => 'team@test.com',
                'membership_type' => 'team',
                'email_verified_at' => now(),
            ]);
        });
    }
}
