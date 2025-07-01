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
use App\Models\Tenants\Word;
use App\Models\Tenants\ContentReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Team Content Review Workflow
 * 
 * Tests the complete content creation → review → publication workflow
 * that ensures content quality and cultural authenticity for Plains Cree education.
 */
class TeamContentReviewWorkflowTest extends TenantTestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $teamMember;
    protected User $reviewer;
    protected User $admin;
    protected Language $language;
    protected LearningPath $learningPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        // Create test tenant
        $this->tenant = $this->createTestTenant();

        // Create test users with different roles
        $this->teamMember = $this->createTenantTeam();
        $this->reviewer = $this->createTenantTeam(); // Another team member who can review
        $this->admin = $this->createTenantAdmin();

        // Create basic learning structure
        $this->createLearningStructure();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    protected function createLearningStructure(): void
    {
        $this->runInTenantContext($this->tenant, function () {
            $this->language = Language::create([
                'name' => 'Plains Cree',
                'code' => 'crk',
                'native_name' => 'nêhiyawêwin',
                'direction' => 'ltr',
                'status' => 'active',
            ]);

            $this->learningPath = LearningPath::create([
                'title' => 'Plains Cree for Beginners',
                'description' => 'Learn Plains Cree from scratch',
                'language_id' => $this->language->id,
                'target_level' => 'beginner',
                'status' => 'draft',  // Start as draft
                'review_status' => 'draft',
                'created_by' => $this->teamMember->id,
            ]);
        });
    }

    /**
     * Test complete unit review workflow
     */
    public function test_unit_review_workflow_from_creation_to_publication()
    {
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        // Step 1: Team member creates unit in draft
        $unitData = [
            'title' => 'Unit 1: Basic Greetings',
            'description' => 'Learn basic Plains Cree greetings and introductions',
            'learning_path_id' => $this->learningPath->id,
            'order' => 1,
            'status' => 'draft',
            'review_status' => 'draft',
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/units", $unitData);
        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.review_status', 'draft');

        $unitId = $response->json('data.id');

        // Step 2: Team member submits unit for review
        $response = $this->postJson("/api/{$this->tenant->slug}/team/units/{$unitId}/submit-for-review", [
            'notes' => 'Ready for cultural and linguistic review'
        ]);
        $response->assertStatus(200)
            ->assertJsonPath('data.review_status', 'pending');

        // Verify ContentReview was created
        $this->runInTenantContext($this->tenant, function () use ($unitId) {
            $this->assertDatabaseHas('content_reviews', [
                'content_type' => 'App\\Models\\Tenants\\Unit',
                'content_id' => $unitId,
                'status' => 'pending',
                'submitted_by' => $this->teamMember->id,
            ]);
        });

        // Step 3: Cannot be published while in review
        $response = $this->patchJson("/api/{$this->tenant->slug}/team/units/{$unitId}/status", [
            'status' => 'published'
        ]);
        $response->assertStatus(422);

        // Step 4: Reviewer approves the unit
        Sanctum::actingAs($this->reviewer, ['*'], 'tenant');

        $this->runInTenantContext($this->tenant, function () use ($unitId) {
            $review = ContentReview::where('content_id', $unitId)->first();
            $review->update([
                'status' => 'approved',
                'assigned_to' => $this->reviewer->id,
                'reviewer_feedback' => 'Linguistically accurate and culturally appropriate',
                'completed_at' => now(),
            ]);
        });

        // Step 5: Now can be published
        $response = $this->patchJson("/api/{$this->tenant->slug}/team/units/{$unitId}/status", [
            'status' => 'published'
        ]);
        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.review_status', 'approved');
    }

    /**
     * Test topic review rejection and revision workflow
     */
    public function test_topic_review_rejection_and_revision_workflow()
    {
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        // Create unit first
        $unit = $this->runInTenantContext($this->tenant, function () {
            return Unit::create([
                'title' => 'Unit 1',
                'learning_path_id' => $this->learningPath->id,
                'order' => 1,
                'status' => 'published',
                'review_status' => 'approved',
            ]);
        });

        // Step 1: Create topic with potentially problematic content
        $topicData = [
            'title' => 'Inappropriate Cultural References',
            'description' => 'This content may not be culturally appropriate',
            'unit_id' => $unit->id,
            'order' => 1,
            'status' => 'draft',
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/topics", $topicData);
        $response->assertStatus(201);
        $topicId = $response->json('data.id');

        // Step 2: Submit for review
        $response = $this->postJson("/api/{$this->tenant->slug}/team/topics/{$topicId}/submit-for-review", [
            'notes' => 'Please review cultural appropriateness'
        ]);
        $response->assertStatus(200);

        // Step 3: Reviewer rejects with feedback
        Sanctum::actingAs($this->reviewer, ['*'], 'tenant');

        $this->runInTenantContext($this->tenant, function () use ($topicId) {
            $review = ContentReview::where('content_id', $topicId)->first();
            $review->update([
                'status' => 'rejected',
                'assigned_to' => $this->reviewer->id,
                'reviewer_feedback' => 'Content does not align with Plains Cree cultural values. Please revise to focus on traditional greetings.',
                'completed_at' => now(),
            ]);
        });

        // Verify topic status updated
        $response = $this->getJson("/api/{$this->tenant->slug}/team/topics/{$topicId}");
        $response->assertStatus(200)
            ->assertJsonPath('data.review_status', 'rejected');

        // Step 4: Team member revises content
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        $response = $this->putJson("/api/{$this->tenant->slug}/team/topics/{$topicId}", [
            'title' => 'Traditional Plains Cree Greetings',
            'description' => 'Learn traditional and respectful Plains Cree greetings',
        ]);
        $response->assertStatus(200);

        // Step 5: Resubmit for review
        $response = $this->postJson("/api/{$this->tenant->slug}/team/topics/{$topicId}/submit-for-review", [
            'notes' => 'Revised content to focus on traditional greetings'
        ]);
        $response->assertStatus(200)
            ->assertJsonPath('data.review_status', 'pending');

        // Step 6: Reviewer approves revised content
        Sanctum::actingAs($this->reviewer, ['*'], 'tenant');

        $this->runInTenantContext($this->tenant, function () use ($topicId) {
            $review = ContentReview::where('content_id', $topicId)
                ->where('status', 'pending')
                ->first();
            $review->update([
                'status' => 'approved',
                'reviewer_feedback' => 'Much better! Content is now culturally appropriate.',
                'completed_at' => now(),
            ]);
        });

        // Step 7: Content can now be published
        $response = $this->patchJson("/api/{$this->tenant->slug}/team/topics/{$topicId}/status", [
            'status' => 'published'
        ]);
        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'published');
    }

    /**
     * Test lesson with exercises review workflow
     */
    public function test_lesson_with_exercises_review_workflow()
    {
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        // Create approved topic
        $topic = $this->runInTenantContext($this->tenant, function () {
            $unit = Unit::create([
                'title' => 'Unit 1',
                'learning_path_id' => $this->learningPath->id,
                'order' => 1,
                'status' => 'published',
                'review_status' => 'approved',
            ]);

            return Topic::create([
                'title' => 'Greetings',
                'slug' => 'greetings',
                'unit_id' => $unit->id,
                'order' => 1,
                'status' => 'published',
                'review_status' => 'approved',
            ]);
        });

        // Step 1: Create lesson
        $lessonData = [
            'title' => 'Basic Greetings in Plains Cree',
            'description' => 'Learn to greet people properly in Plains Cree',
            'topic_id' => $topic->id,
            'order' => 1,
            'status' => 'draft',
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/lessons", $lessonData);
        $response->assertStatus(201);
        $lessonId = $response->json('data.id');

        // Step 2: Add exercises to lesson
        $exerciseData = [
            'title' => 'Multiple Choice: Greetings',
            'description' => 'Choose the correct greeting',
            'lesson_id' => $lessonId,
            'type' => 'multiple_choice',
            'content' => [
                'question' => 'What does "tansi" mean?',
                'options' => ['Hello', 'Goodbye', 'Thank you', 'Please']
            ],
            'answers' => [
                'correct' => 'Hello'
            ]
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/exercises", $exerciseData);
        $response->assertStatus(201);

        // Step 3: Submit lesson for review
        $response = $this->postJson("/api/{$this->tenant->slug}/team/lessons/{$lessonId}/submit-for-review", [
            'notes' => 'Lesson with exercise ready for review'
        ]);
        $response->assertStatus(200);

        // Step 4: Reviewer checks both lesson and exercises
        Sanctum::actingAs($this->reviewer, ['*'], 'tenant');

        $this->runInTenantContext($this->tenant, function () use ($lessonId) {
            $review = ContentReview::where('content_id', $lessonId)->first();
            $review->update([
                'status' => 'approved',
                'assigned_to' => $this->reviewer->id,
                'reviewer_feedback' => 'Lesson structure and exercise content are culturally appropriate and linguistically accurate.',
                'completed_at' => now(),
            ]);
        });

        // Step 5: Publish lesson
        $response = $this->patchJson("/api/{$this->tenant->slug}/team/lessons/{$lessonId}/status", [
            'status' => 'published'
        ]);
        $response->assertStatus(200);
    }

    /**
     * Test bulk content review workflow
     */
    public function test_bulk_content_review_workflow()
    {
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        // Create multiple pieces of content
        $contentIds = [];

        $unit = $this->runInTenantContext($this->tenant, function () {
            return Unit::create([
                'title' => 'Unit 1',
                'learning_path_id' => $this->learningPath->id,
                'order' => 1,
                'status' => 'draft',
            ]);
        });
        $contentIds['unit'] = $unit->id;

        // Create 3 topics
        for ($i = 1; $i <= 3; $i++) {
            $topicData = [
                'title' => "Topic {$i}",
                'description' => "Topic {$i} description",
                'unit_id' => $unit->id,
                'order' => $i,
                'status' => 'draft',
            ];

            $response = $this->postJson("/api/{$this->tenant->slug}/team/topics", $topicData);
            $response->assertStatus(201);
            $contentIds["topic_{$i}"] = $response->json('data.id');
        }

        // Submit all for review
        $response = $this->postJson("/api/{$this->tenant->slug}/team/units/{$unit->id}/submit-for-review");
        $response->assertStatus(200);

        foreach ($contentIds as $key => $id) {
            if (str_starts_with($key, 'topic_')) {
                $response = $this->postJson("/api/{$this->tenant->slug}/team/topics/{$id}/submit-for-review");
                $response->assertStatus(200);
            }
        }

        // Verify all content reviews created
        $this->runInTenantContext($this->tenant, function () {
            $this->assertEquals(4, ContentReview::where('status', 'pending')->count());
        });

        // Reviewer approves all
        Sanctum::actingAs($this->reviewer, ['*'], 'tenant');

        $this->runInTenantContext($this->tenant, function () {
            ContentReview::where('status', 'pending')->update([
                'status' => 'approved',
                'assigned_to' => $this->reviewer->id,
                'reviewer_feedback' => 'All content approved for cultural accuracy',
                'completed_at' => now(),
            ]);
        });

        // Verify all can be published
        $response = $this->patchJson("/api/{$this->tenant->slug}/team/units/{$unit->id}/status", [
            'status' => 'published'
        ]);
        $response->assertStatus(200);
    }

    /**
     * Test review workflow prevents unauthorized publishing
     */
    public function test_review_workflow_prevents_unauthorized_publishing()
    {
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        // Create topic
        $topicData = [
            'title' => 'Test Topic',
            'description' => 'Test description',
            'unit_id' => $this->runInTenantContext($this->tenant, function () {
                return Unit::create([
                    'title' => 'Unit 1',
                    'learning_path_id' => $this->learningPath->id,
                    'order' => 1,
                    'status' => 'published',
                    'review_status' => 'approved',
                ])->id;
            }),
            'order' => 1,
            'status' => 'draft',
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/topics", $topicData);
        $response->assertStatus(201);
        $topicId = $response->json('data.id');

        // Try to publish without review - should fail
        $response = $this->patchJson("/api/{$this->tenant->slug}/team/topics/{$topicId}/status", [
            'status' => 'published'
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        // Try to publish with pending review - should fail  
        $this->postJson("/api/{$this->tenant->slug}/team/topics/{$topicId}/submit-for-review");

        $response = $this->patchJson("/api/{$this->tenant->slug}/team/topics/{$topicId}/status", [
            'status' => 'published'
        ]);
        $response->assertStatus(422);

        // Only after approval can it be published
        $this->runInTenantContext($this->tenant, function () use ($topicId) {
            $review = ContentReview::where('content_id', $topicId)->first();
            $review->update(['status' => 'approved', 'completed_at' => now()]);
        });

        $response = $this->patchJson("/api/{$this->tenant->slug}/team/topics/{$topicId}/status", [
            'status' => 'published'
        ]);
        $response->assertStatus(200);
    }
}
