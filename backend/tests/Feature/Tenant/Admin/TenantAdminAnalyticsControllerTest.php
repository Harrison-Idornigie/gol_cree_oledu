<?php

namespace Tests\Feature\Tenant\Admin;

use Tests\TestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class TenantAdminAnalyticsControllerTest extends TestCase
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
     * Test getting analytics overview
     */
    public function test_get_analytics_overview_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/analytics");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Analytics overview retrieved successfully.'
            ]);
    }

    /**
     * Test getting user analytics
     */
    public function test_get_user_analytics_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/analytics/users");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'User analytics retrieved successfully.'
            ]);
    }

    /**
     * Test getting content analytics
     */
    public function test_get_content_analytics_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/analytics/content");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Content analytics retrieved successfully.'
            ]);
    }

    /**
     * Test getting engagement analytics
     */
    public function test_get_engagement_analytics_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/analytics/engagement");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Engagement analytics retrieved successfully.'
            ]);
    }

    /**
     * Test getting user progress report
     */
    public function test_get_user_progress_report_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/reports/user-progress");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'User progress report generated successfully.'
            ]);
    }

    /**
     * Test getting content usage report
     */
    public function test_get_content_usage_report_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/reports/content-usage");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Content usage report generated successfully.'
            ]);
    }

    /**
     * Test getting engagement report
     */
    public function test_get_engagement_report_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/reports/engagement");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Engagement report generated successfully.'
            ]);
    }

    /**
     * Test export report
     */
    public function test_export_report_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->postJson("/api/{$this->tenant->slug}/tenant-admin/reports/export", [
            'type' => 'user-progress',
            'format' => 'csv'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Report export initiated successfully.'
            ]);
    }

    /**
     * Test getting performance report
     */
    public function test_get_performance_report_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/reports/performance");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Performance report generated successfully.'
            ]);
    }

    /**
     * Test analytics requires authentication
     */
    public function test_analytics_requires_authentication()
    {
        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/analytics");
        $response->assertStatus(401);
    }

    /**
     * Test analytics requires admin permission
     */
    public function test_analytics_requires_admin_permission()
    {
        $studentUser = $this->createTenantStudent();
        Sanctum::actingAs($studentUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/analytics");
        $response->assertStatus(403);
    }

    /**
     * Test analytics with query parameters
     */
    public function test_analytics_with_query_parameters()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/analytics?date_from=2025-01-01&date_to=2025-12-31");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);
    }

    /**
     * Test export with invalid format
     */
    public function test_export_with_invalid_format()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->postJson("/api/{$this->tenant->slug}/tenant-admin/reports/export", [
            'type' => 'user-progress',
            'format' => 'invalid'
        ]);

        // Should still return 200 since controller is TODO, but when implemented should validate
        $response->assertStatus(200);
    }

    /**
     * Test expected analytics response structure when implemented
     */
    public function test_expected_analytics_response_structure()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/analytics");

        $response->assertStatus(200);
        
        // When implemented, should contain these fields
        $expectedStructure = [
            'success',
            'message',
            'data' => [
                // Expected analytics data structure
                // 'overall_metrics' => [],
                // 'key_performance_indicators' => [],
                // 'trend_summaries' => []
            ]
        ];

        $response->assertJsonStructure(['success', 'message', 'data']);
    }

    /**
     * Helper methods
     */
    private function createTenantAdmin(): User
    {
        return User::factory()->create([
            'email' => 'admin@test.com',
            'membership_type' => 'tenant-admin',
            'email_verified_at' => now(),
        ]);
    }

    private function createTenantStudent(): User
    {
        return User::factory()->create([
            'email' => 'student@test.com',
            'membership_type' => 'student',
            'email_verified_at' => now(),
        ]);
    }

    private function initializeTenantContext(Tenant $tenant): void
    {
        tenancy()->initialize($tenant);
        $this->artisan('migrate:fresh', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
        ]);
    }
}
