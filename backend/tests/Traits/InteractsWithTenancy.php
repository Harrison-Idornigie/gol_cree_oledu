<?php

namespace Tests\Traits;

use App\Models\Landlord\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\DatabaseManager;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Events\TenantDeleted;

/**
 * Trait for handling multi-tenant testing
 * 
 * This trait provides utilities for:
 * - Creating temporary tenant databases for testing
 * - Properly initializing tenant contexts
 * - Running tenant migrations in test environment
 * - Cleaning up tenant databases after tests
 */
trait InteractsWithTenancy
{
    /**
     * Created tenants during test execution
     */
    protected array $createdTenants = [];

    /**
     * Original database configuration
     */
    protected array $originalDatabaseConfig = [];

    /**
     * Setup tenancy testing environment
     */
    protected function setUpTenancy(): void
    {
        // Store original database configuration
        $this->originalDatabaseConfig = config('database.connections');

        // Ensure we're using SQLite for testing
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        // Configure tenant database template for testing
        Config::set('tenancy.database.template_tenant_connection', 'tenant_template');
        Config::set('database.connections.tenant_template', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // Disable Stancl's automatic database creation for testing
        Config::set('tenancy.database.managers.sqlite', [
            'driver' => 'sqlite',
            'database' => storage_path('framework/testing/tenant_{tenant_id}.sqlite'),
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    /**
     * Cleanup tenancy testing environment
     */
    protected function tearDownTenancy(): void
    {
        // Delete all created tenant databases
        foreach ($this->createdTenants as $tenant) {
            $this->deleteTenantDatabase($tenant);
        }

        // Clear created tenants array
        $this->createdTenants = [];

        // Restore original database configuration
        Config::set('database.connections', $this->originalDatabaseConfig);
    }

    /**
     * Create a test tenant with proper database setup
     */
    protected function createTestTenant(array $attributes = []): Tenant
    {
        $defaultAttributes = [
            'id' => 'test_' . Str::random(8),
            'name' => 'Test Tenant ' . Str::random(4),
            'slug' => 'test-tenant-' . Str::random(4),
            'status' => 'active',
        ];

        $tenantAttributes = array_merge($defaultAttributes, $attributes);
        
        // Create tenant record in central database
        $tenant = Tenant::create($tenantAttributes);
        
        // Track created tenant for cleanup
        $this->createdTenants[] = $tenant;

        // Create and setup tenant database
        $this->createTenantDatabase($tenant);
        $this->migrateTenantDatabase($tenant);

        return $tenant;
    }

    /**
     * Create tenant database for testing
     */
    protected function createTenantDatabase(Tenant $tenant): void
    {
        $databasePath = $this->getTenantDatabasePath($tenant);
        
        // Ensure directory exists
        $directory = dirname($databasePath);
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // Create empty SQLite database file
        touch($databasePath);

        // Configure tenant database connection
        Config::set("database.connections.tenant_{$tenant->id}", [
            'driver' => 'sqlite',
            'database' => $databasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    /**
     * Run tenant migrations for testing
     */
    protected function migrateTenantDatabase(Tenant $tenant): void
    {
        // Switch to tenant database connection
        $originalConnection = DB::getDefaultConnection();
        
        try {
            // Set tenant database as default
            Config::set('database.default', "tenant_{$tenant->id}");
            DB::purge("tenant_{$tenant->id}");
            DB::reconnect("tenant_{$tenant->id}");

            // Run tenant migrations
            Artisan::call('migrate', [
                '--database' => "tenant_{$tenant->id}",
                '--path' => 'database/migrations/tenants',
                '--force' => true,
            ]);

        } finally {
            // Restore original connection
            Config::set('database.default', $originalConnection);
            DB::reconnect($originalConnection);
        }
    }

    /**
     * Delete tenant database
     */
    protected function deleteTenantDatabase(Tenant $tenant): void
    {
        $databasePath = $this->getTenantDatabasePath($tenant);
        
        if (File::exists($databasePath)) {
            File::delete($databasePath);
        }

        // Remove database connection configuration
        $connections = config('database.connections');
        unset($connections["tenant_{$tenant->id}"]);
        Config::set('database.connections', $connections);
    }

    /**
     * Get tenant database file path
     */
    protected function getTenantDatabasePath(Tenant $tenant): string
    {
        return storage_path("framework/testing/tenant_{$tenant->id}.sqlite");
    }

    /**
     * Run code in tenant context
     */
    protected function runInTenantContext(Tenant $tenant, callable $callback)
    {
        $originalConnection = DB::getDefaultConnection();
        
        try {
            // Initialize tenancy for this tenant
            tenancy()->initialize($tenant);
            
            // Switch to tenant database
            Config::set('database.default', "tenant_{$tenant->id}");
            DB::purge("tenant_{$tenant->id}");
            DB::reconnect("tenant_{$tenant->id}");

            // Run the callback
            return $callback();

        } finally {
            // End tenancy
            tenancy()->end();
            
            // Restore original connection
            Config::set('database.default', $originalConnection);
            DB::reconnect($originalConnection);
        }
    }

    /**
     * Assert tenant database exists and has tables
     */
    protected function assertTenantDatabaseExists(Tenant $tenant): void
    {
        $databasePath = $this->getTenantDatabasePath($tenant);
        $this->assertTrue(File::exists($databasePath), "Tenant database file does not exist: {$databasePath}");

        // Check if database has tables
        $this->runInTenantContext($tenant, function () {
            $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
            $this->assertNotEmpty($tables, 'Tenant database has no tables');
        });
    }

    /**
     * Assert tenant has specific table
     */
    protected function assertTenantHasTable(Tenant $tenant, string $tableName): void
    {
        $this->runInTenantContext($tenant, function () use ($tableName) {
            $this->assertTrue(
                DB::getSchemaBuilder()->hasTable($tableName),
                "Tenant database does not have table: {$tableName}"
            );
        });
    }

    /**
     * Create test data in tenant context
     */
    protected function createTenantData(Tenant $tenant, string $model, array $attributes = [])
    {
        return $this->runInTenantContext($tenant, function () use ($model, $attributes) {
            return $model::create($attributes);
        });
    }

    /**
     * Assert data exists in tenant database
     */
    protected function assertTenantHasData(Tenant $tenant, string $table, array $data): void
    {
        $this->runInTenantContext($tenant, function () use ($table, $data) {
            $this->assertDatabaseHas($table, $data);
        });
    }
}
