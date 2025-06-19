<?php

namespace Tests\Feature\Tenant\Auth;

use Tests\TestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;

class TenantVerificationControllerTest extends TestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $unverifiedUser;
    protected User $verifiedUser;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        
        // Create test tenant
        $this->tenant = $this->createTestTenant();
        
        // Create unverified user
        $this->unverifiedUser = $this->runInTenantContext($this->tenant, function () {
            return User::create([
                'name' => 'Unverified User',
                'email' => 'unverified_' . Str::random(5) . '@example.com',
                'password' => bcrypt('password'),
                'email_verified_at' => null, // Explicitly set as unverified
            ]);
        });
        
        // Create verified user
        $this->verifiedUser = $this->runInTenantContext($this->tenant, function () {
            return User::create([
                'name' => 'Verified User',
                'email' => 'verified_' . Str::random(5) . '@example.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now(), // Already verified
            ]);
        });
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }
    
    /** @test */
    public function user_can_send_verification_email()
    {
        Notification::fake();
        
        // Authenticate as unverified user
        Sanctum::actingAs($this->unverifiedUser, [], 'tenant');
        
        // API call to request verification email
        $response = $this->postJson(
            "/api/{$this->tenant->slug}/auth/email/verification-notification"
        );
        
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Verification link sent!'
            ]);
        
        // Verify notification was sent to the correct user
        Notification::assertSentTo(
            $this->unverifiedUser,
            VerifyEmail::class
        );
    }
    
    /** @test */
    public function already_verified_user_does_not_need_verification()
    {
        Notification::fake();
        
        // Authenticate as verified user
        Sanctum::actingAs($this->verifiedUser, [], 'tenant');
        
        // API call to request verification email
        $response = $this->postJson(
            "/api/{$this->tenant->slug}/auth/email/verification-notification"
        );
        
        // Should get a message that they're already verified
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Email already verified!'
            ]);
        
        // No notification should be sent
        Notification::assertNotSentTo(
            $this->verifiedUser,
            VerifyEmail::class
        );
    }
    
    /** @test */
    public function guest_cannot_request_verification_email()
    {
        // API call without authentication
        $response = $this->postJson(
            "/api/{$this->tenant->slug}/auth/email/verification-notification"
        );
        
        // Should fail with 401 unauthorized
        $response->assertStatus(401);
    }
    
    /** @test */
    public function user_can_verify_email_with_valid_signature()
    {
        $this->runInTenantContext($this->tenant, function () {
            // Generate a signed verification URL
            $verificationUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(60),
                [
                    'id' => $this->unverifiedUser->getKey(),
                    'hash' => sha1($this->unverifiedUser->getEmailForVerification())
                ]
            );
            
            // Extract the relative path from the full URL
            $relativePath = parse_url($verificationUrl, PHP_URL_PATH);
            $queryString = parse_url($verificationUrl, PHP_URL_QUERY);
            $relativeUrl = $relativePath . '?' . $queryString;
            
            // Visit the verification URL (using GET not JSON)
            $response = $this->get($relativeUrl);
            
            // Should redirect to the frontend verification success page
            $response->assertRedirect();
            
            // Verify the user is now verified
            $this->assertNotNull($this->unverifiedUser->fresh()->email_verified_at);
        });
    }
    
    /** @test */
    public function verification_fails_with_invalid_signature()
    {
        // Generate an invalid verification URL (without signature)
        $invalidUrl = "/api/{$this->tenant->slug}/auth/email/verify/{$this->unverifiedUser->id}/invalid-hash";
        
        // Visit the invalid verification URL
        $response = $this->get($invalidUrl);
        
        // Should fail with 403 (signature invalid)
        $response->assertStatus(403);
        
        // User should still be unverified
        $this->assertNull($this->unverifiedUser->fresh()->email_verified_at);
    }
    
    /** @test */
    public function throttle_verification_requests()
    {
        Notification::fake();
        
        // Authenticate as unverified user
        Sanctum::actingAs($this->unverifiedUser, [], 'tenant');
        
        // Send multiple requests in quick succession
        for ($i = 0; $i < 7; $i++) {
            $this->postJson(
                "/api/{$this->tenant->slug}/auth/email/verification-notification"
            );
        }
        
        // The 7th request should be throttled (limit is 6 per minute as defined in the route)
        $response = $this->postJson(
            "/api/{$this->tenant->slug}/auth/email/verification-notification"
        );
        
        // Should be throttled with 429 Too Many Requests
        $response->assertStatus(429);
    }
}
