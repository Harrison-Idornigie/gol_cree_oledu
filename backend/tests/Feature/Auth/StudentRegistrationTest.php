<?php
namespace Tests\Feature\Auth;

use App\Models\Tenants\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StudentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_student_can_register()
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Test Student',
            'email'                 => 'student@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'membership',
                ],
            ],
            'message',
        ]);

        $this->assertDatabaseHas('users', [
            'name'  => 'Test Student',
            'email' => 'student@example.com',
            'membership'  => 'user',
        ]);

        $user = User::where('email', 'student@example.com')->first();
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_student_cannot_register_with_existing_email()
    {
        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Test Student',
            'email'                 => 'existing@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_student_cannot_register_with_invalid_data()
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => '',
            'email'                 => 'not-an-email',
            'password'              => 'short',
            'password_confirmation' => 'different',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_student_can_login_after_registration()
    {
        // First register a user
        $this->postJson('/api/auth/register', [
            'name'                  => 'Test Student',
            'email'                 => 'student@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // Then attempt to login
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'student@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'token',
                'user',
            ],
            'message',
        ]);
    }

    public function test_student_cannot_access_admin_routes()
    {
        // Register a student user
        $this->postJson('/api/auth/register', [
            'name'                  => 'Test Student',
            'email'                 => 'student@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user  = User::where('email', 'student@example.com')->first();
        $token = $user->createToken('auth-token')->plainTextToken;

        // Attempt to access an admin route
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/admin/users');

        // The response should either be 403 (Forbidden) or 404 (Not Found)
        // Both indicate that the student cannot access the admin route
        $this->assertTrue(
            $response->status() === 403 || $response->status() === 404,
            'Expected response status to be 403 or 404, but got ' . $response->status()
        );
    }
}
