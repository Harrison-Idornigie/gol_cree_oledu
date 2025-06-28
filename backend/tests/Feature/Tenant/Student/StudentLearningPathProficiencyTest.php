<?php

namespace Tests\Feature\Tenant\Student;

use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\Language;
use App\Models\Tenants\LearningPath;
use App\Models\Tenants\UserLearningPath;
use App\Models\Tenants\UserProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;

/**
 * Learning Path API Tests with Proficiency Filtering
 * 
 * Tests comprehensive A1-C2 proficiency level filtering, enrollment,
 * progress tracking, and tenant isolation for Plains Cree content.
 */
class StudentLearningPathProficiencyTest extends TenantTestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected Tenant $otherTenant;
    protected User $studentUser;
    protected User $teamUser;
    protected Language $plainsCreeLanguage;
    protected array $testLearningPaths = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

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



    /** @test */
    public function student_can_get_all_learning_paths_with_proficiency_levels()
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
        $this->assertEquals(['A1', 'A2', 'B1', 'B2', 'C1', 'C2'], array_values(sort($levels)));
    }

    /** @test */
    public function student_can_filter_learning_paths_by_specific_proficiency_level()
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

    /** @test */
    public function student_can_filter_learning_paths_by_language()
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

    /** @test */
    public function student_can_get_individual_learning_path_details()
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

    /** @test */
    public function student_can_enroll_in_learning_path_and_track_progress()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $learningPath = $this->testLearningPaths['A1_kids'];

        // API call to enroll in learning path
        $response = $this->postJson("/api/{$this->tenant->slug}/student/learning-paths/{$learningPath->id}/enroll");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'enrollment_id',
                    'learning_path_id',
                    'user_id',
                    'enrolled_at',
                    'progress'
                ]
            ]);

        $enrollmentData = $response->json('data');
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

    /** @test */
    public function student_can_get_learning_path_progress()
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
                    'learning_path_id',
                    'progress_percentage',
                    'completed',
                    'last_accessed_at',
                    'estimated_completion_date',
                    'units_completed',
                    'total_units'
                ]
            ]);

        $progressData = $response->json('data');
        $this->assertEquals($learningPath->id, $progressData['learning_path_id']);
        $this->assertEquals(25, $progressData['progress_percentage']);
        $this->assertFalse($progressData['completed']);
    }

    /** @test */
    public function student_cannot_access_learning_paths_from_other_tenants()
    {
        // Create learning path in other tenant
        $otherLearningPath = $this->runInTenantContext($this->otherTenant, function () {
            $language = Language::create([
                'name' => 'Other Plains Cree',
                'code' => 'crk',
                'native_name' => 'nēhiyawēwin',
                'is_active' => true
            ]);

            return LearningPath::create([
                'title' => 'Other Tenant Plains Cree A1',
                'slug' => 'other-plains-cree-a1',
                'description' => 'Plains Cree A1 in other tenant',
                'language_id' => $language->id,
                'target_level' => 'A1',
                'status' => 'published',
                'created_by' => 1,
            ]);
        });

        // Authenticate as student in original tenant
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // Try to access learning path from other tenant
        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths/{$otherLearningPath->id}");
        $response->assertStatus(404);

        // Try to enroll in learning path from other tenant
        $response = $this->postJson("/api/{$this->tenant->slug}/student/learning-paths/{$otherLearningPath->id}/enroll");
        $response->assertStatus(404);

        // Try to get progress for learning path from other tenant
        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths/{$otherLearningPath->id}/progress");
        $response->assertStatus(404);
    }

    /** @test */
    public function student_can_get_learning_path_progress_after_enrollment()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // Enroll in one learning path
        $enrolledPath = $this->testLearningPaths['A1_kids'];
        $enrollResponse = $this->postJson("/api/{$this->tenant->slug}/student/learning-paths/{$enrolledPath->id}/enroll");
        $enrollResponse->assertStatus(200);

        // API call to get progress for the enrolled learning path
        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths/{$enrolledPath->id}/progress");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'status',
                    'completion_percentage',
                    'units_progress'
                ]
            ]);

        $progressData = $response->json('data');
        $this->assertEquals('in_progress', $progressData['status']);
    }



    /** @test */
    public function student_cannot_enroll_in_same_learning_path_twice()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $learningPath = $this->testLearningPaths['A1_teen_adult'];

        // Verify the learning path exists
        $this->assertNotNull($learningPath, 'Learning path A1_teen_adult should exist');
        $this->assertNotNull($learningPath->id, 'Learning path should have an ID');

        // First enrollment should succeed
        $response = $this->postJson("/api/{$this->tenant->slug}/student/learning-paths/{$learningPath->id}/enroll");
        $response->assertStatus(200);

        // Second enrollment should fail
        $response = $this->postJson("/api/{$this->tenant->slug}/student/learning-paths/{$learningPath->id}/enroll");
        $response->assertStatus(409) // Conflict
            ->assertJson([
                'success' => false,
                'message' => 'Already enrolled in this learning path.'
            ]);
    }

    /** @test */
    public function student_can_search_learning_paths()
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

    // Helper methods
    protected function createTenantStudent(array $attributes = []): User
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return User::factory()->create(array_merge([
                'email' => 'student@test.com',
                'membership' => 'student',
                'email_verified_at' => now(),
            ], $attributes));
        });
    }

    protected function createTenantTeam(array $attributes = []): User
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return User::factory()->create(array_merge([
                'email' => 'team@test.com',
                'membership' => 'team',
                'email_verified_at' => now(),
            ], $attributes));
        });
    }
}
