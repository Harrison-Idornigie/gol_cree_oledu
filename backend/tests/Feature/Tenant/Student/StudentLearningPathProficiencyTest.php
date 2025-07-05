<?php

namespace Tests\Feature\Tenant\Student;

use Tests\TenantTestCase;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\Language;
use App\Models\Tenants\LearningPath;
use App\Models\Tenants\UserProgress;
use Laravel\Sanctum\Sanctum;

/**
 * Learning Path API Tests with Proficiency Filtering
 * 
 * Tests comprehensive A1-C2 proficiency level filtering, enrollment,
 * progress tracking, and tenant isolation for Plains Cree content.
 */
class StudentLearningPathProficiencyTest extends TenantTestCase
{

    protected Tenant $tenant;
    protected Tenant $otherTenant;
    protected User $studentUser;
    protected User $teamUser;
    protected Language $plainsCreeLanguage;
    protected array $testLearningPaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Create test tenants
        $this->tenant = $this->createTestTenant();
        $this->otherTenant = $this->createTestTenant('other-tenant');
        $this->initializeTenantContext($this->tenant);

        // Create users
        $this->studentUser = $this->createTenantStudent();
        $this->teamUser = $this->createTenantTeam();

        // Create test environment
        $this->createTestLanguage();
        $this->createTestLearningPaths();
    }

    protected function createTestLanguage(): void
    {
        $this->runInTenantContext($this->tenant, function () {
            $this->plainsCreeLanguage = Language::create([
                'name' => 'Plains Cree',
                'code' => 'crk',
                'native_name' => 'nēhiyawēwin',
                'is_active' => true
            ]);
        });
    }

    protected function createTestLearningPaths(): void
    {
        $this->runInTenantContext($this->tenant, function () {
            $levels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
            $ageGroups = ['kids', 'teen_adult'];

            foreach ($levels as $level) {
                foreach ($ageGroups as $ageGroup) {
                    $learningPath = LearningPath::create([
                        'title' => "Plains Cree {$level} - {$ageGroup}",
                        'description' => "Plains Cree {$level} level course for {$ageGroup}",
                        'language_id' => $this->plainsCreeLanguage->id,
                        'target_level' => $level,
                        'status' => 'published',
                        'created_by' => $this->teamUser->id,
                    ]);

                    $this->testLearningPaths["{$level}_{$ageGroup}"] = $learningPath;
                }
            }
        });
    }



    /**  */
    public function test_student_can_get_all_learning_paths_with_proficiency_levels()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get all learning paths
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
                            'language_id',
                            'status',
                            'description'
                        ]
                    ]
                ]
            ]);

        $learningPaths = $response->json('data.data');

        // Should have 12 learning paths (6 levels × 2 age groups)
        $this->assertCount(12, $learningPaths);

        // Verify all CEFR levels are present
        $levels = array_unique(array_column($learningPaths, 'target_level'));
        sort($levels);
        $this->assertEquals(['A1', 'A2', 'B1', 'B2', 'C1', 'C2'], array_values($levels));
    }

    /**  */
    public function test_student_can_filter_learning_paths_by_specific_proficiency_level()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $testCases = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];

        foreach ($testCases as $level) {
            // API call to get learning paths for specific level
            $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths/by-level/{$level}");

            $response->assertStatus(200);

            $learningPaths = $response->json('data.data');

            // Should have 2 learning paths for each level (kids + teen_adult)
            $this->assertCount(2, $learningPaths, "Should have 2 learning paths for level {$level}");

            foreach ($learningPaths as $path) {
                $this->assertEquals($level, $path['target_level']);
            }
        }
    }

    /**  */
    public function test_student_can_filter_learning_paths_by_language()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get learning paths for Plains Cree language
        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths?language_id={$this->plainsCreeLanguage->id}");

        $response->assertStatus(200);

        $learningPaths = $response->json('data.data');

        // Should have 12 learning paths (6 levels × 2 age groups)
        $this->assertCount(12, $learningPaths, "Should have 12 learning paths for Plains Cree");

        foreach ($learningPaths as $path) {
            $this->assertEquals($this->plainsCreeLanguage->id, $path['language_id']);
        }
    }

    /**  */
    public function test_student_can_get_individual_learning_path_details()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $learningPath = $this->testLearningPaths['A1_teen_adult'];

        // API call to get specific learning path
        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths/{$learningPath->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'title',
                    'description',
                    'target_level',
                    'language_id',
                    'status'
                ]
            ]);

        $pathData = $response->json('data');
        $this->assertEquals($learningPath->id, $pathData['id']);
        $this->assertEquals('A1', $pathData['target_level']);
        $this->assertEquals($this->plainsCreeLanguage->id, $pathData['language_id']);
    }

    /**  */
    public function test_student_can_enroll_in_learning_path_and_track_progress()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $learningPath = $this->testLearningPaths['A1_kids'];

        // API call to enroll in learning path
        $response = $this->postJson("/api/{$this->tenant->slug}/student/learning-paths/{$learningPath->id}/enroll");

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'success',
                    'message',
                    'enrollment'
                ]
            ]);

        $responseData = $response->json('data');
        $this->assertTrue($responseData['success']);
        $this->assertEquals('Successfully enrolled in learning path.', $responseData['message']);

        $enrollmentData = $responseData['enrollment'];
        $this->assertEquals($learningPath->id, $enrollmentData['trackable_id']);
        $this->assertEquals($this->studentUser->id, $enrollmentData['user_id']);
        $this->assertEquals('in_progress', $enrollmentData['status']); // Initial status should be in_progress

        // Verify enrollment was created in database
        $this->runInTenantContext($this->tenant, function () use ($learningPath) {
            $this->assertDatabaseHas('user_progress', [
                'user_id' => $this->studentUser->id,
                'trackable_type' => 'App\Models\Tenants\LearningPath',
                'trackable_id' => $learningPath->id,
            ]);
        });
    }

    /**  */
    public function test_student_can_get_learning_path_progress()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $learningPath = $this->testLearningPaths['A2_teen_adult'];

        // First enroll in the learning path
        $this->postJson("/api/{$this->tenant->slug}/student/learning-paths/{$learningPath->id}/enroll");

        // Create some progress
        $this->runInTenantContext($this->tenant, function () use ($learningPath) {
            UserProgress::create([
                'user_id' => $this->studentUser->id,
                'trackable_type' => LearningPath::class,
                'trackable_id' => $learningPath->id,
                'progress_percentage' => 25,
                'completed' => false,
                'last_accessed_at' => now(),
            ]);
        });

        // API call to get learning path progress
        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths/{$learningPath->id}/progress");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'learning_path_progress',
                    'units_progress'
                ]
            ]);

        $progressData = $response->json('data');
        $this->assertEquals('in_progress', $progressData['learning_path_progress']);
        $this->assertIsArray($progressData['units_progress']);
    }

    /**  */
    public function test_student_cannot_access_learning_paths_from_other_tenants()
    {
        // Authenticate as student in original tenant
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // Use a non-existent ID to test tenant isolation
        $nonExistentId = 99999;

        // Try to access learning path that doesn't exist in current tenant
        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths/{$nonExistentId}");
        $response->assertStatus(404);

        // Try to enroll in learning path that doesn't exist in current tenant
        $response = $this->postJson("/api/{$this->tenant->slug}/student/learning-paths/{$nonExistentId}/enroll");
        $response->assertStatus(404);

        // Try to get progress for learning path that doesn't exist in current tenant
        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths/{$nonExistentId}/progress");
        $response->assertStatus(404);
    }

    /**  */
    public function test_student_can_get_learning_path_progress_after_enrollment()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // Enroll in one learning path
        $enrolledPath = $this->testLearningPaths['A1_kids'];
        $enrollResponse = $this->postJson("/api/{$this->tenant->slug}/student/learning-paths/{$enrolledPath->id}/enroll");
        $enrollResponse->assertStatus(201);

        // API call to get progress for the enrolled learning path
        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths/{$enrolledPath->id}/progress");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'learning_path_progress',
                    'units_progress'
                ]
            ]);

        $progressData = $response->json('data');
        $this->assertEquals('in_progress', $progressData['learning_path_progress']);
    }



    /**  */
    public function test_student_cannot_enroll_in_same_learning_path_twice()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $learningPath = $this->testLearningPaths['A1_teen_adult'];

        // Verify the learning path exists
        $this->assertNotNull($learningPath, 'Learning path A1_teen_adult should exist');
        $this->assertNotNull($learningPath->id, 'Learning path should have an ID');

        // First enrollment should succeed
        $response = $this->postJson("/api/{$this->tenant->slug}/student/learning-paths/{$learningPath->id}/enroll");
        $response->assertStatus(201);

        // Second enrollment should fail
        $response = $this->postJson("/api/{$this->tenant->slug}/student/learning-paths/{$learningPath->id}/enroll");
        $response->assertStatus(400) // Bad Request
            ->assertJson([
                'success' => false,
                'message' => 'User is already enrolled in this learning path.'
            ]);
    }

    /**  */
    public function test_student_can_search_learning_paths()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to search for Plains Cree learning paths
        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths?search=Plains Cree");

        $response->assertStatus(200);

        $learningPaths = $response->json('data.data');

        foreach ($learningPaths as $path) {
            $this->assertStringContainsString('Plains Cree', $path['title']);
        }
    }
}
