<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    /**
     * Indicates whether the default seeder should run before each test.
     */
    protected $seed = false;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Configure test database settings to avoid VACUUM issues
        $this->configureTestDatabases();

        // Setup central database
        $this->setupCentralDatabase();
    }

    /**
     * Tear down the test environment.
     */
    protected function tearDown(): void
    {
        // Clean up any test databases
        $this->cleanupTestDatabases();

        parent::tearDown();
    }

    /**
     * Configure test databases to use in-memory SQLite with VACUUM fixes
     */
    protected function configureTestDatabases(): void
    {
        // Use in-memory SQLite for testing with proper configuration
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // Configure tenant database template for in-memory SQLite
        Config::set('database.connections.tenant_template', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }

    /**
     * Setup central database for testing
     */
    protected function setupCentralDatabase(): void
    {
        try {
            // Clear any existing connections
            DB::purge('sqlite');

            // Run central database migrations
            $this->artisan('migrate:fresh', [
                '--database' => 'sqlite',
                '--path' => 'database/migrations/landlord',
                '--force' => true,
            ]);

            // Create basic landlord data for testing
            $this->createBasicLandlordData();
        } catch (\Exception $e) {
            // If landlord migrations don't exist, try regular migrations
            try {
                $this->artisan('migrate:fresh', [
                    '--database' => 'sqlite',
                    '--force' => true,
                ]);
            } catch (\Exception $e2) {
                // Continue without migrations if they fail
            }
        }
    }



    /**
     * Create basic landlord data for testing
     */
    protected function createBasicLandlordData(): void
    {
        try {
            // Create basic data that might be needed for tests
            if (DB::getSchemaBuilder()->hasTable('tenants')) {
                // Basic tenant data will be created by individual tests
            }
        } catch (\Exception $e) {
            // Continue if tables don't exist
        }
    }

    /**
     * Clean up test databases
     */
    protected function cleanupTestDatabases(): void
    {
        try {
            // In-memory SQLite databases are automatically cleaned up

            // Clear database connections
            $connections = ['sqlite', 'tenant_template'];
            foreach ($connections as $connection) {
                try {
                    DB::purge($connection);
                } catch (\Exception $e) {
                    // Continue if connection doesn't exist
                }
            }
        } catch (\Exception $e) {
            // Continue cleanup even if some operations fail
        }
    }



    /**
     * Create a fresh application instance.
     */
    public function createApplication()
    {
        $app = require __DIR__ . '/../bootstrap/app.php';

        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }
}
