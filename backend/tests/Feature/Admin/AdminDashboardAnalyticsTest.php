<?php

namespace Tests\Feature\Admin;

use App\Models\Tenants\User;
use App\Models\LearningPath;
use App\Models\Unit;
use App\Models\Lesson;
use App\Models\UserProgress;
use App\Models\UserStreak;
use App\Models\XpHistory;
use App\Models\Language;
use App\Models\Achievement;
use App\Models\Exercise;
use App\Models\ExerciseAttempt;
 
use App\Models\Section;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminDashboardAnalyticsTest extends AdminTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestData();
    }

    protected function createTestData(): void
    {
        // Create test languages
        $japanese = Language::create([
            'name' => 'Japanese',
            'code' => 'ja',
            'native_name' => '日本語',
            'is_active' => true
        ]);

        // Create learning path structure
        $learningPath = LearningPath::create([
            'title' => 'Japanese 101',
            'description' => 'Learn basic Japanese',
            'target_level' => 'beginner',
            'status' => 'published',
            'language_id' => $japanese->id
        ]);

        $unit = Unit::create([
            'learning_path_id' => $learningPath->id,
            'title' => 'Unit 1',
            'description' => 'Basic greetings',
            'order' => 1,
            'status' => 'published'
        ]);

        $lesson = Lesson::create([
            'unit_id' => $unit->id,
            'title' => 'Greetings',
            'description' => 'Learn common greetings',
            'order' => 1,
            'status' => 'published'
        ]);

        // Create a section for the lesson
        $section = Section::create([
            'lesson_id' => $lesson->id,
            'title' => 'Basic Greetings',
            'slug' => 'basic-greetings',
            'description' => 'Introduction to common greetings',
            'order' => 1,
            'type' => 'exercise',
            'status' => 'published'
        ]);

        // Create test exercises
        $exercise = Exercise::create([
            'section_id' => $section->id,
            'lesson_id' => $lesson->id,
            'title' => 'Test Exercise',
            'slug' => 'test-exercise',
            'type' => 'multiple_choice',
            'content' => json_encode([
                'question' => 'Test question',
                'answers' => [
                    ['id' => 'A', 'text' => 'Option A', 'correct' => true],
                    ['id' => 'B', 'text' => 'Option B', 'correct' => false],
                ]
            ]),
            'order' => 1
        ]);

 
        // Create test users with progress and streaks
        for ($i = 1; $i <= 5; $i++) {
            $user = User::create([
                'name' => "Test User $i",
                'email' => "user$i@example.com",
                'password' => bcrypt('password'),
                'role' => 'user'
            ]);

            // Add progress
            UserProgress::create([
                'user_id' => $user->id,
                'trackable_type' => Lesson::class,
                'trackable_id' => $lesson->id,
                'status' => $i <= 3 ? 'completed' : 'in_progress',
                'xp_earned' => $i <= 3 ? 20 : 10
            ]);

            // Add streaks
            UserStreak::create([
                'user_id' => $user->id,
                'current_streak' => $i,
                'longest_streak' => $i + 2,
                'last_activity_date' => now()->subDays(1)
            ]);

            // Add XP history
            XpHistory::create([
                'user_id' => $user->id,
                'amount' => $i * 10,
                'source' => 'lesson_completion',
                'created_at' => now()->subDays($i)
            ]);

            // Add exercise attempts
            ExerciseAttempt::create([
                'exercise_id' => $exercise->id,
                'user_id' => $user->id,
                'is_correct' => $i % 2 == 0,
                'user_answer' => $i % 2 == 0 ? 'A' : 'B',
                'time_taken_seconds' => 30 + $i
            ]);

        }

        // Create some achievements
        Achievement::create([
            'name' => 'First Lesson',
            'description' => 'Complete your first lesson',
            'requirements' => json_encode(['lessons_completed' => 1]),
            'rewards' => json_encode(['xp' => 50]),
            'status' => 'active'
        ]);
    }

    public function test_dashboard_shows_user_engagement_metrics()
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/dashboard/engagement');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'daily_active_users',
                    'streak_statistics' => [
                        'users_with_streaks',
                        'average_streak_length',
                        'longest_current_streak'
                    ],
                    'engagement_metrics',
                    'retention_rate'
                ]
            ]);
    }

    public function test_dashboard_shows_learning_progress()
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/dashboard/progress');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'completion_rates',
                    'average_completion_time',
                    'progress_by_language'
                ]
            ]);
    }

    public function test_dashboard_shows_achievements_overview()
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/dashboard/achievements');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_achievements',
                    'most_earned',
                    'least_earned'
                ]
            ]);
    }

    public function test_dashboard_shows_xp_leaderboards()
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/dashboard/leaderboards');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'daily_leaders',
                    'weekly_leaders',
                    'all_time_leaders'
                ]
            ]);
    }

    public function test_dashboard_shows_content_health()
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/dashboard/content-health');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'needs_review',
                    'completion_issues',
                    'feedback_summary'
                ]
            ]);
    }

    public function test_unauthorized_user_cannot_access_dashboard()
    {
        $response = $this->actingAsUser()
            ->getJson('/api/admin/dashboard/engagement');

        $this->assertUnauthorized($response);
    }
}