<?php

namespace Tests\Feature\Landlord\Auth;

use Tests\Feature\Landlord\TenantTestCase;
use App\Models\Landlord\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;

class TenantRegistrationTest extends TenantTestCase
{
    use RefreshDatabase;

    /**
     * Test tenant admin registration
     * 
     * 
     */
    public function test_super_admin_can_register_new_tenant_admin()
    {
        Sanctum::actingAs($this->superAdmin, ['central']);

        $registrationData = [
            'tenant' => [
                'name' => 'Test Company',
                'slug' => 'test-company-' . Str::random(5),
                'email' => 'admin@testcompany.com',
                'phone' => '+1234567890',
                'address' => '123 Test St, Test City, TC 12345'
            ],
            'admin' => [
                'name' => 'Admin User',
                'email' => 'admin@testcompany.com',
                'password' => 'SecurePassword123!',
                'password_confirmation' => 'SecurePassword123!'
            ]
        ];

        $response = $this->postJson('/api/super-admin/register-tenant-admin', $registrationData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'tenant' => [
                    'id',
                    'name',
                    'slug',
                    'status'
                ],
                'admin' => [
                    'id',
                    'name',
                    'email'
                ],
                'progress_id'
            ]);
    }

    /**
     * Test tenant slug validation
     * 
     * 
     */
    public function test_can_validate_tenant_slug_availability()
    {
        $slug = 'available-slug-' . Str::random(5);

        $response = $this->getJson("/api/super-admin/validate-tenant-slug/{$slug}");

        $response->assertStatus(200)
            ->assertJson([
                'available' => true,
                'slug' => $slug
            ]);
    }

    /**
     * Test tenant slug validation for existing slug
     * 
     * 
     */
    public function test_validation_fails_for_existing_tenant_slug()
    {
        $tenant = $this->createTestTenant();

        $response = $this->getJson("/api/super-admin/validate-tenant-slug/{$tenant->slug}");

        $response->assertStatus(200)
            ->assertJson([
                'available' => false,
                'slug' => $tenant->slug
            ]);
    }

    /**
     * Test tenant creation progress tracking
     * 
     * 
     */
    public function test_can_check_tenant_creation_progress()
    {
        $progressId = 'test-progress-' . Str::random(8);

        $response = $this->getJson("/api/super-admin/tenant-creation-progress/{$progressId}");

        // Should return progress info or 404 if progress not found
        $this->assertContains($response->status(), [200, 404]);

        if ($response->status() === 200) {
            $response->assertJsonStructure([
                'progress_id',
                'status',
                'steps',
                'current_step',
                'completed'
            ]);
        }
    }

    /**
     * Test registration validation
     * 
     * 
     */
    public function test_tenant_registration_validates_required_fields()
    {
        Sanctum::actingAs($this->superAdmin, ['central']);

        $invalidData = [
            'tenant' => [
                'name' => '',
                'slug' => '',
                'email' => 'invalid-email'
            ],
            'admin' => [
                'name' => '',
                'email' => 'invalid-email',
                'password' => '123',
                'password_confirmation' => '456'
            ]
        ];

        $response = $this->postJson('/api/super-admin/register-tenant-admin', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'tenant.name',
                'tenant.slug',
                'tenant.email',
                'admin.name',
                'admin.email',
                'admin.password'
            ]);
    }

    /**
     * Test duplicate slug handling
     * 
     * 
     */
    public function test_registration_fails_with_duplicate_slug()
    {
        Sanctum::actingAs($this->superAdmin, ['central']);

        $existingTenant = $this->createTestTenant();

        $registrationData = [
            'tenant' => [
                'name' => 'Another Company',
                'slug' => $existingTenant->slug, // Use existing slug
                'email' => 'another@company.com',
            ],
            'admin' => [
                'name' => 'Another Admin',
                'email' => 'another@company.com',
                'password' => 'SecurePassword123!',
                'password_confirmation' => 'SecurePassword123!'
            ]
        ];

        $response = $this->postJson('/api/super-admin/register-tenant-admin', $registrationData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tenant.slug']);
    }

    /**
     * Test duplicate email handling
     * 
     * 
     */
    public function test_registration_fails_with_duplicate_email()
    {
        Sanctum::actingAs($this->superAdmin, ['central']);

        $registrationData = [
            'tenant' => [
                'name' => 'Test Company',
                'slug' => 'test-company-' . Str::random(5),
                'email' => $this->superAdmin->email, // Use existing email
            ],
            'admin' => [
                'name' => 'Admin User',
                'email' => $this->superAdmin->email, // Use existing email
                'password' => 'SecurePassword123!',
                'password_confirmation' => 'SecurePassword123!'
            ]
        ];

        $response = $this->postJson('/api/super-admin/register-tenant-admin', $registrationData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['admin.email']);
    }

    /**
     * Test registration requires super admin auth
     * 
     * 
     */
    public function test_tenant_registration_requires_super_admin_auth()
    {
        $registrationData = [
            'tenant' => [
                'name' => 'Test Company',
                'slug' => 'test-company',
                'email' => 'admin@testcompany.com',
            ],
            'admin' => [
                'name' => 'Admin User',
                'email' => 'admin@testcompany.com',
                'password' => 'SecurePassword123!',
                'password_confirmation' => 'SecurePassword123!'
            ]
        ];

        // Unauthenticated request
        $response = $this->postJson('/api/super-admin/register-tenant-admin', $registrationData);
        $response->assertStatus(401);
    }
}
