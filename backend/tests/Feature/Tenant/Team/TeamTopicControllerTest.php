<?php

namespace Tests\Feature\Tenant\Team;

use Tests\TestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\Language;
use App\Models\Tenants\LearningPath;
use App\Models\Tenants\Unit;
use App\Models\Tenants\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

class TeamTopicControllerTest extends TestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $teamMember;
    protected User $studentUser;
    protected Language $language;
    protected LearningPath $learningPath;
    protected Unit $unit;

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

        // Create test data structure
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
                'description' => 'Basic Spanish vocabulary',
                'learning_path_id' => $this->learningPath->id,
                'order_index' => 1,
                'status' => 'active',
                'created_by' => $this->teamMember->id,
            ]);
        });
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    protected function createTestTopic(array $attributes = []): Topic
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return Topic::create(array_merge([
                'title' => 'Topic 1: Greetings',
                'description' => 'Learn basic greetings',
                'unit_id' => $this->unit->id,
                'order_index' => 1,
                'status' => 'draft',
                'created_by' => $this->teamMember->id,
            ], $attributes));
        });
    }

    /**
     * Test team member can list topics
     * 
     * @test
     */
    public function test_team_member_can_list_topics()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $topic1 = $this->createTestTopic(['title' => 'Topic 1']);
        $topic2 = $this->createTestTopic(['title' => 'Topic 2', 'order_index' => 2]);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/topics");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'unit_id',
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
     * Test team member can create topic
     * 
     * @test
     */
    public function test_team_member_can_create_topic()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $topicData = [
            'title' => 'New Topic',
            'description' => 'A new topic for testing',
            'unit_id' => $this->unit->id,
            'order_index' => 1,
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/topics", $topicData);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'New Topic')
            ->assertJsonPath('data.description', 'A new topic for testing')
            ->assertJsonPath('data.unit_id', $this->unit->id)
            ->assertJsonPath('data.order_index', 1)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.created_by', $this->teamMember->id);

        $this->runInTenantContext($this->tenant, function () {
            $this->assertDatabaseHas('topics', [
                'title' => 'New Topic',
                'description' => 'A new topic for testing',
                'unit_id' => $this->unit->id,
            ]);
        });
    }

    /**
     * Test topic creation validation
     * 
     * @test
     */
    public function test_topic_creation_validation()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        // Missing required fields
        $response = $this->postJson("/api/{$this->tenant->slug}/team/topics", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'unit_id']);

        // Invalid unit
        $response = $this->postJson("/api/{$this->tenant->slug}/team/topics", [
            'title' => 'Test Topic',
            'unit_id' => 99999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['unit_id']);
    }

    /**
     * Test team member can show topic
     * 
     * @test
     */
    public function test_team_member_can_show_topic()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $topic = $this->createTestTopic();

        $response = $this->getJson("/api/{$this->tenant->slug}/team/topics/{$topic->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $topic->id)
            ->assertJsonPath('data.title', $topic->title)
            ->assertJsonPath('data.description', $topic->description)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'description',
                    'unit_id',
                    'order_index',
                    'status',
                    'created_by',
                    'lessons_count',
                    'created_at',
                    'updated_at'
                ]
            ]);
    }

    /**
     * Test team member can update topic
     * 
     * @test
     */
    public function test_team_member_can_update_topic()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $topic = $this->createTestTopic();

        $updateData = [
            'title' => 'Updated Topic Title',
            'description' => 'Updated description',
            'order_index' => 5,
        ];

        $response = $this->putJson("/api/{$this->tenant->slug}/team/topics/{$topic->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Updated Topic Title')
            ->assertJsonPath('data.description', 'Updated description')
            ->assertJsonPath('data.order_index', 5);

        $this->runInTenantContext($this->tenant, function () use ($topic) {
            $this->assertDatabaseHas('topics', [
                'id' => $topic->id,
                'title' => 'Updated Topic Title',
                'description' => 'Updated description',
                'order_index' => 5,
            ]);
        });
    }

    /**
     * Test team member can delete topic
     * 
     * @test
     */
    public function test_team_member_can_delete_topic()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $topic = $this->createTestTopic();

        $response = $this->deleteJson("/api/{$this->tenant->slug}/team/topics/{$topic->id}");

        $response->assertStatus(204);

        $this->runInTenantContext($this->tenant, function () use ($topic) {
            $this->assertDatabaseMissing('topics', [
                'id' => $topic->id,
            ]);
        });
    }

    /**
     * Test team member can submit topic for review
     * 
     * @test
     */
    public function test_team_member_can_submit_topic_for_review()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $topic = $this->createTestTopic();

        $response = $this->postJson("/api/{$this->tenant->slug}/team/topics/{$topic->id}/submit-for-review", [
            'notes' => 'Ready for review'
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'pending_review');

        $this->runInTenantContext($this->tenant, function () use ($topic) {
            $this->assertDatabaseHas('topics', [
                'id' => $topic->id,
                'status' => 'pending_review',
            ]);
        });
    }

    /**
     * Test team member can update topic status
     * 
     * @test
     */
    public function test_team_member_can_update_topic_status()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $topic = $this->createTestTopic();

        $response = $this->patchJson("/api/{$this->tenant->slug}/team/topics/{$topic->id}/status", [
            'status' => 'published'
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'published');

        $this->runInTenantContext($this->tenant, function () use ($topic) {
            $this->assertDatabaseHas('topics', [
                'id' => $topic->id,
                'status' => 'published',
            ]);
        });
    }

    /**
     * Test team member can reorder topic lessons
     * 
     * @test
     */
    public function test_team_member_can_reorder_topic_lessons()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $topic = $this->createTestTopic();

        $reorderData = [
            'lesson_ids' => [3, 1, 2] // Assuming some lesson IDs
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/topics/{$topic->id}/reorder-lessons", $reorderData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'reordered_lessons'
                ]
            ]);
    }

    /**
     * Test students cannot access team topic endpoints
     * 
     * @test
     */
    public function test_students_cannot_access_team_topic_endpoints()
    {
        Sanctum::actingAs($this->studentUser, ['tenant']);

        $topic = $this->createTestTopic();

        // Test various endpoints
        $response = $this->getJson("/api/{$this->tenant->slug}/team/topics");
        $response->assertStatus(403);

        $response = $this->postJson("/api/{$this->tenant->slug}/team/topics", [
            'title' => 'Test Topic',
            'unit_id' => $this->unit->id,
        ]);
        $response->assertStatus(403);

        $response = $this->putJson("/api/{$this->tenant->slug}/team/topics/{$topic->id}", [
            'title' => 'Updated Title',
        ]);
        $response->assertStatus(403);

        $response = $this->deleteJson("/api/{$this->tenant->slug}/team/topics/{$topic->id}");
        $response->assertStatus(403);
    }

    /**
     * Test unauthenticated access is blocked
     * 
     * @test
     */
    public function test_unauthenticated_access_blocked()
    {
        $response = $this->getJson("/api/{$this->tenant->slug}/team/topics");
        $response->assertStatus(401);

        $response = $this->postJson("/api/{$this->tenant->slug}/team/topics", [
            'title' => 'Test Topic',
            'unit_id' => $this->unit->id,
        ]);
        $response->assertStatus(401);
    }

    /**
     * Test nonexistent topic returns 404
     * 
     * @test
     */
    public function test_nonexistent_topic_returns_404()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/topics/99999");
        $response->assertStatus(404);

        $response = $this->putJson("/api/{$this->tenant->slug}/team/topics/99999", [
            'title' => 'Updated Title',
        ]);
        $response->assertStatus(404);

        $response = $this->deleteJson("/api/{$this->tenant->slug}/team/topics/99999");
        $response->assertStatus(404);
    }
}
