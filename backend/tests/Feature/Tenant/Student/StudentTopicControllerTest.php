<?php

namespace Tests\Feature\Tenant\Student;

use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;

class StudentTopicControllerTest extends TenantTestCase
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
        $this->teamUser = $this->createTenantTeamMember();

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

            // Assign team role
            try {
                // Direct DB insert to user_permissions for compatibility
                if (\Illuminate\Support\Facades\Schema::hasTable('user_permissions')) {
                    \Illuminate\Support\Facades\DB::table('user_permissions')->insert([
                        'id' => (string) Str::uuid(),
                        'user_id' => $user->id,
                        'permission' => 'team',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // If we're using membership_type
                if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'membership_type')) {
                    $user->membership_type = 'team';
                    $user->save();
                }
            } catch (\Exception $e) {
                // Role assignment might fail if tables don't exist yet
            }

            return $user;
        });
    }



    /**
     * Setup test data for topics, units, etc.
     */
    protected function setupTestData()
    {
        return $this->runInTenantContext($this->tenant, function () {
            // Create language for testing using Eloquent
            $language = \App\Models\Tenants\Language::create([
                'name' => 'Test Language',
                'code' => 'tl',
                'native_name' => 'Test Native',
                'is_active' => true
            ]);

            // Create learning path using Eloquent
            $learningPath = \App\Models\Tenants\LearningPath::create([
                'title' => 'Test Learning Path',
                'description' => 'A test learning path',
                'language_id' => $language->id,
                'target_level' => 'A1',
                'status' => 'published',
                'created_by' => $this->teamUser->id
            ]);

            // Create unit using Eloquent
            $unit = \App\Models\Tenants\Unit::create([
                'learning_path_id' => $learningPath->id,
                'title' => 'Test Unit',
                'description' => 'Test unit description',
                'order' => 1,
                'status' => 'published'
            ]);

            // Create topics for this unit using Eloquent
            $topic1 = \App\Models\Tenants\Topic::create([
                'unit_id' => $unit->id,
                'title' => 'Topic 1',
                'slug' => 'topic-1',
                'description' => 'Topic 1 description',
                'order' => 1,
                'status' => 'published'
            ]);

            $topic2 = \App\Models\Tenants\Topic::create([
                'unit_id' => $unit->id,
                'title' => 'Topic 2',
                'slug' => 'topic-2',
                'description' => 'Topic 2 description',
                'order' => 2,
                'status' => 'published'
            ]);

            // Create user progress for first topic using Eloquent
            \App\Models\Tenants\UserProgress::create([
                'user_id' => $this->studentUser->id,
                'trackable_type' => \App\Models\Tenants\Topic::class,
                'trackable_id' => $topic1->id,
                'status' => 'in_progress',
                'meta_data' => ['progress' => 70]
            ]);

            return [
                'language_id' => $language->id,
                'learning_path_id' => $learningPath->id,
                'unit_id' => $unit->id,
                'topic1_id' => $topic1->id,
                'topic2_id' => $topic2->id
            ];
        });
    }

    /** @test */
    public function student_can_view_topics_in_unit()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get topics in unit
        $response = $this->getJson("/api/{$this->tenant->slug}/student/units/{$this->testData['unit_id']}/topics");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'order',
                        'status',
                        'user_progress' => [
                            'overall_progress',
                            'lessons_completed',
                            'lessons_total',
                            'exercises_completed',
                            'exercises_total',
                            'xp_earned',
                            'xp_total',
                            'time_spent_minutes',
                            'last_activity',
                            'current_lesson',
                            'next_lesson',
                            'is_completed'
                        ],
                        'is_accessible',
                        'completion_status'
                    ]
                ]
            ])
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'title' => 'Topic 1'
            ])
            ->assertJsonFragment([
                'title' => 'Topic 2'
            ])
            ->assertJsonPath('data.0.user_progress.overall_progress', 70);
    }

    /** @test */
    public function student_can_view_individual_topic()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get specific topic
        $response = $this->getJson("/api/{$this->tenant->slug}/student/topics/{$this->testData['topic1_id']}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'description',
                    'order',
                    'status',
                    'unit_id',
                    'progress',
                    'user_progress' => [
                        'overall_progress',
                        'lessons_completed',
                        'lessons_total',
                        'exercises_completed',
                        'exercises_total',
                        'xp_earned',
                        'xp_total',
                        'time_spent_minutes',
                        'last_activity',
                        'current_lesson',
                        'next_lesson',
                        'is_completed'
                    ],
                    'completion_status',
                    'created_at',
                    'updated_at'
                ]
            ])
            ->assertJsonFragment([
                'title' => 'Topic 1'
            ])
            ->assertJsonPath('data.progress', 70)
            ->assertJsonPath('data.user_progress.overall_progress', 70);
    }

    /** @test */
    public function student_can_view_topic_progress()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get topic progress
        $response = $this->getJson("/api/{$this->tenant->slug}/student/topics/{$this->testData['topic1_id']}/progress");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'overall_progress',
                    'lessons_completed',
                    'lessons_total',
                    'exercises_completed',
                    'exercises_total',
                    'xp_earned',
                    'xp_total',
                    'time_spent_minutes',
                    'last_activity',
                    'current_lesson',
                    'next_lesson',
                    'is_completed'
                ]
            ])
            ->assertJsonFragment([
                'overall_progress' => 70,
                'is_completed' => false
            ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_topics()
    {
        // API call without authentication
        $response = $this->getJson("/api/{$this->tenant->slug}/student/topics/{$this->testData['topic1_id']}");

        $response->assertStatus(401);
    }

    /** @test */
    public function student_cannot_access_unpublished_topic()
    {
        // Create an unpublished topic
        $unpublishedTopicId = $this->runInTenantContext($this->tenant, function () {
            $unpublishedTopic = \App\Models\Tenants\Topic::create([
                'unit_id' => $this->testData['unit_id'],
                'title' => 'Unpublished Topic',
                'slug' => 'unpublished-topic',
                'description' => 'Unpublished topic description',
                'order' => 3,
                'status' => 'draft' // Unpublished
            ]);
            return $unpublishedTopic->id;
        });

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to access unpublished topic
        $response = $this->getJson("/api/{$this->tenant->slug}/student/topics/{$unpublishedTopicId}");

        // Should return 403 as students shouldn't access unpublished content
        $response->assertStatus(403);
    }
}
