<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\User;
use App\Models\LearningPath;
use App\Models\Unit;
use App\Models\Lesson;
use App\Models\UserProgress;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminProgressController extends BaseAPIController
{
    /**
     * Map of content types to their model classes
     */
    protected $contentTypeMap = [
        'learning-paths' => 'App\\Models\\LearningPath',
        'units' => 'App\\Models\\Unit',
        'lessons' => 'App\\Models\\Lesson',
        'sections' => 'App\\Models\\Section',
        'exercises' => 'App\\Models\\Exercise',
     ];

    /**
     * Get an overview of user progress statistics
     */
    public function overview(): JsonResponse
    {
        try {
            // Get total active users
            $totalUsers = User::where('status', 'active')->count();
            
            // Get users who started at least one learning path
            $activeUsers = DB::table('user_progress')
                ->select('user_id')
                ->distinct()
                ->count('user_id');
            
            // Get completion statistics for learning paths
            $learningPathStats = DB::table('user_progress')
                ->select('trackable_type', 'status', DB::raw('count(*) as count'))
                ->where('trackable_type', 'App\\Models\\LearningPath')
                ->groupBy('trackable_type', 'status')
                ->get()
                ->groupBy('status')
                ->map(function ($item) {
                    return $item->first()->count;
                });
            
            // Get completion statistics for units
            $unitStats = DB::table('user_progress')
                ->select('trackable_type', 'status', DB::raw('count(*) as count'))
                ->where('trackable_type', 'App\\Models\\Unit')
                ->groupBy('trackable_type', 'status')
                ->get()
                ->groupBy('status')
                ->map(function ($item) {
                    return $item->first()->count;
                });
            
            // Get completion statistics for lessons
            $lessonStats = DB::table('user_progress')
                ->select('trackable_type', 'status', DB::raw('count(*) as count'))
                ->where('trackable_type', 'App\\Models\\Lesson')
                ->groupBy('trackable_type', 'status')
                ->get()
                ->groupBy('status')
                ->map(function ($item) {
                    return $item->first()->count;
                });
            
            // Get recent activity
            $recentActivity = DB::table('user_progress')
                ->join('users', 'user_progress.user_id', '=', 'users.id')
                ->select('user_progress.*', 'users.name as user_name', 'users.email as user_email')
                ->orderBy('user_progress.updated_at', 'desc')
                ->limit(20)
                ->get();
            
            return $this->sendResponse([
                'user_stats' => [
                    'total_users' => $totalUsers,
                    'active_users' => $activeUsers,
                    'completion_rate' => $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 2) : 0
                ],
                'learning_path_stats' => $learningPathStats,
                'unit_stats' => $unitStats,
                'lesson_stats' => $lessonStats,
                'recent_activity' => $recentActivity
            ]);
        } catch (Exception $e) {
            Log::error('Failed to get progress overview: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to get progress overview: ' . $e->getMessage(), [], 500);
        }
    }
    
    /**
     * Get progress details for a specific user
     */
    public function userProgress(User $user): JsonResponse
    {
        try {
            // Confirm user exists
            if (!$user) {
                return $this->sendError('User not found', [], 404);
            }

            // Get learning paths, units, and lessons with progress
            $learningPaths = LearningPath::with(['progress' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }])->get();
            
            $units = Unit::with(['progress' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }])->get();
            
            $lessons = Lesson::with(['progress' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }])->get();
            
            // Calculate overall completion percentage
            $totalContent = $learningPaths->count() + $units->count() + $lessons->count();
            $completedContent = 0;
            
            foreach ($learningPaths as $path) {
                if ($path->progress->isNotEmpty() && $path->progress->first()->status === UserProgress::STATUS_COMPLETED) {
                    $completedContent++;
                }
            }
            
            foreach ($units as $unit) {
                if ($unit->progress->isNotEmpty() && $unit->progress->first()->status === UserProgress::STATUS_COMPLETED) {
                    $completedContent++;
                }
            }
            
            foreach ($lessons as $lesson) {
                if ($lesson->progress->isNotEmpty() && $lesson->progress->first()->status === UserProgress::STATUS_COMPLETED) {
                    $completedContent++;
                }
            }
            
            $completionPercentage = $totalContent > 0 ? round(($completedContent / $totalContent) * 100) : 0;
            
            // Get recent activity for this user
            $recentActivity = UserProgress::where('user_id', $user->id)
                ->orderBy('updated_at', 'desc')
                ->limit(20)
                ->get();
            
            return $this->sendResponse([
                'user' => $user,
                'completion_percentage' => $completionPercentage,
                'learning_paths' => $learningPaths->map(function ($path) {
                    return [
                        'id' => $path->id,
                        'title' => $path->title,
                        'status' => $path->progress->isNotEmpty() ? $path->progress->first()->status : UserProgress::STATUS_NOT_STARTED,
                        'last_activity' => $path->progress->isNotEmpty() ? $path->progress->first()->updated_at : null
                    ];
                }),
                'units' => $units->map(function ($unit) {
                    return [
                        'id' => $unit->id,
                        'title' => $unit->title,
                        'status' => $unit->progress->isNotEmpty() ? $unit->progress->first()->status : UserProgress::STATUS_NOT_STARTED,
                        'last_activity' => $unit->progress->isNotEmpty() ? $unit->progress->first()->updated_at : null
                    ];
                }),
                'lessons' => $lessons->map(function ($lesson) {
                    return [
                        'id' => $lesson->id,
                        'title' => $lesson->title,
                        'status' => $lesson->progress->isNotEmpty() ? $lesson->progress->first()->status : UserProgress::STATUS_NOT_STARTED,
                        'last_activity' => $lesson->progress->isNotEmpty() ? $lesson->progress->first()->updated_at : null
                    ];
                }),
                'recent_activity' => $recentActivity
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->sendError('User not found', [], 404);
        } catch (Exception $e) {
            Log::error('Failed to get user progress: ' . $e->getMessage(), [
                'user_id' => $user->id ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to get user progress: ' . $e->getMessage(), [], 500);
        }
    }
    
    /**
     * Get progress details for a specific content item
     */
    public function contentProgress(string $type, int $id): JsonResponse
    {
        try {
            if (!array_key_exists($type, $this->contentTypeMap)) {
                return $this->sendError('Invalid content type.', ['status' => 400], 400);
            }
            
            $modelClass = $this->contentTypeMap[$type];
            
            if (!class_exists($modelClass)) {
                return $this->sendError('Invalid model class', ['class' => $modelClass], 400);
            }
            
            $content = $modelClass::findOrFail($id);
            
            // Get all progress records for this content
            $progress = UserProgress::where('trackable_type', get_class($content))
                ->where('trackable_id', $content->id)
                ->with('user')
                ->get();
            
            // Calculate statistics
            $totalUsers = User::where('status', 'active')->count();
            $usersStarted = $progress->count();
            $usersCompleted = $progress->where('status', UserProgress::STATUS_COMPLETED)->count();
            
            // Group progress by status
            $statusCounts = $progress->groupBy('status')->map->count();
            
            // Get average completion time (for completed items)
            $completedProgress = $progress->where('status', UserProgress::STATUS_COMPLETED);
            $averageTimeSpent = $completedProgress->isNotEmpty() 
                ? $completedProgress->avg(function ($item) {
                    return $item->getTimeSpent();
                }) 
                : 0;
            
            // Get average score (if applicable)
            $averageScore = $completedProgress->isNotEmpty() 
                ? $completedProgress->avg(function ($item) {
                    return $item->getBestScore() ?? 0;
                }) 
                : 0;
            
            // Get recent activity
            $recentActivity = $progress->sortByDesc('updated_at')->take(10);
            
            return $this->sendResponse([
                'content' => $content,
                'total_users' => $totalUsers,
                'users_started' => $usersStarted,
                'users_completed' => $usersCompleted,
                'completion_rate' => $totalUsers > 0 ? round(($usersCompleted / $totalUsers) * 100) : 0,
                'status_breakdown' => $statusCounts,
                'average_time_spent' => round($averageTimeSpent),
                'average_score' => round($averageScore, 1),
                'recent_activity' => $recentActivity->map(function ($item) {
                    return [
                        'user' => $item->user,
                        'status' => $item->status,
                        'completed_at' => $item->completed_at,
                        'updated_at' => $item->updated_at,
                        'summary' => $item->getSummary()
                    ];
                })
            ]);
        } catch (ModelNotFoundException $e) {
            return $this->sendError('Content not found', ['type' => $type, 'id' => $id], 404);
        } catch (Exception $e) {
            Log::error('Failed to get content progress: ' . $e->getMessage(), [
                'type' => $type,
                'id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to get content progress: ' . $e->getMessage(), [], 500);
        }
    }
}