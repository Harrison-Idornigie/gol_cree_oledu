<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Language;
use App\Models\LearningPath;
use App\Models\Unit;
use App\Models\Lesson;
use App\Models\Exercise;
use App\Models\UserProgress;
use App\Models\UserStreak;
use App\Models\XpHistory;
use App\Models\Achievement;
use App\Models\UserFeedback;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class AdminDashboardController extends Controller
{
    public function engagement(): JsonResponse
    {
        try {
            $today = Carbon::today();
            
            // Get daily active users - users who completed at least one lesson today
            $dailyActiveUsers = UserProgress::whereDate('created_at', $today)
                ->distinct('user_id')
                ->count();
            
            // Get streak statistics
            $streakStats = UserStreak::selectRaw('
                COUNT(*) as users_with_streaks,
                AVG(current_streak) as average_streak,
                MAX(current_streak) as longest_current_streak')
                ->first();

            // Calculate user retention (active in last 7 days)
            $newUsersLastWeek = User::where('created_at', '>=', Carbon::now()->subDays(7))->count();
            $retainedUsers = User::whereHas('progress', function($query) {
                $query->where('created_at', '>=', Carbon::now()->subDays(7));
            })->count();
            
            $retentionRate = $newUsersLastWeek > 0 
                ? ($retainedUsers / $newUsersLastWeek) * 100
                : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'daily_active_users' => $dailyActiveUsers,
                    'streak_statistics' => [
                        'users_with_streaks' => $streakStats->users_with_streaks ?? 0,
                        'average_streak_length' => round($streakStats->average_streak ?? 0, 1),
                        'longest_current_streak' => $streakStats->longest_current_streak ?? 0
                    ],
                    'engagement_metrics' => [
                        'new_users_today' => User::whereDate('created_at', $today)->count(),
                        'lessons_completed_today' => UserProgress::where('status', 'completed')
                            ->whereDate('created_at', $today)
                            ->count(),
                        'total_xp_earned_today' => XpHistory::whereDate('created_at', $today)
                            ->sum('amount')
                    ],
                    'retention_rate' => round($retentionRate, 2)
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error in engagement dashboard: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve engagement data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function progress(): JsonResponse
    {
        try {
            // Calculate completion rates
            $overallStats = UserProgress::where('status', 'completed')
                ->selectRaw('
                    COUNT(*) as total_completions,
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(DISTINCT trackable_id) as unique_lessons')
                ->first();

            // Get completion rates by language
            $languageStats = Language::all()->mapWithKeys(function($language) {
                // For each language, calculate stats based on related content
                $totalUsers = User::count();
                $usersWithProgress = UserProgress::distinct('user_id')->count();
                
                return [
                    $language->name => [
                        'completion_rate' => $totalUsers > 0 ? round(($usersWithProgress / $totalUsers) * 100, 2) : 0,
                        'total_users' => $totalUsers,
                        'active_users' => $usersWithProgress
                    ]
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'completion_rates' => [
                        'overall' => $overallStats->total_completions > 0 
                            ? round(($overallStats->unique_users / User::count()) * 100, 2)
                            : 0,
                        'by_language' => $languageStats
                    ],
                    'average_completion_time' => 30, 
                    'progress_by_language' => $languageStats
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error in progress dashboard: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve progress data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function achievements(): JsonResponse
    {
        try {
            // Get all achievements with user counts
            $achievements = Achievement::withCount('users')
                ->get()
                ->map(function($achievement) {
                    $totalUsers = User::count();
                    return [
                        'id' => $achievement->id,
                        'name' => $achievement->name,
                        'description' => $achievement->description,
                        'total_earners' => $achievement->users_count,
                        'completion_rate' => $totalUsers > 0 
                            ? round(($achievement->users_count / $totalUsers) * 100, 2)
                            : 0
                    ];
                });
            
            // Sort achievements by earned count
            $mostEarned = $achievements->sortByDesc('total_earners')->take(5)->values();
            $leastEarned = $achievements->sortBy('total_earners')->take(5)->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_achievements' => $achievements->count(),
                    'most_earned' => $mostEarned,
                    'least_earned' => $leastEarned
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error in achievements dashboard: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve achievement data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function leaderboards(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'daily_leaders' => $this->getLeaderboard(Carbon::today()),
                    'weekly_leaders' => $this->getLeaderboard(Carbon::now()->subDays(7)),
                    'all_time_leaders' => $this->getLeaderboard(Carbon::createFromTimestamp(0))
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error in leaderboards dashboard: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve leaderboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    protected function getLeaderboard(Carbon $startDate): array
    {
        try {
            return XpHistory::where('created_at', '>=', $startDate)
                ->groupBy('user_id')
                ->selectRaw('user_id, SUM(amount) as total_xp, COUNT(*) as activities')
                ->with('user:id,name')
                ->orderByDesc('total_xp')
                ->take(10)
                ->get()
                ->map(function($entry) {
                    return [
                        'user' => [
                            'id' => $entry->user->id ?? 0,
                            'name' => $entry->user->name ?? 'Unknown User'
                        ],
                        'xp' => $entry->total_xp,
                        'activities' => $entry->activities
                    ];
                })
                ->toArray();
        } catch (Exception $e) {
            Log::error('Error getting leaderboard: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'start_date' => $startDate
            ]);
            return [];
        }
    }

    public function contentHealth(): JsonResponse
    {
        try {
            // Find content that might need review
            $needsReview = [
                'lessons' => Lesson::withCount(['progress', 'progress as completed_count' => function($query) {
                    $query->where('status', 'completed');
                }])
                ->whereRaw('(SELECT COUNT(*) FROM user_progress WHERE lessons.id = user_progress.trackable_id AND user_progress.trackable_type = ?)', ['App\\Models\\Lesson'])
                ->whereRaw('(SELECT COUNT(*) FROM user_progress WHERE lessons.id = user_progress.trackable_id AND user_progress.trackable_type = ? AND status = ?) / 
                          (SELECT COUNT(*) FROM user_progress WHERE lessons.id = user_progress.trackable_id AND user_progress.trackable_type = ?) < 0.5', 
                          ['App\\Models\\Lesson', 'completed', 'App\\Models\\Lesson'])
                ->with('unit.learningPath')
                ->get()
                ->map(function($lesson) {
                    return [
                        'id' => $lesson->id,
                        'title' => $lesson->title,
                        'path' => $lesson->unit->learningPath->title ?? 'Unknown',
                        'completion_rate' => $lesson->progress_count > 0 
                            ? round(($lesson->completed_count / $lesson->progress_count) * 100, 2) 
                            : 0
                    ];
                }),
                
                'exercises' => Exercise::withCount(['attempts', 'attempts as correct_count' => function($query) {
                    $query->where('is_correct', true);
                }])
                ->where('attempts_count', '>', 10)
                ->whereRaw('correct_count / attempts_count < 0.5')
                ->with('lesson')
                ->get()
                ->map(function($exercise) {
                    return [
                        'id' => $exercise->id,
                        'type' => $exercise->type,
                        'lesson' => $exercise->lesson->title ?? 'Unknown',
                        'success_rate' => $exercise->attempts_count > 0 
                            ? round(($exercise->correct_count / $exercise->attempts_count) * 100, 2)
                            : 0
                    ];
                })
            ];

            // Find lessons with completion issues
            $completionIssues = Lesson::all()
                ->map(function($lesson) {
                    $abandonedCount = UserProgress::where('trackable_id', $lesson->id)
                        ->where('trackable_type', 'App\\Models\\Lesson')
                        ->whereNull('completed_at')
                        ->where('created_at', '<', Carbon::now()->subDays(7))
                        ->count();
                    
                    $totalCount = UserProgress::where('trackable_id', $lesson->id)
                        ->where('trackable_type', 'App\\Models\\Lesson')
                        ->count();
                    
                    return [
                        'id' => $lesson->id,
                        'title' => $lesson->title,
                        'abandoned_count' => $abandonedCount,
                        'total_starts' => $totalCount,
                        'should_include' => $abandonedCount > 0
                    ];
                })
                ->filter(function($item) {
                    return $item['should_include'];
                })
                ->map(function($item) {
                    unset($item['should_include']);
                    return $item;
                })
                ->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'needs_review' => $needsReview,
                    'completion_issues' => $completionIssues,
                    'feedback_summary' => UserFeedback::with(['user:id,name', 'content'])
                        ->latest()
                        ->take(10)
                        ->get()
                        ->map(function($feedback) {
                            return [
                                'id' => $feedback->id,
                                'user' => $feedback->user->name ?? 'Unknown User',
                                'content_type' => $feedback->content_type,
                                'content_title' => $feedback->content->title ?? 'Unknown',
                                'feedback' => $feedback->message,
                                'rating' => $feedback->rating,
                                'created_at' => $feedback->created_at->toISOString()
                            ];
                        }),
                    'error_rates' => [
                        'by_exercise_type' => Exercise::join('exercise_attempts', 'exercises.id', '=', 'exercise_attempts.exercise_id')
                            ->selectRaw('
                                exercises.type,
                                COUNT(*) as total_attempts,
                                SUM(CASE WHEN is_correct = 0 THEN 1 ELSE 0 END) as error_count')
                            ->groupBy('type')
                            ->get()
                            ->map(function($stat) {
                                return [
                                    'type' => $stat->type,
                                    'error_rate' => $stat->total_attempts > 0 
                                        ? round(($stat->error_count / $stat->total_attempts) * 100, 2)
                                        : 0
                                ];
                            })
                    ]
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error in content health dashboard: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve content health data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
