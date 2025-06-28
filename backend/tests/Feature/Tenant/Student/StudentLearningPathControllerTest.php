<?php

namespace Tests\Feature\Tenant\Student;

use Tests\TenantTestCase;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\Language;
use App\Models\Tenants\LearningPath;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;

class StudentLearningPathControllerTest extends TenantTestCase
{

    protected Tenant $tenant;
    protected User $studentUser;
    protected User $teamUser;
    protected Language $language;
    protected LearningPath $learningPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test tenant
        $this->tenant = $this->createTestTenant();
        $this->initializeTenantContext($this->tenant);

        // Create users with different roles in tenant context
        $this->studentUser = $this->createTenantStudent();
        $this->teamUser = $this->createTenantTeam();

        // Create test environment
        $this->language = $this->createLanguage();
        $this->learningPath = $this->createLearningPath([
            'title' => 'Test Learning Path',
            'description' => 'This is a test learning path',
            'language_id' => $this->language->id,
            'target_level' => 'beginner',
            'status' => 'published',
            'created_by' => $this->teamUser->id,
        ]);
    }

    /**
     * Test listing all learning paths
     */
    public function test_index_success()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'title',
                            'target_level',
                            'status'
                        ]
                    ],
                    'current_page',
                    'total',
                    'per_page'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Learning paths retrieved successfully.'
            ]);
    }

    /**
     * Test showing a specific learning path
     */
    public function test_show_success()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');



        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths/{$this->learningPath->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'title',
                    'description',
                    'language_id',
                    'target_level',
                    'status',
                    'created_by',
                    'created_at',
                    'updated_at'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Learning path retrieved successfully.',
                'data' => [
                    'id' => $this->learningPath->id,
                    'title' => $this->learningPath->title
                ]
            ]);
    }

    /**
     * Test getting learning path progress
     */
    public function test_progress_success()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths/{$this->learningPath->id}/progress");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Learning path progress retrieved successfully.'
            ]);
    }

    /**
     * Test enrolling in a learning path
     */
    public function test_enroll_success()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        $response = $this->postJson("/api/{$this->tenant->slug}/student/learning-paths/{$this->learningPath->id}/enroll");

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Successfully enrolled in learning path.'
            ]);

        // Verify enrollment was created in user_progress table
        $this->runInTenantContext($this->tenant, function () {
            $this->assertDatabaseHas('user_progress', [
                'user_id' => $this->studentUser->id,
                'trackable_type' => 'App\\Models\\Tenants\\LearningPath',
                'trackable_id' => $this->learningPath->id,
                'status' => 'in_progress',
            ]);
        });
    }

    /**
     * Test getting learning paths by level
     */
    public function test_by_level_success()
    {
        // Create a learning path with a different level
        $intermediatePath = $this->runInTenantContext($this->tenant, function () {
            return LearningPath::create([
                'title' => 'Intermediate Learning Path',
                'description' => 'This is an intermediate learning path',
                'language_id' => $this->language->id,
                'target_level' => 'intermediate',
                'status' => 'published',
                'created_by' => $this->teamUser->id,
            ]);
        });

        // Debug: Verify the learning path was created
        $this->assertNotNull($intermediatePath, 'Intermediate learning path should be created');
        $this->assertEquals('intermediate', $intermediatePath->target_level, 'Learning path should have intermediate level');

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');



        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths/by-level/intermediate");



        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'title',
                            'target_level',
                            'status'
                        ]
                    ],
                    'current_page',
                    'total'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Learning paths by level retrieved successfully.'
            ]);

        // Verify the response contains only intermediate level paths
        $learningPaths = $response->json('data.data');
        $this->assertNotEmpty($learningPaths, 'Should have at least one intermediate learning path');

        foreach ($learningPaths as $path) {
            $this->assertEquals('intermediate', $path['target_level']);
        }

        // Verify no beginner level paths are included
        $beginnerPaths = collect($learningPaths)->where('target_level', 'beginner');
        $this->assertEmpty($beginnerPaths, 'Should not contain any beginner level paths');
    }

    /**
     * Test unauthorized access
     */
    public function test_unauthorized_access()
    {
        // Not authenticated
        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths");
        $response->assertStatus(401);
    }

    /**
     * Test access to unpublished learning path
     */
    public function test_unpublished_learning_path_access()
    {
        // Create an unpublished learning path
        $unpublishedPath = $this->runInTenantContext($this->tenant, function () {
            return LearningPath::create([
                'title' => 'Unpublished Learning Path',
                'description' => 'This is an unpublished learning path',
                'language_id' => $this->language->id,
                'target_level' => 'beginner',
                'status' => 'draft', // Unpublished status
                'created_by' => $this->teamUser->id,
            ]);
        });

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths/{$unpublishedPath->id}");
        $response->assertStatus(404);
    }
}
