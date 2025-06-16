<?php

namespace App\Services\Landlord;

use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\Role;
use App\Events\Landlord\TenantSetupCompleted;
use App\Events\Landlord\TenantSeedingRequested;
use App\Events\Landlord\TenantDeleting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Exception;

/**
 * Tenant Service
 * 
 * Handles comprehensive tenant management operations including:
 * - Tenant creation with database initialization
 * - Admin user setup and role assignment
 * - Event-driven seeding and configuration
 * - Validation and error handling with rollback
 * - Tenant updates and deletion
 */
class TenantService
{
    /**
     * Get filtered tenants with pagination
     */
    public function getTenants(Request $request): LengthAwarePaginator
    {
        $query = Tenant::query();

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('contact_email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('trial_status')) {
            if ($request->trial_status === 'active') {
                $query->onTrial();
            } elseif ($request->trial_status === 'expired') {
                $query->whereNotNull('trial_ends_at')
                      ->where('trial_ends_at', '<=', now());
            }
        }

        // Include relationships if requested
        if ($request->has('with_stats')) {
            $query->withCount(['domains']);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->input('per_page', 15);
        return $query->paginate($perPage);
    }

    /**
     * Create a new tenant with complete setup
     */
    public function createTenant(array $tenantData, array $adminData, ?string $progressId = null): array
    {
        // Validate input data
        $this->validateTenantData($tenantData);
        $this->validateAdminData($adminData);

        DB::beginTransaction();
        try {
            Log::info('Starting tenant creation process', [
                'tenant_name' => $tenantData['name'],
                'admin_email' => $adminData['email'],
                'progress_id' => $progressId
            ]);

            $this->updateProgress($progressId, 'validating', 'Validating tenant data...', 10);

            // Step 1: Create the tenant record
            $this->updateProgress($progressId, 'creating_tenant', 'Creating organization record...', 20);
            $tenant = $this->createTenantRecord($tenantData);

            // Step 2: Create domains if provided
            $this->updateProgress($progressId, 'creating_domains', 'Setting up domains...', 30);
            $this->createTenantDomains($tenant, $tenantData);

            // Step 3: Create admin user in central database first
            $this->updateProgress($progressId, 'creating_central_admin', 'Creating central admin record...', 40);
            $centralAdminUser = $this->createCentralAdminUser($adminData);

            // Step 4: Initialize tenant database (via Stancl events)
            $this->updateProgress($progressId, 'creating_database', 'Creating tenant database...', 50);
            Log::info('About to initialize tenant database', [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
                'auth_model_config' => config('auth.providers.users.model')
            ]);
            $this->initializeTenantDatabase($tenant);

            // Step 5: Create admin user in tenant database (copy from central)
            $this->updateProgress($progressId, 'creating_tenant_admin', 'Creating tenant admin user...', 70);
            $adminUser = $this->createTenantAdminUser($tenant, $centralAdminUser, $adminData);

            // Step 5: Fire tenant setup completed event
            $this->updateProgress($progressId, 'setting_up_data', 'Setting up default data...', 80);
            event(new TenantSetupCompleted($tenant, $adminUser, [
                'created_via' => 'tenant_service',
                'has_admin_user' => true
            ]));

            // Step 6: Run critical seeding synchronously within transaction
            $this->updateProgress($progressId, 'seeding_data', 'Seeding initial content...', 90);

            // Verify database is still ready before seeding
            $this->verifyTenantDatabaseReady($tenant);

            // Run critical seeding synchronously to ensure completion before API response
            $this->runCriticalSeeding($tenant, $adminUser);

            DB::commit();

            $this->updateProgress($progressId, 'completed', 'Organization created successfully!', 100);

            Log::info('Tenant creation completed successfully', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'admin_user_id' => $adminUser->id,
                'progress_id' => $progressId
            ]);

            return [
                'tenant' => $tenant->load(['domains']),
                'admin_user' => $adminUser
            ];

        } catch (Exception $e) {
            DB::rollBack();

            $this->updateProgress($progressId, 'failed', 'Failed to create organization: ' . $e->getMessage(), 0, $e->getMessage());

            Log::error('Tenant creation failed', [
                'tenant_name' => $tenantData['name'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'progress_id' => $progressId
            ]);

            // Cleanup any partially created resources
            $this->cleanupFailedTenantCreation($tenantData);

            throw new Exception('Failed to create tenant: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Update progress for tenant creation
     */
    protected function updateProgress(?string $progressId, string $stage, string $message, int $percentage, ?string $error = null): void
    {
        if (!$progressId) {
            return;
        }

        $progressData = [
            'stage' => $stage,
            'message' => $message,
            'percentage' => $percentage,
            'timestamp' => now()->toISOString(),
        ];

        if ($error) {
            $progressData['error'] = $error;
        }

        // Store progress in cache with 10-minute expiration
        Cache::put("tenant_creation_progress:{$progressId}", $progressData, 600);

        // Optionally broadcast progress via WebSocket/SSE
        // broadcast(new TenantCreationProgress($progressId, $progressData));
    }

    /**
     * Update an existing tenant
     */
    public function updateTenant(Tenant $tenant, array $data): Tenant
    {
        $this->validateTenantUpdateData($data, $tenant);

        DB::beginTransaction();
        try {
            $oldData = $tenant->toArray();
            $tenant->update($data);

            Log::info('Tenant updated successfully', [
                'tenant_id' => $tenant->id,
                'changes' => array_diff_assoc($data, $oldData)
            ]);

            DB::commit();
            return $tenant->fresh();

        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Tenant update failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage()
            ]);
            
            throw new Exception('Failed to update tenant: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Delete a tenant and cleanup all resources
     */
    public function deleteTenant(Tenant $tenant): bool
    {
        DB::beginTransaction();
        try {
            Log::info('Starting tenant deletion process', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name
            ]);

            // Fire tenant deleting event for cleanup
            event(new TenantDeleting($tenant));

            // Delete tenant (Stancl will handle database cleanup)
            $tenant->delete();

            DB::commit();

            Log::info('Tenant deleted successfully', [
                'tenant_id' => $tenant->id
            ]);

            return true;

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Tenant deletion failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage()
            ]);

            throw new Exception('Failed to delete tenant: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate tenant data
     */
    protected function validateTenantData(array $data): void
    {
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:tenants,slug',
            'domain' => 'nullable|string|max:255|unique:domains,domain',
            'subdomain' => 'nullable|string|max:255|unique:domains,domain',
            'custom_domain' => 'nullable|string|max:255|unique:domains,domain',
            'description' => 'nullable|string|max:1000',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'settings' => 'nullable|array',
            'contact_info' => 'nullable|array',
            'trial_ends_at' => 'nullable|date|after:today',
            'subscription_ends_at' => 'nullable|date|after:trial_ends_at',
            'status' => 'nullable|in:active,inactive,suspended,trial',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Additional business logic validation
        if (!empty($data['subdomain']) && !empty($data['domain'])) {
            throw new Exception('Cannot specify both subdomain and custom domain');
        }
    }

    /**
     * Validate admin user data
     */
    protected function validateAdminData(array $data): void
    {
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:central_users,email',
            'password' => 'required|string|min:8',
            'interface_language' => 'nullable|string|in:en,es,fr,de,ja,ko,zh',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Validate tenant update data
     */
    protected function validateTenantUpdateData(array $data, Tenant $tenant): void
    {
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', "unique:tenants,slug,{$tenant->id}"],
            'description' => 'nullable|string|max:1000',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'settings' => 'nullable|array',
            'contact_info' => 'nullable|array',
            'trial_ends_at' => 'nullable|date',
            'subscription_ends_at' => 'nullable|date',
            'status' => 'required|in:active,inactive,suspended,trial',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Create the tenant record
     */
    protected function createTenantRecord(array $data): Tenant
    {
        // Set default values
        $tenantData = array_merge([
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(30),
            'settings' => [],
        ], $data);

        return Tenant::create($tenantData);
    }

    /**
     * Create tenant domains
     */
    protected function createTenantDomains(Tenant $tenant, array $data): void
    {
        // Create custom domain if provided
        if (!empty($data['domain'])) {
            $tenant->domains()->create(['domain' => $data['domain']]);
        }

        // Create subdomain if provided
        if (!empty($data['subdomain'])) {
            $centralDomain = parse_url(config('app.url'), PHP_URL_HOST);
            $fullDomain = $data['subdomain'] . '.' . $centralDomain;
            $tenant->domains()->create(['domain' => $fullDomain]);
        }

        // Create custom domain if provided
        if (!empty($data['custom_domain'])) {
            $tenant->domains()->create(['domain' => $data['custom_domain']]);
        }
    }

    /**
     * Initialize tenant database and wait for completion
     */
    protected function initializeTenantDatabase(Tenant $tenant): void
    {
        Log::info('Tenant database initialization triggered', [
            'tenant_id' => $tenant->id,
            'database_name' => $tenant->database_name ?? $tenant->id
        ]);

        // Stancl automatically handles database creation and migration
        // through the TenantCreated event pipeline, but we need to wait for completion
        $this->waitForTenantDatabaseReady($tenant);

        Log::info('Tenant database initialization completed', [
            'tenant_id' => $tenant->id,
            'database_name' => $tenant->database_name ?? $tenant->id
        ]);
    }

    /**
     * Wait for tenant database to be ready (created and migrated)
     */
    protected function waitForTenantDatabaseReady(Tenant $tenant): void
    {
        $maxAttempts = 30; // 30 seconds timeout
        $attempt = 0;
        $delay = 1; // 1 second between attempts

        Log::info('Waiting for tenant database to be ready', [
            'tenant_id' => $tenant->id,
            'max_attempts' => $maxAttempts
        ]);

        while ($attempt < $maxAttempts) {
            try {
                // Try to connect to tenant database and verify essential tables exist
                $tenant->run(function () {
                    // Test database connection (should be automatically switched to tenant)
                    $connection = DB::connection();
                    $connection->getPdo();

                    // Verify essential tables exist (migrations completed)
                    $tables = ['users', 'roles', 'languages'];
                    foreach ($tables as $table) {
                        DB::select("SELECT 1 FROM {$table} LIMIT 1");
                    }
                });

                Log::info('Tenant database is ready', [
                    'tenant_id' => $tenant->id,
                    'attempts' => $attempt + 1
                ]);
                return; // Success - database is ready

            } catch (Exception $e) {
                $attempt++;

                Log::debug('Tenant database not ready yet', [
                    'tenant_id' => $tenant->id,
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'error' => $e->getMessage()
                ]);

                if ($attempt >= $maxAttempts) {
                    Log::error('Tenant database failed to become ready', [
                        'tenant_id' => $tenant->id,
                        'attempts' => $attempt,
                        'last_error' => $e->getMessage()
                    ]);

                    throw new Exception(
                        "Tenant database not ready after {$maxAttempts} seconds. " .
                        "Database creation or migration may have failed. " .
                        "Last error: " . $e->getMessage()
                    );
                }

                sleep($delay);
            }
        }
    }

    /**
     * Quick verification that tenant database is still ready
     */
    protected function verifyTenantDatabaseReady(Tenant $tenant): void
    {
        try {
            $tenant->run(function () {
                // Quick connection test (should be automatically switched to tenant)
                DB::connection()->getPdo();
                // Verify users table exists (essential for admin user creation)
                DB::select('SELECT 1 FROM users LIMIT 1');
            });
        } catch (Exception $e) {
            Log::error('Tenant database verification failed before seeding', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage()
            ]);

            throw new Exception(
                'Tenant database became unavailable before seeding. ' .
                'This may indicate a database connection issue. ' .
                'Error: ' . $e->getMessage()
            );
        }
    }

    /**
     * Run critical seeding synchronously within the transaction
     * This ensures essential setup completes before API response
     */
    protected function runCriticalSeeding(Tenant $tenant, User $adminUser): void
    {
        try {
            Log::info('Starting critical tenant seeding', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name
            ]);

            // Dispatch seeding event synchronously (not queued)
            event(new TenantSeedingRequested($tenant, $adminUser, [
                'seed_roles' => true,
                'seed_languages' => true,
                'seed_settings' => true,
                'database_ready' => true,
                'critical_seeding' => true, // Flag to indicate this is critical seeding
            ]));

            Log::info('Critical tenant seeding completed successfully', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name
            ]);

        } catch (Exception $e) {
            Log::error('Critical tenant seeding failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Critical seeding failure should cause transaction rollback
            throw new Exception(
                'Critical tenant seeding failed. This indicates a serious setup issue. ' .
                'Error: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Create admin user in central database (Phase 1)
     */
    protected function createCentralAdminUser(array $adminData): \App\Models\Landlord\CentralUser
    {
        return \App\Models\Landlord\CentralUser::create([
            'name' => $adminData['name'],
            'email' => $adminData['email'],
            'password' => Hash::make($adminData['password']),
            'role' => 'tenant-admin',
            'interface_language' => $adminData['interface_language'] ?? 'en',
            'is_active' => true,
        ]);
    }

    /**
     * Create admin user in tenant database (Phase 2)
     */
    protected function createTenantAdminUser(Tenant $tenant, \App\Models\Landlord\CentralUser $centralUser, array $adminData): User
    {
        // In testing environment, we might not have actual tenant databases
        if (app()->environment('testing') && !$this->tenantDatabaseExists($tenant)) {
            // Create a mock user for testing
            return new User([
                'id' => 1,
                'name' => $centralUser->name,
                'email' => $centralUser->email,
                'interface_language' => $centralUser->interface_language,
                'email_verified_at' => now(),
                'tenant_id' => $tenant->id,
            ]);
        }

        $adminUser = null;

        // Switch to tenant context to create user
        $tenant->run(function () use ($tenant, $centralUser, $adminData, &$adminUser) {
            Log::info('Creating admin user in tenant context', [
                'tenant_id' => $tenant->id,
                'current_database' => DB::connection()->getDatabaseName(),
                'user_model_class' => User::class,
                'admin_email' => $centralUser->email,
                'central_user_id' => $centralUser->id
            ]);

            try {
                $adminUser = User::create([
                    'name' => $centralUser->name,
                    'email' => $centralUser->email,
                    'password' => $centralUser->password, // Already hashed
                    'interface_language' => $centralUser->interface_language,
                    'email_verified_at' => now(),
                    'role' => 'admin', // Set to 'admin' (allowed enum value)
                    'tenant_id' => $tenant->id,
                    // 'central_user_id' => $centralUser->id, // TODO: Enable after migration
                ]);

                Log::info('Admin user created successfully in tenant database', [
                    'user_id' => $adminUser->id,
                    'tenant_id' => $tenant->id,
                    'central_user_id' => $centralUser->id
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to create admin user in tenant database', [
                    'tenant_id' => $tenant->id,
                    'central_user_id' => $centralUser->id,
                    'error' => $e->getMessage(),
                    'database' => DB::connection()->getDatabaseName()
                ]);
                throw $e;
            }

            // Note: Role creation and assignment is handled by the seeding events
            // This ensures proper separation of concerns and consistent seeding process
        });

        if (!$adminUser) {
            throw new Exception('Failed to create admin user in tenant database');
        }

        return $adminUser;
    }

    /**
     * Legacy method - kept for backward compatibility
     * @deprecated Use createTenantAdminUser instead
     */
    protected function createAdminUser(Tenant $tenant, array $adminData): User
    {
        // In testing environment, we might not have actual tenant databases
        if (app()->environment('testing') && !$this->tenantDatabaseExists($tenant)) {
            // Create a mock user for testing
            return new User([
                'id' => 1,
                'name' => $adminData['name'],
                'email' => $adminData['email'],
                'interface_language' => $adminData['interface_language'] ?? 'en',
                'email_verified_at' => now(),
                'tenant_id' => $tenant->id,
            ]);
        }

        $adminUser = null;

        // Switch to tenant context to create user
        $tenant->run(function () use ($tenant, $adminData, &$adminUser) {
            Log::info('Creating admin user in tenant context', [
                'tenant_id' => $tenant->id,
                'current_database' => DB::connection()->getDatabaseName(),
                'user_model_class' => User::class,
                'admin_email' => $adminData['email']
            ]);
            
            try {
                $adminUser = User::create([
                    'name' => $adminData['name'],
                    'email' => $adminData['email'],
                    'password' => Hash::make($adminData['password']),
                    'interface_language' => $adminData['interface_language'] ?? 'en',
                    'email_verified_at' => now(),
                    'tenant_id' => $tenant->id,
                ]);
                
                Log::info('Admin user created successfully', [
                    'user_id' => $adminUser->id,
                    'tenant_id' => $tenant->id
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to create admin user', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                    'database' => DB::connection()->getDatabaseName()
                ]);
                throw $e;
            }

            // Create tenant admin role if it doesn't exist
            Log::info('About to create role in tenant context', [
                'tenant_id' => $tenant->id,
                'current_database' => DB::connection()->getDatabaseName(),
                'connection_name' => DB::connection()->getName()
            ]);

            $tenantAdminRole = Role::firstOrCreate([
                'slug' => 'tenant-admin',
                'tenant_id' => $tenant->id,
            ], [
                'name' => 'Tenant Administrator',
                'description' => "Administrator for {$tenant->name}",
                'is_system' => true,
            ]);

            // Assign tenant admin role
            $adminUser->roles()->attach($tenantAdminRole);
        });

        if (!$adminUser) {
            throw new Exception('Failed to create admin user');
        }

        return $adminUser;
    }

    /**
     * Check if tenant database exists
     */
    protected function tenantDatabaseExists(Tenant $tenant): bool
    {
        // In testing environment, assume database doesn't exist unless explicitly created
        if (app()->environment('testing')) {
            return false;
        }

        try {
            // Try to switch to tenant context to verify database exists
            $tenant->run(function () {
                // If we can run this, database exists
                return true;
            });
            return true;
        } catch (Exception $e) {
            // If any error occurs, assume database doesn't exist
            return false;
        }
    }

    /**
     * Cleanup failed tenant creation
     */
    protected function cleanupFailedTenantCreation(array $tenantData): void
    {
        try {
            // Try to find and delete any partially created tenant
            if (!empty($tenantData['slug'])) {
                $tenant = Tenant::where('slug', $tenantData['slug'])->first();
                if ($tenant) {
                    $tenant->delete();
                }
            }
        } catch (Exception $e) {
            Log::warning('Failed to cleanup partially created tenant', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
