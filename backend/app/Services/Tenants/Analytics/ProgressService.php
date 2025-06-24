<?php

namespace App\Services\Tenants\Analytics;

use App\Models\Tenants\Progress;
use App\Models\Tenants\UserProgress;
use App\Models\Tenants\LearningPath;
use App\Models\Tenants\Unit;
use App\Models\Tenants\Topic;
use App\Models\Tenants\Lesson;
use App\Models\Tenants\User;
use App\Models\Tenants\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ProgressService
{
    public const AUDIT_AREA = 'progress_analytics';

    /**
     * Get overall progress overview for team member's content.
     */
    public function getTeamProgressOverview(int $userId, Request $request): array
    {
        $overview = [
            'content_stats' => $this->getContentCreationStats($userId),
            'engagement_stats' => $this->getEngagementStats($userId),
            'performance_metrics' => $this->getPerformanceMetrics($userId),
            'recent_activity' => $this->getRecentActivity($userId, 10)
        ];

        $this->createAuditLog('progress_overview_viewed', null, [
            'user_id' => $userId,
            'context' => 'team_overview'
        ]);

        return $overview;
    }

    /**
     * Get progress on content created by team member.
     */
    public function getMyContentProgress(int $userId, Request $request): LengthAwarePaginator
    {
        $perPage = min($request->get('per_page', 15), 100);
        $contentType = $request->get('content_type');
        $status = $request->get('status');

        // Build query for content based on audit logs to determine creator
        $createdContentIds = AuditLog::where('area', 'like', '%learning_paths%')
            ->orWhere('area', 'like', '%lessons%')
            ->orWhere('area', 'like', '%topics%')
            ->orWhere('area', 'like', '%units%')
            ->where('user_id', $userId)
            ->where('action', 'created')
            ->pluck('record_id')
            ->unique();

        $query = Progress::query()
            ->whereIn('content_id', $createdContentIds)
            ->with(['user', 'content'])
            ->orderBy('updated_at', 'desc');

        if ($contentType) {
            $query->where('content_type', $contentType);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $results = $query->paginate($perPage);

        $this->createAuditLog('content_progress_viewed', null, [
            'user_id' => $userId,
            'filters' => $request->only(['content_type', 'status']),
            'results_count' => $results->count()
        ]);

        return $results;
    }

    /**
     * Get students' progress on team member's content.
     */
    public function getStudentsProgressOnMyContent(int $userId, Request $request): LengthAwarePaginator
    {
        $perPage = min($request->get('per_page', 15), 100);
        $studentId = $request->get('student_id');
        $contentType = $request->get('content_type');

        // Get content created by this team member
        $createdContentIds = AuditLog::where('area', 'like', '%learning_paths%')
            ->orWhere('area', 'like', '%lessons%')
            ->orWhere('area', 'like', '%topics%')
            ->orWhere('area', 'like', '%units%')
            ->where('user_id', $userId)
            ->where('action', 'created')
            ->pluck('record_id')
            ->unique();

        $query = Progress::query()
            ->whereIn('content_id', $createdContentIds)
            ->with(['user:id,name,email', 'content'])
            ->orderBy('updated_at', 'desc');

        if ($studentId) {
            $query->where('user_id', $studentId);
        }

        if ($contentType) {
            $query->where('content_type', $contentType);
        }

        $results = $query->paginate($perPage);

        // Add aggregated statistics
        $results->appends([
            'total_students' => Progress::whereIn('content_id', $createdContentIds)
                ->distinct('user_id')->count('user_id'),
            'completion_rate' => $this->calculateCompletionRate($createdContentIds),
            'average_progress' => Progress::whereIn('content_id', $createdContentIds)
                ->avg('progress_percentage')
        ]);

        $this->createAuditLog('students_progress_viewed', null, [
            'user_id' => $userId,
            'filters' => $request->only(['student_id', 'content_type']),
            'results_count' => $results->count()
        ]);

        return $results;
    }

    /**
     * Get detailed progress for specific content item.
     */
    public function getContentProgressDetails(int $userId, string $contentType, int $contentId, Request $request): array
    {
        // Verify the content belongs to this team member
        $isOwner = AuditLog::where('area', 'like', "%{$contentType}%")
            ->where('user_id', $userId)
            ->where('record_id', $contentId)
            ->where('action', 'created')
            ->exists();

        if (!$isOwner) {
            throw new \Illuminate\Auth\Access\AuthorizationException('You can only view progress for content you created.');
        }

        $progress = Progress::where('content_type', $contentType)
            ->where('content_id', $contentId)
            ->with(['user:id,name,email', 'content'])
            ->get();

        $analytics = [
            'content_info' => $this->getContentInfo($contentType, $contentId),
            'overall_stats' => [
                'total_users' => $progress->count(),
                'completed_users' => $progress->where('status', 'completed')->count(),
                'in_progress_users' => $progress->where('status', 'in_progress')->count(),
                'average_progress' => $progress->avg('progress_percentage'),
                'completion_rate' => $progress->count() > 0 ?
                    ($progress->where('status', 'completed')->count() / $progress->count()) * 100 : 0
            ],
            'progress_distribution' => $this->getProgressDistribution($progress),
            'recent_activity' => $progress->sortByDesc('updated_at')->take(10)->values(),
            'top_performers' => $progress->where('progress_percentage', '>', 80)
                ->sortByDesc('progress_percentage')->take(5)->values()
        ];

        $this->createAuditLog('content_progress_details_viewed', $contentId, [
            'user_id' => $userId,
            'content_type' => $contentType,
            'users_count' => $progress->count()
        ]);

        return $analytics;
    }

    /**
     * Create audit log entry.
     */
    private function createAuditLog(string $action, ?int $recordId, array $metadata = []): void
    {
        AuditLog::create([
            'area' => self::AUDIT_AREA,
            'action' => $action,
            'record_id' => $recordId,
            'user_id' => Auth::id(),
            'metadata' => $metadata
        ]);
    }

    /**
     * Get content creation statistics for a team member.
     */
    private function getContentCreationStats(int $userId): array
    {
        $auditCounts = AuditLog::where('user_id', $userId)
            ->where('action', 'created')
            ->select('area', DB::raw('count(*) as count'))
            ->groupBy('area')
            ->pluck('count', 'area')
            ->toArray();

        return [
            'learning_paths' => $auditCounts['learning_paths'] ?? 0,
            'units' => $auditCounts['units'] ?? 0,
            'topics' => $auditCounts['topics'] ?? 0,
            'lessons' => $auditCounts['lessons'] ?? 0,
            'total_content' => array_sum($auditCounts)
        ];
    }

    /**
     * Get engagement statistics for content created by team member.
     */
    private function getEngagementStats(int $userId): array
    {
        $createdContentIds = AuditLog::where('user_id', $userId)
            ->where('action', 'created')
            ->pluck('record_id')
            ->unique();

        if ($createdContentIds->isEmpty()) {
            return [
                'total_engagements' => 0,
                'unique_users' => 0,
                'average_session_time' => 0,
                'engagement_rate' => 0
            ];
        }

        $progressRecords = Progress::whereIn('content_id', $createdContentIds)->get();

        return [
            'total_engagements' => $progressRecords->count(),
            'unique_users' => $progressRecords->unique('user_id')->count(),
            'average_session_time' => $progressRecords->avg('last_position') ?? 0,
            'engagement_rate' => $progressRecords->where('progress_percentage', '>', 10)->count()
        ];
    }

    /**
     * Get performance metrics for team member's content.
     */
    private function getPerformanceMetrics(int $userId): array
    {
        $createdContentIds = AuditLog::where('user_id', $userId)
            ->where('action', 'created')
            ->pluck('record_id')
            ->unique();

        if ($createdContentIds->isEmpty()) {
            return [
                'completion_rate' => 0,
                'average_progress' => 0,
                'time_to_completion' => 0,
                'student_satisfaction' => 0
            ];
        }

        $progressRecords = Progress::whereIn('content_id', $createdContentIds)->get();
        $completedRecords = $progressRecords->where('status', 'completed');

        return [
            'completion_rate' => $progressRecords->count() > 0 ?
                ($completedRecords->count() / $progressRecords->count()) * 100 : 0,
            'average_progress' => $progressRecords->avg('progress_percentage') ?? 0,
            'time_to_completion' => $this->calculateAverageCompletionTime($completedRecords),
            'student_satisfaction' => $this->calculateSatisfactionScore($createdContentIds)
        ];
    }

    /**
     * Get recent activity on team member's content.
     */
    private function getRecentActivity(int $userId, int $limit = 10): Collection
    {
        $createdContentIds = AuditLog::where('user_id', $userId)
            ->where('action', 'created')
            ->pluck('record_id')
            ->unique();

        return Progress::whereIn('content_id', $createdContentIds)
            ->with(['user:id,name', 'content'])
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Calculate completion rate for given content IDs.
     */
    private function calculateCompletionRate(Collection $contentIds): float
    {
        if ($contentIds->isEmpty()) {
            return 0;
        }

        $totalProgress = Progress::whereIn('content_id', $contentIds)->count();
        $completedProgress = Progress::whereIn('content_id', $contentIds)
            ->where('status', 'completed')->count();

        return $totalProgress > 0 ? ($completedProgress / $totalProgress) * 100 : 0;
    }

    /**
     * Get progress distribution (ranges).
     */
    private function getProgressDistribution(Collection $progress): array
    {
        $distribution = [
            '0-25%' => 0,
            '26-50%' => 0,
            '51-75%' => 0,
            '76-100%' => 0
        ];

        foreach ($progress as $p) {
            $percentage = $p->progress_percentage;
            if ($percentage <= 25) {
                $distribution['0-25%']++;
            } elseif ($percentage <= 50) {
                $distribution['26-50%']++;
            } elseif ($percentage <= 75) {
                $distribution['51-75%']++;
            } else {
                $distribution['76-100%']++;
            }
        }

        return $distribution;
    }

    /**
     * Get content information by type and ID.
     */
    private function getContentInfo(string $contentType, int $contentId): ?array
    {
        $model = match ($contentType) {
            'App\\Models\\Tenants\\LearningPath' => LearningPath::find($contentId),
            'App\\Models\\Tenants\\Unit' => Unit::find($contentId),
            'App\\Models\\Tenants\\Topic' => Topic::find($contentId),
            'App\\Models\\Tenants\\Lesson' => Lesson::find($contentId),
            default => null
        };

        if (!$model) {
            return null;
        }

        return [
            'id' => $model->id,
            'title' => $model->title ?? $model->name ?? 'Unknown',
            'type' => class_basename($contentType),
            'status' => $model->status ?? 'unknown',
            'created_at' => $model->created_at,
            'updated_at' => $model->updated_at
        ];
    }

    /**
     * Calculate average completion time.
     */
    private function calculateAverageCompletionTime(Collection $completedRecords): float
    {
        if ($completedRecords->isEmpty()) {
            return 0;
        }

        $totalTime = 0;
        $count = 0;

        foreach ($completedRecords as $record) {
            if ($record->completed_at && $record->created_at) {
                $totalTime += $record->completed_at->diffInMinutes($record->created_at);
                $count++;
            }
        }

        return $count > 0 ? $totalTime / $count : 0;
    }

    /**
     * Calculate satisfaction score based on reviews/ratings.
     */
    private function calculateSatisfactionScore(Collection $contentIds): float
    {
        // This would integrate with a review/rating system
        // For now, return a placeholder based on completion rates
        $completionRate = $this->calculateCompletionRate($contentIds);
        return min($completionRate * 0.8, 100); // Rough approximation
    }
}
