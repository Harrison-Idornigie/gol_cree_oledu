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
use App\Models\Tenants\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class TeamReviewWorkflowControllerTest extends TestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $teamUser;
    protected User $adminUser;
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
        $this->initializeTenantContext($this->tenant);
        
        // Create users
        $this->teamUser = $this->createTenantTeam();
        $this->adminUser = $this->createTenantAdmin();
        $this->studentUser = $this->createTenantStudent();
        
        // Create test content
        $this->language = $this->createLanguage();
        $this->createTestContent();
    }

    protected function createTestContent(): void
    {
        $this->runInTenantContext($this->tenant, function () {
            $this->learningPath = LearningPath::factory()->create([
                'language_id' => $this->language->id,
                'status' => 'draft'
            ]);

            $this->unit = Unit::factory()->create([
                'learning_path_id' => $this->learningPath->id,
                'status' => 'draft'
            ]);

            $this->topic = Topic::factory()->create([
                'unit_id' => $this->unit->id,
                'status' => 'draft'
            ]);

            $this->lesson = Lesson::factory()->create([
                'topic_id' => $this->topic->id,
                'status' => 'draft'
            ]);
        });
    }

    /** @test */
    public function team_member_can_submit_learning_path_for_review()
    {
        Sanctum::actingAs($this->teamUser, ['*'], 'tenant');
        
        $response = $this->postJson("/api/{$this->tenant->slug}/team/learning-paths/{$this->learningPath->id}/submit-for-review", [
            'review_notes' => 'Ready for review - all content completed'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'status',
                    'review_status'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Learning path submitted for review successfully.',
                'data' => [
                    'status' => 'review_pending'
                ]
            ]);
    }

    /** @test */
    public function admin_can_update_learning_path_status()
    {
        // First submit for review
        $this->runInTenantContext($this->tenant, function () {
            $this->learningPath->update(['status' => 'review_pending']);
        });
        
        Sanctum::actingAs($this->adminUser, ['*'], 'tenant');
        
        $response = $this->patchJson("/api/{$this->tenant->slug}/team/learning-paths/{$this->learningPath->id}/status", [
            'status' => 'published',
            'review_notes' => 'Approved - excellent content'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'status'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Learning path status updated successfully.',
                'data' => [
                    'status' => 'published'
                ]
            ]);
    }

    /** @test */
    public function team_member_can_reorder_units_in_learning_path()
    {
        // Create additional units
        $unit2 = $this->runInTenantContext($this->tenant, function () {
            return Unit::factory()->create([
                'learning_path_id' => $this->learningPath->id,
                'order' => 2
            ]);
        });

        $unit3 = $this->runInTenantContext($this->tenant, function () {
            return Unit::factory()->create([
                'learning_path_id' => $this->learningPath->id,
                'order' => 3
            ]);
        });
        
        Sanctum::actingAs($this->teamUser, ['*'], 'tenant');
        
        $response = $this->postJson("/api/{$this->tenant->slug}/team/learning-paths/{$this->learningPath->id}/reorder-units", [
            'unit_orders' => [
                ['id' => $unit2->id, 'order' => 1],
                ['id' => $this->unit->id, 'order' => 2],
                ['id' => $unit3->id, 'order' => 3]
            ]
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Units reordered successfully.'
            ]);
    }

    /** @test */
    public function team_member_can_submit_unit_for_review()
    {
        Sanctum::actingAs($this->teamUser, ['*'], 'tenant');
        
        $response = $this->postJson("/api/{$this->tenant->slug}/team/units/{$this->unit->id}/submit-for-review", [
            'review_notes' => 'Unit content is complete and ready for review'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'status'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Unit submitted for review successfully.',
                'data' => [
                    'status' => 'review_pending'
                ]
            ]);
    }

    /** @test */
    public function admin_can_update_unit_status()
    {
        $this->runInTenantContext($this->tenant, function () {
            $this->unit->update(['status' => 'review_pending']);
        });
        
        Sanctum::actingAs($this->adminUser, ['*'], 'tenant');
        
        $response = $this->patchJson("/api/{$this->tenant->slug}/team/units/{$this->unit->id}/status", [
            'status' => 'published',
            'review_notes' => 'Unit approved'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Unit status updated successfully.',
                'data' => [
                    'status' => 'published'
                ]
            ]);
    }

    /** @test */
    public function team_member_can_reorder_topics_in_unit()
    {
        // Create additional topics
        $topic2 = $this->runInTenantContext($this->tenant, function () {
            return Topic::factory()->create([
                'unit_id' => $this->unit->id,
                'order' => 2
            ]);
        });
        
        Sanctum::actingAs($this->teamUser, ['*'], 'tenant');
        
        $response = $this->postJson("/api/{$this->tenant->slug}/team/units/{$this->unit->id}/reorder-topics", [
            'topic_orders' => [
                ['id' => $topic2->id, 'order' => 1],
                ['id' => $this->topic->id, 'order' => 2]
            ]
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Topics reordered successfully.'
            ]);
    }

    /** @test */
    public function team_member_can_submit_topic_for_review()
    {
        Sanctum::actingAs($this->teamUser, ['*'], 'tenant');
        
        $response = $this->postJson("/api/{$this->tenant->slug}/team/topics/{$this->topic->id}/submit-for-review", [
            'review_notes' => 'Topic is ready for review'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Topic submitted for review successfully.',
                'data' => [
                    'status' => 'review_pending'
                ]
            ]);
    }

    /** @test */
    public function team_member_can_submit_lesson_for_review()
    {
        Sanctum::actingAs($this->teamUser, ['*'], 'tenant');
        
        $response = $this->postJson("/api/{$this->tenant->slug}/team/lessons/{$this->lesson->id}/submit-for-review", [
            'review_notes' => 'Lesson content complete'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Lesson submitted for review successfully.',
                'data' => [
                    'status' => 'review_pending'
                ]
            ]);
    }

    /** @test */
    public function students_cannot_access_review_workflow_endpoints()
    {
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');
        
        $endpoints = [
            ['POST', "/api/{$this->tenant->slug}/team/learning-paths/{$this->learningPath->id}/submit-for-review"],
            ['PATCH', "/api/{$this->tenant->slug}/team/learning-paths/{$this->learningPath->id}/status"],
            ['POST', "/api/{$this->tenant->slug}/team/units/{$this->unit->id}/submit-for-review"],
            ['PATCH', "/api/{$this->tenant->slug}/team/units/{$this->unit->id}/status"],
        ];

        foreach ($endpoints as [$method, $url]) {
            $response = $this->json($method, $url, []);
            $response->assertStatus(403);
        }
    }

    /** @test */
    public function review_workflow_validates_status_transitions()
    {
        Sanctum::actingAs($this->adminUser, ['*'], 'tenant');
        
        // Try to publish without review
        $response = $this->patchJson("/api/{$this->tenant->slug}/team/learning-paths/{$this->learningPath->id}/status", [
            'status' => 'published'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    /** @test */
    public function review_workflow_requires_authentication()
    {
        $response = $this->postJson("/api/{$this->tenant->slug}/team/learning-paths/{$this->learningPath->id}/submit-for-review");
        $response->assertStatus(401);
    }

    /** @test */
    public function review_workflow_is_tenant_isolated()
    {
        $otherTenant = $this->createTestTenant(['slug' => 'other-tenant']);
        
        Sanctum::actingAs($this->teamUser, ['*'], 'tenant');
        
        $response = $this->postJson("/api/{$otherTenant->slug}/team/learning-paths/{$this->learningPath->id}/submit-for-review");
        $response->assertStatus(404);
    }
}
