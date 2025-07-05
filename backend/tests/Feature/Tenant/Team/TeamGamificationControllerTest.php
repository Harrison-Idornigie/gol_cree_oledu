<?php

namespace Tests\Feature\Tenant\Team;

use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\Language;
use App\Models\Tenants\Achievement;
use App\Models\Tenants\LearningPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

class TeamGamificationControllerTest extends TenantTestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $teamMember;
    protected User $studentUser;
    protected Language $language;
    protected LearningPath $learningPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        // Create test tenant
        $this->tenant = $this->createTestTenant();

        // Create test users in tenant context
        $this->teamMember = $this->runInTenantContext($this->tenant, function () {
            return User::create([
                'name' => 'Team Member',
                'email' => 'team@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);
        });

        $this->studentUser = $this->runInTenantContext($this->tenant, function () {
            return User::create([
                'name' => 'Student User',
                'email' => 'student@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);
        });

        // Create test language and learning path
        $this->language = $this->runInTenantContext($this->tenant, function () {
            return Language::create([
                'name' => 'Spanish',
                'code' => 'es',
                'native_name' => 'Español',
                'direction' => 'ltr',
                'status' => 'active',
            ]);
        });

        $this->learningPath = $this->runInTenantContext($this->tenant, function () {
            return LearningPath::create([
                'title' => 'Spanish for Beginners',
                'description' => 'Learn Spanish from scratch',
                'language_id' => $this->language->id,
                'level' => 'beginner',
                'status' => 'active',
                'created_by' => $this->teamMember->id,
            ]);
        });
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    protected function createTestAchievement(array $attributes = []): Achievement
    {
        return $this->runInTenantContext($this->tenant, function () use ($attributes) {
            return Achievement::create(array_merge([
                'name' => 'First Steps',
                'description' => 'Complete your first lesson',
                'type' => 'milestone',
                'category' => 'progress',
                'criteria' => json_encode([
                    'lessons_completed' => 1
                ]),
                'points' => 10,
                'badge_icon' => 'trophy',
                'badge_color' => '#FFD700',
                'is_active' => true,
                'created_by' => $this->teamMember->id,
            ], $attributes));
        });
    }

    protected function createTestUserAchievement(User $user, Achievement $achievement)
    {
        return $this->runInTenantContext($this->tenant, function () use ($user, $achievement) {
            // Create a mock user achievement record
            return (object) [
                'user_id' => $user->id,
                'achievement_id' => $achievement->id,
                'earned_at' => now(),
                'progress_data' => json_encode(['lessons_completed' => 1]),
            ];
        });
    }

    /**
     * Test team member can get achievements overview
     * 
     * 
     */
    public function test_team_member_can_get_achievements_overview()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $achievement1 = $this->createTestAchievement(['name' => 'First Steps']);
        $achievement2 = $this->createTestAchievement(['name' => 'Fast Learner', 'type' => 'streak']);

        // Create some user achievements
        $this->createTestUserAchievement($this->studentUser, $achievement1);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/gamification/achievements");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'achievements' => [
                        '*' => [
                            'id',
                            'name',
                            'description',
                            'type',
                            'category',
                            'points',
                            'badge_icon',
                            'badge_color',
                            'is_active',
                            'earned_count',
                            'total_users',
                            'earn_rate',
                            'created_at'
                        ]
                    ],
                    'summary' => [
                        'total_achievements',
                        'active_achievements',
                        'total_earned',
                        'most_popular'
                    ]
                ]
            ]);
    }

    /**
     * Test team member can get detailed statistics
     * 
     * 
     */
    public function test_team_member_can_get_detailed_statistics()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $achievement = $this->createTestAchievement();
        $this->createTestUserAchievement($this->studentUser, $achievement);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/gamification/statistics");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'overview' => [
                        'total_users',
                        'active_users',
                        'total_achievements_earned',
                        'total_points_awarded',
                        'average_engagement_score'
                    ],
                    'achievement_stats' => [
                        'most_earned',
                        'least_earned',
                        'recent_achievements',
                        'achievement_distribution'
                    ],
                    'user_engagement' => [
                        'daily_active_users',
                        'weekly_active_users',
                        'user_retention_rate',
                        'average_session_duration'
                    ],
                    'learning_progress' => [
                        'completion_rates',
                        'average_progress_speed',
                        'struggling_areas',
                        'top_performers'
                    ],
                    'trends' => [
                        'weekly_achievements',
                        'monthly_achievements',
                        'engagement_trends'
                    ]
                ]
            ]);
    }

    /**
     * Test filtering achievements by type
     * 
     * 
     */
    public function test_filtering_achievements_by_type()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $milestoneAchievement = $this->createTestAchievement([
            'name' => 'Milestone Achievement',
            'type' => 'milestone'
        ]);

        $streakAchievement = $this->createTestAchievement([
            'name' => 'Streak Achievement',
            'type' => 'streak'
        ]);

        // Filter by milestone type
        $response = $this->getJson("/api/{$this->tenant->slug}/team/gamification/achievements?type=milestone");
        $response->assertStatus(200);

        $achievements = $response->json('data.achievements');
        $this->assertCount(1, $achievements);
        $this->assertEquals('Milestone Achievement', $achievements[0]['name']);

        // Filter by streak type
        $response = $this->getJson("/api/{$this->tenant->slug}/team/gamification/achievements?type=streak");
        $response->assertStatus(200);

        $achievements = $response->json('data.achievements');
        $this->assertCount(1, $achievements);
        $this->assertEquals('Streak Achievement', $achievements[0]['name']);
    }

    /**
     * Test filtering achievements by category
     * 
     * 
     */
    public function test_filtering_achievements_by_category()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $progressAchievement = $this->createTestAchievement([
            'name' => 'Progress Achievement',
            'category' => 'progress'
        ]);

        $socialAchievement = $this->createTestAchievement([
            'name' => 'Social Achievement',
            'category' => 'social'
        ]);

        // Filter by progress category
        $response = $this->getJson("/api/{$this->tenant->slug}/team/gamification/achievements?category=progress");
        $response->assertStatus(200);

        $achievements = $response->json('data.achievements');
        $this->assertCount(1, $achievements);
        $this->assertEquals('Progress Achievement', $achievements[0]['name']);

        // Filter by social category
        $response = $this->getJson("/api/{$this->tenant->slug}/team/gamification/achievements?category=social");
        $response->assertStatus(200);

        $achievements = $response->json('data.achievements');
        $this->assertCount(1, $achievements);
        $this->assertEquals('Social Achievement', $achievements[0]['name']);
    }

    /**
     * Test filtering statistics by date range
     * 
     * 
     */
    public function test_filtering_statistics_by_date_range()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $achievement = $this->createTestAchievement();
        $this->createTestUserAchievement($this->studentUser, $achievement);

        $startDate = now()->subDays(30)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $response = $this->getJson("/api/{$this->tenant->slug}/team/gamification/statistics?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'date_range' => [
                        'start_date',
                        'end_date'
                    ],
                    'filtered_stats' => [
                        'achievements_earned_in_period',
                        'new_users_in_period',
                        'engagement_in_period'
                    ]
                ]
            ]);
    }

    /**
     * Test getting statistics by learning path
     * 
     * 
     */
    public function test_getting_statistics_by_learning_path()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/gamification/statistics?learning_path_id={$this->learningPath->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'learning_path_info' => [
                        'id',
                        'title'
                    ],
                    'path_specific_stats' => [
                        'enrolled_users',
                        'completion_rate',
                        'average_progress',
                        'achievements_earned'
                    ]
                ]
            ]);
    }

    /**
     * Test achievement engagement metrics
     * 
     * 
     */
    public function test_achievement_engagement_metrics()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $achievement = $this->createTestAchievement();
        $this->createTestUserAchievement($this->studentUser, $achievement);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/gamification/achievements?include_metrics=true");

        $response->assertStatus(200);

        $achievements = $response->json('data.achievements');
        $this->assertArrayHasKey('earned_count', $achievements[0]);
        $this->assertArrayHasKey('total_users', $achievements[0]);
        $this->assertArrayHasKey('earn_rate', $achievements[0]);
    }

    /**
     * Test students cannot access team gamification endpoints
     * 
     * 
     */
    public function test_students_cannot_access_team_gamification_endpoints()
    {
        Sanctum::actingAs($this->studentUser, ['tenant']);

        // Test achievements endpoint
        $response = $this->getJson("/api/{$this->tenant->slug}/team/gamification/achievements");
        $response->assertStatus(403);

        // Test statistics endpoint
        $response = $this->getJson("/api/{$this->tenant->slug}/team/gamification/statistics");
        $response->assertStatus(403);
    }

    /**
     * Test unauthenticated access is blocked
     * 
     * 
     */
    public function test_unauthenticated_access_blocked()
    {
        $response = $this->getJson("/api/{$this->tenant->slug}/team/gamification/achievements");
        $response->assertStatus(401);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/gamification/statistics");
        $response->assertStatus(401);
    }

    /**
     * Test achievement statistics with no data
     * 
     * 
     */
    public function test_achievement_statistics_with_no_data()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/gamification/achievements");

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.total_achievements', 0)
            ->assertJsonPath('data.summary.total_earned', 0);
    }

    /**
     * Test statistics export functionality
     * 
     * 
     */
    public function test_statistics_export_functionality()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $achievement = $this->createTestAchievement();
        $this->createTestUserAchievement($this->studentUser, $achievement);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/gamification/statistics?format=export");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'export_data' => [
                        'achievements_summary',
                        'user_engagement_data',
                        'learning_progress_data'
                    ],
                    'export_info' => [
                        'generated_at',
                        'total_records',
                        'format'
                    ]
                ]
            ]);
    }

    /**
     * Test achievement performance analytics
     * 
     * 
     */
    public function test_achievement_performance_analytics()
    {
        Sanctum::actingAs($this->teamMember, ['tenant']);

        $popularAchievement = $this->createTestAchievement(['name' => 'Popular Achievement']);
        $rareAchievement = $this->createTestAchievement(['name' => 'Rare Achievement']);

        // Create multiple user achievements for the popular one
        $this->createTestUserAchievement($this->studentUser, $popularAchievement);

        $response = $this->getJson("/api/{$this->tenant->slug}/team/gamification/achievements?analytics=performance");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'performance_metrics' => [
                        'top_performing_achievements',
                        'underperforming_achievements',
                        'achievement_difficulty_analysis'
                    ]
                ]
            ]);
    }
}
