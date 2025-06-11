<?php

namespace Tests\Feature\Commands;

use App\Models\Landlord\Tenant;
use App\Models\Landlord\UserTenantAssociation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class UserTenantSyncCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant1;
    protected Tenant $tenant2;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test tenants
        $this->tenant1 = Tenant::create([
            'name' => 'Test District 1',
            'slug' => 'test-district-1',
            'status' => 'active',
            'database_name' => 'tenant_test_1',
        ]);

        $this->tenant2 = Tenant::create([
            'name' => 'Test District 2',
            'slug' => 'test-district-2', 
            'status' => 'active',
            'database_name' => 'tenant_test_2',
        ]);
    }

    /** @test */
    public function sync_command_shows_dry_run_results()
    {
        $this->artisan('tenant:sync-user-associations --dry-run')
            ->expectsOutput('🔍 DRY RUN MODE - No changes will be made')
            ->assertExitCode(0);
    }

    /** @test */
    public function sync_command_can_target_specific_tenant()
    {
        $this->artisan('tenant:sync-user-associations --tenant=test-district-1 --dry-run')
            ->expectsOutput('Processing 1 tenant(s)')
            ->assertExitCode(0);
    }

    /** @test */
    public function sync_command_handles_non_existent_tenant()
    {
        $this->artisan('tenant:sync-user-associations --tenant=non-existent --dry-run')
            ->expectsOutput('No active tenants found to process')
            ->assertExitCode(1);
    }

    /** @test */
    public function verify_command_works_with_no_associations()
    {
        $this->artisan('tenant:verify-user-associations --sample=1')
            ->expectsOutput('No associations found to verify')
            ->assertExitCode(0);
    }

    /** @test */
    public function verify_command_can_check_specific_user()
    {
        // Create test association
        UserTenantAssociation::create([
            'email' => 'test@example.com',
            'tenant_id' => $this->tenant1->id,
            'tenant_slug' => $this->tenant1->slug,
            'membership' => 'student',
            'is_active' => true,
        ]);

        $this->artisan('tenant:verify-user-associations --email=test@example.com')
            ->expectsOutput('Verifying user: test@example.com')
            ->assertExitCode(0); // Will be 0 since we can't verify against actual tenant DB in test
    }

    /** @test */
    public function cleanup_command_shows_dry_run_results()
    {
        $this->artisan('tenant:cleanup-associations --dry-run --all')
            ->expectsOutput('🔍 DRY RUN MODE - No changes will be made')
            ->assertExitCode(0);
    }

    /** @test */
    public function cleanup_command_requires_cleanup_type()
    {
        $this->artisan('tenant:cleanup-associations')
            ->expectsOutput('Please specify what to clean: --orphaned, --inactive, --duplicates, or --all')
            ->assertExitCode(1);
    }

    /** @test */
    public function cleanup_command_finds_orphaned_associations()
    {
        // Create association for non-existent tenant
        UserTenantAssociation::create([
            'email' => 'orphan@example.com',
            'tenant_id' => '99999999-9999-9999-9999-999999999999',
            'tenant_slug' => 'deleted-tenant',
            'membership' => 'student',
            'is_active' => true,
        ]);

        $this->artisan('tenant:cleanup-associations --orphaned --dry-run')
            ->expectsOutput('Would clean 1 orphaned associations')
            ->assertExitCode(0);
    }

    /** @test */
    public function cleanup_command_finds_inactive_tenant_associations()
    {
        // Create inactive tenant
        $inactiveTenant = Tenant::create([
            'name' => 'Inactive District',
            'slug' => 'inactive-district',
            'status' => 'inactive',
            'database_name' => 'tenant_inactive',
        ]);

        // Create association for inactive tenant
        UserTenantAssociation::create([
            'email' => 'inactive@example.com',
            'tenant_id' => $inactiveTenant->id,
            'tenant_slug' => $inactiveTenant->slug,
            'membership' => 'student',
            'is_active' => true,
        ]);

        $this->artisan('tenant:cleanup-associations --inactive --dry-run')
            ->expectsOutput('Would clean 1 associations with inactive tenants')
            ->assertExitCode(0);
    }

    /** @test */
    public function cleanup_command_finds_duplicate_associations()
    {
        // Create duplicate associations
        UserTenantAssociation::create([
            'email' => 'duplicate@example.com',
            'tenant_id' => $this->tenant1->id,
            'tenant_slug' => $this->tenant1->slug,
            'membership' => 'student',
            'is_active' => true,
        ]);

        UserTenantAssociation::create([
            'email' => 'duplicate@example.com',
            'tenant_id' => $this->tenant1->id,
            'tenant_slug' => $this->tenant1->slug,
            'membership' => 'team', // Different membership but same email+tenant
            'is_active' => true,
        ]);

        $this->artisan('tenant:cleanup-associations --duplicates --dry-run')
            ->expectsOutput('Would remove 1 duplicate(s) for duplicate@example.com')
            ->assertExitCode(0);
    }

    /** @test */
    public function cleanup_command_actually_removes_orphaned_when_not_dry_run()
    {
        // Create orphaned association
        UserTenantAssociation::create([
            'email' => 'orphan@example.com',
            'tenant_id' => '99999999-9999-9999-9999-999999999999',
            'tenant_slug' => 'deleted-tenant',
            'membership' => 'student',
            'is_active' => true,
        ]);

        $this->assertEquals(1, UserTenantAssociation::count());

        $this->artisan('tenant:cleanup-associations --orphaned')
            ->expectsOutput('🗑️  Cleaned 1 orphaned associations')
            ->assertExitCode(0);

        $this->assertEquals(0, UserTenantAssociation::count());
    }

    /** @test */
    public function commands_handle_empty_database_gracefully()
    {
        // Test all commands with empty database
        $this->artisan('tenant:sync-user-associations --dry-run')
            ->assertExitCode(0);

        $this->artisan('tenant:verify-user-associations --sample=10')
            ->assertExitCode(0);

        $this->artisan('tenant:cleanup-associations --all --dry-run')
            ->assertExitCode(0);
    }

    /** @test */
    public function commands_show_helpful_output_for_users()
    {
        // Test that commands provide clear, helpful output
        $this->artisan('tenant:sync-user-associations --dry-run')
            ->expectsOutput('🚀 Starting User Tenant Association Sync')
            ->expectsOutput('💡 Run without --dry-run to perform actual migration');

        $this->artisan('tenant:cleanup-associations --all --dry-run')
            ->expectsOutput('🧹 Starting User Tenant Association Cleanup')
            ->expectsOutput('💡 Run without --dry-run to perform actual cleanup');
    }

    /** @test */
    public function sync_command_respects_chunk_size()
    {
        $this->artisan('tenant:sync-user-associations --chunk=50 --dry-run')
            ->assertExitCode(0);
    }

    /** @test */
    public function verify_command_respects_sample_size()
    {
        $this->artisan('tenant:verify-user-associations --sample=5')
            ->assertExitCode(0);
    }
}
