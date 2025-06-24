<?php

namespace Tests\Feature\Tenant\Auth;

use Tests\TestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Notifications\ResetPassword;

class TenantForgotPasswordControllerTest extends TestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        
        $this->tenant = $this->createTestTenant();
        $this->user = $this->createTenantUser();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    protected function createTenantUser(): User
    {
        return $this->runInTenantContext($this->tenant, function () {
            return User::factory()->create([
                'email' => 'test@example.com',
                'password' => bcrypt('password123'),
                'membership' => 'student'
            ]);
        });
    }

    /** @test */
    public function user_can_request_password_reset_with_valid_email()
    {
        Notification::fake();

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/email", [
            'email' => $this->user->email
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true
            ]);

        // Verify notification was sent
        Notification::assertSentTo(
            $this->user,
            ResetPassword::class
        );
    }

    /** @test */
    public function password_reset_fails_with_invalid_email()
    {
        Notification::fake();

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/email", [
            'email' => 'nonexistent@example.com'
        ]);

        $response->assertStatus(400)
            ->assertJsonStructure([
                'success',
                'message',
                'errors'
            ])
            ->assertJson([
                'success' => false
            ]);

        // Verify no notification was sent
        Notification::assertNothingSent();
    }

    /** @test */
    public function password_reset_requires_email_field()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/email", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function password_reset_validates_email_format()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/email", [
            'email' => 'invalid-email-format'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function password_reset_works_within_tenant_context()
    {
        Notification::fake();

        // Create another tenant with a user with the same email
        $otherTenant = $this->createTestTenant('other-tenant');
        $otherUser = $this->runInTenantContext($otherTenant, function () {
            return User::factory()->create([
                'email' => $this->user->email, // Same email as first tenant
                'password' => bcrypt('password123'),
                'membership' => 'student'
            ]);
        });

        // Request password reset for first tenant
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/email", [
            'email' => $this->user->email
        ]);

        $response->assertStatus(200);

        // Verify notification was sent to the correct user in the correct tenant
        Notification::assertSentTo($this->user, ResetPassword::class);
        Notification::assertNotSentTo($otherUser, ResetPassword::class);
    }

    /** @test */
    public function password_reset_handles_server_errors_gracefully()
    {
        // Mock Password facade to throw exception
        Password::shouldReceive('sendResetLink')
            ->once()
            ->andThrow(new \Exception('Server error'));

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/email", [
            'email' => $this->user->email
        ]);

        $response->assertStatus(500)
            ->assertJsonStructure([
                'success',
                'message',
                'errors'
            ])
            ->assertJson([
                'success' => false,
                'message' => 'Failed to send reset link'
            ]);
    }

    /** @test */
    public function password_reset_endpoint_is_accessible_without_authentication()
    {
        // This endpoint should be accessible without authentication
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/email", [
            'email' => $this->user->email
        ]);

        // Should not return 401 Unauthorized
        $response->assertStatus(200);
    }

    /** @test */
    public function password_reset_respects_tenant_isolation()
    {
        Notification::fake();

        // Create user in different tenant
        $otherTenant = $this->createTestTenant('other-tenant');
        $otherUser = $this->runInTenantContext($otherTenant, function () {
            return User::factory()->create([
                'email' => 'other@example.com',
                'password' => bcrypt('password123'),
                'membership' => 'student'
            ]);
        });

        // Try to reset password for other tenant's user through this tenant's endpoint
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/email", [
            'email' => $otherUser->email
        ]);

        $response->assertStatus(400); // Should fail because user doesn't exist in this tenant

        // Verify no notification was sent
        Notification::assertNotSentTo($otherUser, ResetPassword::class);
    }
}
