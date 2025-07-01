<?php

namespace Tests\Feature\Tenant\Student;

use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\Language;
use App\Models\Tenants\Exercise;
use App\Models\Tenants\Lesson;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;

class StudentExerciseControllerTest extends TenantTestCase
{
    use InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $studentUser;
    protected User $teamUser;
    protected Language $language;
    protected Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        // Create test tenant
        $this->tenant = $this->createTestTenant();

        // Create users with different roles in tenant context
        $this->studentUser = $this->createTenantStudent();
        $this->teamUser = $this->createTenantTeam();

        // Create test environment (but not exercise - create that in each test)
        $this->language = $this->createLanguage();
        $this->lesson = $this->createLesson();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    /**
     * Helper to create a test language
     */
    protected function createLanguage(array $attributes = []): Language
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return Language::create(array_merge([
                'name' => 'Test Language',
                'code' => 'tl-' . Str::random(4),
                'native_name' => 'Test Language Native',
                'is_active' => true
            ], $attributes));
        });
    }

    /**
     * Helper to create a test lesson
     */
    protected function createLesson()
    {
        return $this->runInTenantContext($this->tenant, function () {
            // Create the full hierarchy: learning_path -> unit -> topic -> lesson
            $learningPath = \App\Models\Tenants\LearningPath::create([
                'title' => 'Test Learning Path',
                'language_id' => $this->language->id,
                'description' => 'Test learning path',
                'status' => 'published',
                'target_level' => 'A1',
                'tenant_id' => $this->tenant->id,
            ]);

            $unit = \App\Models\Tenants\Unit::create([
                'learning_path_id' => $learningPath->id,
                'title' => 'Test Unit',
                'description' => 'Test unit',
                'order' => 1,
                'status' => 'published',
                'tenant_id' => $this->tenant->id,
            ]);

            $topic = \App\Models\Tenants\Topic::create([
                'unit_id' => $unit->id,
                'title' => 'Test Topic',
                'slug' => 'test-topic-' . Str::random(8),
                'description' => 'Test topic',
                'order' => 1,
                'status' => 'published',
                'tenant_id' => $this->tenant->id,
            ]);

            return Lesson::create([
                'topic_id' => $topic->id,
                'title' => 'Test Lesson',
                'description' => 'This is a test lesson',
                'order' => 1,
                'status' => 'published',
                'created_by' => $this->teamUser->id,
                'tenant_id' => $this->tenant->id,
            ]);
        });
    }

    /**
     * Helper to create a test exercise
     */
    protected function createExercise(array $attributes = []): Exercise
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return Exercise::create(array_merge([
                'title' => 'Test Exercise',
                'slug' => 'test-exercise-' . Str::random(8),
                'type' => 'multiple_choice',
                'lesson_id' => $this->lesson->id,
                'difficulty_level' => 1,
                'status' => 'published',
                'content' => json_encode([
                    'question' => 'Test Question',
                    'options' => ['Option 1', 'Option 2', 'Option 3', 'Option 4']
                ]),
                'answers' => [
                    'correct' => 'Option 2'
                ],
                'created_by' => $this->teamUser->id,
                'tenant_id' => $this->tenant->id,
            ], $attributes));
        });
    }

    /**
     * Test listing exercises
     */
    public function test_index_success()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        $response = $this->getJson("/api/{$this->tenant->slug}/student/exercises");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Exercises retrieved successfully.'
            ]);
    }

    /**
     * Test retrieving a specific exercise
     */
    public function test_show_success()
    {
        // Create exercise within the test method
        $exercise = $this->createExercise();

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        $response = $this->getJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'title',
                    'type',
                    'lesson_id',
                    'difficulty_level',
                    'status',
                    'content',
                    'created_by',
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Exercise retrieved successfully.'
            ]);
    }

    /**
     * Test checking an answer for an exercise
     */
    public function test_check_answer_success()
    {
        // Create exercise within the test method
        $exercise = $this->createExercise();

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        $answerData = [
            'answer' => 'Option 2' // This matches the correct answer in our test exercise
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}/check", $answerData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'is_correct',
                    'score',
                    'passed',
                    'feedback',
                    'attempt_number',
                    'exercise_completed'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Answer checked successfully.',
                'data' => [
                    'is_correct' => true,
                    'passed' => true
                ]
            ]);
    }

    /**
     * Test checking a wrong answer
     */
    public function test_check_wrong_answer()
    {
        // Create exercise within the test method
        $exercise = $this->createExercise();

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        $answerData = [
            'answer' => 'Option 1' // This is an incorrect answer
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}/check", $answerData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'is_correct',
                    'score',
                    'passed',
                    'feedback',
                    'attempt_number',
                    'exercise_completed'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Answer checked successfully.',
                'data' => [
                    'is_correct' => false,
                    'passed' => false
                ]
            ]);
    }

    /**
     * Test getting exercise statistics
     */
    public function test_statistics_success()
    {
        // Create exercise within the test method
        $exercise = $this->createExercise();

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        $response = $this->getJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}/statistics");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Exercise statistics retrieved successfully.'
            ]);
    }

    /**
     * Test unauthorized access
     */
    public function test_unauthorized_access()
    {
        // Create exercise within the test method
        $exercise = $this->createExercise();

        // Not authenticated
        $response = $this->getJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}");
        $response->assertStatus(401);
    }

    /**
     * Test access to unpublished exercise
     */
    public function test_unpublished_exercise_access()
    {
        // Create an unpublished exercise
        $unpublishedExercise = $this->runInTenantContext($this->tenant, function () {
            return Exercise::create([
                'title' => 'Unpublished Exercise',
                'slug' => 'unpublished-exercise-' . Str::random(8),
                'type' => 'multiple_choice',
                'lesson_id' => $this->lesson->id,
                'difficulty_level' => 1,
                'status' => 'draft', // Unpublished status
                'content' => [
                    'question' => 'Hidden Question',
                    'options' => ['Option A', 'Option B', 'Option C', 'Option D'],
                ],
                'answers' => [
                    'correct' => 'Option A'
                ],
                'created_by' => $this->teamUser->id,
                'tenant_id' => $this->tenant->id,
            ]);
        });

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        $response = $this->getJson("/api/{$this->tenant->slug}/student/exercises/{$unpublishedExercise->id}");
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Failed to retrieve exercise.',
                'errors' => [
                    'error' => 'This action is unauthorized.'
                ]
            ]);
    }

    /**
     * Helper method to initialize tenant context
     */
    protected function initializeTenantContext(Tenant $tenant): void
    {
        // The tenant is already initialized and seeded in createTestTenant
        // This method is kept for compatibility but not needed with enhanced trait
    }

    /**
     * Helper method to create tenant team member
     */
    protected function createTenantTeam(array $attributes = []): User
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return User::factory()->create(array_merge([
                'email' => 'team@test.com',
                'membership' => 'team',
                'email_verified_at' => now(),
            ], $attributes));
        });
    }

    /**
     * Helper method to create tenant student
     */
    protected function createTenantStudent(array $attributes = []): User
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return User::factory()->create(array_merge([
                'email' => 'student@test.com',
                'membership' => 'student',
                'email_verified_at' => now(),
            ], $attributes));
        });
    }
}
