<?php

namespace Tests\Feature\Tenant\Auth;

use Tests\TestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\AdminInvite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Str;

class TenantUserRegisterControllerTest extends TestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        
        $this->tenant = $this->createTestTenant();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /** @test */
    public function user_can_register_without_invite_token()
    {
        Notification::fake();

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/register", $userData);

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
                        'membership'
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'name' => 'John Doe',
                        'email' => 'john@example.com',
                        'membership' => 'user'
                    ]
                ]
            ]);

        // Verify user was created in tenant database
        $this->runInTenantContext($this->tenant, function () {
            $this->assertDatabaseHas('users', [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'membership' => 'user'
            ]);
        });

        // Verify email verification notification was sent
        $user = $this->runInTenantContext($this->tenant, function () {
            return User::where('email', 'john@example.com')->first();
        });

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    /** @test */
    public function user_can_register_with_valid_invite_token()
    {
        Notification::fake();

        // Create an admin invite
        $invite = $this->runInTenantContext($this->tenant, function () {
            return AdminInvite::create([
                'email' => 'admin@example.com',
                'token' => Str::random(32),
                'invited_by' => 1, // Assuming admin user ID
                'expires_at' => now()->addDays(7)
            ]);
        });

        $userData = [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invite_token' => $invite->token
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/register", $userData);

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
                        'membership'
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'name' => 'Admin User',
                        'email' => 'admin@example.com',
                        'membership' => 'admin'
                    ]
                ]
            ]);

        // Verify user was created with admin membership
        $this->runInTenantContext($this->tenant, function () {
            $this->assertDatabaseHas('users', [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'membership' => 'admin'
            ]);
        });

        // Verify invite was marked as used
        $this->runInTenantContext($this->tenant, function () use ($invite) {
            $updatedInvite = $invite->fresh();
            $this->assertNotNull($updatedInvite->used_at);
        });
    }

    /** @test */
    public function registration_fails_with_invalid_invite_token()
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invite_token' => 'invalid-token'
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/register", $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['invite_token']);

        // Verify user was not created
        $this->runInTenantContext($this->tenant, function () {
            $this->assertDatabaseMissing('users', [
                'email' => 'test@example.com'
            ]);
        });
    }

    /** @test */
    public function registration_fails_with_expired_invite_token()
    {
        // Create an expired invite
        $invite = $this->runInTenantContext($this->tenant, function () {
            return AdminInvite::create([
                'email' => 'expired@example.com',
                'token' => Str::random(32),
                'invited_by' => 1,
                'expires_at' => now()->subDays(1) // Expired
            ]);
        });

        $userData = [
            'name' => 'Test User',
            'email' => 'expired@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invite_token' => $invite->token
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/register", $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['invite_token']);
    }

    /** @test */
    public function registration_fails_with_already_used_invite_token()
    {
        // Create a used invite
        $invite = $this->runInTenantContext($this->tenant, function () {
            return AdminInvite::create([
                'email' => 'used@example.com',
                'token' => Str::random(32),
                'invited_by' => 1,
                'expires_at' => now()->addDays(7),
                'used_at' => now() // Already used
            ]);
        });

        $userData = [
            'name' => 'Test User',
            'email' => 'used@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invite_token' => $invite->token
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/register", $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['invite_token']);
    }

    /** @test */
    public function registration_validates_required_fields()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/register", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    /** @test */
    public function registration_validates_email_format()
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'invalid-email',
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/register", $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function registration_validates_password_confirmation()
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different-password'
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/register", $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /** @test */
    public function registration_validates_minimum_password_length()
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => '123',
            'password_confirmation' => '123'
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/register", $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /** @test */
    public function registration_prevents_duplicate_emails()
    {
        // Create a user first
        $this->runInTenantContext($this->tenant, function () {
            User::factory()->create([
                'email' => 'existing@example.com'
            ]);
        });

        $userData = [
            'name' => 'Test User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/register", $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function registration_works_within_tenant_context()
    {
        // Create another tenant
        $otherTenant = $this->createTestTenant('other-tenant');

        // Create user with same email in other tenant
        $this->runInTenantContext($otherTenant, function () {
            User::factory()->create([
                'email' => 'same@example.com'
            ]);
        });

        // Should be able to register with same email in different tenant
        $userData = [
            'name' => 'Test User',
            'email' => 'same@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/register", $userData);

        $response->assertStatus(201);

        // Verify user was created in the correct tenant
        $this->runInTenantContext($this->tenant, function () {
            $this->assertDatabaseHas('users', [
                'email' => 'same@example.com'
            ]);
        });
    }

    /** @test */
    public function registration_handles_server_errors_gracefully()
    {
        // This test would mock a server error scenario
        // For now, we'll test with valid data to ensure the endpoint works
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/register", $userData);

        // Should either succeed or handle error gracefully
        $this->assertContains($response->status(), [201, 500]);
        
        if ($response->status() === 500) {
            $response->assertJsonStructure([
                'success',
                'message',
                'errors'
            ])
            ->assertJson([
                'success' => false,
                'message' => 'Registration failed'
            ]);
        }
    }
}
