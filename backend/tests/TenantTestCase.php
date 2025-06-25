<?php

namespace Tests;

use App\Models\Landlord\Tenant;
use App\Models\Landlord\CentralUser;
use App\Models\Tenants\User;
use App\Models\Tenants\Membership;
use App\Models\Tenants\Role;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\TestCase;
use Tests\Traits\InteractsWithTenancy;

/**
 * Base test case for multi-tenant testing
 *
 * Provides common setup and utilities for testing tenant-related functionality
 */
abstract class TenantTestCase extends TestCase
{
    use WithFaker, InteractsWithTenancy;

    /**
     * This test case requires tenancy setup
     */
    protected bool $requiresTenancy = true;

    /**
     * Super admin user for testing
     */
    protected CentralUser $superAdmin;

    /**
     * Setup the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Setup tenancy testing environment
        $this->setUpTenancy();

        // Create super admin for API testing
        $this->createSuperAdmin();
    }

    /**
     * Cleanup after tests
     */
    protected function tearDown(): void
    {
        // Cleanup tenancy testing environment
        $this->tearDownTenancy();

        parent::tearDown();
    }

    /**
     * Create super admin user for testing
     */
    protected function createSuperAdmin(): void
    {
        // Create super admin user in central database
        $this->superAdmin = CentralUser::create([
            'email' => 'superadmin@example.com',
            'name' => 'Super Admin',
            'membership' => 'super-admin',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    /**
     * Authenticate as super admin for API testing
     */
    protected function actingAsSuperAdmin(): self
    {
        Sanctum::actingAs($this->superAdmin);
        return $this;
    }

    /**
     * Create a test tenant with full setup including admin user
     */
    protected function createFullTestTenant(array $tenantAttributes = [], array $adminAttributes = []): array
    {
        // Create tenant with database
        $tenant = $this->createTestTenant($tenantAttributes);

        // Default admin attributes
        $defaultAdminAttributes = [
            'name' => 'Tenant Admin',
            'email' => 'admin@' . $tenant->slug . '.test',
            'password' => 'password123',
            'interface_language' => 'en',
            'membership' => 'admin',
        ];

        $adminData = array_merge($defaultAdminAttributes, $adminAttributes);

        // Create admin user and role in tenant context
        $adminUser = $this->runInTenantContext($tenant, function () use ($adminData, $tenant) {
            // Create tenant admin role
            $tenantAdminMembership = Role::create([
                'name' => 'Tenant Administrator',
                'slug' => 'tenant-admin',
                'description' => "Administrator for {$tenant->name}",
                'is_system' => true,
                'tenant_id' => $tenant->id,
            ]);

            // Create admin user
            $adminUser = User::create([
                'name' => $adminData['name'],
                'email' => $adminData['email'],
                'password' => bcrypt($adminData['password']),
                'interface_language' => $adminData['interface_language'],
                'membership' => 'admin',
                'email_verified_at' => now(),
                'tenant_id' => $tenant->id,
            ]);

            // Assign tenant admin role
            $adminUser->roles()->attach($tenantAdminMembership);

            return $adminUser;
        });

        return [
            'tenant' => $tenant,
            'admin_user' => $adminUser,
            'admin_data' => $adminData,
        ];
    }

    /**
     * Assert tenant creation was successful
     */
    protected function assertTenantCreatedSuccessfully(Tenant $tenant, array $expectedData = []): void
    {
        // Assert tenant exists in central database
        $this->assertDatabaseHas('tenants', array_merge([
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
        ], $expectedData));

        // Assert tenant database exists and is properly migrated
        $this->assertTenantDatabaseExists($tenant);

        // Assert essential tables exist in tenant database
        $this->assertTenantHasTable($tenant, 'users');
        $this->assertTenantHasTable($tenant, 'roles');
        $this->assertTenantHasTable($tenant, 'role_user');
    }

    /**
     * Assert admin user was created successfully in tenant
     */
    protected function assertTenantAdminCreated(Tenant $tenant, array $expectedAdminData): void
    {
        $this->runInTenantContext($tenant, function () use ($expectedAdminData) {
            // Assert admin user exists
            $this->assertDatabaseHas('users', [
                'email' => $expectedAdminData['email'],
                'name' => $expectedAdminData['name'],
            ]);

            // Assert tenant admin role exists
            $this->assertDatabaseHas('roles', [
                'slug' => 'tenant-admin',
                'name' => 'Tenant Administrator',
            ]);

            // Assert admin user has tenant admin role
            $adminUser = User::where('email', $expectedAdminData['email'])->first();
            $this->assertTrue($adminUser->isTenantAdmin());
        });
    }

    /**
     * Assert events were fired during tenant creation
     */
    protected function assertTenantCreationEvents(): void
    {
        Event::assertDispatched(TenantCreated::class);
        // Add other tenant creation events as needed
    }

    /**
     * Create API request data for tenant creation
     */
    protected function getTenantCreationApiData(array $overrides = []): array
    {
        $defaults = [
            'name' => 'Test School District',
            'slug' => 'test-school-' . $this->faker->randomNumber(4),
            'description' => 'A test school district for automated testing',
            'contact_email' => 'contact@testschool.edu',
            'admin_name' => 'John Admin',
            'admin_email' => 'john@testschool.edu',
            'admin_password' => 'password123',
            'admin_interface_language' => 'en',
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * Assert API response structure for tenant creation
     */
    protected function assertTenantCreationApiResponse($response): void
    {
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Tenant created successfully.',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'status',
                    'created_at',
                    'updated_at',
                ]
            ]);
    }

    /**
     * Mock tenant database creation for unit tests
     */
    protected function mockTenantDatabaseCreation(): void
    {
        Event::fake([
            \Stancl\Tenancy\Events\TenantCreated::class,
            \Stancl\Tenancy\Events\DatabaseCreated::class,
            \Stancl\Tenancy\Events\DatabaseMigrated::class,
        ]);
    }

    /**
     * Simulate tenant database creation without actual database operations
     */
    protected function simulateTenantCreation(array $tenantData): Tenant
    {
        $this->mockTenantDatabaseCreation();

        // Create tenant record only
        $tenant = Tenant::create($tenantData);

        // Track for cleanup
        $this->createdTenants[] = $tenant;

        return $tenant;
    }
}
