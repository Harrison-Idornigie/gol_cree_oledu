<?php

namespace Tests\Feature\Tenant\Student;

use Tests\TestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\Language;
use App\Models\Tenants\LearningPath;
use App\Models\Tenants\Unit;
use App\Models\Tenants\Topic;
use App\Models\Tenants\Lesson;
use App\Models\Tenants\Exercise;
use App\Models\Tenants\ExerciseAttempt;
use App\Models\Tenants\UserProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class StudentExerciseStatisticsControllerTest extends TestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $studentUser;
    protected User $otherStudentUser;
    protected User $teamUser;
    protected Language $language;
    protected Exercise $exercise;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();
        
        // Create test tenant
        $this->tenant = $this->createTestTenant();
        $this->initializeTenantContext($this->tenant);
        
        // Create users
        $this->studentUser = $this->createTenantStudent();
        $this->otherStudentUser = $this->createTenantStudent(['email' => 'other@example.com']);
        $this->teamUser = $this->createTenantTeam();
        
        // Create test content
        $this->language = $this->createLanguage();
        $this->createTestContent();
    }

    protected function createTestContent(): void
    {
        $this->runInTenantContext($this->tenant, function () {
            $learningPath = LearningPath::factory()->create([
                'language_id' => $this->language->id,
                'status' => 'published'
            ]);

            $unit = Unit::factory()->create([
                'learning_path_id' => $learningPath->id,
                'status' => 'published'
            ]);

            $topic = Topic::factory()->create([
                'unit_id' => $unit->id,
                'status' => 'published'
            ]);

            $lesson = Lesson::factory()->create([
                'topic_id' => $topic->id,
                'status' => 'published'
            ]);

            $this->exercise = Exercise::factory()->create([
                'lesson_id' => $lesson->id,
                'status' => 'published',
                'exercise_type' => 'multiple_choice'
            ]);

            // Create exercise attempts for the student
            ExerciseAttempt::factory()->count(5)->create([
                'exercise_id' => $this->exercise->id,
                'user_id' => $this->studentUser->id,
                'is_correct' => true,
                'score' => 85.5,
                'time_taken_seconds' => 30
            ]);

            ExerciseAttempt::factory()->count(3)->create([
                'exercise_id' => $this->exercise->id,
                'user_id' => $this->studentUser->id,
                'is_correct' => false,
                'score' => 45.0,
                'time_taken_seconds' => 60
            ]);

            // Create attempts for other student (should not be visible)
            ExerciseAttempt::factory()->count(2)->create([
                'exercise_id' => $this->exercise->id,
                'user_id' => $this->otherStudentUser->id,
                'is_correct' => true,
                'score' => 90.0
            ]);
        });
    }

    /** @test */
    public function student_can_get_exercise_statistics()
    {
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');
        
        $response = $this->getJson("/api/{$this->tenant->slug}/student/exercises/{$this->exercise->id}/statistics");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'exercise_id',
                    'total_attempts',
                    'correct_attempts',
                    'incorrect_attempts',
                    'success_rate',
                    'average_score',
                    'best_score',
                    'worst_score',
                    'average_time_seconds',
                    'fastest_time_seconds',
                    'slowest_time_seconds',
                    'recent_attempts' => [
                        '*' => [
                            'id',
                            'is_correct',
                            'score',
                            'time_taken_seconds',
                            'created_at'
                        ]
                    ],
                    'performance_trend' => [
                        '*' => [
                            'date',
                            'attempts',
                            'success_rate',
                            'average_score'
                        ]
                    ]
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Exercise statistics retrieved successfully.',
                'data' => [
                    'exercise_id' => $this->exercise->id,
                    'total_attempts' => 8, // Only this student's attempts
                    'correct_attempts' => 5,
                    'incorrect_attempts' => 3
                ]
            ]);

        // Verify success rate calculation
        $data = $response->json('data');
        $this->assertEquals(62.5, $data['success_rate']); // 5/8 * 100
    }

    /** @test */
    public function exercise_statistics_only_show_current_students_data()
    {
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');
        
        $response = $this->getJson("/api/{$this->tenant->slug}/student/exercises/{$this->exercise->id}/statistics");

        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Should only show 8 attempts (5 correct + 3 incorrect) for current student
        // Not the 2 attempts from other student
        $this->assertEquals(8, $data['total_attempts']);
        $this->assertEquals(5, $data['correct_attempts']);
        $this->assertEquals(3, $data['incorrect_attempts']);
    }

    /** @test */
    public function exercise_statistics_calculates_scores_correctly()
    {
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');
        
        $response = $this->getJson("/api/{$this->tenant->slug}/student/exercises/{$this->exercise->id}/statistics");

        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Verify score calculations
        // 5 attempts with 85.5 score + 3 attempts with 45.0 score
        // Average: (5 * 85.5 + 3 * 45.0) / 8 = (427.5 + 135) / 8 = 70.3125
        $this->assertEquals(70.31, round($data['average_score'], 2));
        $this->assertEquals(85.5, $data['best_score']);
        $this->assertEquals(45.0, $data['worst_score']);
    }

    /** @test */
    public function exercise_statistics_calculates_time_correctly()
    {
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');
        
        $response = $this->getJson("/api/{$this->tenant->slug}/student/exercises/{$this->exercise->id}/statistics");

        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Verify time calculations
        // 5 attempts with 30 seconds + 3 attempts with 60 seconds
        // Average: (5 * 30 + 3 * 60) / 8 = (150 + 180) / 8 = 41.25
        $this->assertEquals(41.25, $data['average_time_seconds']);
        $this->assertEquals(30, $data['fastest_time_seconds']);
        $this->assertEquals(60, $data['slowest_time_seconds']);
    }

    /** @test */
    public function exercise_statistics_includes_recent_attempts()
    {
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');
        
        $response = $this->getJson("/api/{$this->tenant->slug}/student/exercises/{$this->exercise->id}/statistics");

        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Should include recent attempts (limited to last 10)
        $this->assertArrayHasKey('recent_attempts', $data);
        $this->assertLessThanOrEqual(10, count($data['recent_attempts']));
        
        // Recent attempts should be ordered by created_at desc
        $recentAttempts = $data['recent_attempts'];
        if (count($recentAttempts) > 1) {
            $this->assertGreaterThanOrEqual(
                strtotime($recentAttempts[1]['created_at']),
                strtotime($recentAttempts[0]['created_at'])
            );
        }
    }

    /** @test */
    public function exercise_statistics_returns_404_for_nonexistent_exercise()
    {
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');
        
        $response = $this->getJson("/api/{$this->tenant->slug}/student/exercises/99999/statistics");

        $response->assertStatus(404);
    }

    /** @test */
    public function exercise_statistics_returns_empty_data_for_no_attempts()
    {
        // Create exercise with no attempts
        $exerciseWithNoAttempts = $this->runInTenantContext($this->tenant, function () {
            return Exercise::factory()->create([
                'lesson_id' => $this->exercise->lesson_id,
                'status' => 'published'
            ]);
        });
        
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');
        
        $response = $this->getJson("/api/{$this->tenant->slug}/student/exercises/{$exerciseWithNoAttempts->id}/statistics");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'exercise_id' => $exerciseWithNoAttempts->id,
                    'total_attempts' => 0,
                    'correct_attempts' => 0,
                    'incorrect_attempts' => 0,
                    'success_rate' => 0,
                    'average_score' => 0,
                    'best_score' => 0,
                    'worst_score' => 0,
                    'recent_attempts' => []
                ]
            ]);
    }

    /** @test */
    public function team_members_cannot_access_student_exercise_statistics()
    {
        Sanctum::actingAs($this->teamUser, ['*'], 'tenant');
        
        $response = $this->getJson("/api/{$this->tenant->slug}/student/exercises/{$this->exercise->id}/statistics");

        $response->assertStatus(403);
    }

    /** @test */
    public function unauthenticated_users_cannot_access_exercise_statistics()
    {
        $response = $this->getJson("/api/{$this->tenant->slug}/student/exercises/{$this->exercise->id}/statistics");

        $response->assertStatus(401);
    }

    /** @test */
    public function exercise_statistics_are_tenant_isolated()
    {
        $otherTenant = $this->createTestTenant(['slug' => 'other-tenant']);
        
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');
        
        // Try to access exercise from current tenant using other tenant's slug
        $response = $this->getJson("/api/{$otherTenant->slug}/student/exercises/{$this->exercise->id}/statistics");

        $response->assertStatus(404);
    }

    /** @test */
    public function exercise_statistics_include_performance_trend()
    {
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');
        
        $response = $this->getJson("/api/{$this->tenant->slug}/student/exercises/{$this->exercise->id}/statistics");

        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        $this->assertArrayHasKey('performance_trend', $data);
        $this->assertIsArray($data['performance_trend']);
        
        // Performance trend should group attempts by date
        foreach ($data['performance_trend'] as $trendPoint) {
            $this->assertArrayHasKey('date', $trendPoint);
            $this->assertArrayHasKey('attempts', $trendPoint);
            $this->assertArrayHasKey('success_rate', $trendPoint);
            $this->assertArrayHasKey('average_score', $trendPoint);
        }
    }
}
