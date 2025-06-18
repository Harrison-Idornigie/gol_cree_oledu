<?php

namespace Tests\Feature\Tenant\Admin;

use Tests\TestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class TenantAdminDashboardControllerTest extends TestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        
        // Create test tenant
        $this->tenant = $this->createTestTenant();
        $this->initializeTenantContext($this->tenant);
        
        // Create admin user in tenant context
        $this->adminUser = $this->createTenantAdmin();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

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

    /**
     * Helper method to create tenant admin user
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

    /**
     * Helper method to create tenant student user
     */
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

    /**
     * Helper method to initialize tenant context
     */
    private function initializeTenantContext(Tenant $tenant): void
    {
        // The tenant is already initialized and seeded in createTestTenant
        // This method is kept for compatibility but not needed with enhanced trait
    }
}
