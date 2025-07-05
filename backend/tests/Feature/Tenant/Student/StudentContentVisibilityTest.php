<?php

namespace Tests\Feature\Tenant\Student;

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
use App\Models\Tenants\ContentReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Student Content Visibility and Review Workflow
 * 
 * Ensures students can only access published, reviewed content
 * and that the review workflow properly protects content quality.
 */
class StudentContentVisibilityTest extends TenantTestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $studentUser;
    protected User $teamMember;
    protected User $reviewer;
    protected Language $language;
    protected LearningPath $learningPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        // Create test tenant
        $this->tenant = $this->createTestTenant();

        // Create test users
        $this->studentUser = $this->createTenantStudent();
        $this->teamMember = $this->createTenantTeam();
        $this->reviewer = $this->createTenantTeam();

        // Create learning structure
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
                'status' => 'published',
                'review_status' => 'approved',
                'created_by' => $this->teamMember->id,
            ]);
        });
    }

    /**
     * Test students can only see published units
     */
    public function test_students_can_only_see_published_units()
    {
        // Create units in different states
        $units = $this->runInTenantContext($this->tenant, function () {
            return [
                'draft' => Unit::create([
                    'title' => 'Draft Unit',
                    'learning_path_id' => $this->learningPath->id,
                    'order' => 1,
                    'status' => 'draft',
                    'review_status' => 'none',
                ]),
                'pending' => Unit::create([
                    'title' => 'Pending Review Unit',
                    'learning_path_id' => $this->learningPath->id,
                    'order' => 2,
                    'status' => 'draft',
                    'review_status' => 'pending',
                ]),
                'rejected' => Unit::create([
                    'title' => 'Rejected Unit',
                    'learning_path_id' => $this->learningPath->id,
                    'order' => 3,
                    'status' => 'draft',
                    'review_status' => 'rejected',
                ]),
                'published' => Unit::create([
                    'title' => 'Published Unit',
                    'learning_path_id' => $this->learningPath->id,
                    'order' => 4,
                    'status' => 'published',
                    'review_status' => 'approved',
                ])
            ];
        });

        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        // Student can only see published unit
        $response = $this->getJson("/api/{$this->tenant->slug}/student/units");
        $response->assertStatus(200);

        $unitTitles = collect($response->json('data'))->pluck('title')->toArray();
        $this->assertContains('Published Unit', $unitTitles);
        $this->assertNotContains('Draft Unit', $unitTitles);
        $this->assertNotContains('Pending Review Unit', $unitTitles);
        $this->assertNotContains('Rejected Unit', $unitTitles);

        // Student cannot directly access draft units
        $response = $this->getJson("/api/{$this->tenant->slug}/student/units/{$units['draft']->id}");
        $response->assertStatus(404);

        $response = $this->getJson("/api/{$this->tenant->slug}/student/units/{$units['pending']->id}");
        $response->assertStatus(404);

        $response = $this->getJson("/api/{$this->tenant->slug}/student/units/{$units['rejected']->id}");
        $response->assertStatus(404);

        // Student can access published unit
        $response = $this->getJson("/api/{$this->tenant->slug}/student/units/{$units['published']->id}");
        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Published Unit');
    }

    /**
     * Test students cannot see draft topics even in published units
     */
    public function test_students_cannot_see_draft_topics_in_published_units()
    {
        $publishedUnit = $this->runInTenantContext($this->tenant, function () {
            return Unit::create([
                'title' => 'Published Unit',
                'learning_path_id' => $this->learningPath->id,
                'order' => 1,
                'status' => 'published',
                'review_status' => 'approved',
            ]);
        });

        // Create topics in different states within the published unit
        $topics = $this->runInTenantContext($this->tenant, function () use ($publishedUnit) {
            return [
                'draft' => Topic::create([
                    'title' => 'Draft Topic',
                    'slug' => 'draft-topic',
                    'unit_id' => $publishedUnit->id,
                    'order' => 1,
                    'status' => 'draft',
                    'review_status' => 'none',
                ]),
                'published' => Topic::create([
                    'title' => 'Published Topic',
                    'slug' => 'published-topic',
                    'unit_id' => $publishedUnit->id,
                    'order' => 2,
                    'status' => 'published',
                    'review_status' => 'approved',
                ])
            ];
        });

        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        // Student can see the unit
        $response = $this->getJson("/api/{$this->tenant->slug}/student/units/{$publishedUnit->id}");
        $response->assertStatus(200);

        // But student can only see published topics
        $response = $this->getJson("/api/{$this->tenant->slug}/student/topics");
        $response->assertStatus(200);

        $topicTitles = collect($response->json('data'))->pluck('title')->toArray();
        $this->assertContains('Published Topic', $topicTitles);
        $this->assertNotContains('Draft Topic', $topicTitles);

        // Direct access to draft topic should fail
        $response = $this->getJson("/api/{$this->tenant->slug}/student/topics/{$topics['draft']->id}");
        $response->assertStatus(404);

        // Access to published topic should work
        $response = $this->getJson("/api/{$this->tenant->slug}/student/topics/{$topics['published']->id}");
        $response->assertStatus(200);
    }

    /**
     * Test students cannot see draft lessons even in published topics
     */
    public function test_students_cannot_see_draft_lessons_in_published_topics()
    {
        $publishedTopic = $this->runInTenantContext($this->tenant, function () {
            $unit = Unit::create([
                'title' => 'Published Unit',
                'learning_path_id' => $this->learningPath->id,
                'order' => 1,
                'status' => 'published',
                'review_status' => 'approved',
            ]);

            return Topic::create([
                'title' => 'Published Topic',
                'slug' => 'published-topic',
                'unit_id' => $unit->id,
                'order' => 1,
                'status' => 'published',
                'review_status' => 'approved',
            ]);
        });

        // Create lessons in different states
        $lessons = $this->runInTenantContext($this->tenant, function () use ($publishedTopic) {
            return [
                'draft' => Lesson::create([
                    'title' => 'Draft Lesson',
                    'description' => 'Draft lesson description',
                    'topic_id' => $publishedTopic->id,
                    'order' => 1,
                    'status' => 'draft',
                    'review_status' => 'none',
                    'created_by' => $this->teamMember->id,
                ]),
                'published' => Lesson::create([
                    'title' => 'Published Lesson',
                    'description' => 'Published lesson description',
                    'topic_id' => $publishedTopic->id,
                    'order' => 2,
                    'status' => 'published',
                    'review_status' => 'approved',
                    'created_by' => $this->teamMember->id,
                ])
            ];
        });

        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        // Student can only see published lessons
        $response = $this->getJson("/api/{$this->tenant->slug}/student/lessons");
        $response->assertStatus(200);

        $lessonTitles = collect($response->json('data'))->pluck('title')->toArray();
        $this->assertContains('Published Lesson', $lessonTitles);
        $this->assertNotContains('Draft Lesson', $lessonTitles);

        // Direct access should respect status
        $response = $this->getJson("/api/{$this->tenant->slug}/student/lessons/{$lessons['draft']->id}");
        $response->assertStatus(404);

        $response = $this->getJson("/api/{$this->tenant->slug}/student/lessons/{$lessons['published']->id}");
        $response->assertStatus(200);
    }

    /**
     * Test students cannot access exercises from draft lessons
     */
    public function test_students_cannot_access_exercises_from_draft_lessons()
    {
        $publishedLesson = $this->runInTenantContext($this->tenant, function () {
            $unit = Unit::create([
                'title' => 'Published Unit',
                'learning_path_id' => $this->learningPath->id,
                'order' => 1,
                'status' => 'published',
                'review_status' => 'approved',
            ]);

            $topic = Topic::create([
                'title' => 'Published Topic',
                'slug' => 'published-topic',
                'unit_id' => $unit->id,
                'order' => 1,
                'status' => 'published',
                'review_status' => 'approved',
            ]);

            return Lesson::create([
                'title' => 'Published Lesson',
                'description' => 'Published lesson description',
                'topic_id' => $topic->id,
                'order' => 1,
                'status' => 'published',
                'review_status' => 'approved',
                'created_by' => $this->teamMember->id,
            ]);
        });

        $draftLesson = $this->runInTenantContext($this->tenant, function () use ($publishedLesson) {
            return Lesson::create([
                'title' => 'Draft Lesson',
                'description' => 'Draft lesson description',
                'topic_id' => $publishedLesson->topic_id,
                'order' => 2,
                'status' => 'draft',
                'review_status' => 'none',
                'created_by' => $this->teamMember->id,
            ]);
        });

        // Create exercises in both lessons
        $exercises = $this->runInTenantContext($this->tenant, function () use ($publishedLesson, $draftLesson) {
            return [
                'published_lesson' => Exercise::create([
                    'title' => 'Exercise in Published Lesson',
                    'slug' => 'exercise-published-lesson',
                    'lesson_id' => $publishedLesson->id,
                    'type' => 'multiple_choice',
                    'status' => 'published',
                    'review_status' => 'approved',
                    'content' => ['question' => 'Test?', 'options' => ['A', 'B']],
                    'answers' => ['correct' => 'A'],
                    'created_by' => $this->teamMember->id,
                ]),
                'draft_lesson' => Exercise::create([
                    'title' => 'Exercise in Draft Lesson',
                    'slug' => 'exercise-draft-lesson',
                    'lesson_id' => $draftLesson->id,
                    'type' => 'multiple_choice',
                    'status' => 'published',  // Exercise is published
                    'review_status' => 'approved',
                    'content' => ['question' => 'Test?', 'options' => ['A', 'B']],
                    'answers' => ['correct' => 'A'],
                    'created_by' => $this->teamMember->id,
                ])
            ];
        });

        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        // Student can access exercise from published lesson
        $response = $this->getJson("/api/{$this->tenant->slug}/student/exercises/{$exercises['published_lesson']->id}");
        $response->assertStatus(200);

        // Student cannot access exercise from draft lesson, even if exercise is published
        $response = $this->getJson("/api/{$this->tenant->slug}/student/exercises/{$exercises['draft_lesson']->id}");
        $response->assertStatus(404);

        // Exercises list should only show exercises from published lessons
        $response = $this->getJson("/api/{$this->tenant->slug}/student/exercises");
        $response->assertStatus(200);

        $exerciseTitles = collect($response->json('data'))->pluck('title')->toArray();
        $this->assertContains('Exercise in Published Lesson', $exerciseTitles);
        $this->assertNotContains('Exercise in Draft Lesson', $exerciseTitles);
    }

    /**
     * Test review workflow transition visibility to students
     */
    public function test_review_workflow_transition_visibility_to_students()
    {
        // Create lesson that starts as draft
        $lesson = $this->runInTenantContext($this->tenant, function () {
            $unit = Unit::create([
                'title' => 'Published Unit',
                'learning_path_id' => $this->learningPath->id,
                'order' => 1,
                'status' => 'published',
                'review_status' => 'approved',
            ]);

            $topic = Topic::create([
                'title' => 'Published Topic',
                'slug' => 'published-topic',
                'unit_id' => $unit->id,
                'order' => 1,
                'status' => 'published',
                'review_status' => 'approved',
            ]);

            return Lesson::create([
                'title' => 'Test Lesson',
                'description' => 'Test lesson description',
                'topic_id' => $topic->id,
                'order' => 1,
                'status' => 'draft',
                'review_status' => 'none',
                'created_by' => $this->teamMember->id,
            ]);
        });

        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        // Student cannot see draft lesson
        $response = $this->getJson("/api/{$this->tenant->slug}/student/lessons/{$lesson->id}");
        $response->assertStatus(404);

        // Submit for review (as team member)
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');
        $this->postJson("/api/{$this->tenant->slug}/team/lessons/{$lesson->id}/submit-for-review");

        // Student still cannot see pending lesson
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');
        $response = $this->getJson("/api/{$this->tenant->slug}/student/lessons/{$lesson->id}");
        $response->assertStatus(404);

        // Approve and publish (as reviewer)
        $this->runInTenantContext($this->tenant, function () use ($lesson) {
            ContentReview::where('content_id', $lesson->id)->update([
                'status' => 'approved',
                'assigned_to' => $this->reviewer->id,
                'completed_at' => now(),
            ]);

            $lesson->update([
                'status' => 'published',
                'review_status' => 'approved',
            ]);
        });

        // Now student can see the lesson
        $response = $this->getJson("/api/{$this->tenant->slug}/student/lessons/{$lesson->id}");
        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Test Lesson');
    }

    /**
     * Test students cannot access team review endpoints
     */
    public function test_students_cannot_access_team_review_endpoints()
    {
        $lesson = $this->runInTenantContext($this->tenant, function () {
            $unit = Unit::create([
                'title' => 'Test Unit',
                'learning_path_id' => $this->learningPath->id,
                'order' => 1,
                'status' => 'published',
                'review_status' => 'approved',
            ]);

            $topic = Topic::create([
                'title' => 'Test Topic',
                'slug' => 'test-topic',
                'unit_id' => $unit->id,
                'order' => 1,
                'status' => 'published',
                'review_status' => 'approved',
            ]);

            return Lesson::create([
                'title' => 'Test Lesson',
                'description' => 'Test lesson description',
                'topic_id' => $topic->id,
                'order' => 1,
                'status' => 'draft',
                'review_status' => 'none',
                'created_by' => $this->teamMember->id,
            ]);
        });

        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        // Students cannot submit content for review
        $response = $this->postJson("/api/{$this->tenant->slug}/team/lessons/{$lesson->id}/submit-for-review");
        $response->assertStatus(403);

        // Students cannot update status
        $response = $this->patchJson("/api/{$this->tenant->slug}/team/lessons/{$lesson->id}/status", [
            'status' => 'published'
        ]);
        $response->assertStatus(403);

        // Students cannot access team content endpoints
        $response = $this->getJson("/api/{$this->tenant->slug}/team/lessons/{$lesson->id}");
        $response->assertStatus(403);
    }

    /**
     * Test content quality protection through review workflow
     */
    public function test_content_quality_protection_through_review_workflow()
    {
        // Simulate a scenario where team creates potentially problematic content
        $lesson = $this->runInTenantContext($this->tenant, function () {
            $unit = Unit::create([
                'title' => 'Published Unit',
                'learning_path_id' => $this->learningPath->id,
                'order' => 1,
                'status' => 'published',
                'review_status' => 'approved',
            ]);

            $topic = Topic::create([
                'title' => 'Published Topic',
                'slug' => 'published-topic',
                'unit_id' => $unit->id,
                'order' => 1,
                'status' => 'published',
                'review_status' => 'approved',
            ]);

            return Lesson::create([
                'title' => 'Potentially Problematic Content',
                'description' => 'This content may have cultural issues',
                'topic_id' => $topic->id,
                'order' => 1,
                'status' => 'draft',
                'review_status' => 'none',
                'created_by' => $this->teamMember->id,
            ]);
        });

        // Submit for review and reject
        $this->runInTenantContext($this->tenant, function () use ($lesson) {
            ContentReview::create([
                'content_type' => 'App\\Models\\Tenants\\Lesson',
                'content_id' => $lesson->id,
                'submitted_by' => $this->teamMember->id,
                'assigned_to' => $this->reviewer->id,
                'review_type' => 'cultural_review',
                'status' => 'rejected',
                'reviewer_feedback' => 'Content does not align with Plains Cree cultural values',
                'submitted_at' => now(),
                'completed_at' => now(),
            ]);

            $lesson->update(['review_status' => 'rejected']);
        });

        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        // Student is protected from seeing rejected content
        $response = $this->getJson("/api/{$this->tenant->slug}/student/lessons/{$lesson->id}");
        $response->assertStatus(404);

        $response = $this->getJson("/api/{$this->tenant->slug}/student/lessons");
        $response->assertStatus(200);

        $lessonTitles = collect($response->json('data'))->pluck('title')->toArray();
        $this->assertNotContains('Potentially Problematic Content', $lessonTitles);

        // Only after revision and approval would content become visible
        $this->runInTenantContext($this->tenant, function () use ($lesson) {
            $lesson->update([
                'title' => 'Culturally Appropriate Content',
                'description' => 'Revised content that respects Plains Cree culture',
                'status' => 'published',
                'review_status' => 'approved',
            ]);
        });

        // Now student can see the improved content
        $response = $this->getJson("/api/{$this->tenant->slug}/student/lessons/{$lesson->id}");
        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Culturally Appropriate Content');
    }
}
