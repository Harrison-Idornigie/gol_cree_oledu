<?php
namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_can_be_requested()
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post('/api/auth/password/email', [
            'email' => $user->email,
        ]);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
        $response->assertJson(['message' => 'We have emailed your password reset link.']);
    }

    public function test_password_can_be_reset_with_valid_token()
    {
        // Create a user
        $user = User::factory()->create();

        // Create a password reset token
        $token = 'test-token-' . Str::random(10);

        // Insert the token into the database
        DB::table('password_reset_tokens')->insert([
            'email'      => $user->email,
            'token'      => Hash::make($token),
            'created_at' => now(),
        ]);

        // Attempt to reset the password
        $response = $this->post('/api/auth/password/reset', [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        // Assert the response
        $response->assertJson(['message' => 'Your password has been reset.']);

        // Assert the password was changed
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_password_cannot_be_reset_with_invalid_token()
    {
        $user = User::factory()->create();

        $response = $this->post('/api/auth/password/reset', [
            'token'                 => 'invalid-token',
            'email'                 => $user->email,
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(400);
        $this->assertFalse(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_password_cannot_be_reset_with_invalid_email()
    {
        $user  = User::factory()->create();
        $token = 'valid-token';

        DB::table('password_reset_tokens')->insert([
            'email'      => $user->email,
            'token'      => Hash::make($token),
            'created_at' => now(),
        ]);

        $response = $this->post('/api/auth/password/reset', [
            'token'                 => $token,
            'email'                 => 'wrong@example.com',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(400);
        $this->assertFalse(Hash::check('newpassword123', $user->fresh()->password));
    }
}