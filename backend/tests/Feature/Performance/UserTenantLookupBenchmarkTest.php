<?php

namespace Tests\Feature\Performance;

use App\Models\Landlord\Tenant;
use App\Models\Landlord\UserTenantAssociation;
use App\Services\Auth\UserTenantAssociationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Performance Benchmark Test for User Tenant Lookups
 * 
 * This test verifies that the optimized user tenant lookup system
 * provides significant performance improvements over the legacy method.
 */
class UserTenantLookupBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    protected UserTenantAssociationService $service;
    protected array $testTenants = [];
    protected string $testEmail = 'benchmark@example.com';

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = app(UserTenantAssociationService::class);
        
        // Create multiple tenants for realistic testing
        for ($i = 1; $i <= 20; $i++) {
            $this->testTenants[] = Tenant::create([
                'name' => "Test District {$i}",
                'slug' => "test-district-{$i}",
                'status' => 'active',
                'database_name' => "tenant_test_{$i}",
            ]);
        }
    }

    /** @test */
    public function optimized_lookup_is_significantly_faster_than_legacy()
    {
        // Setup: Create associations for user in multiple tenants
        foreach (array_slice($this->testTenants, 0, 5) as $tenant) {
            UserTenantAssociation::create([
                'email' => $this->testEmail,
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
                'membership' => 'student',
                'is_active' => true,
            ]);
        }

        // Clear any existing cache
        Cache::flush();

        // Benchmark optimized method (multiple runs for accuracy)
        $optimizedTimes = [];
        for ($i = 0; $i < 5; $i++) {
            Cache::flush(); // Clear cache between runs
            
            $start = microtime(true);
            $tenants = $this->service->getUserTenants($this->testEmail);
            $optimizedTimes[] = microtime(true) - $start;
            
            $this->assertCount(5, $tenants);
        }

        // Calculate average optimized time
        $avgOptimizedTime = array_sum($optimizedTimes) / count($optimizedTimes);

        // Benchmark legacy method (if available)
        $legacyTimes = [];
        for ($i = 0; $i < 3; $i++) { // Fewer runs since legacy is slow
            $start = microtime(true);
            $tenants = $this->service->getUserTenantsLegacy($this->testEmail);
            $legacyTimes[] = microtime(true) - $start;
        }

        $avgLegacyTime = array_sum($legacyTimes) / count($legacyTimes);

        // Performance assertions
        $this->assertLessThan(0.1, $avgOptimizedTime, 'Optimized method should be under 100ms');
        
        // Optimized should be at least 5x faster than legacy
        $speedImprovement = $avgLegacyTime / $avgOptimizedTime;
        $this->assertGreaterThan(5, $speedImprovement, 'Optimized method should be at least 5x faster');

        // Output performance metrics for visibility
        $this->addToAssertionCount(1); // Prevent risky test warning
        echo "\n";
        echo "Performance Benchmark Results:\n";
        echo "- Optimized average time: " . round($avgOptimizedTime * 1000, 2) . "ms\n";
        echo "- Legacy average time: " . round($avgLegacyTime * 1000, 2) . "ms\n";
        echo "- Speed improvement: " . round($speedImprovement, 1) . "x faster\n";
    }

    /** @test */
    public function cached_lookups_are_extremely_fast()
    {
        // Setup associations
        UserTenantAssociation::create([
            'email' => $this->testEmail,
            'tenant_id' => $this->testTenants[0]->id,
            'tenant_slug' => $this->testTenants[0]->slug,
            'membership' => 'student',
            'is_active' => true,
        ]);

        // First call - populates cache
        $this->service->getUserTenants($this->testEmail);

        // Benchmark cached calls
        $cachedTimes = [];
        for ($i = 0; $i < 10; $i++) {
            $start = microtime(true);
            $tenants = $this->service->getUserTenants($this->testEmail);
            $cachedTimes[] = microtime(true) - $start;
            
            $this->assertCount(1, $tenants);
        }

        $avgCachedTime = array_sum($cachedTimes) / count($cachedTimes);

        // Cached calls should be extremely fast (under 10ms)
        $this->assertLessThan(0.01, $avgCachedTime, 'Cached lookups should be under 10ms');

        echo "\nCached lookup average time: " . round($avgCachedTime * 1000, 2) . "ms\n";
    }

    /** @test */
    public function specific_tenant_lookup_is_fast()
    {
        // Setup association
        UserTenantAssociation::create([
            'email' => $this->testEmail,
            'tenant_id' => $this->testTenants[0]->id,
            'tenant_slug' => $this->testTenants[0]->slug,
            'membership' => 'admin',
            'is_active' => true,
        ]);

        // Benchmark specific tenant lookup
        $lookupTimes = [];
        for ($i = 0; $i < 10; $i++) {
            $start = microtime(true);
            $result = $this->service->getUserInTenant($this->testEmail, $this->testTenants[0]->slug);
            $lookupTimes[] = microtime(true) - $start;
            
            $this->assertNotNull($result);
            $this->assertEquals('admin', $result['membership']);
        }

        $avgLookupTime = array_sum($lookupTimes) / count($lookupTimes);

        // Specific tenant lookups should be very fast
        $this->assertLessThan(0.05, $avgLookupTime, 'Specific tenant lookups should be under 50ms');

        echo "\nSpecific tenant lookup average time: " . round($avgLookupTime * 1000, 2) . "ms\n";
    }

    /** @test */
    public function memory_usage_is_reasonable_with_many_tenants()
    {
        // Create associations for user in many tenants
        foreach ($this->testTenants as $tenant) {
            UserTenantAssociation::create([
                'email' => $this->testEmail,
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
                'membership' => 'student',
                'is_active' => true,
            ]);
        }

        $memoryBefore = memory_get_usage(true);
        
        // Get user tenants
        $tenants = $this->service->getUserTenants($this->testEmail);
        
        $memoryAfter = memory_get_usage(true);
        $memoryUsed = $memoryAfter - $memoryBefore;

        $this->assertCount(20, $tenants);
        
        // Memory usage should be reasonable (under 10MB for 20 tenants)
        $this->assertLessThan(10 * 1024 * 1024, $memoryUsed, 'Memory usage should be under 10MB');

        echo "\nMemory usage for 20 tenants: " . round($memoryUsed / 1024 / 1024, 2) . "MB\n";
    }

    /** @test */
    public function database_query_count_is_minimal()
    {
        // Setup associations
        foreach (array_slice($this->testTenants, 0, 5) as $tenant) {
            UserTenantAssociation::create([
                'email' => $this->testEmail,
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
                'membership' => 'student',
                'is_active' => true,
            ]);
        }

        // Clear cache to ensure we hit the database
        Cache::flush();

        // Count database queries
        $queryCount = 0;
        DB::listen(function ($query) use (&$queryCount) {
            $queryCount++;
        });

        $tenants = $this->service->getUserTenants($this->testEmail);

        $this->assertCount(5, $tenants);
        
        // Should only need 1-2 queries (one for associations, one for eager-loaded tenants)
        $this->assertLessThanOrEqual(2, $queryCount, 'Should use minimal database queries');

        echo "\nDatabase queries for 5 tenants: {$queryCount}\n";
    }

    /** @test */
    public function performance_scales_linearly_with_user_tenant_count()
    {
        $results = [];

        // Test with different numbers of tenant associations
        $tenantCounts = [1, 5, 10, 20];

        foreach ($tenantCounts as $count) {
            // Clear previous associations
            UserTenantAssociation::where('email', $this->testEmail)->delete();
            Cache::flush();

            // Create associations
            foreach (array_slice($this->testTenants, 0, $count) as $tenant) {
                UserTenantAssociation::create([
                    'email' => $this->testEmail,
                    'tenant_id' => $tenant->id,
                    'tenant_slug' => $tenant->slug,
                    'membership' => 'student',
                    'is_active' => true,
                ]);
            }

            // Benchmark
            $times = [];
            for ($i = 0; $i < 3; $i++) {
                Cache::flush();
                
                $start = microtime(true);
                $tenants = $this->service->getUserTenants($this->testEmail);
                $times[] = microtime(true) - $start;
                
                $this->assertCount($count, $tenants);
            }

            $avgTime = array_sum($times) / count($times);
            $results[$count] = $avgTime;
        }

        // Verify performance scales reasonably
        foreach ($results as $count => $time) {
            $this->assertLessThan(0.1, $time, "Lookup for {$count} tenants should be under 100ms");
        }

        // Output scaling results
        echo "\nPerformance Scaling Results:\n";
        foreach ($results as $count => $time) {
            echo "- {$count} tenants: " . round($time * 1000, 2) . "ms\n";
        }
    }
}
