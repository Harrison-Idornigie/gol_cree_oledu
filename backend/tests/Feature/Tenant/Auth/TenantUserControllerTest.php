<?php

namespace Tests\Feature\Tenant\Auth;

use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;

class TenantUserControllerTest extends TenantTestCase
{
     

    protected Tenant $tenant;
    protected User $user;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        
        // Create test tenant
        $this->tenant = $this->createTestTenant();
        
        // Create verified user in tenant context
        $this->user = $this->runInTenantContext($this->tenant, function () {
            return User::create([
                'name' => 'Test User',
                'email' => 'test_' . Str::random(5) . '@example.com',
                'password' => bcrypt('password123'),
                'email_verified_at' => now(),
            ]);
        });
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }
    
    /** @test */
    public function user_can_get_their_profile()
    {
        // Authenticate user
        Sanctum::actingAs($this->user, [], 'tenant');
        
        // API call to get user profile
        $response = $this->getJson("/api/{$this->tenant->slug}/auth/me");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'name',
                'email',
                'email_verified_at',
                'created_at',
                'updated_at'
            ])
            ->assertJsonFragment([
                'name' => $this->user->name,
                'email' => $this->user->email
            ]);
    }
    
    /** @test */
    public function unauthenticated_user_cannot_get_profile()
    {
        // API call without authentication
        $response = $this->getJson("/api/{$this->tenant->slug}/auth/me");
        
        $response->assertStatus(401);
    }
    
    /** @test */
    public function user_can_update_profile()
    {
        // Authenticate user
        Sanctum::actingAs($this->user, [], 'tenant');
        
        // API call to update profile
        $response = $this->putJson("/api/{$this->tenant->slug}/auth/me", [
            'name' => 'Updated Name',
            'preferences' => [
                'notifications' => true,
                'theme' => 'dark'
            ]
        ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'name',
                'email',
                'preferences'
            ])
            ->assertJsonFragment([
                'name' => 'Updated Name',
                'preferences' => [
                    'notifications' => true,
                    'theme' => 'dark'
                ]
            ]);
        
        // Verify user was updated in DB
        $this->runInTenantContext($this->tenant, function () {
            $updatedUser = $this->user->fresh();
            $this->assertEquals('Updated Name', $updatedUser->name);
            $this->assertEquals(['notifications' => true, 'theme' => 'dark'], $updatedUser->preferences ?? null);
        });
    }
    
    /** @test */
    public function user_cannot_update_email_directly()
    {
        // Authenticate user
        Sanctum::actingAs($this->user, [], 'tenant');
        
        $originalEmail = $this->user->email;
        
        // API call attempting to update email
        $response = $this->putJson("/api/{$this->tenant->slug}/auth/me", [
            'name' => 'Updated Name',
            'email' => 'new_' . Str::random(5) . '@example.com'
        ]);
        
        // Response should be successful but email shouldn't change
        $response->assertStatus(200)
            ->assertJson([
                'name' => 'Updated Name',
                'email' => $originalEmail // Should still be the original email
            ]);
        
        // Verify email wasn't changed in DB
        $this->runInTenantContext($this->tenant, function () use ($originalEmail) {
            $updatedUser = $this->user->fresh();
            $this->assertEquals($originalEmail, $updatedUser->email);
        });
    }
    
    /** @test */
    public function user_can_change_password()
    {
        // Authenticate user
        Sanctum::actingAs($this->user, [], 'tenant');
        
        // API call to change password
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/change-password", [
            'current_password' => 'password123',
            'password' => 'newPassword456',
            'password_confirmation' => 'newPassword456'
        ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Password changed successfully'
            ]);
        
        // Verify password was updated in DB
        $this->runInTenantContext($this->tenant, function () {
            $updatedUser = $this->user->fresh();
            $this->assertTrue(\Illuminate\Support\Facades\Hash::check('newPassword456', $updatedUser->password));
        });
    }
    
    /** @test */
    public function password_change_fails_with_incorrect_current_password()
    {
        // Authenticate user
        Sanctum::actingAs($this->user, [], 'tenant');
        
        // API call with wrong current password
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/change-password", [
            'current_password' => 'wrongPassword',
            'password' => 'newPassword456',
            'password_confirmation' => 'newPassword456'
        ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
        
        // Verify password wasn't changed in DB
        $this->runInTenantContext($this->tenant, function () {
            $updatedUser = $this->user->fresh();
            $this->assertTrue(\Illuminate\Support\Facades\Hash::check('password123', $updatedUser->password));
        });
    }
    
    /** @test */
    public function user_can_request_account_deletion()
    {
        // Authenticate user
        Sanctum::actingAs($this->user, [], 'tenant');
        
        // API call to request account deletion
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/request-deletion");
        
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Account deletion request received'
            ]);
        
        // Verify deletion request was recorded in DB
        $this->runInTenantContext($this->tenant, function () {
            $deletionRequest = \Illuminate\Support\Facades\DB::table('deletion_requests')
                ->where('user_id', $this->user->id)
                ->first();
                
            $this->assertNotNull($deletionRequest);
        });
    }
    
    /** @test */
    public function user_can_get_notifications()
    {
        // Authenticate user
        Sanctum::actingAs($this->user, [], 'tenant');
        
        // Create some notifications for the user
        $this->runInTenantContext($this->tenant, function () {
            // Create notifications in the database
            for ($i = 1; $i <= 3; $i++) {
                \Illuminate\Support\Facades\DB::table('notifications')->insert([
                    'id' => (string) Str::uuid(),
                    'type' => 'App\\Notifications\\TestNotification',
                    'notifiable_type' => 'App\\Models\\Tenants\\User',
                    'notifiable_id' => $this->user->id,
                    'data' => json_encode([
                        'message' => "Notification {$i}",
                        'link' => "/test/{$i}"
                    ]),
                    'read_at' => null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        });
        
        // API call to get user notifications
        $response = $this->getJson("/api/{$this->tenant->slug}/auth/notifications");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'data',
                        'read_at',
                        'created_at'
                    ]
                ],
                'meta' => [
                    'unread_count'
                ]
            ])
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.unread_count', 3);
    }
    
    /** @test */
    public function user_can_mark_notification_as_read()
    {
        // Authenticate user
        Sanctum::actingAs($this->user, [], 'tenant');
        
        // Create a notification for the user
        $notificationId = $this->runInTenantContext($this->tenant, function () {
            // Create a notification
            $id = (string) Str::uuid();
            \Illuminate\Support\Facades\DB::table('notifications')->insert([
                'id' => $id,
                'type' => 'App\\Notifications\\TestNotification',
                'notifiable_type' => 'App\\Models\\Tenants\\User',
                'notifiable_id' => $this->user->id,
                'data' => json_encode([
                    'message' => "Test Notification",
                    'link' => "/test"
                ]),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            return $id;
        });
        
        // API call to mark notification as read
        $response = $this->postJson("/api/{$this->tenant->slug}/auth/notifications/{$notificationId}/read");
        
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Notification marked as read'
            ]);
        
        // Verify notification was marked as read in DB
        $this->runInTenantContext($this->tenant, function () use ($notificationId) {
            $notification = \Illuminate\Support\Facades\DB::table('notifications')
                ->where('id', $notificationId)
                ->first();
                
            $this->assertNotNull($notification->read_at);
        });
    }
}
