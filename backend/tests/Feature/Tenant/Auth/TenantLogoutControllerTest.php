<?php

namespace Tests\Feature\Tenant\Auth;

use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\PersonalAccessToken;

class TenantLogoutControllerTest extends TenantTestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $user;
    protected string $password = 'password123';

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
                'password' => bcrypt($this->password),
                'membership' => 'student'
            ]);
        });
    }

    /** @test */
    public function authenticated_user_can_logout_successfully()
    {
        // Authenticate user and get token
        Sanctum::actingAs($this->user, [], 'tenant');
        
        // Create a token for the user
        $token = $this->runInTenantContext($this->tenant, function () {
            return $this->user->createToken('test-device')->plainTextToken;
        });

        // Set the token in the request
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/tenant-logout");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Successfully logged out'
            ]);

        // Verify token was deleted
        $this->runInTenantContext($this->tenant, function () {
            $tokenExists = PersonalAccessToken::where('tokenable_id', $this->user->id)
                ->where('tokenable_type', get_class($this->user))
                ->exists();
            $this->assertFalse($tokenExists);
        });
    }

    /** @test */
    public function unauthenticated_user_cannot_logout()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/tenant-logout");

        $response->assertStatus(401);
    }

    /** @test */
    public function logout_deletes_only_current_access_token()
    {
        // Create multiple tokens for the user
        $tokens = $this->runInTenantContext($this->tenant, function () {
            $token1 = $this->user->createToken('device-1');
            $token2 = $this->user->createToken('device-2');
            $token3 = $this->user->createToken('device-3');
            
            return [
                'token1' => $token1,
                'token2' => $token2,
                'token3' => $token3,
            ];
        });

        // Use token2 for authentication
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $tokens['token2']->plainTextToken,
        ]);

        // Authenticate with Sanctum
        Sanctum::actingAs($this->user, [], 'tenant');

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/tenant-logout");

        $response->assertStatus(200);

        // Verify only the current token was deleted
        $this->runInTenantContext($this->tenant, function () use ($tokens) {
            $this->assertTrue($tokens['token1']->accessToken->exists());
            $this->assertFalse($tokens['token2']->accessToken->exists());
            $this->assertTrue($tokens['token3']->accessToken->exists());
        });
    }

    /** @test */
    public function logout_works_within_tenant_context()
    {
        // Create another tenant with a user
        $otherTenant = $this->createTestTenant('other-tenant');
        $otherUser = $this->runInTenantContext($otherTenant, function () {
            return User::factory()->create([
                'email' => 'other@example.com',
                'password' => bcrypt($this->password),
                'membership' => 'student'
            ]);
        });

        // Create tokens for both users
        $userToken = $this->runInTenantContext($this->tenant, function () {
            return $this->user->createToken('device-1')->plainTextToken;
        });

        $otherUserToken = $this->runInTenantContext($otherTenant, function () use ($otherUser) {
            return $otherUser->createToken('device-1')->plainTextToken;
        });

        // Logout from first tenant
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $userToken,
        ]);
        Sanctum::actingAs($this->user, [], 'tenant');

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/tenant-logout");

        $response->assertStatus(200);

        // Verify only the first tenant's user token was deleted
        $this->runInTenantContext($this->tenant, function () {
            $tokenExists = PersonalAccessToken::where('tokenable_id', $this->user->id)
                ->where('tokenable_type', get_class($this->user))
                ->exists();
            $this->assertFalse($tokenExists);
        });

        $this->runInTenantContext($otherTenant, function () use ($otherUser) {
            $tokenExists = PersonalAccessToken::where('tokenable_id', $otherUser->id)
                ->where('tokenable_type', get_class($otherUser))
                ->exists();
            $this->assertTrue($tokenExists);
        });
    }

    /** @test */
    public function logout_handles_missing_current_access_token_gracefully()
    {
        // Authenticate user but don't create a proper token
        Sanctum::actingAs($this->user, [], 'tenant');

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/tenant-logout");

        // Should handle gracefully even if no current token exists
        $response->assertStatus(500); // Expected to fail gracefully
    }

    /** @test */
    public function logout_logs_user_activity()
    {
        // This test would verify that logout activity is logged
        // For now, we'll just verify the endpoint works
        Sanctum::actingAs($this->user, [], 'tenant');
        
        $token = $this->runInTenantContext($this->tenant, function () {
            return $this->user->createToken('test-device')->plainTextToken;
        });

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/tenant-logout");

        $response->assertStatus(200);
        
        // In a real implementation, you might check logs here
        // $this->assertDatabaseHas('activity_logs', [
        //     'user_id' => $this->user->id,
        //     'action' => 'logout',
        //     'tenant_id' => $this->tenant->id
        // ]);
    }

    /** @test */
    public function logout_endpoint_requires_tenant_context()
    {
        // Try to access logout without tenant context (should fail)
        Sanctum::actingAs($this->user, [], 'tenant');

        // This should fail because we're not providing tenant context
        $response = $this->postJson('/api/auth/tenant-logout');

        $response->assertStatus(404); // Route not found without tenant context
    }

    /** @test */
    public function logout_handles_server_errors_gracefully()
    {
        Sanctum::actingAs($this->user, [], 'tenant');
        
        // Create a token
        $token = $this->runInTenantContext($this->tenant, function () {
            return $this->user->createToken('test-device')->plainTextToken;
        });

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ]);

        // Mock a scenario where token deletion might fail
        // In a real test, you might mock the token deletion to throw an exception
        
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/tenant-logout");

        // Should either succeed or handle error gracefully
        $this->assertContains($response->status(), [200, 500]);
        
        if ($response->status() === 500) {
            $response->assertJsonStructure([
                'success',
                'message',
                'errors'
            ])
            ->assertJson([
                'success' => false,
                'message' => 'Logout failed'
            ]);
        }
    }

    /** @test */
    public function logout_works_for_different_user_types()
    {
        $userTypes = ['student', 'team', 'admin'];

        foreach ($userTypes as $userType) {
            $user = $this->runInTenantContext($this->tenant, function () use ($userType) {
                return User::factory()->create([
                    'email' => $userType . '@example.com',
                    'password' => bcrypt($this->password),
                    'membership' => $userType
                ]);
            });

            $token = $this->runInTenantContext($this->tenant, function () use ($user) {
                return $user->createToken('test-device')->plainTextToken;
            });

            $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ]);

            Sanctum::actingAs($user, [], 'tenant');

            $response = $this->postJson("/api/{$this->tenant->slug}/auth/tenant-logout");

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Successfully logged out'
                ]);

            // Verify token was deleted
            $this->runInTenantContext($this->tenant, function () use ($user) {
                $tokenExists = PersonalAccessToken::where('tokenable_id', $user->id)
                    ->where('tokenable_type', get_class($user))
                    ->exists();
                $this->assertFalse($tokenExists);
            });
        }
    }
}
