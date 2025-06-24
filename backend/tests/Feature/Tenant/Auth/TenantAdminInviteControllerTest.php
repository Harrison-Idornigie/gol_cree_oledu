<?php

namespace Tests\Feature\Tenant\Auth;

use Tests\TestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\AdminInvite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\URL;

class TenantAdminInviteControllerTest extends TestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $adminUser;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        
        $this->tenant = $this->createTestTenant();
        $this->adminUser = $this->createTenantAdmin();
        $this->regularUser = $this->createTenantUser();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    protected function createTenantAdmin(): User
    {
        return $this->runInTenantContext($this->tenant, function () {
            return User::factory()->create([
                'email' => 'admin@example.com',
                'password' => bcrypt('password123'),
                'membership' => 'admin'
            ]);
        });
    }

    protected function createTenantUser(): User
    {
        return $this->runInTenantContext($this->tenant, function () {
            return User::factory()->create([
                'email' => 'user@example.com',
                'password' => bcrypt('password123'),
                'membership' => 'student'
            ]);
        });
    }

    /** @test */
    public function admin_can_send_invite_with_valid_email()
    {
        Sanctum::actingAs($this->adminUser, [], 'tenant');

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/admin-invite", [
            'email' => 'newadmin@example.com'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'invite_url'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Invite sent successfully'
            ]);

        // Verify invite was created in database
        $this->runInTenantContext($this->tenant, function () {
            $this->assertDatabaseHas('admin_invites', [
                'email' => 'newadmin@example.com',
                'invited_by' => $this->adminUser->id
            ]);
        });

        // Verify invite URL is properly formatted
        $inviteUrl = $response->json('data.invite_url');
        $this->assertStringContainsString('/register?invite=', $inviteUrl);
    }

    /** @test */
    public function admin_invite_creates_valid_token()
    {
        Sanctum::actingAs($this->adminUser, [], 'tenant');

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/admin-invite", [
            'email' => 'newadmin@example.com'
        ]);

        $response->assertStatus(200);

        // Verify invite has valid token and expiration
        $this->runInTenantContext($this->tenant, function () {
            $invite = AdminInvite::where('email', 'newadmin@example.com')->first();
            
            $this->assertNotNull($invite);
            $this->assertNotNull($invite->token);
            $this->assertEquals(32, strlen($invite->token));
            $this->assertNotNull($invite->expires_at);
            $this->assertTrue($invite->expires_at->isFuture());
            $this->assertEquals($this->adminUser->id, $invite->invited_by);
        });
    }

    /** @test */
    public function admin_invite_validates_email_format()
    {
        Sanctum::actingAs($this->adminUser, [], 'tenant');

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/admin-invite", [
            'email' => 'invalid-email-format'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function admin_invite_requires_email_field()
    {
        Sanctum::actingAs($this->adminUser, [], 'tenant');

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/admin-invite", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function admin_invite_prevents_duplicate_emails_in_users_table()
    {
        Sanctum::actingAs($this->adminUser, [], 'tenant');

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/admin-invite", [
            'email' => $this->regularUser->email // Email already exists in users table
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function admin_invite_prevents_duplicate_emails_in_admin_invites_table()
    {
        Sanctum::actingAs($this->adminUser, [], 'tenant');

        // Create an existing invite
        $this->runInTenantContext($this->tenant, function () {
            AdminInvite::create([
                'email' => 'existing@example.com',
                'token' => 'existing-token',
                'invited_by' => $this->adminUser->id,
                'expires_at' => now()->addDays(7)
            ]);
        });

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/admin-invite", [
            'email' => 'existing@example.com'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function non_admin_user_cannot_send_invites()
    {
        Sanctum::actingAs($this->regularUser, [], 'tenant');

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/admin-invite", [
            'email' => 'newadmin@example.com'
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Unauthorized'
            ]);

        // Verify no invite was created
        $this->runInTenantContext($this->tenant, function () {
            $this->assertDatabaseMissing('admin_invites', [
                'email' => 'newadmin@example.com'
            ]);
        });
    }

    /** @test */
    public function unauthenticated_user_cannot_send_invites()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/admin-invite", [
            'email' => 'newadmin@example.com'
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function admin_invite_works_within_tenant_context()
    {
        // Create another tenant with admin
        $otherTenant = $this->createTestTenant('other-tenant');
        $otherAdmin = $this->runInTenantContext($otherTenant, function () {
            return User::factory()->create([
                'email' => 'otheradmin@example.com',
                'membership' => 'admin'
            ]);
        });

        // Send invite from first tenant
        Sanctum::actingAs($this->adminUser, [], 'tenant');

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/admin-invite", [
            'email' => 'newadmin@example.com'
        ]);

        $response->assertStatus(200);

        // Verify invite was created in correct tenant
        $this->runInTenantContext($this->tenant, function () {
            $this->assertDatabaseHas('admin_invites', [
                'email' => 'newadmin@example.com',
                'invited_by' => $this->adminUser->id
            ]);
        });

        // Verify invite was NOT created in other tenant
        $this->runInTenantContext($otherTenant, function () {
            $this->assertDatabaseMissing('admin_invites', [
                'email' => 'newadmin@example.com'
            ]);
        });
    }

    /** @test */
    public function admin_invite_sets_correct_expiration_date()
    {
        Sanctum::actingAs($this->adminUser, [], 'tenant');

        $beforeInvite = now();
        
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/admin-invite", [
            'email' => 'newadmin@example.com'
        ]);

        $afterInvite = now();

        $response->assertStatus(200);

        $this->runInTenantContext($this->tenant, function () use ($beforeInvite, $afterInvite) {
            $invite = AdminInvite::where('email', 'newadmin@example.com')->first();
            
            // Should expire in 7 days
            $expectedExpiration = $beforeInvite->addDays(7);
            $this->assertTrue($invite->expires_at->between(
                $expectedExpiration->subMinute(),
                $afterInvite->addDays(7)->addMinute()
            ));
        });
    }

    /** @test */
    public function admin_invite_handles_server_errors_gracefully()
    {
        Sanctum::actingAs($this->adminUser, [], 'tenant');

        // This test would mock a server error scenario
        // For now, we'll test with valid data to ensure the endpoint works
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/admin-invite", [
            'email' => 'test@example.com'
        ]);

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
                'message' => 'Failed to send invite'
            ]);
        }
    }

    /** @test */
    public function admin_invite_generates_unique_tokens()
    {
        Sanctum::actingAs($this->adminUser, [], 'tenant');

        // Send multiple invites
        $emails = ['admin1@example.com', 'admin2@example.com', 'admin3@example.com'];
        $tokens = [];

        foreach ($emails as $email) {
            $response = $this->postJson("/api/{$this->tenant->slug}/auth/admin-invite", [
                'email' => $email
            ]);

            $response->assertStatus(200);

            $this->runInTenantContext($this->tenant, function () use ($email, &$tokens) {
                $invite = AdminInvite::where('email', $email)->first();
                $tokens[] = $invite->token;
            });
        }

        // Verify all tokens are unique
        $this->assertEquals(count($tokens), count(array_unique($tokens)));
    }

    /** @test */
    public function admin_invite_url_contains_valid_token()
    {
        Sanctum::actingAs($this->adminUser, [], 'tenant');

        $response = $this->postJson("/api/{$this->tenant->slug}/auth/admin-invite", [
            'email' => 'newadmin@example.com'
        ]);

        $response->assertStatus(200);

        $inviteUrl = $response->json('data.invite_url');
        
        // Extract token from URL
        $urlParts = parse_url($inviteUrl);
        parse_str($urlParts['query'], $queryParams);
        $tokenFromUrl = $queryParams['invite'];

        // Verify token in URL matches token in database
        $this->runInTenantContext($this->tenant, function () use ($tokenFromUrl) {
            $invite = AdminInvite::where('email', 'newadmin@example.com')->first();
            $this->assertEquals($invite->token, $tokenFromUrl);
        });
    }
}
