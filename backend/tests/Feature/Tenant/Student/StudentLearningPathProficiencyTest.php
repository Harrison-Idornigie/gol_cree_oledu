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
                'is_active' => true,
                'metadata' => [
                    'writing_system' => 'syllabics',
                    'has_audio' => true,
                    'cultural_context' => 'indigenous',
                    'starter_pack' => true
                ]
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
                        'slug' => "plains-cree-{$level}-{$ageGroup}-" . Str::random(8),
                        'description' => "Plains Cree {$level} level course for {$ageGroup}",
                        'language_id' => $this->plainsCreeLanguage->id,
                        'target_level' => $level,
                        'status' => 'published',
                        'metadata' => [
                            'age_group' => $ageGroup,
                            'is_starter_pack' => true,
                            'is_official' => true,
                            'proficiency_level' => $level,
                            'prerequisites' => $this->getPrerequisites($level),
                            'estimated_hours' => $this->getEstimatedHours($level),
                            'vocabulary_constraints' => $this->getVocabularyConstraints($level),
                            'duolingo_features' => [
                                'clickable_vocabulary' => true,
                                'audio_pronunciation' => true,
                                'syllabics_display' => true,
                                'cultural_context' => true
                            ]
                        ],
                        'created_by' => $this->teamUser->id,
                    ]);

                    $this->testLearningPaths["{$level}_{$ageGroup}"] = $learningPath;
                }
            }
        });
    }

    protected function getPrerequisites(string $level): array
    {
        $prerequisites = [
            'A1' => [],
            'A2' => ['A1'],
            'B1' => ['A1', 'A2'],
            'B2' => ['A1', 'A2', 'B1'],
            'C1' => ['A1', 'A2', 'B1', 'B2'],
            'C2' => ['A1', 'A2', 'B1', 'B2', 'C1'],
        ];

        return $prerequisites[$level] ?? [];
    }

    protected function getEstimatedHours(string $level): int
    {
        $hours = [
            'A1' => 60,
            'A2' => 80,
            'B1' => 100,
            'B2' => 120,
            'C1' => 150,
            'C2' => 180,
        ];

        return $hours[$level] ?? 60;
    }

    protected function getVocabularyConstraints(string $level): array
    {
        $constraints = [
            'A1' => ['A1'],
            'A2' => ['A1', 'A2'],
            'B1' => ['A1', 'A2', 'B1'],
            'B2' => ['A1', 'A2', 'B1', 'B2'],
            'C1' => ['A1', 'A2', 'B1', 'B2', 'C1'],
            'C2' => ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'],
        ];

        return $constraints[$level] ?? ['A1'];
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
                            'metadata' => [
                                'proficiency_level',
                                'age_group',
                                'prerequisites',
                                'estimated_hours',
                                'vocabulary_constraints'
                            ]
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
                $this->assertEquals($level, $path['metadata']['proficiency_level']);
                $this->assertEquals($this->getPrerequisites($level), $path['metadata']['prerequisites']);
                $this->assertEquals($this->getEstimatedHours($level), $path['metadata']['estimated_hours']);
            }
        }
    }

    /** @test */
    public function student_can_filter_learning_paths_by_age_group()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $ageGroups = ['kids', 'teen_adult'];

        foreach ($ageGroups as $ageGroup) {
            // API call to get learning paths for specific age group
            $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths?age_group={$ageGroup}");

            $response->assertStatus(200);

            $learningPaths = $response->json('data.data');

            // Should have 6 learning paths for each age group (A1-C2)
            $this->assertCount(6, $learningPaths, "Should have 6 learning paths for age group {$ageGroup}");

            foreach ($learningPaths as $path) {
                $this->assertEquals($ageGroup, $path['metadata']['age_group']);
            }
        }
    }

    /** @test */
    public function student_can_get_learning_path_with_vocabulary_progression_constraints()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $testCases = [
            'A1' => ['A1'],
            'A2' => ['A1', 'A2'],
            'B1' => ['A1', 'A2', 'B1'],
            'B2' => ['A1', 'A2', 'B1', 'B2'],
            'C1' => ['A1', 'A2', 'B1', 'B2', 'C1'],
            'C2' => ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'],
        ];

        foreach ($testCases as $level => $expectedConstraints) {
            $learningPath = $this->testLearningPaths["{$level}_teen_adult"];

            // API call to get specific learning path
            $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths/{$learningPath->id}");

            $response->assertStatus(200);

            $pathData = $response->json('data');
            $this->assertEquals($expectedConstraints, $pathData['metadata']['vocabulary_constraints']);
        }
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
        $this->assertEquals($learningPath->id, $enrollmentData['learning_path_id']);
        $this->assertEquals($this->studentUser->id, $enrollmentData['user_id']);
        $this->assertEquals(0, $enrollmentData['progress']); // Initial progress should be 0

        // Verify enrollment was created in database
        $this->runInTenantContext($this->tenant, function () use ($learningPath) {
            $this->assertDatabaseHas('user_learning_paths', [
                'user_id' => $this->studentUser->id,
                'learning_path_id' => $learningPath->id,
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
    public function student_can_get_learning_paths_with_enrollment_status()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // Enroll in one learning path
        $enrolledPath = $this->testLearningPaths['A1_kids'];
        $this->postJson("/api/{$this->tenant->slug}/student/learning-paths/{$enrolledPath->id}/enroll");

        // API call to get all learning paths
        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths?include_enrollment_status=true");

        $response->assertStatus(200);

        $learningPaths = $response->json('data.data');

        foreach ($learningPaths as $path) {
            if ($path['id'] === $enrolledPath->id) {
                $this->assertTrue($path['is_enrolled']);
                $this->assertArrayHasKey('enrollment_date', $path);
            } else {
                $this->assertFalse($path['is_enrolled']);
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

        foreach ($learningPaths as $path) {
            $this->assertEquals($this->plainsCreeLanguage->id, $path['language_id']);
        }
    }

    /** @test */
    public function student_cannot_enroll_in_same_learning_path_twice()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $learningPath = $this->testLearningPaths['A1_teen_adult'];

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
            return User::factory()->create(array_merge(['email' => 'student@test.com',
                'membership' => 'student',
                'email_verified_at' => now(),
            ], $attributes));
        });
    }

    protected function createTenantTeam(array $attributes = []): User
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return User::factory()->create(array_merge(['email' => 'team@test.com',
                'membership' => 'team',
                'email_verified_at' => now(),
            ], $attributes));
        });
    }
}
