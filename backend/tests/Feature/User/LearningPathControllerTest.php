<?php
namespace Tests\Feature\User;

use App\Models\Language;
use App\Models\LearningPath;
use App\Models\Tenants\User;
use App\Models\UserProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningPathControllerTest extends TestCase
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
    }

    /** @test */
    public function it_can_list_all_learning_paths()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/learning-paths');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.title', $this->learningPath1->title)
            ->assertJsonPath('data.1.title', $this->learningPath2->title)
            ->assertJsonPath('data.2.title', $this->learningPath3->title);
    }

    /** @test */
    public function it_can_filter_learning_paths_by_language()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/learning-paths?language_id={$this->language1->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', $this->learningPath1->title)
            ->assertJsonPath('data.1.title', $this->learningPath2->title);
    }

    /** @test */
    public function it_can_filter_learning_paths_by_target_level()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/learning-paths?target_level=beginner');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', $this->learningPath1->title)
            ->assertJsonPath('data.1.title', $this->learningPath3->title);
    }

    /** @test */
    public function it_can_filter_learning_paths_by_language_and_target_level()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/learning-paths?language_id={$this->language1->id}&target_level=beginner");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', $this->learningPath1->title);
    }

    /** @test */
    public function it_can_get_learning_paths_by_language()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/languages/{$this->language2->id}/learning-paths");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', $this->learningPath3->title);
    }

    /** @test */
    public function it_can_get_learning_paths_by_level()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/learning-paths/by-level/advanced");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', $this->learningPath2->title);
    }

    /** @test */
    public function it_can_get_languages_with_learning_paths()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/languages/with-learning-paths");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', $this->language1->name)
            ->assertJsonPath('data.1.name', $this->language2->name);
    }

    /** @test */
    public function it_can_enroll_in_a_learning_path()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/learning-paths/{$this->learningPath1->id}/enroll");

        $response->assertStatus(200);

        // Check that a progress record was created
        $this->assertDatabaseHas('user_progress', [
            'user_id'        => $this->user->id,
            'trackable_type' => LearningPath::class,
            'trackable_id'   => $this->learningPath1->id,
            'status'         => UserProgress::STATUS_IN_PROGRESS,
        ]);
    }

    /** @test */
    public function it_cannot_enroll_in_an_unpublished_learning_path()
    {
        // Create an unpublished learning path
        $draftPath = LearningPath::create([
            'title'        => 'Draft Path',
            'language_id'  => $this->language1->id,
            'description'  => 'This is a draft path',
            'target_level' => 'beginner',
            'status'       => 'draft',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/learning-paths/{$draftPath->id}/enroll");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot enroll in an unpublished learning path.',
            ]);

        // Check that no progress record was created
        $this->assertDatabaseMissing('user_progress', [
            'user_id'        => $this->user->id,
            'trackable_type' => LearningPath::class,
            'trackable_id'   => $draftPath->id,
        ]);
    }

    /** @test */
    public function it_returns_already_enrolled_message_when_enrolling_again()
    {
        // First enrollment
        $this->actingAs($this->user)
            ->postJson("/api/learning-paths/{$this->learningPath1->id}/enroll");

        // Try to enroll again
        $response = $this->actingAs($this->user)
            ->postJson("/api/learning-paths/{$this->learningPath1->id}/enroll");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Already enrolled in this learning path.');

        // Check that only one progress record exists
        $this->assertDatabaseCount('user_progress', 1);
    }

    /** @test */
    public function it_can_show_learning_path_details_with_units()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/learning-paths/{$this->learningPath1->id}?with_units=true");

        $response->assertStatus(200)
            ->assertJsonPath('data.title', $this->learningPath1->title)
            ->assertJsonPath('data.description', $this->learningPath1->description)
            ->assertJsonPath('data.target_level', $this->learningPath1->target_level)
            ->assertJsonPath('data.language_id', $this->learningPath1->language_id);
    }
}