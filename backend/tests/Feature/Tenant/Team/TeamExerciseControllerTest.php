<?php

namespace Tests\Feature\Tenant\Team;

use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\Language;
use App\Models\Tenants\LearningPath;
use App\Models\Tenants\Unit;
use App\Models\Tenants\Topic;
use App\Models\Tenants\Lesson;
use App\Models\Tenants\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

class TeamExerciseControllerTest extends TenantTestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $teamMember;
    protected User $studentUser;
    protected Language $language;
    protected LearningPath $learningPath;
    protected Unit $unit;
    protected Topic $topic;
    protected Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        // Create test tenant
        $this->tenant = $this->createTestTenant();

        // Create test users in tenant context
        $this->teamMember = $this->runInTenantContext($this->tenant, function () {
            return User::create([
                'name' => 'Team Member',
                'email' => 'team@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'membership' => 'team',
                'tenant_id' => $this->tenant->id,
            ]);
        });

        $this->studentUser = $this->runInTenantContext($this->tenant, function () {
            return User::create([
                'name' => 'Student User',
                'email' => 'student@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'membership' => 'student',
                'tenant_id' => $this->tenant->id,
            ]);
        });

        // Create test language, learning path, unit, topic and lesson
        $this->language = $this->runInTenantContext($this->tenant, function () {
            return Language::create([
                'name' => 'Spanish',
                'code' => 'es',
                'native_name' => 'Español',
                'direction' => 'ltr',
                'status' => 'active',
            ]);
        });

        $this->learningPath = $this->runInTenantContext($this->tenant, function () {
            return LearningPath::create([
                'title' => 'Spanish for Beginners',
                'description' => 'Learn Spanish from scratch',
                'language_id' => $this->language->id,
                'target_level' => 'beginner',
                'status' => 'published',
                'created_by' => $this->teamMember->id,
            ]);
        });

        $this->unit = $this->runInTenantContext($this->tenant, function () {
            return Unit::create([
                'title' => 'Unit 1: Basics',
                'description' => 'Basic Spanish vocabulary and phrases',
                'learning_path_id' => $this->learningPath->id,
                'order' => 1,
                'status' => 'published',
            ]);
        });

        $this->topic = $this->runInTenantContext($this->tenant, function () {
            return Topic::create([
                'title' => 'Greetings',
                'slug' => 'greetings',
                'description' => 'Basic Spanish greetings',
                'unit_id' => $this->unit->id,
                'order' => 1,
                'status' => 'published',
            ]);
        });

        $this->lesson = $this->runInTenantContext($this->tenant, function () {
            return Lesson::create([
                'title' => 'Lesson 1: Basic Greetings',
                'description' => 'Learn basic Spanish greetings',
                'topic_id' => $this->topic->id,
                'order' => 1,
                'status' => 'published',
                'created_by' => $this->teamMember->id,
            ]);
        });
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    protected function createTestExercise(array $attributes = []): Exercise
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            $defaults = [
                'title' => 'Exercise 1: Match Greetings',
                'slug' => 'exercise-1-match-greetings-' . uniqid(),
                'description' => 'Match Spanish greetings with their English translations',
                'lesson_id' => $this->lesson->id,
                'type' => 'multiple_choice',
                'order' => 1,
                'status' => 'published',
                'content' => [
                    'question' => 'What does "Hola" mean?',
                    'options' => ['Hello', 'Goodbye', 'Thank you', 'Please'],
                    'correct_answer' => 0
                ],
            ];

            // If a custom slug is provided, use it, otherwise generate unique one
            if (isset($attributes['slug'])) {
                $defaults['slug'] = $attributes['slug'];
            }

            return Exercise::create(array_merge($defaults, $attributes));
        });
    }

    /**
     * Test team member can list exercises
     *
     * 
     */
    public function test_team_member_can_list_exercises()
    {
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        // Create test exercises with unique identifiers
        $uniqueId = uniqid();
        $exercise1 = $this->createTestExercise([
            'title' => "Test Exercise 1 - {$uniqueId}",
            'slug' => "test-exercise-1-{$uniqueId}",
            'order' => 1
        ]);
        $exercise2 = $this->createTestExercise([
            'title' => "Test Exercise 2 - {$uniqueId}",
            'slug' => "test-exercise-2-{$uniqueId}",
            'order' => 2
        ]);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/exercises");

        $response->assertStatus(200);

        // Verify our specific exercises are in the response
        // The response is paginated, so exercises are at data.data
        $exercisesData = $response->json('data.data');
        $this->assertIsArray($exercisesData, 'Exercises data should be an array');

        $exerciseTitles = collect($exercisesData)->pluck('title')->toArray();

        $this->assertContains("Test Exercise 1 - {$uniqueId}", $exerciseTitles);
        $this->assertContains("Test Exercise 2 - {$uniqueId}", $exerciseTitles);

        // Verify the response structure
        $response->assertJsonStructure([
            'success',
            'data' => [
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'lesson_id',
                        'type',
                        'order',
                        'status',
                        'created_by',
                        'created_at',
                        'updated_at'
                    ]
                ]
            ],
            'message'
        ]);
    }

    /**
     * Test team member can create exercise
     * 
     * 
     */
    public function test_team_member_can_create_exercise()
    {
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        $exerciseData = [
            'title' => 'New Exercise',
            'description' => 'A new exercise for testing',
            'lesson_id' => $this->lesson->id,
            'type' => 'fill_blank',
            'order' => 1,
            'content' => [
                'text' => 'Hello means ____ in Spanish',
                'answer' => 'Hola'
            ],
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/exercises", $exerciseData);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'New Exercise')
            ->assertJsonPath('data.description', 'A new exercise for testing')
            ->assertJsonPath('data.lesson_id', $this->lesson->id)
            ->assertJsonPath('data.type', 'fill_blank')
            ->assertJsonPath('data.order', 1)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.created_by', $this->teamMember->id);

        $this->runInTenantContext($this->tenant, function () {
            $this->assertDatabaseHas('exercises', [
                'title' => 'New Exercise',
                'description' => 'A new exercise for testing',
                'lesson_id' => $this->lesson->id,
            ]);
        });
    }

    /**
     * Test exercise creation validation
     * 
     * 
     */
    public function test_exercise_creation_validation()
    {
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        // Missing required fields
        $response = $this->postJson("/api/{$this->tenant->slug}/team/exercises", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'lesson_id', 'type', 'content']);

        // Invalid lesson
        $response = $this->postJson("/api/{$this->tenant->slug}/team/exercises", [
            'title' => 'Test Exercise',
            'lesson_id' => 99999,
            'type' => 'multiple_choice',
            'content' => ['question' => 'Test question'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['lesson_id']);

        // Invalid exercise type
        $response = $this->postJson("/api/{$this->tenant->slug}/team/exercises", [
            'title' => 'Test Exercise',
            'lesson_id' => $this->lesson->id,
            'type' => 'invalid_type',
            'content' => ['question' => 'Test question'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    /**
     * Test team member can show exercise
     * 
     * 
     */
    public function test_team_member_can_show_exercise()
    {
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        $exercise = $this->createTestExercise();

        $response = $this->getJson("/api/{$this->tenant->slug}/team/exercises/{$exercise->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $exercise->id)
            ->assertJsonPath('data.title', $exercise->title)
            ->assertJsonPath('data.description', $exercise->description)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'description',
                    'lesson_id',
                    'type',
                    'order',
                    'status',
                    'content',
                    'created_by',
                    'created_at',
                    'updated_at'
                ]
            ]);
    }

    /**
     * Test team member can update exercise
     * 
     * 
     */
    public function test_team_member_can_update_exercise()
    {
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        $exercise = $this->createTestExercise();

        $updateData = [
            'title' => 'Updated Exercise Title',
            'description' => 'Updated description',
            'order' => 5,
            'type' => 'multiple_choice',
        ];

        $response = $this->putJson("/api/{$this->tenant->slug}/team/exercises/{$exercise->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Updated Exercise Title')
            ->assertJsonPath('data.description', 'Updated description')
            ->assertJsonPath('data.order', 5)
            ->assertJsonPath('data.type', 'multiple_choice');

        $this->runInTenantContext($this->tenant, function () use ($exercise) {
            $this->assertDatabaseHas('exercises', [
                'id' => $exercise->id,
                'title' => 'Updated Exercise Title',
                'description' => 'Updated description',
                'order' => 5,
                'type' => 'multiple_choice',
            ]);
        });
    }

    /**
     * Test team member can delete exercise
     * 
     * 
     */
    public function test_team_member_can_delete_exercise()
    {
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        $exercise = $this->createTestExercise();

        $response = $this->deleteJson("/api/{$this->tenant->slug}/team/exercises/{$exercise->id}");

        $response->assertStatus(204);

        $this->runInTenantContext($this->tenant, function () use ($exercise) {
            $this->assertDatabaseMissing('exercises', [
                'id' => $exercise->id,
            ]);
        });
    }

    /**
     * Test students cannot access team exercise endpoints
     * 
     * 
     */
    public function test_students_cannot_access_team_exercise_endpoints()
    {
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        $exercise = $this->createTestExercise();

        // Test various endpoints
        $response = $this->getJson("/api/{$this->tenant->slug}/team/exercises");
        $response->assertStatus(403);

        $response = $this->postJson("/api/{$this->tenant->slug}/team/exercises", [
            'title' => 'Test Exercise',
            'lesson_id' => $this->lesson->id,
            'type' => 'multiple_choice',
            'content' => ['question' => 'Test question'],
        ]);
        $response->assertStatus(403);

        $response = $this->putJson("/api/{$this->tenant->slug}/team/exercises/{$exercise->id}", [
            'title' => 'Updated Title',
        ]);
        $response->assertStatus(403);

        $response = $this->deleteJson("/api/{$this->tenant->slug}/team/exercises/{$exercise->id}");
        $response->assertStatus(403);
    }

    /**
     * Test unauthenticated access is blocked
     * 
     * 
     */
    public function test_unauthenticated_access_blocked()
    {
        $response = $this->getJson("/api/{$this->tenant->slug}/team/exercises");
        $response->assertStatus(401);

        $response = $this->postJson("/api/{$this->tenant->slug}/team/exercises", [
            'title' => 'Test Exercise',
            'lesson_id' => $this->lesson->id,
            'exercise_type' => 'multiple_choice',
        ]);
        $response->assertStatus(401);
    }

    /**
     * Test nonexistent exercise returns 404
     * 
     * 
     */
    public function test_nonexistent_exercise_returns_404()
    {
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        $response = $this->getJson("/api/{$this->tenant->slug}/team/exercises/99999");
        $response->assertStatus(404);

        $response = $this->putJson("/api/{$this->tenant->slug}/team/exercises/99999", [
            'title' => 'Updated Title',
        ]);
        $response->assertStatus(404);

        $response = $this->deleteJson("/api/{$this->tenant->slug}/team/exercises/99999");
        $response->assertStatus(404);
    }

    /**
     * Test exercise with invalid content format is rejected
     * 
     * 
     */
    public function test_exercise_with_invalid_content_format_is_rejected()
    {
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        $exerciseData = [
            'title' => 'Test Exercise',
            'lesson_id' => $this->lesson->id,
            'type' => 'multiple_choice',
            'content' => 'invalid json content',
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/exercises", $exerciseData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    /**
     * Test exercise types are properly validated
     * 
     * 
     */
    public function test_exercise_types_are_properly_validated()
    {
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        $validTypes = ['multiple_choice', 'fill_blank', 'matching', 'writing', 'speaking', 'conversation', 'listening', 'picture'];

        foreach ($validTypes as $type) {
            $exerciseData = [
                'title' => "Test {$type} Exercise",
                'lesson_id' => $this->lesson->id,
                'type' => $type,
                'content' => ['test' => 'content'],
            ];

            $response = $this->postJson("/api/{$this->tenant->slug}/team/exercises", $exerciseData);
            $response->assertStatus(201);
        }
    }
}
