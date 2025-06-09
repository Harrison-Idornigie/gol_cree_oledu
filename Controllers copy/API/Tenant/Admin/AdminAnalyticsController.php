<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\LearningPath;
use App\Models\User;
use App\Models\UserProgress;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class AdminAnalyticsController extends BaseAPIController
{
    /**
     * Get user engagement metrics with detailed learning behavior analysis.
     * Unlike DashboardController's summary, this provides in-depth behavioral insights.
     */
    public function userEngagement(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'start_date' => 'nullable|date',
                'end_date'   => 'nullable|date|after_or_equal:start_date',
                'interval'   => 'nullable|string|in:daily,weekly,monthly',
            ]);

            $startDate = Carbon::parse($request->input('start_date', Carbon::now()->subDays(30)));
            $endDate   = Carbon::parse($request->input('end_date', Carbon::now()));

            if ($startDate->diffInDays($endDate) > 366) {
                return $this->sendError('Date range too large. Maximum range is 366 days.', [], 400);
            }

            $userEngagement = UserProgress::with('user')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    'user_id',
                    DB::raw('COUNT(*) as total_interactions'),
                    DB::raw('AVG(meta_data->>"$.time_spent") as avg_time_spent'),
                    DB::raw('COUNT(DISTINCT DATE(created_at)) as active_days'),
                    DB::raw('MAX(created_at) as last_activity')
                )
                ->groupBy('user_id')
                ->get()
                ->map(function ($record) use ($startDate, $endDate) {
                    $daysSinceStart = max($startDate->diffInDays($endDate), 1);
                    return [
                        'user'                     => $record->user->name ?? 'Unknown User',
                        'total_interactions'       => $record->total_interactions,
                        'avg_time_per_session'     => round(($record->avg_time_spent ?? 0) / 60, 2), // minutes
                        'engagement_rate'          => round(($record->active_days / $daysSinceStart) * 100, 2),
                        'days_since_last_activity' => Carbon::parse($record->last_activity)->diffInDays(now()),
                    ];
                });

            return $this->sendResponse([
                'engagement_metrics' => $userEngagement,
                'retention_analysis' => $this->calculateRetentionRates($startDate, $endDate),
                'learning_patterns'  => $this->analyzeLearningPatterns($startDate, $endDate),
            ]);
        } catch (Exception $e) {
            Log::error('Error in userEngagement: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return $this->sendError('Failed to retrieve user engagement data: ' . $e->getMessage());
        }
    }

    /**
     * Get detailed content performance metrics with statistical analysis.
     * Provides deeper insights than DashboardController's basic content stats.
     */
    public function contentPerformance(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'content_type' => 'nullable|string|in:learning_paths,units,lessons,exercises',
                'start_date'   => 'nullable|date',
                'end_date'     => 'nullable|date|after_or_equal:start_date',
            ]);

            $startDate = Carbon::parse($request->input('start_date', Carbon::now()->subDays(30)));
            $endDate   = Carbon::parse($request->input('end_date', Carbon::now()));

            if ($startDate->diffInDays($endDate) > 366) {
                return $this->sendError('Date range too large. Maximum range is 366 days.', [], 400);
            }

            $contentType = $request->input('content_type', 'learning_paths');
            $modelClass  = 'App\\Models\\' . ucfirst(Str::singular($contentType));

            if (!class_exists($modelClass)) {
                return $this->sendError('Invalid content type specified.', [], 400);
            }

            $performance = DB::table($contentType)
                ->leftJoin('user_progress', function ($join) use ($contentType, $modelClass) {
                    $join->on($contentType . '.id', '=', 'user_progress.trackable_id')
                        ->where('user_progress.trackable_type', '=', $modelClass);
                })
                ->whereBetween('user_progress.created_at', [$startDate, $endDate])
                ->select(
                    $contentType . '.id',
                    $contentType . '.title',
                    DB::raw('COUNT(DISTINCT user_progress.user_id) as total_users'),
                    DB::raw('COUNT(CASE WHEN user_progress.status = "completed" THEN 1 END) as completions'),
                    DB::raw('AVG(JSON_EXTRACT(user_progress.meta_data, "$.time_spent")) as avg_time_spent'),
                    DB::raw('AVG(JSON_EXTRACT(user_progress.meta_data, "$.score")) as avg_score')
                )
                ->groupBy($contentType . '.id', $contentType . '.title')
                ->get()
                ->map(function ($record) {
                    return [
                        'id'               => $record->id,
                        'title'            => $record->title ?? 'Untitled',
                        'total_users'      => $record->total_users ?? 0,
                        'completion_rate'  => $record->total_users > 0 ?
                            round(($record->completions / $record->total_users) * 100, 2) : 0,
                        'avg_time_spent'   => round(($record->avg_time_spent ?? 0) / 60, 2), // minutes
                        'avg_score'        => round($record->avg_score ?? 0, 2),
                        'engagement_level' => $this->calculateEngagementLevel($record),
                    ];
                });

            return $this->sendResponse([
                'content_performance' => $performance,
                'difficulty_analysis' => $this->analyzeDifficulty($contentType, $startDate, $endDate),
                'progression_paths'   => $this->analyzeProgressionPaths($contentType),
            ]);
        } catch (Exception $e) {
            Log::error('Error in contentPerformance: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return $this->sendError('Failed to retrieve content performance data: ' . $e->getMessage());
        }
    }

    /**
     * Get comprehensive learning progress analytics.
     * Provides more detailed progress tracking than DashboardController.
     */
    public function learningProgress(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'learning_path_id' => 'nullable|exists:learning_paths,id',
                'user_group'       => 'nullable|string',
            ]);

            $query = UserProgress::with(['user', 'trackable'])
                ->when($request->learning_path_id, function ($q) use ($request) {
                    $q->whereHasMorph('trackable', [LearningPath::class], function ($q) use ($request) {
                        $q->where('id', $request->learning_path_id);
                    });
                });

            $userProgressRecords = $query->get();

            if ($userProgressRecords->isEmpty()) {
                return $this->sendResponse([
                    'progress_metrics' => [],
                    'skill_progression' => [],
                    'learning_paths_efficiency' => [],
                    'message' => 'No progress data available for the specified criteria.'
                ]);
            }

            $progress = $userProgressRecords->groupBy('user_id')->map(function ($userProgress) {
                $user = $userProgress->first()->user;
                if (!$user) {
                    return null;
                }

                $totalItems = $userProgress->count();
                if ($totalItems === 0) {
                    return null;
                }

                return [
                    'user_id'           => $user->id,
                    'name'              => $user->name,
                    'completed_items'   => $userProgress->where('status', 'completed')->count(),
                    'total_items'       => $totalItems,
                    'completion_rate'   => round(
                        ($userProgress->where('status', 'completed')->count() / $totalItems) * 100,
                        2
                    ),
                    'avg_score'         => round($userProgress->avg('meta_data.score') ?? 0, 2),
                    'total_time_spent'  => round(($userProgress->sum('meta_data.time_spent') ?? 0) / 3600, 2), // hours
                    'learning_velocity' => $this->calculateLearningVelocity($userProgress),
                ];
            })->filter()->values();

            return $this->sendResponse([
                'progress_metrics'          => $progress,
                'skill_progression'         => $this->analyzeSkillProgression(),
                'learning_paths_efficiency' => $this->analyzeLearningPathsEfficiency(),
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->sendError('Learning path not found.', [], 404);
        } catch (Exception $e) {
            Log::error('Error in learningProgress: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return $this->sendError('Failed to retrieve learning progress data: ' . $e->getMessage());
        }
    }

    // Rest of your methods with error handling added

    /**
     * Calculate retention rates with cohort analysis
     */
    private function calculateRetentionRates(Carbon $startDate, Carbon $endDate): array
    {
        try {
            $cohorts = User::whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    DB::raw('DATE_FORMAT(created_at, "%Y-%m") as cohort'),
                    DB::raw('COUNT(*) as total_users')
                )
                ->groupBy('cohort')
                ->get();

            if ($cohorts->isEmpty()) {
                return [];
            }

            foreach ($cohorts as &$cohort) {
                $retentionByWeek = [];
                $cohortDate      = Carbon::createFromFormat('Y-m', $cohort->cohort);

                for ($week = 1; $week <= 8; $week++) {
                    $weekStart = $cohortDate->copy()->addWeeks($week - 1);
                    $weekEnd   = $weekStart->copy()->addWeek();

                    $activeUsers = UserProgress::whereHas('user', function ($query) use ($cohort) {
                        $query->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$cohort->cohort]);
                    })
                        ->whereBetween('created_at', [$weekStart, $weekEnd])
                        ->distinct('user_id')
                        ->count();

                    $retentionByWeek["week_{$week}"] = [
                        'active_users'   => $activeUsers,
                        'retention_rate' => $cohort->total_users > 0 ?
                            round(($activeUsers / $cohort->total_users) * 100, 2) : 0,
                    ];
                }

                $cohort->retention = $retentionByWeek;
            }

            return $cohorts->toArray();
        } catch (Exception $e) {
            Log::error('Error calculating retention rates: ' . $e->getMessage());
            return [];
        }
    }

    // Continue adding similar error handling to other private methods
}
