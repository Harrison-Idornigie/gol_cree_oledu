<?php

namespace Tests\Feature\Admin;

use App\Models\AdminInvite;
use App\Models\Tenants\User;
use Illuminate\Support\Facades\Mail;

class AdminInviteTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_admin_can_send_invite()
    {
        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/users/invite', [
                'email' => 'newteam@example.com',
                'role' => 'team',
                'message' => 'Welcome to our platform!'
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'email' => 'newteam@example.com',
                    'role' => 'team',
                    'status' => 'pending'
                ]
            ]);

        $this->assertDatabaseHas('admin_invites', [
            'email' => 'newteam@example.com',
            'role' => 'team',
            'status' => 'pending'
        ]);

        Mail::assertSent(\App\Mail\AdminInvitation::class, function ($mail) {
            return $mail->hasTo('newteam@example.com');
        });
    }

    public function test_admin_cannot_invite_existing_user()
    {
        User::create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user'
        ]);

        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/users/invite', [
                'email' => 'existing@example.com',
                'role' => 'team'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_admin_cannot_invite_with_invalid_role()
    {
        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/users/invite', [
                'email' => 'newuser@example.com',
                'role' => 'invalid_role'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    public function test_admin_can_cancel_invite()
    {
        $invite = AdminInvite::create([
            'email' => 'pending@example.com',
            'role' => 'team',
            'token' => 'test-token',
            'status' => 'pending',
            'invited_by' => $this->admin->id,
            'expires_at' => now()->addDays(7)
        ]);

        $response = $this->actingAsAdmin()
            ->deleteJson("/api/admin/users/invite/{$invite->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Invitation cancelled successfully'
            ]);

        $this->assertDatabaseMissing('admin_invites', [
            'id' => $invite->id,
            'status' => 'pending'
        ]);
    }

    public function test_admin_cannot_cancel_accepted_invite()
    {
        $invite = AdminInvite::create([
            'email' => 'accepted@example.com',
            'role' => 'team',
            'token' => 'test-token',
            'status' => 'accepted',
            'invited_by' => $this->admin->id,
            'expires_at' => now()->addDays(7)
        ]);

        $response = $this->actingAsAdmin()
            ->deleteJson("/api/admin/users/invite/{$invite->id}");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot cancel accepted invitation'
            ]);
    }

    public function test_cannot_invite_with_invalid_email()
    {
        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/users/invite', [
                'email' => 'invalid-email',
                'role' => 'team'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_unauthorized_user_cannot_manage_invites()
    {
        $response = $this->actingAsUser()
            ->postJson('/api/admin/users/invite', [
                'email' => 'newuser@example.com',
                'role' => 'team'
            ]);

        $this->assertUnauthorized($response);
    }

    public function test_admin_can_resend_invite()
    {
        $invite = AdminInvite::create([
            'email' => 'pending@example.com',
            'role' => 'team',
            'token' => 'test-token',
            'status' => 'pending',
            'invited_by' => $this->admin->id,
            'expires_at' => now()->addDays(7)
        ]);

        $response = $this->actingAsAdmin()
            ->postJson("/api/admin/users/invite/{$invite->id}/resend");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Invitation resent successfully'
            ]);

        Mail::assertSent(\App\Mail\AdminInvitation::class, function ($mail) {
            return $mail->hasTo('pending@example.com');
        });
    }
}