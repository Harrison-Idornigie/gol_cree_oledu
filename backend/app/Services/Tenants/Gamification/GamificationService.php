<?php

namespace App\Services\Tenants\Gamification;

use App\Models\Tenants\Achievement;
use App\Models\Tenants\User;
use App\Models\Tenants\UserProgress;
use App\Models\Tenants\XpHistory;
use App\Models\Tenants\DailyGoal;
use App\Models\Tenants\League;
use App\Models\Tenants\UserStreak;
use App\Traits\Tenant\HasAuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Exception;
use Carbon\Carbon;

/**
 * Gamification Service
 * 
 * Handles gamification features including achievements, statistics,
 * leaderboards, and engagement analytics for team management.
 */
class GamificationService
{
    use HasAuditLog;

    const AUDIT_AREA = 'gamification';

    /**
     * Get achievements with filters and statistics.
     */
    public function getAchievements(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Achievement::query()->with(['users']);

        // Apply filters
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            $query->where('status', 'active');
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Add statistics
        $query->withCount(['users as earned_count']);

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $achievements = $query->paginate($perPage);

        // Add additional statistics to each achievement
        $totalUsers = User::count();

        $achievements->getCollection()->transform(function ($achievement) use ($totalUsers) {
            $earnRate = $totalUsers > 0 ? round(($achievement->earned_count / $totalUsers) * 100, 2) : 0;

            $achievement->setAttribute('total_users', $totalUsers);
            $achievement->setAttribute('earn_rate', $earnRate);

            return $achievement;
        });

        return $achievements;
    }

    /**
     * Get comprehensive gamification statistics.
     */
    public function getStatistics(array $filters = []): array
    {
        $dateFrom = isset($filters['date_from'])
            ? Carbon::parse($filters['date_from'])
            : Carbon::now()->subDays(30);

        $dateTo = isset($filters['date_to'])
            ? Carbon::parse($filters['date_to'])
            : Carbon::now();

        $learningPathId = $filters['learning_path_id'] ?? null;

        return [
            'overview' => $this->getOverviewStatistics($dateFrom, $dateTo),
            'achievement_stats' => $this->getAchievementStatistics($dateFrom, $dateTo),
            'user_engagement' => $this->getUserEngagementStatistics($dateFrom, $dateTo),
            'learning_progress' => $this->getLearningProgressStatistics($dateFrom, $dateTo, $learningPathId),
            'leaderboard' => $this->getLeaderboardStatistics($dateFrom, $dateTo),
            'trends' => $this->getTrendStatistics($dateFrom, $dateTo),
        ];
    }

    /**
     * Get overview statistics.
     */
    protected function getOverviewStatistics(Carbon $dateFrom, Carbon $dateTo): array
    {
        $totalUsers = User::count();
        $activeUsers = User::whereHas('xpHistory', function ($q) use ($dateFrom, $dateTo) {
            $q->whereBetween('created_at', [$dateFrom, $dateTo]);
        })->count();

        $totalAchievementsEarned = DB::table('user_achievements')
            ->whereBetween('earned_at', [$dateFrom, $dateTo])
            ->count();

        $totalPointsAwarded = XpHistory::whereBetween('created_at', [$dateFrom, $dateTo])
            ->sum('amount');

        $averageEngagement = $this->calculateAverageEngagementScore($dateFrom, $dateTo);

        return [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'total_achievements_earned' => $totalAchievementsEarned,
            'total_points_awarded' => $totalPointsAwarded,
            'average_engagement_score' => $averageEngagement,
            'activity_rate' => $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 2) : 0,
        ];
    }

    /**
     * Get achievement-specific statistics.
     */
    protected function getAchievementStatistics(Carbon $dateFrom, Carbon $dateTo): array
    {
        $mostEarned = Achievement::withCount(['users as earned_count'])
            ->orderBy('earned_count', 'desc')
            ->limit(5)
            ->get();

        $leastEarned = Achievement::withCount(['users as earned_count'])
            ->where('status', 'active')
            ->orderBy('earned_count', 'asc')
            ->limit(5)
            ->get();

        $recentAchievements = DB::table('user_achievements')
            ->join('achievements', 'user_achievements.achievement_id', '=', 'achievements.id')
            ->join('users', 'user_achievements.user_id', '=', 'users.id')
            ->whereBetween('user_achievements.earned_at', [$dateFrom, $dateTo])
            ->select([
                'achievements.name as achievement_name',
                'users.name as user_name',
                'user_achievements.earned_at'
            ])
            ->orderBy('user_achievements.earned_at', 'desc')
            ->limit(10)
            ->get();

        $achievementDistribution = Achievement::withCount(['users as earned_count'])
            ->get()
            ->groupBy(function ($achievement) {
                if ($achievement->earned_count === 0) return 'never_earned';
                if ($achievement->earned_count <= 5) return 'rare';
                if ($achievement->earned_count <= 25) return 'uncommon';
                if ($achievement->earned_count <= 100) return 'common';
                return 'frequent';
            })
            ->map->count();

        return [
            'most_earned' => $mostEarned,
            'least_earned' => $leastEarned,
            'recent_achievements' => $recentAchievements,
            'achievement_distribution' => $achievementDistribution,
        ];
    }

    /**
     * Get user engagement statistics.
     */
    protected function getUserEngagementStatistics(Carbon $dateFrom, Carbon $dateTo): array
    {
        $dailyActiveUsers = $this->getDailyActiveUsers($dateFrom, $dateTo);
        $weeklyActiveUsers = $this->getWeeklyActiveUsers($dateFrom, $dateTo);

        $userRetentionRate = $this->calculateUserRetentionRate($dateFrom, $dateTo);
        $averageSessionDuration = $this->calculateAverageSessionDuration($dateFrom, $dateTo);

        $engagementTrends = $this->getEngagementTrends($dateFrom, $dateTo);

        return [
            'daily_active_users' => $dailyActiveUsers,
            'weekly_active_users' => $weeklyActiveUsers,
            'user_retention_rate' => $userRetentionRate,
            'average_session_duration' => $averageSessionDuration,
            'engagement_trends' => $engagementTrends,
        ];
    }

    /**
     * Get learning progress statistics.
     */
    protected function getLearningProgressStatistics(Carbon $dateFrom, Carbon $dateTo, ?int $learningPathId = null): array
    {
        $query = UserProgress::whereBetween('updated_at', [$dateFrom, $dateTo]);

        if ($learningPathId) {
            // Add filter for specific learning path if needed
            // This would require additional model relationships
        }

        $completionRates = $query->select([
            DB::raw('AVG(CASE WHEN status = "completed" THEN 100 ELSE 0 END) as avg_completion_rate'),
            DB::raw('COUNT(CASE WHEN status = "completed" THEN 1 END) as completed_count'),
            DB::raw('COUNT(*) as total_count')
        ])->first();

        $progressSpeed = $this->calculateAverageProgressSpeed($dateFrom, $dateTo);
        $strugglingAreas = $this->identifyStrugglingAreas($dateFrom, $dateTo);
        $topPerformers = $this->getTopPerformers($dateFrom, $dateTo);

        return [
            'completion_rates' => $completionRates,
            'average_progress_speed' => $progressSpeed,
            'struggling_areas' => $strugglingAreas,
            'top_performers' => $topPerformers,
        ];
    }

    /**
     * Get leaderboard statistics.
     */
    protected function getLeaderboardStatistics(Carbon $dateFrom, Carbon $dateTo): array
    {
        $topXpEarners = User::select(['id', 'name', 'total_points'])
            ->whereHas('xpHistory', function ($q) use ($dateFrom, $dateTo) {
                $q->whereBetween('created_at', [$dateFrom, $dateTo]);
            })
            ->orderBy('total_points', 'desc')
            ->limit(10)
            ->get();

        $streakLeaders = UserStreak::with('user:id,name')
            ->orderBy('current_streak', 'desc')
            ->limit(10)
            ->get();

        $leagueStats = League::withCount(['users as member_count'])
            ->with(['users' => function ($q) {
                $q->orderByPivot('weekly_xp', 'desc')->limit(3);
            }])
            ->get();

        return [
            'top_xp_earners' => $topXpEarners,
            'streak_leaders' => $streakLeaders,
            'league_statistics' => $leagueStats,
        ];
    }

    /**
     * Get trend statistics.
     */
    protected function getTrendStatistics(Carbon $dateFrom, Carbon $dateTo): array
    {
        $weeklyAchievements = DB::table('user_achievements')
            ->select([
                DB::raw('WEEK(earned_at) as week'),
                DB::raw('COUNT(*) as count')
            ])
            ->whereBetween('earned_at', [$dateFrom, $dateTo])
            ->groupBy('week')
            ->orderBy('week')
            ->get();

        $monthlyAchievements = DB::table('user_achievements')
            ->select([
                DB::raw('MONTH(earned_at) as month'),
                DB::raw('COUNT(*) as count')
            ])
            ->whereBetween('earned_at', [$dateFrom, $dateTo])
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $engagementTrends = $this->getDetailedEngagementTrends($dateFrom, $dateTo);

        return [
            'weekly_achievements' => $weeklyAchievements,
            'monthly_achievements' => $monthlyAchievements,
            'engagement_trends' => $engagementTrends,
        ];
    }

    // Helper methods for calculations

    protected function calculateAverageEngagementScore(Carbon $dateFrom, Carbon $dateTo): float
    {
        // Simplified engagement score calculation
        $activeUsers = User::whereHas('xpHistory', function ($q) use ($dateFrom, $dateTo) {
            $q->whereBetween('created_at', [$dateFrom, $dateTo]);
        })->count();

        $totalUsers = User::count();
        return $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 2) : 0;
    }

    protected function getDailyActiveUsers(Carbon $dateFrom, Carbon $dateTo): int
    {
        return User::whereHas('xpHistory', function ($q) {
            $q->whereDate('created_at', Carbon::today());
        })->count();
    }

    protected function getWeeklyActiveUsers(Carbon $dateFrom, Carbon $dateTo): int
    {
        return User::whereHas('xpHistory', function ($q) {
            $q->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
        })->count();
    }

    protected function calculateUserRetentionRate(Carbon $dateFrom, Carbon $dateTo): float
    {
        // Simple retention calculation - users active in both periods
        $previousPeriodStart = $dateFrom->copy()->subDays($dateTo->diffInDays($dateFrom));

        $currentPeriodUsers = User::whereHas('xpHistory', function ($q) use ($dateFrom, $dateTo) {
            $q->whereBetween('created_at', [$dateFrom, $dateTo]);
        })->pluck('id');

        $previousPeriodUsers = User::whereHas('xpHistory', function ($q) use ($previousPeriodStart, $dateFrom) {
            $q->whereBetween('created_at', [$previousPeriodStart, $dateFrom]);
        })->pluck('id');

        $retainedUsers = $currentPeriodUsers->intersect($previousPeriodUsers)->count();

        return $previousPeriodUsers->count() > 0
            ? round(($retainedUsers / $previousPeriodUsers->count()) * 100, 2)
            : 0;
    }

    protected function calculateAverageSessionDuration(Carbon $dateFrom, Carbon $dateTo): int
    {
        // Simplified session duration calculation based on progress time_spent
        $avgTimeSpent = UserProgress::whereBetween('updated_at', [$dateFrom, $dateTo])
            ->whereNotNull('meta_data->time_spent')
            ->avg(DB::raw('JSON_EXTRACT(meta_data, "$.time_spent")'));

        return round($avgTimeSpent ?? 0);
    }

    protected function getEngagementTrends(Carbon $dateFrom, Carbon $dateTo): array
    {
        $daily = [];
        $current = $dateFrom->copy();

        while ($current <= $dateTo) {
            $activeUsers = User::whereHas('xpHistory', function ($q) use ($current) {
                $q->whereDate('created_at', $current);
            })->count();

            $daily[] = [
                'date' => $current->format('Y-m-d'),
                'active_users' => $activeUsers
            ];

            $current->addDay();
        }

        return ['daily' => $daily];
    }

    protected function calculateAverageProgressSpeed(Carbon $dateFrom, Carbon $dateTo): float
    {
        // Calculate average time to completion for lessons/exercises
        $avgSpeed = UserProgress::whereBetween('completed_at', [$dateFrom, $dateTo])
            ->where('status', 'completed')
            ->whereNotNull('meta_data->time_spent')
            ->avg(DB::raw('JSON_EXTRACT(meta_data, "$.time_spent")'));

        return round($avgSpeed ?? 0, 2);
    }

    protected function identifyStrugglingAreas(Carbon $dateFrom, Carbon $dateTo): array
    {
        // Identify content with high failure rates
        return UserProgress::select([
            'trackable_type',
            'trackable_id',
            DB::raw('COUNT(*) as total_attempts'),
            DB::raw('COUNT(CASE WHEN status = "failed" THEN 1 END) as failed_attempts'),
            DB::raw('AVG(CASE WHEN status = "failed" THEN 100 ELSE 0 END) as failure_rate')
        ])
            ->whereBetween('updated_at', [$dateFrom, $dateTo])
            ->groupBy('trackable_type', 'trackable_id')
            ->having('failure_rate', '>', 30) // More than 30% failure rate
            ->orderBy('failure_rate', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
    }

    protected function getTopPerformers(Carbon $dateFrom, Carbon $dateTo): array
    {
        return User::select(['id', 'name'])
            ->withSum(['xpHistory as period_xp' => function ($q) use ($dateFrom, $dateTo) {
                $q->whereBetween('created_at', [$dateFrom, $dateTo]);
            }], 'amount')
            ->having('period_xp', '>', 0)
            ->orderBy('period_xp', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    protected function getDetailedEngagementTrends(Carbon $dateFrom, Carbon $dateTo): array
    {
        $xpTrends = XpHistory::select([
            DB::raw('DATE(created_at) as date'),
            DB::raw('SUM(amount) as total_xp'),
            DB::raw('COUNT(DISTINCT user_id) as active_users')
        ])
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'daily_xp_trends' => $xpTrends,
        ];
    }
}
