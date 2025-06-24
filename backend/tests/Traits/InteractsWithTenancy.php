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

        // Use MySQL for testing to avoid SQLite VACUUM issues
        Config::set('database.default', 'mysql_testing');

        // Configure tenant database template for testing - use MySQL
        Config::set('tenancy.database.template_tenant_connection', 'tenant_template');
        Config::set('database.connections.tenant_template', [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => null, // Will be set dynamically
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ]);

        // Configure tenancy to use MySQL databases for testing
        Config::set('tenancy.database.managers.mysql', [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => null, // Will be set dynamically
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ]);

        // Disable automatic tenant database creation events
        Config::set('tenancy.features', []);
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
    protected function createTestTenant(string $slug = null): Tenant
    {
        $randomId = Str::random(8);
        $tenantSlug = $slug ?? 'test-tenant-' . $randomId;

        $defaultAttributes = [
            'id' => 'test_' . $randomId,
            'name' => 'Test Tenant ' . $randomId,
            'slug' => $tenantSlug,
            'status' => 'active',
        ];

        // Create tenant record in central database
        $tenant = Tenant::create($defaultAttributes);

        // Track created tenant for cleanup
        $this->createdTenants[] = $tenant;

        // Create and setup tenant database (in-memory)
        $this->createTenantDatabase($tenant);
        $this->migrateTenantDatabase($tenant);

        return $tenant;
    }

    /**
     * Create tenant database for testing (MySQL)
     */
    protected function createTenantDatabase(Tenant $tenant): void
    {
        try {
            // Create a unique database name for this tenant
            $databaseName = "gol_2025_testing_tenant_{$tenant->id}";

            // Connect to MySQL to create the tenant database
            $connection = new \PDO(
                'mysql:host=' . env('DB_HOST', '127.0.0.1') . ';port=' . env('DB_PORT', '3306'),
                env('DB_USERNAME', 'root'),
                env('DB_PASSWORD', ''),
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );

            // Create the tenant database
            $connection->exec("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            // Configure tenant database connection
            Config::set("database.connections.tenant_{$tenant->id}", [
                'driver' => 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'database' => $databaseName,
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
                'unix_socket' => env('DB_SOCKET', ''),
                'charset' => env('DB_CHARSET', 'utf8mb4'),
                'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
            ]);
        } catch (\Exception $e) {
            // Continue if database creation fails
        }
    }

    /**
     * Run tenant migrations and seeding for testing
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

            // Try to run tenant-specific migrations first
            try {
                Artisan::call('migrate:fresh', [
                    '--database' => "tenant_{$tenant->id}",
                    '--path' => 'database/migrations/tenant',
                    '--force' => true,
                ]);
            } catch (\Exception $e) {
                // If tenant migrations don't exist, run regular migrations
                Artisan::call('migrate:fresh', [
                    '--database' => "tenant_{$tenant->id}",
                    '--force' => true,
                ]);
            }

            // Run tenant seeders
            $this->seedTenantDatabase($tenant);
        } finally {
            // Restore original connection
            Config::set('database.default', $originalConnection);
            DB::reconnect($originalConnection);
        }
    }

    /**
     * Seed tenant database with test data
     */
    protected function seedTenantDatabase(Tenant $tenant): void
    {
        try {
            // Try to run tenant-specific seeders
            Artisan::call('db:seed', [
                '--database' => "tenant_{$tenant->id}",
                '--class' => 'TenantDatabaseSeeder',
                '--force' => true,
            ]);
        } catch (\Exception $e) {
            // If no seeder exists, create basic test data
            $this->createBasicTenantTestData($tenant);
        }
    }

    /**
     * Create basic test data for tenant
     */
    protected function createBasicTenantTestData(Tenant $tenant): void
    {
        // Create basic language if Language model exists
        try {
            if (class_exists('App\\Models\\Tenants\\Language')) {
                DB::table('languages')->insertOrIgnore([
                    'id' => \Illuminate\Support\Str::uuid(),
                    'name' => 'English',
                    'code' => 'en',
                    'native_name' => 'English',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('languages')->insertOrIgnore([
                    'id' => \Illuminate\Support\Str::uuid(),
                    'name' => 'Plains Cree',
                    'code' => 'cr',
                    'native_name' => 'nêhiyawêwin',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            // Table might not exist, continue
        }

        // Create basic user roles/memberships if they exist
        try {
            if (DB::getSchemaBuilder()->hasTable('memberships')) {
                $memberships = [
                    ['slug' => 'tenant-admin', 'name' => 'Tenant Admin', 'description' => 'Full admin access within tenant'],
                    ['slug' => 'team', 'name' => 'Team Member', 'description' => 'Content creation and management'],
                    ['slug' => 'student', 'name' => 'Student', 'description' => 'Learning access'],
                ];

                foreach ($memberships as $membership) {
                    DB::table('memberships')->insertOrIgnore([
                        'id' => \Illuminate\Support\Str::uuid(),
                        'slug' => $membership['slug'],
                        'name' => $membership['name'],
                        'description' => $membership['description'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Table might not exist, continue
        }

        // Create basic permissions if they exist
        try {
            if (DB::getSchemaBuilder()->hasTable('permissions')) {
                $permissions = [
                    'admin' => 'Full administrative access',
                    'tenant-admin' => 'Tenant administration access',
                    'team' => 'Content creation and management',
                    'student' => 'Learning and progress tracking',
                ];

                foreach ($permissions as $name => $description) {
                    DB::table('permissions')->insertOrIgnore([
                        'id' => \Illuminate\Support\Str::uuid(),
                        'name' => $name,
                        'guard_name' => 'tenant',
                        'description' => $description,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Table might not exist, continue
        }
    }

    /**
     * Delete tenant database (cleanup MySQL database)
     */
    protected function deleteTenantDatabase(Tenant $tenant): void
    {
        try {
            // Get the database name
            $databaseName = "gol_2025_testing_tenant_{$tenant->id}";

            // Purge the database connection
            DB::purge("tenant_{$tenant->id}");

            // Connect to MySQL to drop the tenant database
            $connection = new \PDO(
                'mysql:host=' . env('DB_HOST', '127.0.0.1') . ';port=' . env('DB_PORT', '3306'),
                env('DB_USERNAME', 'root'),
                env('DB_PASSWORD', ''),
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );

            // Drop the tenant database
            $connection->exec("DROP DATABASE IF EXISTS `{$databaseName}`");
        } catch (\Exception $e) {
            // Continue if cleanup fails
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
            // Switch to tenant database
            Config::set('database.default', "tenant_{$tenant->id}");
            DB::purge("tenant_{$tenant->id}");
            DB::reconnect("tenant_{$tenant->id}");

            // Run the callback
            return $callback();
        } finally {
            // Restore original connection
            Config::set('database.default', $originalConnection);
            DB::reconnect($originalConnection);
        }
    }

    /**
     * Initialize tenant context for testing
     */
    protected function initializeTenantContext(Tenant $tenant): void
    {
        // This method is used to set up tenant context for API calls
        // The actual tenant switching happens in runInTenantContext
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
