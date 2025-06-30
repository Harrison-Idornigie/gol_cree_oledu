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
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

class TeamExerciseControllerTest extends TenantTestCase
{
    use InteractsWithTenancy;

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
            ]);
        });

        $this->studentUser = $this->runInTenantContext($this->tenant, function () {
            return User::create([
                'name' => 'Student User',
                'email' => 'student@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
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
                'level' => 'beginner',
                'status' => 'active',
                'created_by' => $this->teamMember->id,
            ]);
        });

        $this->unit = $this->runInTenantContext($this->tenant, function () {
            return Unit::create([
                'title' => 'Unit 1: Basics',
                'description' => 'Basic Spanish vocabulary and phrases',
                'learning_path_id' => $this->learningPath->id,
                'order_index' => 1,
                'status' => 'active',
                'created_by' => $this->teamMember->id,
            ]);
        });

        $this->topic = $this->runInTenantContext($this->tenant, function () {
            return Topic::create([
                'title' => 'Greetings',
                'description' => 'Basic Spanish greetings',
                'unit_id' => $this->unit->id,
                'order_index' => 1,
                'status' => 'active',
                'created_by' => $this->teamMember->id,
            ]);
        });

        $this->lesson = $this->runInTenantContext($this->tenant, function () {
            return Lesson::create([
                'title' => 'Lesson 1: Basic Greetings',
                'description' => 'Learn basic Spanish greetings',
                'topic_id' => $this->topic->id,
                'order_index' => 1,
                'lesson_type' => 'interactive',
                'status' => 'active',
                'content' => json_encode(['introduction' => 'Welcome to the lesson']),
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
            return Exercise::create(array_merge([
                'title' => 'Exercise 1: Match Greetings',
                'description' => 'Match Spanish greetings with their English translations',
                'lesson_id' => $this->lesson->id,
                'exercise_type' => 'multiple_choice',
                'order_index' => 1,
                'status' => 'draft',
                'content' => json_encode([
                    'question' => 'What does "Hola" mean?',
                    'options' => ['Hello', 'Goodbye', 'Thank you', 'Please'],
                    'correct_answer' => 0
                ]),
                'created_by' => $this->teamMember->id,
            ], $attributes));
        });
    }

    /**
     * Test team member can list exercises
     * 
     * @test
     */
    public function test_team_member_can_list_exercises()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $exercise1 = $this->createTestExercise(['title' => 'Exercise 1']);
        $exercise2 = $this->createTestExercise(['title' => 'Exercise 2', 'order_index' => 2]);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/exercises");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Exercise 1')
            ->assertJsonPath('data.1.title', 'Exercise 2')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'lesson_id',
                        'exercise_type',
                        'order_index',
                        'status',
                        'created_by',
                        'created_at',
                        'updated_at'
                    ]
                ]
            ]);
    }

    /**
     * Test team member can create exercise
     * 
     * @test
     */
    public function test_team_member_can_create_exercise()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $exerciseData = [
            'title' => 'New Exercise',
            'description' => 'A new exercise for testing',
            'lesson_id' => $this->lesson->id,
            'exercise_type' => 'fill_in_blanks',
            'order_index' => 1,
            'content' => json_encode([
                'text' => 'Hello means ____ in Spanish',
                'answer' => 'Hola'
            ]),
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/exercises", $exerciseData);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'New Exercise')
            ->assertJsonPath('data.description', 'A new exercise for testing')
            ->assertJsonPath('data.lesson_id', $this->lesson->id)
            ->assertJsonPath('data.exercise_type', 'fill_in_blanks')
            ->assertJsonPath('data.order_index', 1)
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
     * @test
     */
    public function test_exercise_creation_validation()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        // Missing required fields
        $response = $this->postJson("/api/{$this->tenant->slug}/team/exercises", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'lesson_id', 'exercise_type']);

        // Invalid lesson
        $response = $this->postJson("/api/{$this->tenant->slug}/team/exercises", [
            'title' => 'Test Exercise',
            'lesson_id' => 99999,
            'exercise_type' => 'multiple_choice',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['lesson_id']);

        // Invalid exercise type
        $response = $this->postJson("/api/{$this->tenant->slug}/team/exercises", [
            'title' => 'Test Exercise',
            'lesson_id' => $this->lesson->id,
            'exercise_type' => 'invalid_type',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['exercise_type']);
    }

    /**
     * Test team member can show exercise
     * 
     * @test
     */
    public function test_team_member_can_show_exercise()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

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
                    'exercise_type',
                    'order_index',
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
     * @test
     */
    public function test_team_member_can_update_exercise()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $exercise = $this->createTestExercise();

        $updateData = [
            'title' => 'Updated Exercise Title',
            'description' => 'Updated description',
            'order_index' => 5,
            'exercise_type' => 'drag_and_drop',
        ];

        $response = $this->putJson("/api/{$this->tenant->slug}/team/exercises/{$exercise->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Updated Exercise Title')
            ->assertJsonPath('data.description', 'Updated description')
            ->assertJsonPath('data.order_index', 5)
            ->assertJsonPath('data.exercise_type', 'drag_and_drop');

        $this->runInTenantContext($this->tenant, function () use ($exercise) {
            $this->assertDatabaseHas('exercises', [
                'id' => $exercise->id,
                'title' => 'Updated Exercise Title',
                'description' => 'Updated description',
                'order_index' => 5,
                'exercise_type' => 'drag_and_drop',
            ]);
        });
    }

    /**
     * Test team member can delete exercise
     * 
     * @test
     */
    public function test_team_member_can_delete_exercise()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

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
     * @test
     */
    public function test_students_cannot_access_team_exercise_endpoints()
    {
        Sanctum::actingAs($this->studentUser, ['tenant']);

        $exercise = $this->createTestExercise();

        // Test various endpoints
        $response = $this->getJson("/api/{$this->tenant->slug}/team/exercises");
        $response->assertStatus(403);

        $response = $this->postJson("/api/{$this->tenant->slug}/team/exercises", [
            'title' => 'Test Exercise',
            'lesson_id' => $this->lesson->id,
            'exercise_type' => 'multiple_choice',
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
     * @test
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
     * @test
     */
    public function test_nonexistent_exercise_returns_404()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

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
     * @test
     */
    public function test_exercise_with_invalid_content_format_is_rejected()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $exerciseData = [
            'title' => 'Test Exercise',
            'lesson_id' => $this->lesson->id,
            'exercise_type' => 'multiple_choice',
            'content' => 'invalid json content',
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/exercises", $exerciseData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    /**
     * Test exercise types are properly validated
     * 
     * @test
     */
    public function test_exercise_types_are_properly_validated()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $validTypes = ['multiple_choice', 'fill_in_blanks', 'drag_and_drop', 'matching', 'listening', 'speaking'];

        foreach ($validTypes as $type) {
            $exerciseData = [
                'title' => "Test {$type} Exercise",
                'lesson_id' => $this->lesson->id,
                'exercise_type' => $type,
                'content' => json_encode(['test' => 'content']),
            ];

            $response = $this->postJson("/api/{$this->tenant->slug}/team/exercises", $exerciseData);
            $response->assertStatus(201, "Exercise type {$type} should be valid");
        }
    }
}
