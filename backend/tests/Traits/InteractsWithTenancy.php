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
     * Array to track temporary database files for cleanup
     */
    protected array $tempDbFiles = [];

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

        // Configure tenancy to use SQLite for central connection in tests
        Config::set('tenancy.database.central_connection', 'sqlite');

        // Set up central database for testing (needed for tenant lookup)
        $this->setupCentralDatabase();

        // Configure tenant database template for testing - use file-based SQLite
        Config::set('tenancy.database.template_tenant_connection', 'tenant_template');
        Config::set('database.connections.tenant_template', [
            'driver' => 'sqlite',
            'database' => database_path('testing/tenant_template.sqlite'),
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // Configure tenancy database manager for file-based SQLite
        Config::set('tenancy.database.managers.sqlite', \Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager::class);

        // Use simple naming for tests - let Stancl handle the paths
        Config::set('tenancy.database.prefix', 'tenant_');
        Config::set('tenancy.database.suffix', '');

        // Configure the tenant template connection for file-based SQLite testing
        Config::set('database.connections.tenant_template', [
            'driver' => 'sqlite',
            'database' => database_path('testing.sqlite'),
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // Disable automatic tenant database creation events to avoid conflicts
        Config::set('tenancy.features', []);

        // Ensure testing directory exists
        $this->ensureTestingDirectoryExists();

        // Run landlord migrations to ensure central database tables exist
        $this->runLandlordMigrations();
    }

    /**
     * Ensure testing directory exists
     */
    protected function ensureTestingDirectoryExists(): void
    {
        $testingDir = database_path('testing');
        if (!File::exists($testingDir)) {
            File::makeDirectory($testingDir, 0755, true);
        }
    }

    /**
     * Run landlord migrations for testing
     */
    protected function runLandlordMigrations(): void
    {
        try {
            // Run landlord migrations to create central database tables
            Artisan::call('migrate', [
                '--path' => 'database/migrations/landlord',
                '--force' => true,
            ]);
        } catch (\Exception $e) {
            // If migrations fail, continue - they might already be run
        }
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

        // Clean up temporary database files
        foreach ($this->tempDbFiles as $tempFile) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        // Clear arrays
        $this->createdTenants = [];
        $this->tempDbFiles = [];

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

        // Create tenant record in central database using direct DB insert to avoid model issues
        DB::table('tenants')->insert(array_merge($defaultAttributes, [
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        // Retrieve the created tenant
        $tenant = Tenant::find($defaultAttributes['id']);

        // Track created tenant for cleanup
        $this->createdTenants[] = $tenant;

        // Create and setup tenant database (in-memory)
        $this->createTenantDatabase($tenant);
        $this->migrateTenantDatabase($tenant);

        return $tenant;
    }

    /**
     * Setup central database for testing
     */
    protected function setupCentralDatabase(): void
    {
        // Use a file-based database for central database
        $centralDbPath = database_path('testing_central.sqlite');

        // Delete the database file if it exists to ensure a fresh start
        if (file_exists($centralDbPath)) {
            unlink($centralDbPath);
        }

        // Create the database file
        touch($centralDbPath);

        // Configure the default SQLite connection to use the file
        Config::set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => $centralDbPath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // Store the file path for cleanup
        $this->tempDbFiles[] = $centralDbPath;

        // Run central database migrations fresh (landlord migrations), skipping Telescope migrations
        $migrationDir = base_path('database/migrations/landlord');
        $migrationFiles = collect(File::files($migrationDir))
            ->filter(function ($file) {
                return strpos($file->getFilename(), 'telescope') === false;
            })
            ->map(function ($file) use ($migrationDir) {
                return 'database/migrations/landlord/' . $file->getFilename();
            })
            ->toArray();

        foreach ($migrationFiles as $migrationPath) {
            $exitCode = Artisan::call('migrate', [
                '--database' => 'sqlite',
                '--path' => $migrationPath,
                '--force' => true,
            ]);
            if ($exitCode !== 0) {
                throw new \Exception("Central database migration failed for {$migrationPath} with exit code: {$exitCode}");
            }
        }

        // Verify that the tenants table was created
        if (!DB::connection('sqlite')->getSchemaBuilder()->hasTable('tenants')) {
            throw new \Exception("Tenants table was not created in central database");
        }
    }

    /**
     * Create tenant database for testing (SQLite)
     */
    protected function createTenantDatabase(Tenant $tenant): void
    {
        // Use Stancl's database manager to create the database
        $manager = app(\Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager::class);
        $manager->createDatabase($tenant);
    }

    /**
     * Run tenant migrations and seeding for testing
     */
    protected function migrateTenantDatabase(Tenant $tenant): void
    {
        // Use Stancl's tenancy helper to switch context
        tenancy()->initialize($tenant);

        try {
            // Always run migrate:fresh for tenant DB to ensure a clean state
            $migrationPath = 'database/migrations/tenants';
            if (is_dir(base_path($migrationPath))) {
                $exitCode = Artisan::call('migrate:fresh', [
                    '--path' => $migrationPath,
                    '--force' => true,
                ]);
            } else {
                $exitCode = Artisan::call('migrate:fresh', [
                    '--force' => true,
                ]);
            }

            if ($exitCode !== 0) {
                throw new \Exception("Tenant migrations failed with exit code: {$exitCode}");
            }

            // Verify that the users table was created
            if (!DB::getSchemaBuilder()->hasTable('users')) {
                throw new \Exception("Users table was not created in tenant database");
            }

            // Run tenant seeders
            $this->seedTenantDatabase($tenant);
        } finally {
            // End tenancy context
            tenancy()->end();
        }
    }

    /**
     * Seed tenant database with test data
     */
    protected function seedTenantDatabase(Tenant $tenant): void
    {
        try {
            // Try to run tenant-specific seeders (tenancy context already set)
            Artisan::call('db:seed', [
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
        // Get the database name using the same logic
        $prefix = config('tenancy.database.prefix', '');
        $suffix = config('tenancy.database.suffix', '');
        $databaseName = $prefix . $tenant->id . $suffix;

        try {
            // Purge the database connection
            DB::purge($databaseName);
        } catch (\Exception $e) {
            // Continue if connection doesn't exist
        }

        // Remove database connection configuration
        $connections = config('database.connections');
        unset($connections[$databaseName]);
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
        // Use Stancl's tenancy helper for context switching
        tenancy()->initialize($tenant);

        try {
            // Run the callback
            return $callback();
        } finally {
            // End tenancy context
            tenancy()->end();
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
                'membership' => 'team',
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
                'membership' => 'student',
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
                'membership' => 'admin',
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
