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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

class TeamLessonControllerTest extends TenantTestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $teamMember;
    protected User $studentUser;
    protected Language $language;
    protected LearningPath $learningPath;
    protected Unit $unit;
    protected Topic $topic;

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
            ]);
        });

        $this->studentUser = $this->runInTenantContext($this->tenant, function () {
            return User::create([
                'name' => 'Student User',
                'email' => 'student@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'membership' => 'student',
            ]);
        });

        // Create test language, learning path, unit and topic
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
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    protected function createTestLesson(array $attributes = []): Lesson
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return Lesson::create(array_merge([
                'title' => 'Lesson 1: Basic Greetings',
                'description' => 'Learn basic Spanish greetings',
                'topic_id' => $this->topic->id,
                'order' => 1,
                'status' => 'published',
                'created_by' => $this->teamMember->id,
            ], $attributes));
        });
    }

    /**
     * Test team member can list lessons
     * 
     * 
     */
    public function test_team_member_can_list_lessons()
    {
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        $lesson1 = $this->createTestLesson(['title' => 'Lesson 1']);
        $lesson2 = $this->createTestLesson(['title' => 'Lesson 2', 'order' => 2]);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/lessons");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Lesson 1')
            ->assertJsonPath('data.1.title', 'Lesson 2')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'topic_id',
                        'order_index',
                        'lesson_type',
                        'status',
                        'created_by',
                        'created_at',
                        'updated_at'
                    ]
                ]
            ]);
    }

    /**
     * Test team member can create lesson
     * 
     * 
     */
    public function test_team_member_can_create_lesson()
    {
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        $lessonData = [
            'title' => 'New Lesson',
            'description' => 'A new lesson for testing',
            'topic_id' => $this->topic->id,
            'order_index' => 1,
            'lesson_type' => 'interactive',
            'content' => json_encode(['introduction' => 'Test content']),
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/lessons", $lessonData);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'New Lesson')
            ->assertJsonPath('data.description', 'A new lesson for testing')
            ->assertJsonPath('data.topic_id', $this->topic->id)
            ->assertJsonPath('data.order_index', 1)
            ->assertJsonPath('data.lesson_type', 'interactive')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.created_by', $this->teamMember->id);

        $this->runInTenantContext($this->tenant, function () {
            $this->assertDatabaseHas('lessons', [
                'title' => 'New Lesson',
                'description' => 'A new lesson for testing',
                'topic_id' => $this->topic->id,
            ]);
        });
    }

    /**
     * Test lesson creation validation
     * 
     * 
     */
    public function test_lesson_creation_validation()
    {
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        // Missing required fields
        $response = $this->postJson("/api/{$this->tenant->slug}/team/lessons", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'topic_id']);

        // Invalid topic
        $response = $this->postJson("/api/{$this->tenant->slug}/team/lessons", [
            'title' => 'Test Lesson',
            'topic_id' => 99999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['topic_id']);

        // Invalid lesson type
        $response = $this->postJson("/api/{$this->tenant->slug}/team/lessons", [
            'title' => 'Test Lesson',
            'topic_id' => $this->topic->id,
            'lesson_type' => 'invalid_type',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['lesson_type']);
    }

    /**
     * Test team member can show lesson
     * 
     * 
     */
    public function test_team_member_can_show_lesson()
    {
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        $lesson = $this->createTestLesson();

        $response = $this->getJson("/api/{$this->tenant->slug}/team/lessons/{$lesson->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $lesson->id)
            ->assertJsonPath('data.title', $lesson->title)
            ->assertJsonPath('data.description', $lesson->description)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'description',
                    'topic_id',
                    'order',
                    'status',
                    'review_status',
                    'created_by',
                    'created_at',
                    'updated_at'
                ]
            ]);
    }

    /**
     * Test team member can update lesson
     * 
     * 
     */
    public function test_team_member_can_update_lesson()
    {
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        $lesson = $this->createTestLesson();

        $updateData = [
            'title' => 'Updated Lesson Title',
            'description' => 'Updated description',
            'order_index' => 5,
            'lesson_type' => 'video',
        ];

        $response = $this->putJson("/api/{$this->tenant->slug}/team/lessons/{$lesson->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Updated Lesson Title')
            ->assertJsonPath('data.description', 'Updated description')
            ->assertJsonPath('data.order_index', 5)
            ->assertJsonPath('data.lesson_type', 'video');

        $this->runInTenantContext($this->tenant, function () use ($lesson) {
            $this->assertDatabaseHas('lessons', [
                'id' => $lesson->id,
                'title' => 'Updated Lesson Title',
                'description' => 'Updated description',
                'order_index' => 5,
                'lesson_type' => 'video',
            ]);
        });
    }

    /**
     * Test team member can delete lesson
     * 
     * 
     */
    public function test_team_member_can_delete_lesson()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $lesson = $this->createTestLesson();

        $response = $this->deleteJson("/api/{$this->tenant->slug}/team/lessons/{$lesson->id}");

        $response->assertStatus(204);

        $this->runInTenantContext($this->tenant, function () use ($lesson) {
            $this->assertDatabaseMissing('lessons', [
                'id' => $lesson->id,
            ]);
        });
    }

    /**
     * Test team member can submit lesson for review
     * 
     * 
     */
    public function test_team_member_can_submit_lesson_for_review()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $lesson = $this->createTestLesson();

        $response = $this->postJson("/api/{$this->tenant->slug}/team/lessons/{$lesson->id}/submit-for-review", [
            'notes' => 'Ready for review'
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'pending_review');

        $this->runInTenantContext($this->tenant, function () use ($lesson) {
            $this->assertDatabaseHas('lessons', [
                'id' => $lesson->id,
                'status' => 'pending_review',
            ]);
        });
    }

    /**
     * Test team member can update lesson status
     * 
     * 
     */
    public function test_team_member_can_update_lesson_status()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $lesson = $this->createTestLesson();

        $response = $this->patchJson("/api/{$this->tenant->slug}/team/lessons/{$lesson->id}/status", [
            'status' => 'published'
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'published');

        $this->runInTenantContext($this->tenant, function () use ($lesson) {
            $this->assertDatabaseHas('lessons', [
                'id' => $lesson->id,
                'status' => 'published',
            ]);
        });
    }

    /**
     * Test team member can reorder lesson exercises
     * 
     * 
     */
    public function test_team_member_can_reorder_lesson_exercises()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $lesson = $this->createTestLesson();

        $reorderData = [
            'exercise_ids' => [3, 1, 2] // Assuming some exercise IDs
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/lessons/{$lesson->id}/reorder-exercises", $reorderData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'reordered_exercises'
                ]
            ]);
    }

    /**
     * Test students cannot access team lesson endpoints
     * 
     * 
     */
    public function test_students_cannot_access_team_lesson_endpoints()
    {
        Sanctum::actingAs($this->studentUser, ['tenant']);

        $lesson = $this->createTestLesson();

        // Test various endpoints
        $response = $this->getJson("/api/{$this->tenant->slug}/team/lessons");
        $response->assertStatus(403);

        $response = $this->postJson("/api/{$this->tenant->slug}/team/lessons", [
            'title' => 'Test Lesson',
            'topic_id' => $this->topic->id,
        ]);
        $response->assertStatus(403);

        $response = $this->putJson("/api/{$this->tenant->slug}/team/lessons/{$lesson->id}", [
            'title' => 'Updated Title',
        ]);
        $response->assertStatus(403);

        $response = $this->deleteJson("/api/{$this->tenant->slug}/team/lessons/{$lesson->id}");
        $response->assertStatus(403);
    }

    /**
     * Test unauthenticated access is blocked
     * 
     * 
     */
    public function test_unauthenticated_access_blocked()
    {
        $response = $this->getJson("/api/{$this->tenant->slug}/team/lessons");
        $response->assertStatus(401);

        $response = $this->postJson("/api/{$this->tenant->slug}/team/lessons", [
            'title' => 'Test Lesson',
            'topic_id' => $this->topic->id,
        ]);
        $response->assertStatus(401);
    }

    /**
     * Test nonexistent lesson returns 404
     * 
     * 
     */
    public function test_nonexistent_lesson_returns_404()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/lessons/99999");
        $response->assertStatus(404);

        $response = $this->putJson("/api/{$this->tenant->slug}/team/lessons/99999", [
            'title' => 'Updated Title',
        ]);
        $response->assertStatus(404);

        $response = $this->deleteJson("/api/{$this->tenant->slug}/team/lessons/99999");
        $response->assertStatus(404);
    }

    /**
     * Test lesson with invalid content format is rejected
     * 
     * 
     */
    public function test_lesson_with_invalid_content_format_is_rejected()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $lessonData = [
            'title' => 'Test Lesson',
            'topic_id' => $this->topic->id,
            'content' => 'invalid json content',
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/lessons", $lessonData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    /**
     * Test cross-tenant isolation - lessons from different tenants should not be accessible
     * 
     * 
     */
    public function test_cross_tenant_isolation()
    {
        // Create a second tenant
        $secondTenant = $this->createTestTenant();

        // Create lesson in first tenant
        Sanctum::actingAs($this->teamMember, ['tenant']);
        $lesson = $this->createTestLesson(['title' => 'Tenant 1 Lesson']);

        // Create user in second tenant
        $secondTenantUser = $this->runInTenantContext($secondTenant, function () {
            return User::create([
                'name' => 'Second Tenant User',
                'email' => 'user@tenant2.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);
        });

        // Try to access first tenant's lesson from second tenant
        Sanctum::actingAs($secondTenantUser, ['tenant']);
        $response = $this->getJson("/api/{$secondTenant->slug}/team/lessons/{$lesson->id}");

        // Should return 404 because the lesson doesn't exist in the second tenant's context
        $response->assertStatus(404);

        // Verify first tenant can still access its own lesson
        Sanctum::actingAs($this->teamMember, ['tenant']);
        $response = $this->getJson("/api/{$this->tenant->slug}/team/lessons/{$lesson->id}");
        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Tenant 1 Lesson');
    }
}
