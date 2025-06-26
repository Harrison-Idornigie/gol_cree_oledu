<?php

namespace Tests\Feature\Tenant\Auth;

use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\AdminInvite;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Comprehensive Test Suite for TenantAuthController
 * 
 * Tests all authentication functionality including:
 * - Login/Logout (covered in TenantLoginControllerTest)
 * - Password Reset
 * - Email Verification
 * - User Registration
 * - Admin Invitations
 * - User Profile Management
 * - Google OAuth
 */
class TenantAuthControllerTest extends TenantTestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected User $adminUser;
    protected string $password = 'password123';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'test_' . Str::random(8),
            'name' => 'Test Tenant ' . Str::random(8),
            'slug' => 'test-tenant-' . Str::random(8),
        ]);

        $this->runInTenantContext($this->tenant, function () {
            $this->user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => Hash::make($this->password),
                'membership' => 'student',
                'email_verified_at' => now(),
            ]);

            $this->adminUser = User::create([
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => Hash::make($this->password),
                'membership' => 'admin',
                'email_verified_at' => now(),
            ]);
        });
    }

    // ==================== PASSWORD RESET TESTS ====================

    /** @test */
    public function user_can_request_password_reset_with_valid_email()
    {
        Notification::fake();

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/email", [
            'email' => $this->user->email,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data'
        ]);

        Notification::assertSentTo($this->user, ResetPasswordNotification::class);
    }

    /** @test */
    public function password_reset_request_validates_email_format()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/email", [
            'email' => 'invalid-email',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function password_reset_request_requires_email()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/email", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function user_can_reset_password_with_valid_token()
    {
        $token = Password::createToken($this->user);
        $newPassword = 'newpassword123';

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/reset", [
            'token' => $token,
            'email' => $this->user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data'
        ]);

        // Verify password was changed
        $this->runInTenantContext($this->tenant, function () use ($newPassword) {
            $this->user->refresh();
            $this->assertTrue(Hash::check($newPassword, $this->user->password));
        });
    }

    /** @test */
    public function password_reset_validates_required_fields()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/reset", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['token', 'email', 'password']);
    }

    /** @test */
    public function password_reset_requires_password_confirmation()
    {
        $token = Password::createToken($this->user);

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/password/reset", [
            'token' => $token,
            'email' => $this->user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'differentpassword',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    // ==================== EMAIL VERIFICATION TESTS ====================

    /** @test */
    public function user_can_verify_email_with_valid_link()
    {
        $this->runInTenantContext($this->tenant, function () {
            $unverifiedUser = User::create([
                'name' => 'Unverified User',
                'email' => 'unverified@example.com',
                'password' => Hash::make('password'),
                'membership' => 'student',
                'email_verified_at' => null,
            ]);

            $hash = sha1($unverifiedUser->getEmailForVerification());

            // Generate signed URL for email verification
            $verificationUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(60),
                [
                    'id' => $unverifiedUser->id,
                    'hash' => $hash,
                ]
            );

            // Extract the path and query from the signed URL
            $parsedUrl = parse_url($verificationUrl);
            $path = $parsedUrl['path'] ?? '';
            $query = $parsedUrl['query'] ?? '';
            $fullPath = $path . ($query ? '?' . $query : '');

            $response = $this->getJson($fullPath);

            $response->assertStatus(200);
            $response->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);

            $unverifiedUser->refresh();
            $this->assertNotNull($unverifiedUser->email_verified_at);
        });
    }

    /** @test */
    public function email_verification_fails_with_invalid_hash()
    {
        $response = $this->getJson("/api/{$this->tenant->slug}/auth/email/verify/{$this->user->id}/invalid-hash");

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'Invalid verification link'
        ]);
    }

    /** @test */
    public function authenticated_user_can_request_verification_email()
    {
        Notification::fake();

        $this->runInTenantContext($this->tenant, function () {
            $unverifiedUser = User::create([
                'name' => 'Unverified User',
                'email' => 'unverified@example.com',
                'password' => Hash::make('password'),
                'membership' => 'student',
                'email_verified_at' => null,
            ]);

            Sanctum::actingAs($unverifiedUser, [], 'tenant');

            $response = $this->postJson("/api/{$this->tenant->slug}/auth/email/verification-notification");

            $response->assertStatus(200);
            $response->assertJsonStructure([
                'success',
                'message',
                'data'
            ]);

            Notification::assertSentTo($unverifiedUser, VerifyEmailNotification::class);
        });
    }

    /** @test */
    public function already_verified_user_cannot_request_verification_email()
    {
        Sanctum::actingAs($this->user, [], 'tenant');

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/email/verification-notification");

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'Email already verified'
        ]);
    }

    // ==================== USER REGISTRATION TESTS ====================

    /** @test */
    public function user_can_register_with_valid_data()
    {
        Notification::fake();

        $userData = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/register", $userData);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user' => [
                    'id',
                    'name',
                    'email',
                    'membership'
                ]
            ]
        ]);

        $this->runInTenantContext($this->tenant, function () use ($userData) {
            $this->assertDatabaseHas('users', [
                'name' => $userData['name'],
                'email' => $userData['email'],
                'membership' => 'student',
            ]);
        });

        // Verify email notification was sent
        $this->runInTenantContext($this->tenant, function () use ($userData) {
            $user = User::where('email', $userData['email'])->first();
            Notification::assertSentTo($user, VerifyEmailNotification::class);
        });
    }

    /** @test */
    public function user_registration_validates_required_fields()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/register", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    /** @test */
    public function user_registration_prevents_duplicate_emails()
    {
        $userData = [
            'name' => 'Duplicate User',
            'email' => $this->user->email, // Use existing email
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/register", $userData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function user_registration_requires_password_confirmation()
    {
        $userData = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'differentpassword',
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/register", $userData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    // ==================== USER PROFILE TESTS ====================

    /** @test */
    public function authenticated_user_can_get_profile()
    {
        Sanctum::actingAs($this->user, [], 'tenant');

        $response = $this->getJson("/api/{$this->tenant->slug}/auth/me");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user' => [
                    'id',
                    'name',
                    'email',
                    'membership',
                    'email_verified_at'
                ]
            ]
        ]);

        $response->assertJson([
            'data' => [
                'user' => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'membership' => $this->user->membership,
                ]
            ]
        ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_get_profile()
    {
        $response = $this->getJson("/api/{$this->tenant->slug}/auth/me");

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthenticated'
        ]);
    }

    // ==================== ADMIN INVITATION TESTS ====================

    /** @test */
    public function admin_can_send_invitation()
    {
        Sanctum::actingAs($this->adminUser, [], 'tenant');

        $inviteData = [
            'email' => 'newinvite@example.com',
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/admin-invite", $inviteData);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'invite' => [
                    'id',
                    'email',
                    'expires_at',
                    'invite_url'
                ]
            ]
        ]);

        $this->runInTenantContext($this->tenant, function () use ($inviteData) {
            $this->assertDatabaseHas('admin_invites', [
                'email' => $inviteData['email'],
                'invited_by' => $this->adminUser->id,
            ]);
        });
    }

    /** @test */
    public function admin_invite_validates_email_format()
    {
        Sanctum::actingAs($this->adminUser, [], 'tenant');

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/admin-invite", [
            'email' => 'invalid-email',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function admin_invite_prevents_duplicate_user_emails()
    {
        Sanctum::actingAs($this->adminUser, [], 'tenant');

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/admin-invite", [
            'email' => $this->user->email, // Existing user email
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function admin_invite_prevents_duplicate_pending_invites()
    {
        Sanctum::actingAs($this->adminUser, [], 'tenant');

        $email = 'duplicate@example.com';

        // Create first invite
        $this->runInTenantContext($this->tenant, function () use ($email) {
            AdminInvite::create([
                'email' => $email,
                'token' => Str::random(32),
                'invited_by' => $this->adminUser->id,
                'expires_at' => now()->addDays(7),
            ]);
        });

        // Try to create duplicate invite
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/admin-invite", [
            'email' => $email,
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'There is already a pending invitation for this email'
        ]);
    }

    /** @test */
    public function non_admin_user_cannot_send_invites()
    {
        Sanctum::actingAs($this->user, [], 'tenant'); // Regular user, not admin

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/admin-invite", [
            'email' => 'newinvite@example.com',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Forbidden'
        ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_send_invites()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/admin-invite", [
            'email' => 'newinvite@example.com',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function user_can_register_with_valid_admin_invite_token()
    {
        $email = 'invited@example.com';
        $token = Str::random(32);

        // Create admin invite
        $this->runInTenantContext($this->tenant, function () use ($email, $token) {
            AdminInvite::create([
                'email' => $email,
                'token' => $token,
                'invited_by' => $this->adminUser->id,
                'expires_at' => now()->addDays(7),
            ]);
        });

        $userData = [
            'name' => 'Invited Admin',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invite_token' => $token,
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/register", $userData);

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'user' => [
                    'email' => $email,
                    'membership' => 'admin', // Should be admin due to invite
                ]
            ]
        ]);

        // Verify invite was marked as used
        $this->runInTenantContext($this->tenant, function () use ($token) {
            $invite = AdminInvite::where('token', $token)->first();
            $this->assertNotNull($invite->used_at);
        });
    }

    /** @test */
    public function user_cannot_register_with_invalid_invite_token()
    {
        $userData = [
            'name' => 'Invalid Invite User',
            'email' => 'invalid@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invite_token' => 'invalid-token',
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/register", $userData);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'Invalid or expired invitation token'
        ]);
    }

    // ==================== GOOGLE OAUTH TESTS ====================

    /** @test */
    public function user_can_get_google_auth_url()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/google/url", [
            'client_type' => 'web',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'url',
                'state'
            ]
        ]);

        $data = $response->json('data');
        $this->assertStringContainsString('accounts.google.com', $data['url']);
        $this->assertNotEmpty($data['state']);
    }

    /** @test */
    public function google_auth_url_validates_client_type()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/google/url", [
            'client_type' => 'invalid',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['client_type']);
    }

    /** @test */
    public function google_auth_url_requires_client_type()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/google/url", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['client_type']);
    }

    /** @test */
    public function google_callback_validates_required_fields()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/google/callback", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['code']);
    }

    // Note: Full Google OAuth integration tests would require mocking Socialite
    // or using a test Google OAuth setup, which is beyond the scope of this basic test suite.
    // In a real implementation, you would mock the Socialite facade and test the full flow.

    // ==================== INTEGRATION TESTS ====================

    /** @test */
    public function complete_user_registration_and_verification_flow()
    {
        Notification::fake();

        // 1. Register user
        $userData = [
            'name' => 'Flow Test User',
            'email' => 'flowtest@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $registerResponse = $this->postJson("/api/{$this->tenant->slug}/auth/register", $userData);
        $registerResponse->assertStatus(200);

        // 2. Get the created user
        $user = $this->runInTenantContext($this->tenant, function () use ($userData) {
            return User::where('email', $userData['email'])->first();
        });

        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);

        // 3. Verify email
        $hash = sha1($user->getEmailForVerification());
        $verifyResponse = $this->getJson("/api/{$this->tenant->slug}/auth/email/verify/{$user->id}/{$hash}");
        $verifyResponse->assertStatus(200);

        // 4. Check user is verified
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);

        // 5. Login with verified user
        $loginResponse = $this->postJson("/api/{$this->tenant->slug}/auth/tenant-login", [
            'email' => $userData['email'],
            'password' => $userData['password'],
            'device_name' => 'test-device',
        ]);

        $loginResponse->assertStatus(200);
        $loginResponse->assertJsonStructure([
            'success',
            'data' => [
                'user',
                'token',
                'tenant'
            ]
        ]);
    }

    /** @test */
    public function complete_admin_invite_and_registration_flow()
    {
        Sanctum::actingAs($this->adminUser, [], 'tenant');

        // 1. Admin sends invite
        $inviteEmail = 'adminflow@example.com';
        $inviteResponse = $this->postJson("/api/{$this->tenant->slug}/auth/admin-invite", [
            'email' => $inviteEmail,
        ]);

        $inviteResponse->assertStatus(200);
        $inviteData = $inviteResponse->json('data.invite');

        // 2. Extract token from invite URL
        $inviteUrl = $inviteData['invite_url'];
        parse_str(parse_url($inviteUrl, PHP_URL_QUERY), $queryParams);
        $token = $queryParams['token'];

        // 3. Register with invite token
        $userData = [
            'name' => 'Admin Flow User',
            'email' => $inviteEmail,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invite_token' => $token,
        ];

        $registerResponse = $this->postJson("/api/{$this->tenant->slug}/auth/register", $userData);
        $registerResponse->assertStatus(200);

        // 4. Verify user has admin membership
        $registerResponse->assertJson([
            'data' => [
                'user' => [
                    'membership' => 'admin'
                ]
            ]
        ]);

        // 5. Verify invite was marked as used
        $this->runInTenantContext($this->tenant, function () use ($token) {
            $invite = AdminInvite::where('token', $token)->first();
            $this->assertNotNull($invite->used_at);
        });
    }
}
