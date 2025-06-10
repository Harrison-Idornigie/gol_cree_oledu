<?php

namespace Tests\Feature;

use App\Models\Landlord\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TenantRegistrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Test successful tenant registration
     */
    public function test_successful_tenant_registration()
    {
        $tenantData = [
            'tenant' => [
                'name' => 'Test School District',
                'slug' => 'test-school-district',
                'description' => 'A test school district for language learning',
            ],
            'admin' => [
                'name' => 'John Admin',
                'email' => 'admin@test-school.edu',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]
        ];

        $response = $this->postJson('/api/auth/register-tenant-admin', $tenantData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'token',
                        'user' => [
                            'id',
                            'name',
                            'email',
                            'role',
                            'email_verified_at',
                            'tenant_id',
                            'tenant' => [
                                'id',
                                'name',
                                'slug',
                                'status'
                            ]
                        ]
                    ]
                ]);

        // Verify tenant was created
        $this->assertDatabaseHas('tenants', [
            'name' => 'Test School District',
            'slug' => 'test-school-district',
            'status' => 'trial'
        ]);

        // Verify response data
        $responseData = $response->json('data');
        $this->assertEquals('tenant-admin', $responseData['user']['role']);
        $this->assertEquals('test-school-district', $responseData['user']['tenant']['slug']);
        $this->assertNotEmpty($responseData['token']);
    }

    /**
     * Test tenant registration with duplicate slug
     */
    public function test_tenant_registration_with_duplicate_slug()
    {
        // Create existing tenant
        Tenant::create([
            'name' => 'Existing School',
            'slug' => 'test-school',
            'status' => 'active'
        ]);

        $tenantData = [
            'tenant' => [
                'name' => 'Test School',
                'slug' => 'test-school', // Duplicate slug
                'description' => 'Another test school',
            ],
            'admin' => [
                'name' => 'Jane Admin',
                'email' => 'admin@test-school2.edu',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]
        ];

        $response = $this->postJson('/api/auth/register-tenant-admin', $tenantData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['slug']);
    }

    /**
     * Test tenant registration validation
     */
    public function test_tenant_registration_validation()
    {
        $response = $this->postJson('/api/auth/register-tenant-admin', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors([
                    'tenant.name',
                    'admin.name',
                    'admin.email',
                    'admin.password'
                ]);
    }

    /**
     * Test slug validation endpoint
     */
    public function test_slug_validation_endpoint()
    {
        // Test available slug
        $response = $this->getJson('/api/auth/validate-tenant-slug/available-slug');
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'available' => true,
                        'error' => null
                    ]
                ]);

        // Create tenant and test unavailable slug
        Tenant::create([
            'name' => 'Existing School',
            'slug' => 'taken-slug',
            'status' => 'active'
        ]);

        $response = $this->getJson('/api/auth/validate-tenant-slug/taken-slug');
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'available' => false,
                        'error' => 'This organization slug is already taken'
                    ]
                ]);
    }
}
