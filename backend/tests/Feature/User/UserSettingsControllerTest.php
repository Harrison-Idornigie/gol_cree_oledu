<?php
namespace Tests\Feature\User;

use App\Models\Language;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Language $englishLanguage;
    protected Language $spanishLanguage;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create([
            'interface_language' => 'en',
        ]);

        // Create test languages
        $this->englishLanguage = Language::create([
            'code'        => 'en',
            'name'        => 'English',
            'native_name' => 'English',
            'is_active'   => true,
        ]);

        $this->spanishLanguage = Language::create([
            'code'        => 'es',
            'name'        => 'Spanish',
            'native_name' => 'Español',
            'is_active'   => true,
        ]);
    }

    /** @test */
    public function it_returns_user_settings_with_interface_language()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/user/settings');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['interface_language']])
            ->assertJson([
                'data' => [
                    'interface_language' => 'en',
                ],
            ]);
    }

    /** @test */
    public function it_returns_available_interface_languages()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/user/settings/languages');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'code', 'name', 'native_name']]])
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', 'en')
            ->assertJsonPath('data.1.code', 'es');
    }

    /** @test */
    public function it_updates_user_interface_language()
    {
        $response = $this->actingAs($this->user)
            ->patchJson('/api/user/settings/interface-language', [
                'language_code' => 'es',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'success'            => true,
                    'interface_language' => 'es',
                ],
            ]);

        // Check that the user's interface language was updated in the database
        $this->assertDatabaseHas('users', [
            'id'                 => $this->user->id,
            'interface_language' => 'es',
        ]);
    }

    /** @test */
    public function it_validates_language_code_exists()
    {
        $response = $this->actingAs($this->user)
            ->patchJson('/api/user/settings/interface-language', [
                'language_code' => 'invalid_code',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['language_code']);
    }

    /** @test */
    public function it_requires_authentication()
    {
        $response = $this->patchJson('/api/user/settings/interface-language', [
            'language_code' => 'es',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_returns_english_as_default_interface_language()
    {
        // Create a user with the default interface_language (en)
        $userWithDefaultLanguage = User::factory()->create();

        $response = $this->actingAs($userWithDefaultLanguage)
            ->getJson('/api/user/settings');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'interface_language' => 'en',
                ],
            ]);
    }
}