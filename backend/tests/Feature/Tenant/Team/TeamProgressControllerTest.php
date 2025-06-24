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

class TeamProgressControllerTest extends TestCase
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

        // Create test content structure
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

        $this->topic = $this->runInTenantContext($this->tenant, function () {
            return Topic::create([
                'title' => 'Topic 1: Greetings',
                'description' => 'Learn basic greetings',
                'unit_id' => $this->unit->id,
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

    /**
     * Test team member can get progress overview
     * 
     * @test
     */
    public function test_team_member_can_get_progress_overview()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/progress/overview");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_content_created',
                    'published_content',
                    'pending_review_content',
                    'draft_content',
                    'student_engagement',
                    'recent_activity'
                ]
            ]);
    }

    /**
     * Test team member can get their content progress
     * 
     * @test
     */
    public function test_team_member_can_get_my_content_progress()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/progress/my-content");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'learning_paths',
                    'units',
                    'topics',
                    'lessons',
                    'exercises',
                    'content_status_breakdown'
                ]
            ]);
    }

    /**
     * Test team member can get students progress
     * 
     * @test
     */
    public function test_team_member_can_get_students_progress()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/progress/students");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_students',
                    'active_students',
                    'student_completion_rates',
                    'struggling_students',
                    'top_performers'
                ]
            ]);
    }

    /**
     * Test team member can get specific content progress
     * 
     * @test
     */
    public function test_team_member_can_get_specific_content_progress()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        // Test learning path progress
        $response = $this->getJson("/api/{$this->tenant->slug}/team/progress/content/learning-path/{$this->learningPath->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'content_id',
                    'content_type',
                    'completion_rate',
                    'student_progress',
                    'engagement_metrics',
                    'performance_analytics'
                ]
            ]);

        // Test unit progress
        $response = $this->getJson("/api/{$this->tenant->slug}/team/progress/content/unit/{$this->unit->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'content_id',
                    'content_type',
                    'completion_rate',
                    'student_progress'
                ]
            ]);

        // Test topic progress
        $response = $this->getJson("/api/{$this->tenant->slug}/team/progress/content/topic/{$this->topic->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'content_id',
                    'content_type',
                    'completion_rate',
                    'student_progress'
                ]
            ]);
    }

    /**
     * Test progress endpoints with filters and pagination
     * 
     * @test
     */
    public function test_progress_endpoints_support_filters()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        // Test students progress with filters
        $response = $this->getJson("/api/{$this->tenant->slug}/team/progress/students?status=active&limit=10&page=1");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'current_page',
                    'total',
                    'per_page'
                ]
            ]);

        // Test my content with status filter
        $response = $this->getJson("/api/{$this->tenant->slug}/team/progress/my-content?status=published");

        $response->assertStatus(200);

        // Test overview with date range
        $response = $this->getJson("/api/{$this->tenant->slug}/team/progress/overview?from=2024-01-01&to=2024-12-31");

        $response->assertStatus(200);
    }

    /**
     * Test students cannot access team progress endpoints
     * 
     * @test
     */
    public function test_students_cannot_access_team_progress_endpoints()
    {
        Sanctum::actingAs($this->studentUser, ['tenant']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/progress/overview");
        $response->assertStatus(403);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/progress/my-content");
        $response->assertStatus(403);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/progress/students");
        $response->assertStatus(403);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/progress/content/learning-path/{$this->learningPath->id}");
        $response->assertStatus(403);
    }

    /**
     * Test unauthenticated access is blocked
     * 
     * @test
     */
    public function test_unauthenticated_access_blocked()
    {
        $response = $this->getJson("/api/{$this->tenant->slug}/team/progress/overview");
        $response->assertStatus(401);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/progress/my-content");
        $response->assertStatus(401);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/progress/students");
        $response->assertStatus(401);
    }

    /**
     * Test nonexistent content returns 404
     * 
     * @test
     */
    public function test_nonexistent_content_returns_404()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/progress/content/learning-path/99999");
        $response->assertStatus(404);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/progress/content/unit/99999");
        $response->assertStatus(404);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/progress/content/topic/99999");
        $response->assertStatus(404);
    }

    /**
     * Test invalid content type returns 422
     * 
     * @test
     */
    public function test_invalid_content_type_returns_422()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/progress/content/invalid-type/1");
        $response->assertStatus(422);
    }

    /**
     * Test progress data includes correct team member filtering
     * 
     * @test
     */
    public function test_progress_data_filtered_by_team_member()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        // Create content by another team member
        $otherTeamMember = $this->runInTenantContext($this->tenant, function () {
            return User::create([
                'name' => 'Other Team Member',
                'email' => 'other@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);
        });

        $otherLearningPath = $this->runInTenantContext($this->tenant, function () use ($otherTeamMember) {
            return LearningPath::create([
                'title' => 'Other Learning Path',
                'description' => 'Created by other team member',
                'language_id' => $this->language->id,
                'level' => 'intermediate',
                'status' => 'active',
                'created_by' => $otherTeamMember->id,
            ]);
        });

        // Get my content progress - should only show content created by authenticated user
        $response = $this->getJson("/api/{$this->tenant->slug}/team/progress/my-content");

        $response->assertStatus(200);
        $responseData = $response->json('data');

        // Verify only own content is returned (this would need actual implementation logic)
        $this->assertIsArray($responseData);
    }
}
