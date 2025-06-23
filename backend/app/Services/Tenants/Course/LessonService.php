<?php

namespace App\Services\Tenants\Course;

use App\Models\Tenants\Lesson;
use App\Models\Tenants\Topic;
use App\Models\Tenants\User;
use App\Models\Tenants\AuditLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Lesson Service
 * 
 * Handles CRUD operations and business logic for lessons including:
 * - Lesson creation, updates, and deletion
 * - Lesson ordering and organization
 * - Lesson status management
 * - Lesson analytics and progress tracking
 */
class LessonService
{
    /**
     * Get paginated lessons with filters and relationships.
     */
    public function getLessons(array $filters = [], array $sorts = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Lesson::with(['topic.unit.learningPath', 'exercises', 'template'])
            ->withCount(['exercises']);

        // Apply search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Apply filters
        if (!empty($filters['topic_id'])) {
            $query->where('topic_id', $filters['topic_id']);
        }

        if (!empty($filters['unit_id'])) {
            $query->whereHas('topic', function ($q) use ($filters) {
                $q->where('unit_id', $filters['unit_id']);
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        // Apply sorting
        foreach ($sorts as $field => $direction) {
            if (in_array($field, ['title', 'order', 'status', 'created_at'])) {
                $query->orderBy($field, $direction);
            }
        }

        // Default sorting
        if (empty($sorts)) {
            $query->orderBy('order')->orderBy('title');
        }

        return $query->paginate($perPage);
    }

    /**
     * Get lessons for a specific topic.
     */
    public function getLessonsByTopic(int $topicId, bool $includeStats = false): Collection
    {
        $query = Lesson::where('topic_id', $topicId)
            ->with(['exercises', 'template'])
            ->withCount(['exercises']);

        if ($includeStats) {
            $query->with(['exercises.attempts']);
        }

        return $query->orderBy('order')->orderBy('title')->get();
    }

    /**
     * Create a new lesson with validation and audit logging.
     */
    public function createLesson(array $data, User $user): Lesson
    {
        return DB::transaction(function () use ($data, $user) {
            // Validate topic exists and user has access
            $topic = Topic::findOrFail($data['topic_id']);
            
            // Set order if not provided
            if (empty($data['order'])) {
                $data['order'] = $this->getNextOrderForTopic($data['topic_id']);
            }

            // Set defaults
            $data['tenant_id'] = tenant('id');
            $data['status'] = $data['status'] ?? 'draft';

            $lesson = Lesson::create($data);

            // Log the creation for audit trail
            AuditLog::log(
                'create',
                'lessons',
                $lesson,
                [],
                $data,
                $user->id
            );

            Log::info('Lesson created via service', [
                'lesson_id' => $lesson->id,
                'topic_id' => $lesson->topic_id,
                'title' => $lesson->title,
                'created_by' => $user->id,
                'tenant_id' => tenant('id')
            ]);

            return $lesson->load(['topic', 'exercises']);
        });
    }

    /**
     * Update an existing lesson with audit logging.
     */
    public function updateLesson(Lesson $lesson, array $data, User $user): Lesson
    {
        return DB::transaction(function () use ($lesson, $data, $user) {
            $originalData = $lesson->toArray();

            $lesson->update($data);

            // Log the update for audit trail
            AuditLog::log(
                'update',
                'lessons',
                $lesson,
                $originalData,
                $data,
                $user->id
            );

            Log::info('Lesson updated via service', [
                'lesson_id' => $lesson->id,
                'changes' => array_keys($data),
                'updated_by' => $user->id,
                'tenant_id' => tenant('id')
            ]);

            return $lesson->fresh(['topic', 'exercises']);
        });
    }

    /**
     * Delete a lesson with validation and audit logging.
     */
    public function deleteLesson(Lesson $lesson, User $user): bool
    {
        return DB::transaction(function () use ($lesson, $user) {
            // Check if lesson has exercises
            if ($lesson->exercises()->count() > 0) {
                throw new Exception('Cannot delete lesson with existing exercises. Please delete exercises first.');
            }

            $lessonData = $lesson->toArray();

            // Reorder remaining lessons
            $this->reorderLessonsAfterDeletion($lesson->topic_id, $lesson->order);

            $deleted = $lesson->delete();

            if ($deleted) {
                // Log the deletion for audit trail
                AuditLog::log(
                    'delete',
                    'lessons',
                    null,
                    $lessonData,
                    [],
                    $user->id
                );

                Log::info('Lesson deleted via service', [
                    'lesson_id' => $lesson->id,
                    'title' => $lesson->title,
                    'deleted_by' => $user->id,
                    'tenant_id' => tenant('id')
                ]);
            }

            return $deleted;
        });
    }

    /**
     * Reorder lessons within a topic.
     */
    public function reorderLessons(int $topicId, array $lessonOrders, User $user): bool
    {
        return DB::transaction(function () use ($topicId, $lessonOrders, $user) {
            foreach ($lessonOrders as $lessonId => $order) {
                Lesson::where('id', $lessonId)
                    ->where('topic_id', $topicId)
                    ->update(['order' => $order]);
            }

            Log::info('Lessons reordered via service', [
                'topic_id' => $topicId,
                'lesson_count' => count($lessonOrders),
                'reordered_by' => $user->id,
                'tenant_id' => tenant('id')
            ]);

            return true;
        });
    }

    /**
     * Update lesson status with validation.
     */
    public function updateLessonStatus(Lesson $lesson, string $status, User $user): Lesson
    {
        $validStatuses = ['draft', 'published', 'archived'];
        
        if (!in_array($status, $validStatuses)) {
            throw new Exception("Invalid status. Must be one of: " . implode(', ', $validStatuses));
        }

        return $this->updateLesson($lesson, ['status' => $status], $user);
    }

    /**
     * Duplicate a lesson with all its exercises.
     */
    public function duplicateLesson(Lesson $lesson, array $overrides, User $user): Lesson
    {
        return DB::transaction(function () use ($lesson, $overrides, $user) {
            $lessonData = $lesson->toArray();
            
            // Remove ID and timestamps
            unset($lessonData['id'], $lessonData['created_at'], $lessonData['updated_at']);
            
            // Apply overrides
            $lessonData = array_merge($lessonData, $overrides);
            
            // Set new order if not specified
            if (!isset($overrides['order'])) {
                $lessonData['order'] = $this->getNextOrderForTopic($lessonData['topic_id']);
            }

            // Create new lesson
            $newLesson = $this->createLesson($lessonData, $user);

            // TODO: Duplicate exercises if needed
            // This would require ExerciseService to be implemented first

            Log::info('Lesson duplicated via service', [
                'original_lesson_id' => $lesson->id,
                'new_lesson_id' => $newLesson->id,
                'duplicated_by' => $user->id,
                'tenant_id' => tenant('id')
            ]);

            return $newLesson;
        });
    }

    /**
     * Get next order number for topic.
     */
    private function getNextOrderForTopic(int $topicId): int
    {
        return Lesson::where('topic_id', $topicId)->max('order') + 1;
    }

    /**
     * Reorder lessons after deletion.
     */
    private function reorderLessonsAfterDeletion(int $topicId, int $deletedOrder): void
    {
        Lesson::where('topic_id', $topicId)
            ->where('order', '>', $deletedOrder)
            ->decrement('order');
    }

    /**
     * Get lesson statistics.
     */
    public function getLessonStats(Lesson $lesson): array
    {
        $lesson->load(['exercises.attempts']);

        return [
            'exercises_count' => $lesson->exercises->count(),
            'total_attempts' => $lesson->exercises->sum(function ($exercise) {
                return $exercise->attempts->count();
            }),
            'status' => $lesson->status,
            'completion_rate' => 0, // TODO: Calculate based on user progress
            'average_score' => 0, // TODO: Calculate based on exercise attempts
            'difficulty_level' => 0, // TODO: Calculate based on exercises
        ];
    }

    /**
     * Get lesson progress for a specific user.
     */
    public function getLessonProgress(Lesson $lesson, User $user): array
    {
        // TODO: Implement user progress tracking
        return [
            'completed' => false,
            'progress_percentage' => 0,
            'exercises_completed' => 0,
            'exercises_total' => $lesson->exercises->count(),
            'last_accessed' => null,
            'time_spent' => 0,
        ];
    }
}
