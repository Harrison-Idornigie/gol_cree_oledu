<?php
namespace Tests\Feature\User;

use App\Models\Exercise;
use App\Models\Language;
use App\Models\LearningPath;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchingExerciseTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Language $language;
    protected LearningPath $learningPath;
    protected Unit $unit;
    protected Lesson $lesson;
    protected Section $section;
    protected Exercise $matchingExercise;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // Create language
        $this->language = Language::create([
            'code'        => 'es',
            'name'        => 'Spanish',
            'native_name' => 'Español',
            'is_active'   => true,
        ]);

        // Create learning path
        $this->learningPath = LearningPath::create([
            'title'        => 'Spanish Basics',
            'description'  => 'Learn basic Spanish',
            'language_id'  => $this->language->id,
            'target_level' => 'beginner',
            'status'       => 'published',
        ]);

        // Create unit
        $this->unit = Unit::create([
            'learning_path_id' => $this->learningPath->id,
            'title'            => 'Introduction to Spanish',
            'description'      => 'Basic Spanish vocabulary',
            'order'            => 1,
            'status'           => 'published',
        ]);

        // Create lesson
        $this->lesson = Lesson::create([
            'unit_id'     => $this->unit->id,
            'title'       => 'Basic Vocabulary',
            'description' => 'Learn basic Spanish words',
            'order'       => 1,
            'status'      => 'published',
        ]);

        // Create section
        $this->section = Section::create([
            'lesson_id' => $this->lesson->id,
            'title'     => 'Matching Words',
            'slug'      => 'matching-words',
            'content'   => '<p>Match the Spanish words with their English translations</p>',
            'order'     => 1,
            'status'    => 'published',
        ]);

        // Create matching exercise
        $this->matchingExercise = Exercise::create([
            'section_id' => $this->section->id,
            'lesson_id'  => $this->lesson->id,
            'title'      => 'Match Spanish-English',
            'slug'       => 'match-spanish-english',
            'type'       => Exercise::TYPE_MATCHING,
            'content'    => [
                'instructions' => 'Match the Spanish words with their English translations',
                'items'        => ['perro', 'gato', 'casa', 'libro'],
                'matches'      => ['dog', 'cat', 'house', 'book'],
                'word_ids'     => [1, 2, 3, 4],
                'word_mapping' => [
                    'perro' => 1,
                    'gato'  => 2,
                    'casa'  => 3,
                    'libro' => 4,
                    'dog'   => 5,
                    'cat'   => 6,
                    'house' => 7,
                    'book'  => 8,
                ],
                'answers'      => [
                    'correct' => [
                        0 => 0, // perro -> dog
                        1 => 1, // gato -> cat
                        2 => 2, // casa -> house
                        3 => 3, // libro -> book
                    ],
                ],
            ],
            'order'      => 1,
            'status'     => 'published',
        ]);

        // Enroll user in the learning path
        UserProgress::create([
            'user_id'        => $this->user->id,
            'trackable_type' => LearningPath::class,
            'trackable_id'   => $this->learningPath->id,
            'status'         => UserProgress::STATUS_IN_PROGRESS,
        ]);
    }

    /** @test */
    public function it_can_retrieve_matching_exercise()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/exercises?section_id={$this->section->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', Exercise::TYPE_MATCHING)
            ->assertJsonPath('data.0.content.instructions', 'Match the Spanish words with their English translations')
            ->assertJsonPath('data.0.content.items.0', 'perro')
            ->assertJsonPath('data.0.content.matches.0', 'dog');

        // Ensure answers are not included in the response
        $this->assertArrayNotHasKey('answers', $response->json('data.0.content'));
    }

    /** @test */
    public function it_correctly_validates_matching_exercise_correct_answer()
    {
        $correctAnswer = [
            '0' => 0, // perro -> dog
            '1' => 1, // gato -> cat
            '2' => 2, // casa -> house
            '3' => 3, // libro -> book
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/exercises/{$this->matchingExercise->id}/check", [
                'answer'     => $correctAnswer,
                'started_at' => now()->subMinutes(2)->toIso8601String(),
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.correct', true);
    }

    /** @test */
    public function it_correctly_validates_matching_exercise_incorrect_answer()
    {
        $incorrectAnswer = [
            '0' => 1, // perro -> cat (incorrect)
            '1' => 0, // gato -> dog (incorrect)
            '2' => 2, // casa -> house (correct)
            '3' => 3, // libro -> book (correct)
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/exercises/{$this->matchingExercise->id}/check", [
                'answer'     => $incorrectAnswer,
                'started_at' => now()->subMinutes(2)->toIso8601String(),
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.correct', false);
    }

    /** @test */
    public function it_correctly_validates_matching_exercise_partial_answer()
    {
        $partialAnswer = [
            '0' => 0, // perro -> dog (correct)
            '1' => 1, // gato -> cat (correct)
                      // Missing casa and libro
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/exercises/{$this->matchingExercise->id}/check", [
                'answer'     => $partialAnswer,
                'started_at' => now()->subMinutes(2)->toIso8601String(),
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.correct', false);
    }

    /** @test */
    public function it_correctly_validates_matching_exercise_with_extra_answers()
    {
        $extraAnswer = [
            '0' => 0, // perro -> dog (correct)
            '1' => 1, // gato -> cat (correct)
            '2' => 2, // casa -> house (correct)
            '3' => 3, // libro -> book (correct)
            '4' => 0, // Extra answer that shouldn't be there
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/exercises/{$this->matchingExercise->id}/check", [
                'answer'     => $extraAnswer,
                'started_at' => now()->subMinutes(2)->toIso8601String(),
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.correct', false);
    }

    /** @test */
    public function it_records_attempt_when_checking_answer()
    {
        $correctAnswer = [
            '0' => 0, // perro -> dog
            '1' => 1, // gato -> cat
            '2' => 2, // casa -> house
            '3' => 3, // libro -> book
        ];

        $response = $this->actingAs($this->user)
            ->postJson("/api/exercises/{$this->matchingExercise->id}/check", [
                'answer'     => $correctAnswer,
                'started_at' => now()->subMinutes(2)->toIso8601String(),
            ]);

        $response->assertStatus(200);

        // Check that an attempt was recorded in the database
        $this->assertDatabaseHas('exercise_attempts', [
            'exercise_id' => $this->matchingExercise->id,
            'user_id'     => $this->user->id,
            'is_correct'  => true,
        ]);
    }

    /** @test */
    public function it_requires_authentication_to_check_answer()
    {
        $correctAnswer = [
            '0' => 0, // perro -> dog
            '1' => 1, // gato -> cat
            '2' => 2, // casa -> house
            '3' => 3, // libro -> book
        ];

        $response = $this->postJson("/api/exercises/{$this->matchingExercise->id}/check", [
            'answer'     => $correctAnswer,
            'started_at' => now()->subMinutes(2)->toIso8601String(),
        ]);

        $response->assertStatus(401); // Unauthorized
    }

    /** @test */
    public function it_validates_required_fields_when_checking_answer()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/exercises/{$this->matchingExercise->id}/check", [
                // Missing 'answer' field
                'started_at' => now()->subMinutes(2)->toIso8601String(),
            ]);

        $response->assertStatus(422) // Unprocessable Entity
            ->assertJsonValidationErrors(['answer']);

        $response = $this->actingAs($this->user)
            ->postJson("/api/exercises/{$this->matchingExercise->id}/check", [
                'answer' => [
                    '0' => 0,
                ],
                // Missing 'started_at' field
            ]);

        $response->assertStatus(422) // Unprocessable Entity
            ->assertJsonValidationErrors(['started_at']);
    }
}