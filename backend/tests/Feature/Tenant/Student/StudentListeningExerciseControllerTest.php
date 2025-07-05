<?php

namespace Tests\Feature\Tenant\Student;

use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;

class StudentListeningExerciseControllerTest extends TenantTestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $studentUser;
    protected User $teamUser;
    protected array $testData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        // Create test tenant
        $this->tenant = $this->createTestTenant();

        // Create users with different roles in tenant context
        $this->studentUser = $this->createTenantStudent();
        $this->teamUser = $this->createTenantTeamMember();

        // Setup test data
        $this->testData = $this->setupTestData();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /**
     * Helper to create a tenant team member
     */
    protected function createTenantTeamMember()
    {
        return $this->runInTenantContext($this->tenant, function () {
            $user = User::create([
                'name' => 'Team User',
                'email' => 'team_' . Str::random(5) . '@example.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]);

            // Assign team role
            try {
                // Direct DB insert to user_permissions for compatibility
                if (\Illuminate\Support\Facades\Schema::hasTable('user_permissions')) {
                    \Illuminate\Support\Facades\DB::table('user_permissions')->insert([
                        'id' => (string) Str::uuid(),
                        'user_id' => $user->id,
                        'permission' => 'team',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // If we're using membership_type
                if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'membership_type')) {
                    $user->membership_type = 'team';
                    $user->save();
                }
            } catch (\Exception $e) {
                // Role assignment might fail if tables don't exist yet
            }

            return $user;
        });
    }



    /**
     * Setup test data for listening exercises, etc.
     */
    protected function setupTestData()
    {
        return $this->runInTenantContext($this->tenant, function () {
            // Create language for testing
            $languageId = \Illuminate\Support\Facades\DB::table('languages')->insertGetId([
                'name' => 'Test Language',
                'code' => 'tl',
                'native_name' => 'Test Native',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create native language for translations
            $nativeLanguageId = \Illuminate\Support\Facades\DB::table('languages')->insertGetId([
                'name' => 'Native Language',
                'code' => 'nl',
                'native_name' => 'Native Language',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create learning path
            $learningPathId = (string) Str::uuid();
            \Illuminate\Support\Facades\DB::table('learning_paths')->insert([
                'id' => $learningPathId,
                'title' => 'Test Learning Path',
                'description' => 'A test learning path',
                'language_id' => $languageId,
                'status' => 'published',
                'created_by' => $this->teamUser->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create unit
            $unitId = (string) Str::uuid();
            \Illuminate\Support\Facades\DB::table('units')->insert([
                'id' => $unitId,
                'learning_path_id' => $learningPathId,
                'title' => 'Test Unit',
                'description' => 'Test unit description',
                'order' => 1,
                'status' => 'published',
                'created_by' => $this->teamUser->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create topic
            $topicId = (string) Str::uuid();
            \Illuminate\Support\Facades\DB::table('topics')->insert([
                'id' => $topicId,
                'unit_id' => $unitId,
                'title' => 'Test Topic',
                'description' => 'Test topic description',
                'order' => 1,
                'status' => 'published',
                'created_by' => $this->teamUser->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create lesson
            $lessonId = (string) Str::uuid();
            \Illuminate\Support\Facades\DB::table('lessons')->insert([
                'id' => $lessonId,
                'topic_id' => $topicId,
                'title' => 'Test Lesson',
                'description' => 'Test lesson description',
                'order' => 1,
                'status' => 'published',
                'content' => json_encode(['blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Test content']]]]),
                'created_by' => $this->teamUser->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create listening exercises
            $listeningExercises = [];

            for ($i = 1; $i <= 3; $i++) {
                $exerciseId = (string) Str::uuid();

                // Create a listening exercise
                \Illuminate\Support\Facades\DB::table('exercises')->insert([
                    'id' => $exerciseId,
                    'lesson_id' => $lessonId,
                    'title' => "Listening Exercise {$i}",
                    'description' => "Test listening exercise {$i} description",
                    'type' => 'listening',
                    'order' => $i,
                    'status' => 'published',
                    'created_by' => $this->teamUser->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Add listening-specific data
                \Illuminate\Support\Facades\DB::table('listening_exercises')->insert([
                    'exercise_id' => $exerciseId,
                    'audio_url' => "https://example.com/audio/exercise-{$i}.mp3",
                    'transcript' => "This is the transcript for listening exercise {$i}.",
                    'instructions' => "Listen to the audio and write what you hear.",
                    'language_id' => $languageId,
                    'difficulty_level' => $i,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $listeningExercises[] = $exerciseId;
            }

            // Create user progress for first exercise
            \Illuminate\Support\Facades\DB::table('user_exercise_progress')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $this->studentUser->id,
                'exercise_id' => $listeningExercises[0],
                'attempts' => 2,
                'correct_answers' => 1,
                'completed' => false,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return [
                'language_id' => $languageId,
                'native_language_id' => $nativeLanguageId,
                'learning_path_id' => $learningPathId,
                'unit_id' => $unitId,
                'topic_id' => $topicId,
                'lesson_id' => $lessonId,
                'listening_exercises' => $listeningExercises
            ];
        });
    }

    /**  */
    public function student_can_get_listening_exercises_by_language()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get listening exercises by language
        $response = $this->getJson("/api/{$this->tenant->slug}/student/exercises/listening/language/{$this->testData['language_id']}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'type',
                        'audio_url',
                        'difficulty_level',
                        'progress' // Should include student's progress
                    ]
                ]
            ])
            ->assertJsonCount(3, 'data');
    }

    /**  */
    public function student_can_check_listening_exercise_answer()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // Get first exercise
        $exerciseId = $this->testData['listening_exercises'][0];

        // API call to check listening exercise - correct answer
        $response = $this->postJson("/api/{$this->tenant->slug}/student/exercises/listening/check", [
            'exercise_id' => $exerciseId,
            'answer' => 'This is the transcript for listening exercise 1.' // Matches exactly
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'correct',
                    'score',
                    'exercise_id',
                    'feedback'
                ]
            ])
            ->assertJsonFragment([
                'correct' => true,
                'exercise_id' => $exerciseId
            ]);

        // API call to check listening exercise - partially correct answer (with typo)
        $response = $this->postJson("/api/{$this->tenant->slug}/student/exercises/listening/check", [
            'exercise_id' => $exerciseId,
            'answer' => 'This is the transcrpt for listening exercise 1.' // Missing 'i' in transcript
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'correct',
                    'score',
                    'exercise_id',
                    'feedback',
                    'correct_answer'
                ]
            ])
            ->assertJsonPath('data.correct', false)
            ->assertJsonPath('data.score', function ($score) {
                // Should have partial score between 0 and 100 for a near match
                return $score > 0 && $score < 100;
            });

        // API call to check listening exercise - completely wrong answer
        $response = $this->postJson("/api/{$this->tenant->slug}/student/exercises/listening/check", [
            'exercise_id' => $exerciseId,
            'answer' => 'This answer is completely wrong'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'correct',
                    'score',
                    'exercise_id',
                    'feedback',
                    'correct_answer'
                ]
            ])
            ->assertJsonFragment([
                'correct' => false,
                'score' => 0,
                'exercise_id' => $exerciseId,
                'correct_answer' => 'This is the transcript for listening exercise 1.'
            ]);
    }

    /**  */
    public function student_cannot_check_answer_without_required_fields()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // Get first exercise
        $exerciseId = $this->testData['listening_exercises'][0];

        // API call without exercise_id
        $response = $this->postJson("/api/{$this->tenant->slug}/student/exercises/listening/check", [
            'answer' => 'Some answer'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['exercise_id']);

        // API call without answer
        $response = $this->postJson("/api/{$this->tenant->slug}/student/exercises/listening/check", [
            'exercise_id' => $exerciseId
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['answer']);
    }

    /**  */
    public function student_cannot_access_unpublished_listening_exercise()
    {
        // Create an unpublished listening exercise
        $unpublishedExerciseId = $this->runInTenantContext($this->tenant, function () {
            $exerciseId = (string) Str::uuid();

            // Create a draft listening exercise
            \Illuminate\Support\Facades\DB::table('exercises')->insert([
                'id' => $exerciseId,
                'lesson_id' => $this->testData['lesson_id'],
                'title' => "Unpublished Listening Exercise",
                'description' => "This exercise is not published yet",
                'type' => 'listening',
                'order' => 99,
                'status' => 'draft', // Unpublished
                'created_by' => $this->teamUser->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Add listening-specific data
            \Illuminate\Support\Facades\DB::table('listening_exercises')->insert([
                'exercise_id' => $exerciseId,
                'audio_url' => "https://example.com/audio/unpublished.mp3",
                'transcript' => "This is an unpublished exercise.",
                'instructions' => "Draft instructions",
                'language_id' => $this->testData['language_id'],
                'difficulty_level' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return $exerciseId;
        });

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // Try to check answer for unpublished exercise
        $response = $this->postJson("/api/{$this->tenant->slug}/student/exercises/listening/check", [
            'exercise_id' => $unpublishedExerciseId,
            'answer' => 'Some answer'
        ]);

        // Should return 404 as students shouldn't see unpublished exercises
        $response->assertStatus(404);
    }

    /**  */
    public function exercise_completion_is_tracked()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // Get second exercise that has no progress yet
        $exerciseId = $this->testData['listening_exercises'][1];

        // Submit correct answer to complete the exercise
        $this->postJson("/api/{$this->tenant->slug}/student/exercises/listening/check", [
            'exercise_id' => $exerciseId,
            'answer' => 'This is the transcript for listening exercise 2.' // Correct answer
        ]);

        // Verify progress was recorded
        $this->runInTenantContext($this->tenant, function () use ($exerciseId) {
            $progress = \Illuminate\Support\Facades\DB::table('user_exercise_progress')
                ->where('user_id', $this->studentUser->id)
                ->where('exercise_id', $exerciseId)
                ->first();

            $this->assertNotNull($progress);
            $this->assertEquals(1, $progress->attempts);
            $this->assertEquals(1, $progress->correct_answers);
            $this->assertEquals(true, $progress->completed);
        });
    }

    /**  */
    public function unauthenticated_user_cannot_access_listening_exercises()
    {
        // API call without authentication
        $response = $this->getJson("/api/{$this->tenant->slug}/student/exercises/listening/language/{$this->testData['language_id']}");

        $response->assertStatus(401);
    }
}
