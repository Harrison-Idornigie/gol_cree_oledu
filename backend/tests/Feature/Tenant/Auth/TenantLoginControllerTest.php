<?php

namespace Tests\Feature\Tenant\Auth;

use Tests\TenantTestCase;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
// use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Hash;

class TenantLoginControllerTest extends TenantTestCase
{

    protected Tenant $tenant;
    protected User $user;
    protected string $password = 'password123';

    protected function setUp(): void
    {
        parent::setUp();

        // Create test tenant using Stancl's simple approach
        $this->tenant = Tenant::create([
            'id' => 'test_' . Str::random(8),
            'name' => 'Test Tenant ' . Str::random(8),
            'slug' => 'test-tenant-' . Str::random(8),
        ]);

        // Initialize tenancy and create user
        tenancy()->initialize($this->tenant);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test_' . Str::random(5) . '@example.com',
            'password' => Hash::make($this->password),
            'membership' => 'student',
            'interface_language' => 'en',
            'email_verified_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up tenant
        if (isset($this->tenant)) {
            $this->tenant->delete();
        }

        parent::tearDown();
    }

    /** @test */
    public function user_can_login_with_valid_credentials()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/tenant-login", [
            'email' => $this->user->email,
            'password' => $this->password,
            'device_name' => 'test-device'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'token',
                    'user' => [
                        'tenant'
                    ],
                    'redirect',
                    'auth_context',
                    'tenant_slug'
                ],
                'message'
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'email' => $this->user->email,
                        'name' => $this->user->name,
                        'tenant' => [
                            'slug' => $this->tenant->slug
                        ]
                    ],
                    'auth_context' => 'tenant',
                    'tenant_slug' => $this->tenant->slug
                ],
                'message' => 'Successfully logged in'
            ]);

        // Verify token exists
        $this->assertNotEmpty($response->json('data.token'));
    }

    /** @test */
    public function login_fails_with_incorrect_password()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/tenant-login", [
            'email' => $this->user->email,
            'password' => 'wrong-password',
            'device_name' => 'test-device'
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials'
            ]);
    }

    /** @test */
    public function login_fails_with_nonexistent_email()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/tenant-login", [
            'email' => 'nonexistent@example.com',
            'password' => $this->password,
            'device_name' => 'test-device'
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials'
            ]);
    }

    /** @test */
    public function login_requires_device_name()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/tenant-login", [
            'email' => $this->user->email,
            'password' => $this->password
            // Missing device_name
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['device_name']);
    }

    /** @test */
    public function user_can_logout()
    {
        // First login to get a token
        $loginResponse = $this->postJson("/api/{$this->tenant->slug}/auth/tenant-login", [
            'email' => $this->user->email,
            'password' => $this->password,
            'device_name' => 'test-device'
        ]);

        $token = $loginResponse->json('data.token');

        // Then logout using that token
        $logoutResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/{$this->tenant->slug}/auth/tenant-logout");

        $logoutResponse->assertStatus(200)
            ->assertJson([
                'message' => 'Logged out successfully'
            ]);

        // Try to use the token again (should fail with 401)
        $verifyResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson("/api/{$this->tenant->slug}/auth/me");

        $verifyResponse->assertStatus(401);
    }

    /** @test */
    public function user_can_get_list_of_tenants()
    {
        // First login to get authenticated
        Sanctum::actingAs($this->user, [], 'tenant');

        // Test endpoint to get user's tenants
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/tenant-user-tenants", [
            'email' => $this->user->email
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'tenants' => [
                    '*' => [
                        'id',
                        'name',
                        'slug'
                    ]
                ]
            ]);

        // Should contain the current tenant
        $response->assertJsonFragment([
            'slug' => $this->tenant->slug
        ]);
    }

    /** @test */
    public function unverified_user_can_still_login()
    {
        // Create an unverified user
        $unverifiedUser = $this->runInTenantContext($this->tenant, function () {
            return User::create([
                'name' => 'Unverified User',
                'email' => 'unverified_' . Str::random(5) . '@example.com',
                'password' => Hash::make($this->password),
                'email_verified_at' => null, // Not verified
            ]);
        });

        // Attempt login
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/tenant-login", [
            'email' => $unverifiedUser->email,
            'password' => $this->password,
            'device_name' => 'test-device'
        ]);

        // Login should succeed but response should indicate unverified status
        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'email_verified_at'
                ],
                'token',
                'tenant'
            ])
            ->assertJson([
                'user' => [
                    'email' => $unverifiedUser->email,
                    'email_verified_at' => null
                ]
            ]);
    }

    /** @test */
    public function login_validates_email_format()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/tenant-login", [
            'email' => 'invalid-email',
            'password' => $this->password,
            'device_name' => 'test-device'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
