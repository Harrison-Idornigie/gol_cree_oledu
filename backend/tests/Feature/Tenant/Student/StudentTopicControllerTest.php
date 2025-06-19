<?php

namespace Tests\Feature\Tenant\Student;

use Tests\TestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;

class StudentTopicControllerTest extends TestCase
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
     * Helper to create a tenant student
     */
    protected function createTenantStudent()
    {
        return $this->runInTenantContext($this->tenant, function () {
            $user = User::create([
                'name' => 'Student User',
                'email' => 'student_' . Str::random(5) . '@example.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]);
            
            // Assign student role
            try {
                // Direct DB insert to user_permissions for compatibility
                if (\Illuminate\Support\Facades\Schema::hasTable('user_permissions')) {
                    \Illuminate\Support\Facades\DB::table('user_permissions')->insert([
                        'id' => (string) Str::uuid(),
                        'user_id' => $user->id,
                        'permission' => 'student',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                
                // If we're using membership_type
                if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'membership_type')) {
                    $user->membership_type = 'student';
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
            $learningPathId = (string) Str::uuid();
            \Illuminate\Support\Facades\DB::table('learning_paths')->insert([
                'id' => $learningPathId,
                'title' => 'Test Learning Path',
                'description' => 'A test learning path',
                'language_id' => $language,
                'status' => 'published',
                'created_by' => $this->teamUser->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Create unit
            $unitId = (string) Str::uuid();
            \Illuminate\Support\Facades\DB::table('units')->insert([
                'id' => $unitId,
                'learning_path_id' => $learningPathId,
                'title' => 'Test Unit',
                'description' => 'Test unit description',
                'order' => 1,
                'status' => 'published',
                'created_by' => $this->teamUser->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Create topics for this unit
            $topic1Id = (string) Str::uuid();
            $topic2Id = (string) Str::uuid();
            
            \Illuminate\Support\Facades\DB::table('topics')->insert([
                'id' => $topic1Id,
                'unit_id' => $unitId,
                'title' => 'Topic 1',
                'description' => 'Topic 1 description',
                'order' => 1,
                'status' => 'published',
                'created_by' => $this->teamUser->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            \Illuminate\Support\Facades\DB::table('topics')->insert([
                'id' => $topic2Id,
                'unit_id' => $unitId,
                'title' => 'Topic 2',
                'description' => 'Topic 2 description',
                'order' => 2,
                'status' => 'published',
                'created_by' => $this->teamUser->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Create user progress for first topic
            \Illuminate\Support\Facades\DB::table('user_progress')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $this->studentUser->id,
                'progress_type' => 'topic',
                'item_id' => $topic1Id,
                'progress' => 70,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            return [
                'language_id' => $language,
                'learning_path_id' => $learningPathId,
                'unit_id' => $unitId,
                'topic1_id' => $topic1Id,
                'topic2_id' => $topic2Id
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
                        'progress' // Should include student's progress
                    ]
                ]
            ])
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'title' => 'Topic 1',
                'progress' => 70
            ])
            ->assertJsonFragment([
                'title' => 'Topic 2'
            ]);
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
                    'created_at',
                    'updated_at'
                ]
            ])
            ->assertJsonFragment([
                'title' => 'Topic 1',
                'progress' => 70
            ]);
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
                    'topic_id',
                    'progress',
                    'completed',
                    'last_accessed_at'
                ]
            ])
            ->assertJsonFragment([
                'topic_id' => $this->testData['topic1_id'],
                'progress' => 70,
                'completed' => false
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
            $topicId = (string) Str::uuid();
            \Illuminate\Support\Facades\DB::table('topics')->insert([
                'id' => $topicId,
                'unit_id' => $this->testData['unit_id'],
                'title' => 'Unpublished Topic',
                'description' => 'Unpublished topic description',
                'order' => 3,
                'status' => 'draft', // Unpublished
                'created_by' => $this->teamUser->id,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            return $topicId;
        });
        
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');
        
        // API call to access unpublished topic
        $response = $this->getJson("/api/{$this->tenant->slug}/student/topics/{$unpublishedTopicId}");
        
        // Should return 404 as students shouldn't see unpublished content
        $response->assertStatus(404);
    }
    
    /** @test */
    public function student_can_mark_topic_as_started()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');
        
        // API call to mark topic as started (assuming the endpoint exists)
        $response = $this->postJson("/api/{$this->tenant->slug}/student/topics/{$this->testData['topic2_id']}/start", [
            'device_type' => 'web'
        ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'topic_id',
                    'progress',
                    'started_at'
                ]
            ])
            ->assertJson([
                'data' => [
                    'topic_id' => $this->testData['topic2_id'],
                    'progress' => 0 // Initial progress
                ]
            ]);
    }
}
