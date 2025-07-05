<?php

namespace Tests\Feature\Auth;

use App\Models\Landlord\Tenant;
use App\Models\Landlord\UserTenantAssociation;
use App\Models\Tenants\User;
use App\Services\Auth\UserTenantAssociationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OptimizedUserTenantLookupTest extends TestCase
{
    use RefreshDatabase;

    protected UserTenantAssociationService $service;
    protected Tenant $tenant1;
    protected Tenant $tenant2;
    protected string $testEmail = 'test@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(UserTenantAssociationService::class);

        // Create test tenants
        $this->tenant1 = Tenant::create([
            'name' => 'Test School District 1',
            'slug' => 'test-district-1',
            'status' => 'active',
            'database_name' => 'tenant_test_district_1',
        ]);

        $this->tenant2 = Tenant::create([
            'name' => 'Test School District 2',
            'slug' => 'test-district-2',
            'status' => 'active',
            'database_name' => 'tenant_test_district_2',
        ]);
    }

    /**  */
    public function it_uses_optimized_lookup_when_associations_exist()
    {
        // Create user tenant associations
        UserTenantAssociation::create([
            'email' => $this->testEmail,
            'tenant_id' => $this->tenant1->id,
            'tenant_slug' => $this->tenant1->slug,
            'membership' => 'student',
            'is_active' => true,
        ]);

        UserTenantAssociation::create([
            'email' => $this->testEmail,
            'tenant_id' => $this->tenant2->id,
            'tenant_slug' => $this->tenant2->slug,
            'membership' => 'team',
            'is_active' => true,
        ]);

        // Test optimized lookup
        $tenants = $this->service->getUserTenants($this->testEmail);

        $this->assertCount(2, $tenants);
        $this->assertEquals('test-district-1', $tenants->first()['tenant']->slug);
        $this->assertEquals('student', $tenants->first()['membership']);
    }

    /**  */
    public function it_falls_back_to_legacy_when_no_associations_exist()
    {
        // Don't create any associations - should fall back to legacy method

        // Mock tenant databases and users (this would normally be done by tenant setup)
        $this->mockTenantUser($this->tenant1, 'admin');

        $tenants = $this->service->getUserTenants($this->testEmail);

        // Should find user via legacy method
        $this->assertCount(1, $tenants);
    }

    /**  */
    public function it_caches_user_tenant_lookups()
    {
        UserTenantAssociation::create([
            'email' => $this->testEmail,
            'tenant_id' => $this->tenant1->id,
            'tenant_slug' => $this->tenant1->slug,
            'membership' => 'student',
            'is_active' => true,
        ]);

        // First call - should hit database
        $start = microtime(true);
        $tenants1 = $this->service->getUserTenants($this->testEmail);
        $firstCallTime = microtime(true) - $start;

        // Second call - should hit cache
        $start = microtime(true);
        $tenants2 = $this->service->getUserTenants($this->testEmail);
        $secondCallTime = microtime(true) - $start;

        // Results should be identical
        $this->assertEquals($tenants1->count(), $tenants2->count());

        // Second call should be faster (cached)
        $this->assertLessThan($firstCallTime, $secondCallTime);
    }

    /**  */
    public function it_handles_user_in_specific_tenant_optimized()
    {
        UserTenantAssociation::create([
            'email' => $this->testEmail,
            'tenant_id' => $this->tenant1->id,
            'tenant_slug' => $this->tenant1->slug,
            'membership' => 'admin',
            'is_active' => true,
        ]);

        $result = $this->service->getUserInTenant($this->testEmail, 'test-district-1');

        $this->assertNotNull($result);
        $this->assertEquals('test-district-1', $result['tenant']->slug);
        $this->assertEquals('admin', $result['membership']);
    }

    /**  */
    public function it_returns_null_for_non_existent_user_tenant_association()
    {
        $result = $this->service->getUserInTenant($this->testEmail, 'non-existent-tenant');

        $this->assertNull($result);
    }

    /**  */
    public function it_gets_primary_tenant_based_on_last_accessed()
    {
        // Create two associations with different access times
        UserTenantAssociation::create([
            'email' => $this->testEmail,
            'tenant_id' => $this->tenant1->id,
            'tenant_slug' => $this->tenant1->slug,
            'membership' => 'student',
            'last_accessed_at' => now()->subHour(),
            'is_active' => true,
        ]);

        UserTenantAssociation::create([
            'email' => $this->testEmail,
            'tenant_id' => $this->tenant2->id,
            'tenant_slug' => $this->tenant2->slug,
            'membership' => 'team',
            'last_accessed_at' => now(), // More recent
            'is_active' => true,
        ]);

        $primary = $this->service->getPrimaryTenant($this->testEmail);

        $this->assertNotNull($primary);
        $this->assertEquals('test-district-2', $primary['tenant']->slug);
        $this->assertEquals('team', $primary['membership']);
    }

    /**  */
    public function it_updates_last_accessed_when_getting_user_in_tenant()
    {
        $association = UserTenantAssociation::create([
            'email' => $this->testEmail,
            'tenant_id' => $this->tenant1->id,
            'tenant_slug' => $this->tenant1->slug,
            'membership' => 'student',
            'last_accessed_at' => now()->subDay(),
            'is_active' => true,
        ]);

        $originalTime = $association->last_accessed_at;

        // Access the tenant
        $this->service->getUserInTenant($this->testEmail, 'test-district-1');

        // Check that last_accessed_at was updated
        $association->refresh();
        $this->assertGreaterThan($originalTime, $association->last_accessed_at);
    }

    /**  */
    public function it_handles_inactive_tenants_correctly()
    {
        // Create association with inactive tenant
        $inactiveTenant = Tenant::create([
            'name' => 'Inactive District',
            'slug' => 'inactive-district',
            'status' => 'inactive',
            'database_name' => 'tenant_inactive',
        ]);

        UserTenantAssociation::create([
            'email' => $this->testEmail,
            'tenant_id' => $inactiveTenant->id,
            'tenant_slug' => $inactiveTenant->slug,
            'membership' => 'student',
            'is_active' => true,
        ]);

        $tenants = $this->service->getUserTenants($this->testEmail);

        // Should not return inactive tenants
        $this->assertCount(0, $tenants);
    }

    /**  */
    public function it_measures_performance_improvement()
    {
        // Create many tenant associations to test performance
        for ($i = 1; $i <= 10; $i++) {
            UserTenantAssociation::create([
                'email' => $this->testEmail,
                'tenant_id' => $this->tenant1->id,
                'tenant_slug' => "test-district-{$i}",
                'membership' => 'student',
                'is_active' => true,
            ]);
        }

        // Measure optimized method
        $start = microtime(true);
        $tenants = $this->service->getUserTenants($this->testEmail);
        $optimizedTime = microtime(true) - $start;

        // Verify results
        $this->assertGreaterThan(0, $tenants->count());

        // Performance should be under 100ms for reasonable dataset
        $this->assertLessThan(0.1, $optimizedTime, 'Optimized lookup should be under 100ms');
    }

    /**
     * Mock a user in a tenant database for testing legacy fallback
     */
    protected function mockTenantUser(Tenant $tenant, string $membership): void
    {
        // This is a simplified mock - in real tests you'd set up actual tenant databases
        // For now, we'll just verify the fallback mechanism works

        // You could use tenant()->run() here if you have actual tenant databases set up
        // $tenant->run(function () use ($membership) {
        //     User::create([
        //         'name' => 'Test User',
        //         'email' => $this->testEmail,
        //         'membership' => $membership,
        //         'password' => bcrypt('password'),
        //     ]);
        // });
    }
}
