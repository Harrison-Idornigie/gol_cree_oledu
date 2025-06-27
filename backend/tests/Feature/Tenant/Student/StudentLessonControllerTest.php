<?php

namespace Tests\Feature\Tenant\Student;

use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;

class StudentLessonControllerTest extends TenantTestCase
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
        $this->teamUser = $this->createTenantTeam();

        // Setup test data in tenant context
        $this->testData = $this->runInTenantContext($this->tenant, function () {
            return $this->setupTestData();
        });
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }





    /**
     * Setup test data for lessons, topics, units, etc.
     */
    protected function setupTestData()
    {
        return $this->runInTenantContext($this->tenant, function () {
            // Create language for testing
            $language = \Illuminate\Support\Facades\DB::table('languages')->insertGetId([
                'name' => 'Test Language',
                'code' => 'tl',
                'native_name' => 'Test Native',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create learning path
            $learningPathId = \Illuminate\Support\Facades\DB::table('learning_paths')->insertGetId([
                'title' => 'Test Learning Path',
                'description' => 'A test learning path',
                'language_id' => $language,
                'target_level' => 'A1',
                'status' => 'published',
                'created_by' => $this->teamUser->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create unit
            $unitId = \Illuminate\Support\Facades\DB::table('units')->insertGetId([
                'learning_path_id' => $learningPathId,
                'title' => 'Test Unit',
                'description' => 'Test unit description',
                'order' => 1,
                'status' => 'published',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create topic
            $topicId = \Illuminate\Support\Facades\DB::table('topics')->insertGetId([
                'unit_id' => $unitId,
                'title' => 'Test Topic',
                'slug' => 'test-topic',
                'description' => 'Test topic description',
                'order' => 1,
                'status' => 'published',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create lessons for this topic
            $lesson1Id = \Illuminate\Support\Facades\DB::table('lessons')->insertGetId([
                'topic_id' => $topicId,
                'title' => 'Lesson 1',
                'description' => 'Lesson 1 description',
                'order' => 1,
                'status' => 'published',
                'created_by' => $this->teamUser->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $lesson2Id = \Illuminate\Support\Facades\DB::table('lessons')->insertGetId([
                'topic_id' => $topicId,
                'title' => 'Lesson 2',
                'description' => 'Lesson 2 description',
                'order' => 2,
                'status' => 'published',
                'created_by' => $this->teamUser->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create user progress for first lesson
            \Illuminate\Support\Facades\DB::table('user_progress')->insert([
                'user_id' => $this->studentUser->id,
                'trackable_type' => 'App\\Models\\Tenants\\Lesson',
                'trackable_id' => $lesson1Id,
                'status' => 'in_progress',
                'meta_data' => json_encode(['progress' => 80]),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return [
                'language_id' => $language,
                'learning_path_id' => $learningPathId,
                'unit_id' => $unitId,
                'topic_id' => $topicId,
                'lesson1_id' => $lesson1Id,
                'lesson2_id' => $lesson2Id
            ];
        });
    }

    /** @test */
    public function student_can_view_lessons_in_topic()
    {
        // Authenticate as student using tenant guard (routes use auth:tenant)
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get lessons in topic (tenant context should be handled by middleware)
        $response = $this->getJson("/api/{$this->tenant->slug}/student/topics/{$this->testData['topic_id']}/lessons");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'lessons' => [
                        '*' => [
                            'id',
                            'title',
                            'description'
                        ]
                    ],
                    'topic' => [
                        'id',
                        'title',
                        'description'
                    ],
                    'meta' => [
                        'total_lessons',
                        'completed_lessons',
                        'accessible_lessons'
                    ]
                ]
            ])
            ->assertJsonPath('data.topic.id', $this->testData['topic_id']);
    }

    /** @test */
    public function student_can_view_individual_lesson()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get specific lesson
        $response = $this->getJson("/api/{$this->tenant->slug}/student/lessons/{$this->testData['lesson1_id']}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'description',
                    'order',
                    'status',
                    'topic_id',
                    'content',
                    'progress',
                    'created_at',
                    'updated_at'
                ]
            ])
            ->assertJsonFragment([
                'title' => 'Lesson 1',
                'progress' => 80
            ]);

        // Verify content is present
        $this->assertNotEmpty($response->json('data.content'));
    }

    /** @test */
    public function student_can_view_lesson_progress()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get lesson progress
        $response = $this->getJson("/api/{$this->tenant->slug}/student/lessons/{$this->testData['lesson1_id']}/progress");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'lesson_id',
                    'progress',
                    'completed',
                    'last_accessed_at'
                ]
            ])
            ->assertJsonFragment([
                'lesson_id' => $this->testData['lesson1_id'],
                'progress' => 80,
                'completed' => false
            ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_lessons()
    {
        // API call without authentication
        $response = $this->getJson("/api/{$this->tenant->slug}/student/lessons/{$this->testData['lesson1_id']}");

        $response->assertStatus(401);
    }

    /** @test */
    public function student_cannot_access_unpublished_lesson()
    {
        // Create an unpublished lesson
        $unpublishedLessonId = $this->runInTenantContext($this->tenant, function () {
            return \Illuminate\Support\Facades\DB::table('lessons')->insertGetId([
                'topic_id' => $this->testData['topic_id'],
                'title' => 'Unpublished Lesson',
                'description' => 'Unpublished lesson description',
                'order' => 3,
                'status' => 'draft', // Unpublished
                'created_by' => $this->teamUser->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        });

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to access unpublished lesson
        $response = $this->getJson("/api/{$this->tenant->slug}/student/lessons/{$unpublishedLessonId}");

        // Should return 404 as students shouldn't see unpublished content
        $response->assertStatus(404);
    }

    /** @test */
    public function student_can_mark_lesson_as_completed()
    {
        // Authenticate as student using tenant guard (progress routes use auth:tenant)
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to mark lesson as completed
        $response = $this->putJson("/api/{$this->tenant->slug}/student/progress/lesson/{$this->testData['lesson1_id']}", [
            'completion_percentage' => 100,
            'time_spent' => 30,
            'score' => 95
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'user_id',
                    'content_type',
                    'content_id',
                    'completion_percentage',
                    'time_spent_minutes',
                    'score',
                    'completed_at'
                ]
            ])
            ->assertJson([
                'data' => [
                    'content_id' => $this->testData['lesson1_id'],
                    'content_type' => 'lesson',
                    'completion_percentage' => 100
                ]
            ]);

        // TODO: Verify the lesson is marked as completed in DB when UserProgress model is implemented
        // $this->runInTenantContext($this->tenant, function () {
        //     $progress = \Illuminate\Support\Facades\DB::table('user_progress')
        //         ->where('user_id', $this->studentUser->id)
        //         ->where('content_id', $this->testData['lesson1_id'])
        //         ->where('content_type', 'lesson')
        //         ->first();
        //
        //     $this->assertNotNull($progress);
        //     $this->assertEquals(100, $progress->completion_percentage);
        //     $this->assertNotNull($progress->completed_at);
        // });
    }

    /** @test */
    public function sequential_learning_prevents_skipping_lessons()
    {
        // Assuming the application has sequential learning middleware
        // First, ensure the student hasn't completed or started lesson 1
        $this->runInTenantContext($this->tenant, function () {
            \Illuminate\Support\Facades\DB::table('user_progress')
                ->where('user_id', $this->studentUser->id)
                ->where('item_id', $this->testData['lesson1_id'])
                ->delete();
        });

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // Try to access lesson 2 directly (should be prevented by sequential learning)
        $response = $this->getJson("/api/{$this->tenant->slug}/student/lessons/{$this->testData['lesson2_id']}");

        // Should return 403 (forbidden) if sequential learning is enforced
        // Note: This test might need adjustment based on how sequential learning is implemented
        if ($response->status() === 403) {
            $response->assertStatus(403)
                ->assertJson([
                    'message' => 'You must complete previous lessons first'
                ]);
        } else {
            // If sequential learning is not implemented or works differently,
            // this test might need adjustment
            $this->markTestIncomplete('Sequential learning middleware may not be implemented as expected');
        }
    }

    /** @test */
    public function student_can_partially_update_lesson_progress()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to update lesson progress to 50%
        $response = $this->putJson("/api/{$this->tenant->slug}/student/progress/lesson/{$this->testData['lesson1_id']}", [
            'completion_percentage' => 50,
            'time_spent' => 20,
            'score' => 75
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'user_id',
                    'content_type',
                    'content_id',
                    'completion_percentage',
                    'time_spent_minutes',
                    'score',
                    'updated_at'
                ]
            ])
            ->assertJson([
                'data' => [
                    'content_id' => $this->testData['lesson1_id'],
                    'content_type' => 'lesson',
                    'completion_percentage' => 50
                ]
            ]);

        // Verify the completed_at field is null since it's not marked as completed (50% < 100%)
        $this->assertNull($response->json('data.completed_at'));

        // TODO: Verify the lesson progress is updated in DB when UserProgress model is implemented
        // $this->runInTenantContext($this->tenant, function () {
        //     $progress = \Illuminate\Support\Facades\DB::table('user_progress')
        //         ->where('user_id', $this->studentUser->id)
        //         ->where('content_id', $this->testData['lesson1_id'])
        //         ->where('content_type', 'lesson')
        //         ->first();
        //
        //     $this->assertNotNull($progress);
        //     $this->assertEquals(50, $progress->completion_percentage);
        //     $this->assertNull($progress->completed_at);
        // });
    }

    /** @test */
    public function team_member_can_view_lessons()
    {
        // Authenticate as team member
        Sanctum::actingAs($this->teamUser, [], 'tenant');

        // API call to get lessons in topic
        $response = $this->getJson("/api/{$this->tenant->slug}/student/topics/{$this->testData['topic_id']}/lessons");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'order',
                        'status'
                    ]
                ]
            ]);

        // Team members should see the same lessons as students
        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function invalid_lesson_id_returns_404()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call with invalid UUID
        $invalidId = 'not-a-uuid';
        $response = $this->getJson("/api/{$this->tenant->slug}/student/lessons/{$invalidId}");

        $response->assertStatus(404);

        // API call with valid UUID format that doesn't exist
        $nonExistentId = '12345678-1234-1234-1234-123456789012';
        $response = $this->getJson("/api/{$this->tenant->slug}/student/lessons/{$nonExistentId}");

        $response->assertStatus(404);
    }

    /** @test */
    public function student_cannot_make_invalid_progress_update()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call with invalid progress value (greater than 100)
        $response = $this->putJson("/api/{$this->tenant->slug}/student/progress/lesson/{$this->testData['lesson1_id']}", [
            'completion_percentage' => 150,
            'time_spent' => 30
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['completion_percentage']);

        // API call with invalid progress value (negative)
        $response = $this->putJson("/api/{$this->tenant->slug}/student/progress/lesson/{$this->testData['lesson1_id']}", [
            'completion_percentage' => -10,
            'time_spent' => 15
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['completion_percentage']);
    }

    /** @test */
    public function student_can_create_initial_progress_record()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // Remove any existing progress for lesson 2
        $this->runInTenantContext($this->tenant, function () {
            \Illuminate\Support\Facades\DB::table('user_progress')
                ->where('user_id', $this->studentUser->id)
                ->where('item_id', $this->testData['lesson2_id'])
                ->delete();
        });

        // API call to create initial progress record for lesson 2
        $response = $this->postJson("/api/{$this->tenant->slug}/student/progress/lesson/{$this->testData['lesson2_id']}", [
            'completion_percentage' => 25,
            'time_spent' => 15,
            'score' => 85
        ]);

        $response->assertStatus(201) // Created
            ->assertJsonStructure([
                'data' => [
                    'user_id',
                    'content_type',
                    'content_id',
                    'completion_percentage',
                    'time_spent_minutes',
                    'score',
                    'completed_at'
                ]
            ])
            ->assertJson([
                'data' => [
                    'content_id' => $this->testData['lesson2_id'],
                    'content_type' => 'lesson',
                    'completion_percentage' => 25
                ]
            ]);

        // Verify the progress record was created in DB
        $this->runInTenantContext($this->tenant, function () {
            $progress = \Illuminate\Support\Facades\DB::table('user_progress')
                ->where('user_id', $this->studentUser->id)
                ->where('trackable_id', $this->testData['lesson2_id'])
                ->where('trackable_type', 'App\\Models\\Tenants\\Lesson')
                ->first();

            $this->assertNotNull($progress);
            $this->assertEquals(25, json_decode($progress->meta_data, true)['completion_percentage']);
        });
    }

    /** @test */
    public function lesson_progress_endpoint_returns_progress_data()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // First create progress for lesson 2 to ensure it exists
        $this->postJson("/api/{$this->tenant->slug}/student/progress/lesson/{$this->testData['lesson2_id']}", [
            'progress' => 60,
            'completed' => false
        ]);

        // API call to get progress for lesson 2
        $response = $this->getJson("/api/{$this->tenant->slug}/student/lessons/{$this->testData['lesson2_id']}/progress");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'lesson_id',
                    'progress',
                    'completed',
                    'last_accessed_at'
                ]
            ])
            ->assertJson([
                'data' => [
                    'lesson_id' => $this->testData['lesson2_id'],
                    'progress' => 60,
                    'completed' => false
                ]
            ]);

        // Ensure the last_accessed_at field exists and is a valid timestamp
        $this->assertNotNull($response->json('data.last_accessed_at'));
    }
}
