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

        // Use MySQL for testing as requested by user
        Config::set('database.default', 'mysql_testing');

        // Configure tenancy to use MySQL for central connection in tests
        Config::set('tenancy.database.central_connection', 'mysql_testing');

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
        // Clear any active tenant context
        tenancy()->end();

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

        // Clear tenant records from central database
        foreach ($this->createdTenants as $tenant) {
            try {
                DB::table('tenants')->where('id', $tenant->id)->delete();
            } catch (\Exception $e) {
                // Continue if deletion fails
            }
        }

        // Clean up any leftover tenant database files
        $this->cleanupLeftoverTenantDatabases();

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
        // Use file-based database for central database (as requested)
        // The database path is already configured in phpunit.xml

        // Run central database migrations fresh (landlord migrations)
        \Log::info("Running central database migrations...");
        $exitCode = Artisan::call('migrate:fresh', [
            '--database' => 'sqlite',
            '--path' => 'database/migrations/landlord',
            '--force' => true,
        ]);

        \Log::info("Migration exit code: {$exitCode}");
        \Log::info("Migration output: " . Artisan::output());

        if ($exitCode !== 0) {
            throw new \Exception("Central database migration failed with exit code: {$exitCode}");
        }

        // Verify that the tenants table was created
        if (!DB::connection('sqlite')->getSchemaBuilder()->hasTable('tenants')) {
            throw new \Exception("Tenants table was not created in central database");
        }
    }

    /**
     * Create tenant database for testing (file-based SQLite)
     */
    protected function createTenantDatabase(Tenant $tenant): void
    {
        // Ensure the testing directory exists
        $testingDir = database_path('testing');
        if (!File::exists($testingDir)) {
            File::makeDirectory($testingDir, 0755, true);
        }

        // Override the tenant database configuration for testing to ensure consistency
        config([
            'tenancy.database.prefix' => 'tenant_',
            'tenancy.database.suffix' => '',
        ]);

        // Use Stancl's database manager to create the database
        $manager = app(\Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager::class);
        $manager->createDatabase($tenant);

        // Log the database path for debugging
        $databaseName = config('tenancy.database.prefix') . $tenant->getKey() . config('tenancy.database.suffix');
        \Log::info("Created tenant database: {$databaseName} for tenant: {$tenant->slug}");

        // Store the database path for cleanup
        $databasePath = $this->getTenantDatabasePath($tenant);
        $this->tempDbFiles[] = $databasePath;
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
                $output = Artisan::output();
                throw new \Exception("Tenant migrations failed with exit code: {$exitCode}. Output: {$output}");
            }

            // Verify that essential tables were created
            $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
            $tableNames = array_map(function ($table) {
                return $table->name;
            }, $tables);

            if (!DB::getSchemaBuilder()->hasTable('users')) {
                throw new \Exception("Users table was not created in tenant database. Available tables: " . implode(', ', $tableNames));
            }

            if (!DB::getSchemaBuilder()->hasTable('words')) {
                // List all tables to debug
                $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
                $tableNames = array_map(function ($table) {
                    return $table->name;
                }, $tables);
                throw new \Exception("Words table was not created in tenant database. Available tables: " . implode(', ', $tableNames));
            }

            // Run tenant seeders
            $this->seedTenantDatabase($tenant);
        } catch (\Exception $e) {
            // End tenancy context on error
            tenancy()->end();
            throw $e;
        }
        // Keep tenancy context active for the test
    }

    /**
     * Seed tenant database with test data
     */
    protected function seedTenantDatabase(Tenant $tenant): void
    {
        // Skip all seeding during testing to avoid data pollution
        // Tests should create their own minimal test data as needed
        // $this->createBasicTenantTestData($tenant);
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
     * Delete tenant database (cleanup SQLite database files)
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

        // Delete the actual SQLite database file
        // The SQLiteDatabaseManager creates files in database/tenant_{id}
        $sqliteFile = database_path("tenant_{$tenant->id}");
        if (file_exists($sqliteFile)) {
            unlink($sqliteFile);
        }

        // Also check for alternative paths that might be used
        $alternativePaths = [
            storage_path("framework/testing/tenant_{$tenant->id}.sqlite"),
            database_path("tenant_{$tenant->id}.sqlite"),
            base_path("database/tenant_{$tenant->id}"),
        ];

        foreach ($alternativePaths as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    /**
     * Clean up any leftover tenant database files from previous test runs
     */
    protected function cleanupLeftoverTenantDatabases(): void
    {
        $databasePath = database_path();

        // Find all tenant database files
        $tenantFiles = glob($databasePath . '/tenant_*');

        foreach ($tenantFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        // Also clean up any files in storage/framework/testing
        $testingPath = storage_path('framework/testing');
        if (is_dir($testingPath)) {
            $testingFiles = glob($testingPath . '/tenant_*.sqlite');
            foreach ($testingFiles as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Get tenant database file path
     */
    protected function getTenantDatabasePath(Tenant $tenant): string
    {
        // Use the same naming convention as Stancl
        $prefix = config('tenancy.database.prefix', 'tenant_');
        $suffix = config('tenancy.database.suffix', '');
        $databaseName = $prefix . $tenant->getKey() . $suffix;

        return database_path("testing/{$databaseName}.sqlite");
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
        // Initialize tenancy for HTTP requests in tests
        // This ensures that when we make HTTP requests, the middleware can properly identify the tenant
        tenancy()->initialize($tenant);

        // Store the tenant for cleanup
        if (!in_array($tenant, $this->createdTenants)) {
            $this->createdTenants[] = $tenant;
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

    /**
     * Create a tenant team user for testing
     */
    protected function createTenantTeam(array $attributes = []): \App\Models\Tenants\User
    {
        // Ensure tenant context is active
        $tenant = property_exists($this, 'tenant') && $this->tenant ? $this->tenant : $this->createTestTenant();
        tenancy()->initialize($tenant);

        $uniqueId = \Illuminate\Support\Str::random(8);
        return \App\Models\Tenants\User::create(array_merge([
            'id' => \Illuminate\Support\Str::uuid(),
            'name' => 'Team User',
            'email' => "team-{$uniqueId}@test.com",
            'password' => bcrypt('password'),
            'membership' => 'team',
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    /**
     * Create a tenant student user for testing
     */
    protected function createTenantStudent(array $attributes = []): \App\Models\Tenants\User
    {
        // Ensure tenant context is active
        $tenant = property_exists($this, 'tenant') && $this->tenant ? $this->tenant : $this->createTestTenant();
        tenancy()->initialize($tenant);

        $uniqueId = \Illuminate\Support\Str::random(8);
        return \App\Models\Tenants\User::create(array_merge([
            'id' => \Illuminate\Support\Str::uuid(),
            'name' => 'Student User',
            'email' => "student-{$uniqueId}@test.com",
            'password' => bcrypt('password'),
            'membership' => 'student',
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    /**
     * Create a tenant admin user for testing
     */
    protected function createTenantAdmin(array $attributes = []): \App\Models\Tenants\User
    {
        // Ensure tenant context is active
        $tenant = property_exists($this, 'tenant') && $this->tenant ? $this->tenant : $this->createTestTenant();
        tenancy()->initialize($tenant);

        $uniqueId = \Illuminate\Support\Str::random(8);
        return \App\Models\Tenants\User::create(array_merge([
            'id' => \Illuminate\Support\Str::uuid(),
            'name' => 'Admin User',
            'email' => "admin-{$uniqueId}@test.com",
            'password' => bcrypt('password'),
            'membership' => 'admin',
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    /**
     * Create a language for testing
     */
    protected function createLanguage(array $attributes = []): \App\Models\Tenants\Language
    {
        // Ensure tenant context is active
        $tenant = property_exists($this, 'tenant') && $this->tenant ? $this->tenant : $this->createTestTenant();
        tenancy()->initialize($tenant);

        return \App\Models\Tenants\Language::create(array_merge([
            'id' => \Illuminate\Support\Str::uuid(),
            'code' => 'crk-' . \Illuminate\Support\Str::random(4),
            'name' => 'Plains Cree',
            'native_name' => 'nēhiyawēwin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    /**
     * Create a word for testing
     */
    protected function createWord(array $attributes = []): \App\Models\Tenants\Word
    {
        // Ensure tenant context is active
        $tenant = property_exists($this, 'tenant') && $this->tenant ? $this->tenant : $this->createTestTenant();
        tenancy()->initialize($tenant);

        $language = $attributes['language_id'] ?? $this->createLanguage()->id;
        return \App\Models\Tenants\Word::factory()->create(array_merge([
            'language_id' => $language,
        ], $attributes));
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
            // Ensure we have a language_id
            if (!isset($attributes['language_id'])) {
                $language = $this->createLanguage();
                $attributes['language_id'] = $language->id;
            }

            return \App\Models\Tenants\LearningPath::create(array_merge([
                'id' => \Illuminate\Support\Str::uuid(),
                'title' => 'Test Learning Path',
                'description' => 'A test learning path for automated testing',
                'target_level' => 'A1',
                'status' => 'published',
                'review_status' => 'approved',
                'created_at' => now(),
                'updated_at' => now(),
            ], $attributes));
        });
    }

    /**
     * Get or create language pair for testing (English -> Target Language)
     */
    protected function getOrCreateLanguagePair(\App\Models\Tenants\Language $targetLanguage): \App\Models\Tenants\LanguagePair
    {
        $english = \App\Models\Tenants\Language::where('code', 'en')->first();

        if (!$english) {
            $english = \App\Models\Tenants\Language::factory()->create([
                'code' => 'en',
                'name' => 'English',
                'native_name' => 'English',
            ]);
        }

        return \App\Models\Tenants\LanguagePair::firstOrCreate([
            'source_language_id' => $english->id,
            'target_language_id' => $targetLanguage->id,
        ], [
            'is_active' => true,
        ]);
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
