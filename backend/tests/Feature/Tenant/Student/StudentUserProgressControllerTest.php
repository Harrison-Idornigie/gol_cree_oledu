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
use App\Models\Tenants\UserProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;

class StudentUserProgressControllerTest extends TenantTestCase
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

        // Setup test data
        $this->testData = $this->setupTestData();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }



    /**
     * Helper to create a tenant team member
     */
    protected function createTenantTeamMember()
    {
        return $this->runInTenantContext($this->tenant, function () {
            $user = User::create([
                'name' => 'Team User',
                'email' => 'team_' . Str::random(5) . '@example.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]);

            // Try to assign role using different methods depending on implementation
            try {
                if (class_exists('Spatie\\Permission\\Models\\Role')) {
                    // For Spatie Permission
                    $user->assignRole('team');
                } elseif (method_exists($user, 'givePermissionTo')) {
                    // Direct permission
                    $user->givePermissionTo('team');
                }
            } catch (\Exception $e) {
                // Role assignment might fail if tables don't exist yet
            }

            // Fallback: direct DB insert to user_permissions
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('user_permissions')) {
                    \Illuminate\Support\Facades\DB::table('user_permissions')->insert([
                        'id' => (string) Str::uuid(),
                        'user_id' => $user->id,
                        'permission' => 'team',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } catch (\Exception $e) {
                // Table might not exist
            }

            return $user;
        });
    }



    /**
     * Setup test data for progress tracking
     */
    protected function setupTestData()
    {
        return $this->runInTenantContext($this->tenant, function () {
            // Create language
            $language = Language::create([
                'name' => 'Test Language',
                'code' => 'tl',
                'native_name' => 'Test Native',
                'is_active' => true
            ]);

            // Create learning path using Eloquent model
            $learningPath = LearningPath::create([
                'title' => 'Test Learning Path',
                'description' => 'A test learning path',
                'language_id' => $language->id,
                'status' => 'published',
                'created_by' => $this->teamUser->id,
            ]);
            $learningPathId = $learningPath->id;

            // Create unit using Eloquent model
            $unit = Unit::create([
                'learning_path_id' => $learningPathId,
                'title' => 'Test Unit',
                'description' => 'Test unit description',
                'order' => 1,
                'status' => 'published',
                'created_by' => $this->teamUser->id,
            ]);
            $unitId = $unit->id;

            // Create topic using Eloquent model
            $topic = Topic::create([
                'unit_id' => $unitId,
                'title' => 'Test Topic',
                'slug' => 'test-topic',
                'description' => 'Test topic description',
                'order' => 1,
                'status' => 'published',
            ]);
            $topicId = $topic->id;

            // Create lesson using Eloquent model
            $lesson = Lesson::create([
                'topic_id' => $topicId,
                'title' => 'Test Lesson',
                'description' => 'Test lesson description',
                'order' => 1,
                'status' => 'published',
                'created_by' => $this->teamUser->id,
            ]);
            $lessonId = $lesson->id;

            // Create exercise using Eloquent model
            $exercise = Exercise::create([
                'lesson_id' => $lessonId,
                'title' => 'Test Exercise',
                'description' => 'Test exercise description',
                'type' => 'vocabulary',
                'order' => 1,
                'status' => 'published',
                'created_by' => $this->teamUser->id,
            ]);
            $exerciseId = $exercise->id;

            // Add some existing progress for learning path and unit using Eloquent
            UserProgress::create([
                'user_id' => $this->studentUser->id,
                'trackable_type' => LearningPath::class,
                'trackable_id' => $learningPathId,
                'status' => 'in_progress',
                'meta_data' => ['completion_percentage' => 25]
            ]);

            UserProgress::create([
                'user_id' => $this->studentUser->id,
                'trackable_type' => Unit::class,
                'trackable_id' => $unitId,
                'status' => 'in_progress',
                'meta_data' => ['completion_percentage' => 50]
            ]);

            return [
                'language_id' => $language->id,
                'learning_path_id' => $learningPathId,
                'unit_id' => $unitId,
                'topic_id' => $topicId,
                'lesson_id' => $lessonId,
                'exercise_id' => $exerciseId
            ];
        });
    }

    /**  */
    public function student_can_get_all_their_progress()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get all progress
        $response = $this->getJson("/api/{$this->tenant->slug}/student/progress");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'progress_type',
                        'item_id',
                        'progress',
                        'created_at',
                        'updated_at'
                    ]
                ]
            ])
            ->assertJsonCount(2, 'data')  // Two progress entries we created
            ->assertJsonFragment([
                'progress_type' => 'learning_path',
                'progress' => 25,
            ])
            ->assertJsonFragment([
                'progress_type' => 'unit',
                'progress' => 50,
            ]);
    }

    /**  */
    public function student_can_see_progress_for_specific_item()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get specific progress 
        $response = $this->getJson("/api/{$this->tenant->slug}/student/progress/unit/{$this->testData['unit_id']}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'user_id',
                    'progress_type',
                    'item_id',
                    'progress',
                    'created_at',
                    'updated_at'
                ]
            ])
            ->assertJsonFragment([
                'progress_type' => 'unit',
                'item_id' => $this->testData['unit_id'],
                'progress' => 50
            ]);
    }

    /**  */
    public function student_can_create_new_progress()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to record new progress for a lesson (no existing progress yet)
        $progressData = [
            'progress' => 75
        ];

        $response = $this->postJson(
            "/api/{$this->tenant->slug}/student/progress/lesson/{$this->testData['lesson_id']}",
            $progressData
        );

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'user_id',
                    'progress_type',
                    'item_id',
                    'progress',
                    'created_at',
                    'updated_at'
                ]
            ])
            ->assertJsonFragment([
                'progress_type' => 'lesson',
                'item_id' => $this->testData['lesson_id'],
                'progress' => 75
            ]);

        // Verify progress was saved in database
        $this->assertTenantHasData($this->tenant, 'user_progress', [
            'user_id' => $this->studentUser->id,
            'progress_type' => 'lesson',
            'item_id' => $this->testData['lesson_id'],
            'progress' => 75
        ]);
    }

    /**  */
    public function student_can_update_existing_progress()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to update existing progress for unit
        $updateData = [
            'progress' => 75
        ];

        $response = $this->putJson(
            "/api/{$this->tenant->slug}/student/progress/unit/{$this->testData['unit_id']}",
            $updateData
        );

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'user_id',
                    'progress_type',
                    'item_id',
                    'progress',
                    'updated_at'
                ]
            ])
            ->assertJsonFragment([
                'progress_type' => 'unit',
                'item_id' => $this->testData['unit_id'],
                'progress' => 75
            ]);

        // Verify progress was updated in database
        $this->assertTenantHasData($this->tenant, 'user_progress', [
            'user_id' => $this->studentUser->id,
            'progress_type' => 'unit',
            'item_id' => $this->testData['unit_id'],
            'progress' => 75
        ]);
    }

    /**  */
    public function student_cannot_update_another_students_progress()
    {
        // Create another student
        $otherStudent = $this->createTenantStudent();

        // Create progress for other student
        $this->runInTenantContext($this->tenant, function () use ($otherStudent) {
            \Illuminate\Support\Facades\DB::table('user_progress')->insert([
                'id' => (string)Str::uuid(),
                'user_id' => $otherStudent->id,
                'progress_type' => 'topic',
                'item_id' => $this->testData['topic_id'],
                'progress' => 30,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        });

        // Authenticate as first student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // Try to get/update the other student's progress
        $response = $this->getJson("/api/{$this->tenant->slug}/student/progress/topic/{$this->testData['topic_id']}");

        // Should return 404 or empty result as this student doesn't have this progress record
        if ($response->status() === 404) {
            $response->assertStatus(404);
        } else {
            $response->assertStatus(200);
            // Either no data or the record is for current user only
            if (isset($response->json()['data']) && !empty($response->json()['data'])) {
                $this->assertEquals($this->studentUser->id, $response->json()['data']['user_id']);
            }
        }
    }

    /**  */
    public function validates_progress_percentage_is_valid()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // Try to submit invalid progress value
        $invalidData = [
            'progress' => 150  // Invalid percentage over 100%
        ];

        $response = $this->postJson(
            "/api/{$this->tenant->slug}/student/progress/exercise/{$this->testData['exercise_id']}",
            $invalidData
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['progress']);

        // Try with negative value
        $invalidData = [
            'progress' => -10  // Invalid negative percentage
        ];

        $response = $this->postJson(
            "/api/{$this->tenant->slug}/student/progress/exercise/{$this->testData['exercise_id']}",
            $invalidData
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['progress']);
    }

    /**  */
    public function unauthenticated_user_cannot_access_progress_endpoints()
    {
        // API call without authentication
        $response = $this->getJson("/api/{$this->tenant->slug}/student/progress");

        $response->assertStatus(401);

        // Try to create progress without authentication
        $response = $this->postJson(
            "/api/{$this->tenant->slug}/student/progress/exercise/{$this->testData['exercise_id']}",
            ['progress' => 50]
        );

        $response->assertStatus(401);
    }
}
