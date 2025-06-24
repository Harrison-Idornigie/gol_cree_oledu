<?php

namespace Tests\Feature\Tenant\Auth;

use Tests\TestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Str;

class TenantPasswordResetControllerTest extends TestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $user;
    protected Language $language;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        
        // Create test tenant
        $this->tenant = $this->createTestTenant();
        $this->initializeTenantContext($this->tenant);
        
        // Create test user in tenant context
        $this->user = $this->createTenantUser([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);
        
        // Create language for tenant
        $this->language = $this->createLanguage();
    }

    /**
     * Test forgot password email can be sent successfully
     */
    public function test_forgot_password_email_sent_successfully()
    {
        Notification::fake();

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/email", [
            'email' => $this->user->email,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Password reset link sent to your email address.'
            ]);

        Notification::assertSentTo($this->user, ResetPassword::class);
    }

    /**
     * Test forgot password with invalid email
     */
    public function test_forgot_password_with_invalid_email()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/email", [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test forgot password with missing email
     */
    public function test_forgot_password_with_missing_email()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/email", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test forgot password with malformed email
     */
    public function test_forgot_password_with_malformed_email()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/email", [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test password reset with valid token
     */
    public function test_password_reset_with_valid_token()
    {
        // Generate a valid reset token
        $token = Password::broker('tenants')->createToken($this->user);

        $newPassword = 'newpassword123';

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/reset", [
            'token' => $token,
            'email' => $this->user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Password has been reset successfully.'
            ]);

        // Verify password was actually changed
        $this->user->refresh();
        $this->assertTrue(Hash::check($newPassword, $this->user->password));
    }

    /**
     * Test password reset with invalid token
     */
    public function test_password_reset_with_invalid_token()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/reset", [
            'token' => 'invalid-token',
            'email' => $this->user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test password reset with mismatched passwords
     */
    public function test_password_reset_with_mismatched_passwords()
    {
        $token = Password::broker('tenants')->createToken($this->user);

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/reset", [
            'token' => $token,
            'email' => $this->user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'differentpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /**
     * Test password reset with weak password
     */
    public function test_password_reset_with_weak_password()
    {
        $token = Password::broker('tenants')->createToken($this->user);

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/reset", [
            'token' => $token,
            'email' => $this->user->email,
            'password' => '123',
            'password_confirmation' => '123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /**
     * Test password reset with missing required fields
     */
    public function test_password_reset_with_missing_fields()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/reset", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token', 'email', 'password']);
    }

    /**
     * Test password reset with expired token
     */
    public function test_password_reset_with_expired_token()
    {
        // Create token and then manually expire it by manipulating the database
        $token = Password::broker('tenants')->createToken($this->user);
        
        // Simulate expired token by backdating the created_at timestamp
        $this->runInTenantContext($this->tenant, function () {
            \DB::table('password_reset_tokens')
                ->where('email', $this->user->email)
                ->update(['created_at' => now()->subHours(2)]);
        });

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/reset", [
            'token' => $token,
            'email' => $this->user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test password reset rate limiting
     */
    public function test_password_reset_rate_limiting()
    {
        // Send multiple requests rapidly
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/email", [
                'email' => $this->user->email,
            ]);
        }

        // The 6th request should be rate limited
        $response->assertStatus(429);
    }

    /**
     * Test cross-tenant password reset isolation
     */
    public function test_cross_tenant_password_reset_isolation()
    {
        // Create another tenant with a user having the same email
        $otherTenant = $this->createTestTenant(['slug' => 'other-tenant']);
        
        $this->initializeTenantContext($otherTenant);
        $otherUser = $this->createTenantUser([
            'email' => $this->user->email, // Same email, different tenant
            'password' => Hash::make('otherpassword'),
        ]);

        // Generate token for user in first tenant
        $this->initializeTenantContext($this->tenant);
        $token = Password::broker('tenants')->createToken($this->user);

        // Try to use token in other tenant context
        $response = $this->postJson("/api/{$otherTenant->slug}/auth/password/reset", [
            'token' => $token,
            'email' => $this->user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
