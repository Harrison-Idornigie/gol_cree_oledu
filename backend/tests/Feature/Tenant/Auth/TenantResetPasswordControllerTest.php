<?php

namespace Tests\Feature\Tenant\Auth;

use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class TenantResetPasswordControllerTest extends TenantTestCase
{
     

    protected Tenant $tenant;
    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        
        $this->tenant = $this->createTestTenant();
        $this->user = $this->createTenantUser();
        $this->token = $this->createPasswordResetToken();
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
                'password' => bcrypt('oldpassword123'),
                'membership' => 'student'
            ]);
        });
    }

    protected function createPasswordResetToken(): string
    {
        return $this->runInTenantContext($this->tenant, function () {
            return Password::createToken($this->user);
        });
    }

    /** @test */
    public function user_can_reset_password_with_valid_token()
    {
        $newPassword = 'newpassword123';

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/reset", [
            'token' => $this->token,
            'email' => $this->user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword
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

        // Verify password was updated
        $this->runInTenantContext($this->tenant, function () use ($newPassword) {
            $updatedUser = $this->user->fresh();
            $this->assertTrue(Hash::check($newPassword, $updatedUser->password));
        });
    }

    /** @test */
    public function password_reset_fails_with_invalid_token()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/reset", [
            'token' => 'invalid-token',
            'email' => $this->user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123'
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

        // Verify password was not changed
        $this->runInTenantContext($this->tenant, function () {
            $updatedUser = $this->user->fresh();
            $this->assertTrue(Hash::check('oldpassword123', $updatedUser->password));
        });
    }

    /** @test */
    public function password_reset_fails_with_invalid_email()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/reset", [
            'token' => $this->token,
            'email' => 'wrong@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123'
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
    }

    /** @test */
    public function password_reset_requires_all_fields()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/reset", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token', 'email', 'password']);
    }

    /** @test */
    public function password_reset_validates_password_confirmation()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/reset", [
            'token' => $this->token,
            'email' => $this->user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'differentpassword'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /** @test */
    public function password_reset_validates_minimum_password_length()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/reset", [
            'token' => $this->token,
            'email' => $this->user->email,
            'password' => '123',
            'password_confirmation' => '123'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /** @test */
    public function password_reset_validates_email_format()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/reset", [
            'token' => $this->token,
            'email' => 'invalid-email',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function password_reset_works_within_tenant_context()
    {
        // Create another tenant with a user with the same email
        $otherTenant = $this->createTestTenant('other-tenant');
        $otherUser = $this->runInTenantContext($otherTenant, function () {
            return User::factory()->create([
                'email' => $this->user->email, // Same email as first tenant
                'password' => bcrypt('otherpassword123'),
                'membership' => 'student'
            ]);
        });

        $newPassword = 'newpassword123';

        // Reset password for first tenant
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/reset", [
            'token' => $this->token,
            'email' => $this->user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword
        ]);

        $response->assertStatus(200);

        // Verify only the first tenant's user password was changed
        $this->runInTenantContext($this->tenant, function () use ($newPassword) {
            $updatedUser = $this->user->fresh();
            $this->assertTrue(Hash::check($newPassword, $updatedUser->password));
        });

        $this->runInTenantContext($otherTenant, function () use ($otherUser) {
            $otherUpdatedUser = $otherUser->fresh();
            $this->assertTrue(Hash::check('otherpassword123', $otherUpdatedUser->password));
        });
    }

    /** @test */
    public function password_reset_handles_server_errors_gracefully()
    {
        // Mock Password facade to throw exception
        Password::shouldReceive('reset')
            ->once()
            ->andThrow(new \Exception('Server error'));

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/reset", [
            'token' => $this->token,
            'email' => $this->user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123'
        ]);

        $response->assertStatus(500)
            ->assertJsonStructure([
                'success',
                'message',
                'errors'
            ])
            ->assertJson([
                'success' => false,
                'message' => 'Failed to reset password'
            ]);
    }

    /** @test */
    public function password_reset_endpoint_is_accessible_without_authentication()
    {
        // This endpoint should be accessible without authentication
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/reset", [
            'token' => $this->token,
            'email' => $this->user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123'
        ]);

        // Should not return 401 Unauthorized
        $response->assertStatus(200);
    }

    /** @test */
    public function password_reset_invalidates_remember_token()
    {
        $newPassword = 'newpassword123';
        
        // Set a remember token
        $this->runInTenantContext($this->tenant, function () {
            $this->user->setRememberToken(Str::random(60));
            $this->user->save();
        });

        $oldRememberToken = $this->user->remember_token;

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/reset", [
            'token' => $this->token,
            'email' => $this->user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword
        ]);

        $response->assertStatus(200);

        // Verify remember token was changed
        $this->runInTenantContext($this->tenant, function () use ($oldRememberToken) {
            $updatedUser = $this->user->fresh();
            $this->assertNotEquals($oldRememberToken, $updatedUser->remember_token);
        });
    }
}
