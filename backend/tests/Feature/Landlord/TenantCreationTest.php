<?php

namespace Tests\Feature\Landlord;

use App\Models\Landlord\Tenant;
use App\Services\Landlord\TenantService;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

class TenantCreationTest extends TenantTestCase
{
    protected TenantService $tenantService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantService = app(TenantService::class);
    }

    /**  */
    public function it_validates_tenant_data_properly()
    {
        $this->expectException(ValidationException::class);

        $tenantData = [
            // Missing required 'name' field
            'slug' => 'test-school',
        ];

        $adminData = [
            'name' => 'John Admin',
            'email' => 'john@testschool.edu',
            'password' => 'password123',
        ];

        $this->tenantService->createTenant($tenantData, $adminData);
    }

    /**  */
    public function it_validates_admin_data_properly()
    {
        $this->expectException(ValidationException::class);

        $tenantData = [
            'name' => 'Test School District',
            'slug' => 'test-school',
        ];

        $adminData = [
            'name' => 'John Admin',
            // Missing required 'email' field
            'password' => 'password123',
        ];

        $this->tenantService->createTenant($tenantData, $adminData);
    }

    /**  */
    public function it_prevents_duplicate_slugs()
    {
        // Create first tenant
        Tenant::create([
            'name' => 'First School',
            'slug' => 'test-school',
            'status' => 'active',
        ]);

        $this->expectException(ValidationException::class);

        $tenantData = [
            'name' => 'Second School',
            'slug' => 'test-school', // Duplicate slug
        ];

        $adminData = [
            'name' => 'John Admin',
            'email' => 'john@testschool.edu',
            'password' => 'password123',
        ];

        $this->tenantService->createTenant($tenantData, $adminData);
    }

    /**  */
    public function it_prevents_both_domain_and_subdomain()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot specify both subdomain and custom domain');

        $tenantData = [
            'name' => 'Test School District',
            'slug' => 'test-school',
            'domain' => 'testschool.edu',
            'subdomain' => 'testschool', // Both domain and subdomain
        ];

        $adminData = [
            'name' => 'John Admin',
            'email' => 'john@testschool.edu',
            'password' => 'password123',
        ];

        $this->tenantService->createTenant($tenantData, $adminData);
    }

    /**  */
    public function it_can_update_tenant()
    {
        $tenant = Tenant::create([
            'name' => 'Original Name',
            'slug' => 'original-slug',
            'status' => 'trial',
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'status' => 'active',
        ];

        $updatedTenant = $this->tenantService->updateTenant($tenant, $updateData);

        $this->assertEquals('Updated Name', $updatedTenant->name);
        $this->assertEquals('Updated description', $updatedTenant->description);
        $this->assertEquals('active', $updatedTenant->status);
    }

    /**  */
    public function it_can_create_tenant_with_full_database_setup()
    {
        Event::fake();

        $tenantData = [
            'name' => 'Test School District',
            'slug' => 'test-school',
            'description' => 'A test school district',
            'contact_email' => 'admin@testschool.edu',
            'status' => 'trial',
        ];

        $adminData = [
            'name' => 'John Admin',
            'email' => 'john@testschool.edu',
            'password' => 'password123',
            'interface_language' => 'en',
        ];

        // Create tenant with full setup
        $result = $this->createFullTestTenant($tenantData, $adminData);
        $tenant = $result['tenant'];
        $adminUser = $result['admin_user'];

        // Assert tenant was created successfully
        $this->assertTenantCreatedSuccessfully($tenant, [
            'description' => 'A test school district',
            'contact_email' => 'admin@testschool.edu',
            'status' => 'trial',
        ]);

        // Assert admin user was created in tenant context
        $this->assertTenantAdminCreated($tenant, $adminData);

        // Assert admin user has correct attributes
        $this->assertEquals('John Admin', $adminUser->name);
        $this->assertEquals('john@testschool.edu', $adminUser->email);
        $this->assertEquals('en', $adminUser->interface_language);
    }

    /**  */
    public function it_can_create_tenant_with_domain()
    {
        $tenantData = [
            'name' => 'Test School District',
            'slug' => 'test-school',
            'domain' => 'testschool.edu',
        ];

        $adminData = [
            'name' => 'John Admin',
            'email' => 'john@testschool.edu',
            'password' => 'password123',
        ];

        $result = $this->createFullTestTenant($tenantData, $adminData);
        $tenant = $result['tenant'];

        // Assert tenant was created with domain
        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertEquals('testschool.edu', $tenant->domains->first()->domain);
    }

    /**  */
    public function it_can_create_tenant_with_subdomain()
    {
        $tenantData = [
            'name' => 'Test School District',
            'slug' => 'test-school',
            'subdomain' => 'testschool',
        ];

        $adminData = [
            'name' => 'John Admin',
            'email' => 'john@testschool.edu',
            'password' => 'password123',
        ];

        $result = $this->createFullTestTenant($tenantData, $adminData);
        $tenant = $result['tenant'];

        // Assert tenant was created with subdomain
        $this->assertInstanceOf(Tenant::class, $tenant);

        $expectedDomain = 'testschool.' . parse_url(config('app.url'), PHP_URL_HOST);
        $this->assertEquals($expectedDomain, $tenant->domains->first()->domain);
    }

    /**  */
    public function it_can_delete_tenant()
    {
        Event::fake();

        $tenant = $this->createTestTenant([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
        ]);

        $result = $this->tenantService->deleteTenant($tenant);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);

        // Assert cleanup event was fired
        Event::assertDispatched(\App\Events\Landlord\TenantDeleting::class);
    }

    /**  */
    public function it_can_test_tenant_service_with_mocked_database_creation()
    {
        Event::fake();

        $tenantData = [
            'name' => 'Test School District',
            'slug' => 'test-school',
            'description' => 'A test school district',
            'contact_email' => 'admin@testschool.edu',
            'status' => 'trial',
        ];

        $adminData = [
            'name' => 'John Admin',
            'email' => 'john@testschool.edu',
            'password' => 'password123',
            'interface_language' => 'en',
        ];

        // This will create tenant record but mock the database creation
        $tenant = $this->simulateTenantCreation($tenantData, $adminData);

        // Assert tenant was created
        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertEquals('Test School District', $tenant->name);
        $this->assertEquals('test-school', $tenant->slug);
        $this->assertEquals('trial', $tenant->status);

        // Assert tenant exists in database
        $this->assertDatabaseHas('tenants', [
            'name' => 'Test School District',
            'slug' => 'test-school',
            'status' => 'trial',
        ]);
    }
}
