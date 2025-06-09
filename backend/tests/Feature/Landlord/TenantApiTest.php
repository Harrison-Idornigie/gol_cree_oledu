<?php

namespace Tests\Feature\Landlord;

use App\Models\Landlord\Tenant;
use Illuminate\Support\Facades\Event;

class TenantApiTest extends TenantTestCase

    /** @test */
    public function it_can_create_tenant_via_api()
    {
        Event::fake();

        $tenantData = $this->getTenantCreationApiData([
            'slug' => 'test-school-api',
        ]);

        $response = $this->actingAsSuperAdmin()
                        ->postJson('/api/super-admin/tenants', $tenantData);

        $this->assertTenantCreationApiResponse($response);

        // Assert tenant was created in database
        $this->assertDatabaseHas('tenants', [
            'name' => $tenantData['name'],
            'slug' => $tenantData['slug'],
        ]);

        // Assert events were fired
        $this->assertTenantCreationEvents();
    }

    /** @test */
    public function it_requires_authentication_to_create_tenant()
    {
        $tenantData = [
            'name' => 'Test School District',
            'admin_name' => 'John Admin',
            'admin_email' => 'john@testschool.edu',
            'admin_password' => 'password123',
        ];

        $response = $this->postJson('/api/super-admin/tenants', $tenantData);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_requires_super_admin_role_to_create_tenant()
    {
        // Create regular user
        $regularUser = User::factory()->create();
        Sanctum::actingAs($regularUser);

        $tenantData = [
            'name' => 'Test School District',
            'admin_name' => 'John Admin',
            'admin_email' => 'john@testschool.edu',
            'admin_password' => 'password123',
        ];

        $response = $this->postJson('/api/super-admin/tenants', $tenantData);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_validates_required_fields_for_tenant_creation()
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->postJson('/api/super-admin/tenants', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors([
                    'name',
                    'admin_name',
                    'admin_email',
                    'admin_password',
                ]);
    }

    /** @test */
    public function it_validates_email_format()
    {
        Sanctum::actingAs($this->superAdmin);

        $tenantData = [
            'name' => 'Test School District',
            'admin_name' => 'John Admin',
            'admin_email' => 'invalid-email',
            'admin_password' => 'password123',
        ];

        $response = $this->postJson('/api/super-admin/tenants', $tenantData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['admin_email']);
    }

    /** @test */
    public function it_validates_unique_slug()
    {
        Sanctum::actingAs($this->superAdmin);

        // Create existing tenant
        Tenant::create([
            'name' => 'Existing School',
            'slug' => 'test-school',
            'status' => 'active',
        ]);

        $tenantData = [
            'name' => 'Test School District',
            'slug' => 'test-school', // Duplicate slug
            'admin_name' => 'John Admin',
            'admin_email' => 'john@testschool.edu',
            'admin_password' => 'password123',
        ];

        $response = $this->postJson('/api/super-admin/tenants', $tenantData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['slug']);
    }

    /** @test */
    public function it_can_list_tenants()
    {
        Sanctum::actingAs($this->superAdmin);

        // Create some test tenants
        Tenant::factory()->count(3)->create();

        $response = $this->getJson('/api/super-admin/tenants');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                ])
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'data' => [
                            '*' => [
                                'id',
                                'name',
                                'slug',
                                'status',
                                'created_at',
                                'updated_at',
                            ]
                        ],
                        'current_page',
                        'per_page',
                        'total',
                    ]
                ]);
    }

    /** @test */
    public function it_can_show_specific_tenant()
    {
        Sanctum::actingAs($this->superAdmin);

        $tenant = Tenant::create([
            'name' => 'Test School',
            'slug' => 'test-school',
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/super-admin/tenants/{$tenant->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $tenant->id,
                        'name' => 'Test School',
                        'slug' => 'test-school',
                        'status' => 'active',
                    ]
                ]);
    }

    /** @test */
    public function it_can_update_tenant()
    {
        Sanctum::actingAs($this->superAdmin);

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

        $response = $this->putJson("/api/super-admin/tenants/{$tenant->id}", $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'name' => 'Updated Name',
                        'description' => 'Updated description',
                        'status' => 'active',
                    ]
                ]);
    }

    /** @test */
    public function it_can_delete_tenant()
    {
        Event::fake();
        Sanctum::actingAs($this->superAdmin);

        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
        ]);

        $response = $this->deleteJson("/api/super-admin/tenants/{$tenant->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);

        // Assert cleanup event was fired
        Event::assertDispatched(\App\Events\Landlord\TenantDeleting::class);
    }
}
