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
        $this->originalDatabaseConfig = config('database.connections', []);

        // Use SQLite for testing with proper configuration to avoid VACUUM issues
        Config::set('database.default', 'sqlite');

        // Configure tenant database template for testing - use in-memory SQLite
        Config::set('tenancy.database.template_tenant_connection', 'tenant_template');
        Config::set('database.connections.tenant_template', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // Configure tenancy to use in-memory SQLite databases for testing
        Config::set('tenancy.database.managers.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // Disable automatic tenant database creation events to avoid conflicts
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

        // Create tenant record in central database using direct DB insert to avoid factory issues
        try {
            $tenant = new Tenant($defaultAttributes);
            $tenant->save();
        } catch (\Exception $e) {
            // If model creation fails, try direct database insert
            DB::table('tenants')->insert(array_merge($defaultAttributes, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            $tenant = Tenant::find($defaultAttributes['id']);
        }

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
        // Configure tenant database connection to use in-memory SQLite
        Config::set("database.connections.tenant_{$tenant->id}", [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
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

            // Check if migrations table exists to avoid conflicts
            $hasTable = false;
            try {
                $hasTable = DB::getSchemaBuilder()->hasTable('migrations');
            } catch (\Exception $e) {
                // Continue if we can't check
            }

            // Try to run tenant-specific migrations first
            try {
                if (!$hasTable) {
                    Artisan::call('migrate:fresh', [
                        '--database' => "tenant_{$tenant->id}",
                        '--path' => 'database/migrations/tenant',
                        '--force' => true,
                    ]);
                }
            } catch (\Exception $e) {
                // If tenant migrations don't exist, run regular migrations
                try {
                    if (!$hasTable) {
                        Artisan::call('migrate:fresh', [
                            '--database' => "tenant_{$tenant->id}",
                            '--force' => true,
                        ]);
                    }
                } catch (\Exception $e2) {
                    // Continue if migrations fail
                }
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
            // Purge the in-memory database connection
            DB::purge("tenant_{$tenant->id}");
        } catch (\Exception $e) {
            // Continue if connection doesn't exist
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

    /**
     * Create a tenant team user for testing
     */
    protected function createTenantTeam(array $attributes = []): \App\Models\Tenants\User
    {
        return $this->runInTenantContext($this->tenant ?? $this->createTestTenant(), function () use ($attributes) {
            return \App\Models\Tenants\User::factory()->create(array_merge([
                'email' => 'team@test.com',
                'membership_type' => 'team',
                'email_verified_at' => now(),
            ], $attributes));
        });
    }

    /**
     * Create a tenant student user for testing
     */
    protected function createTenantStudent(array $attributes = []): \App\Models\Tenants\User
    {
        return $this->runInTenantContext($this->tenant ?? $this->createTestTenant(), function () use ($attributes) {
            return \App\Models\Tenants\User::factory()->create(array_merge([
                'email' => 'student@test.com',
                'membership_type' => 'student',
                'email_verified_at' => now(),
            ], $attributes));
        });
    }

    /**
     * Create a tenant admin user for testing
     */
    protected function createTenantAdmin(array $attributes = []): \App\Models\Tenants\User
    {
        return $this->runInTenantContext($this->tenant ?? $this->createTestTenant(), function () use ($attributes) {
            return \App\Models\Tenants\User::factory()->create(array_merge([
                'email' => 'admin@test.com',
                'membership_type' => 'tenant-admin',
                'email_verified_at' => now(),
            ], $attributes));
        });
    }

    /**
     * Create a language for testing
     */
    protected function createLanguage(array $attributes = []): \App\Models\Tenants\Language
    {
        return $this->runInTenantContext($this->tenant ?? $this->createTestTenant(), function () use ($attributes) {
            return \App\Models\Tenants\Language::factory()->create(array_merge([
                'code' => 'crk',
                'name' => 'Plains Cree',
                'native_name' => 'nēhiyawēwin',
                'is_active' => true,
            ], $attributes));
        });
    }

    /**
     * Create a word for testing
     */
    protected function createWord(array $attributes = []): \App\Models\Tenants\Word
    {
        $tenant = $this->tenant ?? $this->createTestTenant();
        return $this->runInTenantContext($tenant, function () use ($attributes) {
            $language = $attributes['language_id'] ?? $this->createLanguage()->id;
            return \App\Models\Tenants\Word::factory()->create(array_merge([
                'language_id' => $language,
            ], $attributes));
        });
    }

    /**
     * Create a sentence for testing
     */
    protected function createSentence(array $attributes = []): \App\Models\Tenants\Sentence
    {
        $tenant = $this->tenant ?? $this->createTestTenant();
        return $this->runInTenantContext($tenant, function () use ($attributes) {
            $language = $attributes['language_id'] ?? $this->createLanguage()->id;
            return \App\Models\Tenants\Sentence::factory()->create(array_merge([
                'language_id' => $language,
            ], $attributes));
        });
    }

    /**
     * Create a learning path for testing
     */
    protected function createLearningPath(array $attributes = []): \App\Models\Tenants\LearningPath
    {
        $tenant = $this->tenant ?? $this->createTestTenant();
        return $this->runInTenantContext($tenant, function () use ($attributes) {
            $language = $attributes['language_id'] ?? $this->createLanguage()->id;
            return \App\Models\Tenants\LearningPath::factory()->create(array_merge([
                'language_id' => $language,
                'status' => 'draft',
            ], $attributes));
        });
    }

    /**
     * Create a unit for testing
     */
    protected function createUnit(array $attributes = []): \App\Models\Tenants\Unit
    {
        $tenant = $this->tenant ?? $this->createTestTenant();
        return $this->runInTenantContext($tenant, function () use ($attributes) {
            $learningPath = $attributes['learning_path_id'] ?? $this->createLearningPath()->id;
            return \App\Models\Tenants\Unit::factory()->create(array_merge([
                'learning_path_id' => $learningPath,
                'status' => 'draft',
            ], $attributes));
        });
    }

    /**
     * Create an exercise for testing
     */
    protected function createExercise(array $attributes = []): \App\Models\Tenants\Exercise
    {
        $tenant = $this->tenant ?? $this->createTestTenant();
        return $this->runInTenantContext($tenant, function () use ($attributes) {
            $unit = $attributes['unit_id'] ?? $this->createUnit()->id;
            return \App\Models\Tenants\Exercise::factory()->create(array_merge([
                'unit_id' => $unit,
                'type' => 'multiple_choice',
            ], $attributes));
        });
    }
}
