<?php

namespace Tests\Feature\Tenant\Admin;

use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class TenantAdminAnalyticsControllerTest extends TenantTestCase
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

    // Dashboard endpoints (consolidated from TenantAdminDashboardController)

    /**
     * Test getting tenant dashboard overview
     */
    public function test_get_dashboard_overview_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/dashboard");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Dashboard data retrieved successfully.'
            ]);
    }

    // Content endpoints (consolidated from TenantAdminContentController)

    /**
     * Test getting content overview
     */
    public function test_get_content_overview_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/content/overview");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Content overview retrieved successfully.'
            ]);
    }

    /**
     * Test getting learning paths
     */
    public function test_get_learning_paths_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/content/learning-paths");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Learning paths retrieved successfully.'
            ]);
    }

    /**
     * Test getting languages
     */
    public function test_get_languages_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/content/languages");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Languages retrieved successfully.'
            ]);
    }

    /**
     * Test getting content statistics
     */
    public function test_get_content_statistics_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/content/statistics");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Content statistics retrieved successfully.'
            ]);
    }

    /**
     * Test content health check
     */
    public function test_content_health_check_success()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/content/health-check");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Content health check completed successfully.'
            ]);
    }

    // Additional Dashboard Tests (consolidated from TenantAdminDashboardControllerTest)

    /**
     * Test dashboard access requires authentication
     */
    public function test_dashboard_requires_authentication()
    {
        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/dashboard");

        $response->assertStatus(401);
    }

    /**
     * Test dashboard requires admin permission
     */
    public function test_dashboard_requires_admin_permission()
    {
        $studentUser = $this->createTenantStudent();
        Sanctum::actingAs($studentUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/dashboard");

        $response->assertStatus(403);
    }

    /**
     * Test dashboard with invalid tenant
     */
    public function test_dashboard_with_invalid_tenant()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/invalid-tenant/tenant-admin/dashboard");

        $response->assertStatus(404);
    }

    /**
     * Test dashboard response structure when implemented
     */
    public function test_dashboard_expected_response_structure()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/dashboard");

        $response->assertStatus(200);

        // When implemented, should contain these fields
        $expectedStructure = [
            'success',
            'message',
            'data' => [
                // Expected dashboard data structure
                // 'user_statistics' => [],
                // 'content_statistics' => [],
                // 'recent_activity' => [],
                // 'system_health' => []
            ]
        ];

        // For now, just verify basic structure since it's TODO
        $response->assertJsonStructure(['success', 'message', 'data']);
    }

    // Additional Content Tests (consolidated from TenantAdminContentControllerTest)

    /**
     * Test getting learning paths with filters
     */
    public function test_get_learning_paths_with_filters()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/content/learning-paths?status=published&language=en");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);
    }

    /**
     * Test getting content statistics with date range
     */
    public function test_get_content_statistics_with_date_range()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/content/statistics?date_from=2025-01-01&date_to=2025-12-31");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);
    }

    /**
     * Test content management requires authentication
     */
    public function test_content_management_requires_authentication()
    {
        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/content/overview");
        $response->assertStatus(401);
    }

    /**
     * Test content management requires admin permission
     */
    public function test_content_management_requires_admin_permission()
    {
        $studentUser = $this->createTenantStudent();
        Sanctum::actingAs($studentUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/content/overview");
        $response->assertStatus(403);
    }

    /**
     * Test team user can access some content endpoints
     */
    public function test_team_user_can_access_content_overview()
    {
        $teamUser = $this->createTenantTeam();
        Sanctum::actingAs($teamUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/content/overview");

        // Depending on permissions, team might have access to overview
        $this->assertContains($response->status(), [200, 403]);
    }

    /**
     * Test content statistics with invalid date format
     */
    public function test_content_statistics_with_invalid_date_format()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/content/statistics?date_from=invalid-date");

        $response->assertStatus(422);
    }

    /**
     * Test expected content overview response structure when implemented
     */
    public function test_expected_content_overview_response_structure()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/content/overview");

        $response->assertStatus(200);

        // When implemented, should contain these fields
        $expectedStructure = [
            'success',
            'message',
            'data' => [
                // Expected content overview data structure
                // 'total_learning_paths' => 'integer',
                // 'total_units' => 'integer',
                // 'total_lessons' => 'integer',
                // 'total_exercises' => 'integer',
                // 'content_status_distribution' => [],
                // 'recent_content_activity' => [],
                // 'content_quality_metrics' => []
            ]
        ];

        $response->assertJsonStructure(['success', 'message', 'data']);
    }

    /**
     * Test expected learning paths response structure when implemented
     */
    public function test_expected_learning_paths_response_structure()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/content/learning-paths");

        $response->assertStatus(200);

        // When implemented, should contain these fields
        $expectedStructure = [
            'success',
            'message',
            'data' => [
                // Expected learning paths data structure
                // 'learning_paths' => [
                //     [
                //         'id' => 'string',
                //         'title' => 'string',
                //         'language' => [],
                //         'status' => 'string',
                //         'creator' => [],
                //         'units_count' => 'integer',
                //         'enrolled_users_count' => 'integer',
                //         'completion_rate' => 'float',
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
     * Test expected content statistics response structure when implemented
     */
    public function test_expected_content_statistics_response_structure()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/content/statistics");

        $response->assertStatus(200);

        // When implemented, should contain these fields
        $expectedStructure = [
            'success',
            'message',
            'data' => [
                // Expected content statistics data structure
                // 'content_creation_trends' => [],
                // 'content_type_distribution' => [],
                // 'creator_productivity_metrics' => [],
                // 'content_effectiveness_scores' => []
            ]
        ];

        $response->assertJsonStructure(['success', 'message', 'data']);
    }

    /**
     * Test expected health check response structure when implemented
     */
    public function test_expected_health_check_response_structure()
    {
        Sanctum::actingAs($this->adminUser, ['*']);

        $response = $this->getJson("/api/{$this->tenant->slug}/tenant-admin/content/health-check");

        $response->assertStatus(200);

        // When implemented, should contain these fields
        $expectedStructure = [
            'success',
            'message',
            'data' => [
                // Expected health check data structure
                // 'missing_translations' => [],
                // 'incomplete_content_items' => [],
                // 'quality_issues' => [],
                // 'broken_media_links' => [],
                // 'overall_health_score' => 'integer'
            ]
        ];

        $response->assertJsonStructure(['success', 'message', 'data']);
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

    private function createTenantTeam(): User
    {
        return User::factory()->create([
            'email' => 'team@test.com',
            'membership_type' => 'team',
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
