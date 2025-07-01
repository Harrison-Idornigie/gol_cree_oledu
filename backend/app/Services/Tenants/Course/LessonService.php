<?php

namespace App\Services\Tenants\Course;

use App\Models\Tenants\Lesson;
use App\Models\Tenants\Topic;
use App\Models\Tenants\User;
use App\Models\Tenants\UserProgress;
use App\Models\Tenants\Exercise;
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
                ['user_id' => $user->id]
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
                ['user_id' => $user->id]
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
                    ['user_id' => $user->id]
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

        // Prevent publishing content that's under review
        if ($status === 'published' && $lesson->review_status === 'pending') {
            throw new \InvalidArgumentException('Cannot publish content while it is under review. Please wait for review approval.');
        }

        // Require review approval for publishing (except for drafts being published by admins)
        if ($status === 'published' && $lesson->review_status === 'none' && !$user->isTenantAdmin()) {
            throw new \InvalidArgumentException('Content must be reviewed before publishing.');
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
        $progress = UserProgress::where('user_id', $user->id)
            ->where('trackable_type', Lesson::class)
            ->where('trackable_id', $lesson->id)
            ->first();

        $exercisesCompleted = $this->getCompletedExercisesCount($lesson, $user);
        $totalExercises = $lesson->exercises()->count();

        // Check if progress is stored in meta_data, otherwise calculate from exercises
        $progressPercentage = 0;
        if ($progress && isset($progress->meta_data['progress'])) {
            $progressPercentage = $progress->meta_data['progress'];
        } elseif ($progress && isset($progress->meta_data['completion_percentage'])) {
            $progressPercentage = $progress->meta_data['completion_percentage'];
        } elseif ($totalExercises > 0) {
            $progressPercentage = ($exercisesCompleted / $totalExercises) * 100;
        }

        return [
            'completed' => $progress && $progress->status === UserProgress::STATUS_COMPLETED,
            'progress_percentage' => round($progressPercentage, 2),
            'exercises_completed' => $exercisesCompleted,
            'exercises_total' => $totalExercises,
            'last_accessed' => $progress?->updated_at,
            'time_spent' => $progress?->meta_data['time_spent'] ?? 0,
            'status' => $progress?->status ?? UserProgress::STATUS_NOT_STARTED,
            'next_exercise_id' => $this->getNextExerciseId($lesson, $user)
        ];
    }

    /**
     * Get lessons for a topic with student accessibility and progress.
     */
    public function getLessonsForStudent(int $topicId, User $user): Collection
    {
        $lessons = $this->getLessonsByTopic($topicId);

        // Transform the lessons and return as a Collection
        $transformedLessons = $lessons->map(function ($lesson) use ($user) {
            $lessonData = $lesson->toArray();
            $lessonData['progress'] = $this->getLessonProgress($lesson, $user);
            $lessonData['accessible'] = $this->isLessonAccessible($lesson, $user);
            $lessonData['prerequisite_completed'] = $this->arePrerequisitesCompleted($lesson, $user);

            return (object) $lessonData;
        });

        // Convert to Eloquent Collection to match return type
        return new Collection($transformedLessons->all());
    }

    /**
     * Get detailed lesson information for student viewing.
     */
    public function getLessonForStudent(Lesson $lesson, User $user): array
    {
        // Check if lesson is accessible to student
        if (!$this->isLessonAccessible($lesson, $user)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('You must complete previous lessons first');
        }

        $lessonData = $lesson->load([
            'topic.unit.learningPath',
            'exercises' => function ($query) {
                $query->orderBy('order');
            },
            'exercises.attempts' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }
        ])->toArray();

        $lessonData['progress'] = $this->getLessonProgress($lesson, $user);
        $lessonData['exercises'] = $lesson->exercises->map(function ($exercise) use ($user) {
            $exerciseData = $exercise->toArray();
            $exerciseData['completed'] = $this->isExerciseCompleted($exercise, $user);
            $exerciseData['attempts_count'] = $exercise->attempts->count();
            $exerciseData['best_score'] = $this->getBestScore($exercise, $user);
            return $exerciseData;
        });

        // Mark lesson as accessed
        $this->markLessonAccessed($lesson, $user);

        return $lessonData;
    }

    /**
     * Check if lesson is accessible based on sequential learning rules.
     */
    public function isLessonAccessible(Lesson $lesson, User $user): bool
    {
        // Students can only access published lessons
        if ($lesson->status !== 'published') {
            return false;
        }

        // Get previous lesson in the topic
        $previousLesson = Lesson::where('topic_id', $lesson->topic_id)
            ->where('order', '<', $lesson->order)
            ->orderBy('order', 'desc')
            ->first();

        // If no previous lesson, this lesson is accessible
        if (!$previousLesson) {
            return true;
        }

        // Check if previous lesson is completed
        $isPreviousCompleted = $this->isLessonCompleted($previousLesson, $user);

        return $isPreviousCompleted;
    }

    /**
     * Check if lesson prerequisites are completed.
     */
    public function arePrerequisitesCompleted(Lesson $lesson, User $user): bool
    {
        // Check if previous lessons in topic are completed
        $previousLessons = Lesson::where('topic_id', $lesson->topic_id)
            ->where('order', '<', $lesson->order)
            ->get();

        foreach ($previousLessons as $prevLesson) {
            $isCompleted = $this->isLessonCompleted($prevLesson, $user);
            if (!$isCompleted) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if lesson is completed by user.
     */
    public function isLessonCompleted(Lesson $lesson, User $user): bool
    {
        $progress = UserProgress::where('user_id', $user->id)
            ->where('trackable_type', Lesson::class)
            ->where('trackable_id', $lesson->id)
            ->first();

        $isCompleted = $progress && $progress->status === UserProgress::STATUS_COMPLETED;

        return $isCompleted;
    }

    /**
     * Get count of completed exercises in lesson.
     */
    private function getCompletedExercisesCount(Lesson $lesson, User $user): int
    {
        return $lesson->exercises()->whereHas('attempts', function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->where('status', 'correct');
        })->count();
    }

    /**
     * Get next exercise ID for user in lesson.
     */
    private function getNextExerciseId(Lesson $lesson, User $user): ?int
    {
        $nextExercise = $lesson->exercises()
            ->whereDoesntHave('attempts', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->where('status', 'correct');
            })
            ->orderBy('order')
            ->first();

        return $nextExercise?->id;
    }

    /**
     * Check if specific exercise is completed.
     */
    private function isExerciseCompleted(Exercise $exercise, User $user): bool
    {
        return $exercise->attempts()
            ->where('user_id', $user->id)
            ->where('status', 'correct')
            ->exists();
    }

    /**
     * Get best score for exercise.
     */
    private function getBestScore(Exercise $exercise, User $user): ?float
    {
        return $exercise->attempts()
            ->where('user_id', $user->id)
            ->max('score');
    }

    /**
     * Mark lesson as accessed by user.
     */
    private function markLessonAccessed(Lesson $lesson, User $user): void
    {
        UserProgress::updateOrCreate(
            [
                'user_id' => $user->id,
                'trackable_type' => Lesson::class,
                'trackable_id' => $lesson->id,
            ],
            [
                'status' => UserProgress::STATUS_IN_PROGRESS,
                'meta_data' => [
                    'last_accessed' => now(),
                    'access_count' => DB::raw('COALESCE(JSON_EXTRACT(meta_data, "$.access_count"), 0) + 1')
                ]
            ]
        );
    }
}
