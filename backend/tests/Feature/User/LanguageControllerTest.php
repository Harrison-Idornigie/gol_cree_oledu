<?php
namespace Tests\Feature\User;

use App\Models\Language;
use App\Models\LearningPath;
use App\Models\Tenants\User;
use App\Models\UserProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LanguageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Language $language1;
    protected Language $language2;
    protected LearningPath $learningPath1;
    protected LearningPath $learningPath2;
    protected LearningPath $learningPath3;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user
        $this->user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // Create languages
        $this->language1 = Language::create([
            'code'        => 'en',
            'name'        => 'English',
            'native_name' => 'English',
            'is_active'   => true,
        ]);

        $this->language2 = Language::create([
            'code'        => 'es',
            'name'        => 'Spanish',
            'native_name' => 'Español',
            'is_active'   => true,
        ]);

        // Create an inactive language
        $inactiveLanguage = Language::create([
            'code'        => 'fr',
            'name'        => 'French',
            'native_name' => 'Français',
            'is_active'   => false,
        ]);

        // Create learning paths
        $this->learningPath1 = LearningPath::create([
            'title'        => 'English Beginner',
            'language_id'  => $this->language1->id,
            'description'  => 'Learn basic English',
            'target_level' => 'beginner',
            'status'       => 'published',
        ]);

        $this->learningPath2 = LearningPath::create([
            'title'        => 'English Advanced',
            'language_id'  => $this->language1->id,
            'description'  => 'Advanced English course',
            'target_level' => 'advanced',
            'status'       => 'published',
        ]);

        $this->learningPath3 = LearningPath::create([
            'title'        => 'Spanish Beginner',
            'language_id'  => $this->language2->id,
            'description'  => 'Learn basic Spanish',
            'target_level' => 'beginner',
            'status'       => 'published',
        ]);

        // Create a draft learning path
        LearningPath::create([
            'title'        => 'Spanish Advanced',
            'language_id'  => $this->language2->id,
            'description'  => 'Advanced Spanish course',
            'target_level' => 'advanced',
            'status'       => 'draft',
        ]);

        // Create a learning path for the inactive language
        LearningPath::create([
            'title'        => 'French Beginner',
            'language_id'  => $inactiveLanguage->id,
            'description'  => 'Learn basic French',
            'target_level' => 'beginner',
            'status'       => 'published',
        ]);
    }

    /** @test */
    public function it_can_list_all_active_languages()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/languages');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', $this->language1->name)
            ->assertJsonPath('data.1.name', $this->language2->name);
    }

    /** @test */
    public function it_can_show_a_specific_language()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/languages/{$this->language1->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.name', $this->language1->name)
            ->assertJsonPath('data.code', $this->language1->code);
    }

    /** @test */
    public function it_cannot_show_an_inactive_language()
    {
        $inactiveLanguage = Language::where('code', 'fr')->first();

        $response = $this->actingAs($this->user)
            ->getJson("/api/languages/{$inactiveLanguage->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function it_can_list_languages_with_learning_paths()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/languages/with-learning-paths');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', $this->language1->name)
            ->assertJsonPath('data.1.name', $this->language2->name);
    }

    /** @test */
    public function it_can_get_learning_paths_for_a_language()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/languages/{$this->language1->id}/learning-paths");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', $this->learningPath1->title)
            ->assertJsonPath('data.1.title', $this->learningPath2->title);
    }

    /** @test */
    public function it_can_filter_learning_paths_by_target_level()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/languages/{$this->language1->id}/learning-paths?target_level=beginner");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', $this->learningPath1->title);
    }

    /** @test */
    public function it_can_get_proficiency_levels_for_a_language()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/languages/{$this->language1->id}/proficiency-levels");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['data' => ['beginner', 'advanced']]);
    }

    /** @test */
    public function it_can_get_user_progress_for_a_language()
    {
        // Create progress for the first learning path
        UserProgress::create([
            'user_id'        => $this->user->id,
            'trackable_type' => LearningPath::class,
            'trackable_id'   => $this->learningPath1->id,
            'status'         => UserProgress::STATUS_COMPLETED,
        ]);

        // Create progress for the second learning path
        UserProgress::create([
            'user_id'        => $this->user->id,
            'trackable_type' => LearningPath::class,
            'trackable_id'   => $this->learningPath2->id,
            'status'         => UserProgress::STATUS_IN_PROGRESS,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/languages/{$this->language1->id}/progress");

        $response->assertStatus(200)
            ->assertJsonPath('data.language.id', $this->language1->id)
            ->assertJsonPath('data.total_paths', 2)
            ->assertJsonPath('data.completed_paths', 1)
            ->assertJsonPath('data.in_progress_paths', 1)
            ->assertJsonPath('data.not_started_paths', 0)
            ->assertJsonPath('data.progress_percentage', 75);
    }
}
