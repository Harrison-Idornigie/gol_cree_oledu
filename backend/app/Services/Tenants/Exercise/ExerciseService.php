<?php

namespace App\Services\Tenants\Exercise;

use App\Models\Tenants\Exercise;
use App\Models\Tenants\ExerciseAttempt;
use App\Models\Tenants\Lesson;
use App\Models\Tenants\User;
use App\Models\Tenants\UserProgress;
use App\Models\Tenants\AuditLog;
use App\Services\Tenants\Exercise\ExerciseTypeService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

/**
 * Exercise Service
 * 
 * Handles CRUD operations and business logic for exercises including:
 * - Exercise creation, updates, and deletion
 * - Exercise ordering and organization
 * - Exercise status management
 * - Exercise validation and content management
 */
class ExerciseService
{
    protected ExerciseTypeService $exerciseTypeService;

    public function __construct(ExerciseTypeService $exerciseTypeService)
    {
        $this->exerciseTypeService = $exerciseTypeService;
    }

    /**
     * Get a single exercise with relationships.
     */
    public function getExercise(int $exerciseId, array $with = []): ?Exercise
    {
        $defaultWith = ['lesson.topic.unit', 'template'];
        $with = array_merge($defaultWith, $with);

        return Exercise::with($with)
            ->withCount(['attempts'])
            ->find($exerciseId);
    }

    /**
     * Get paginated exercises with filters and relationships.
     */
    public function getExercises(array $filters = [], array $sorts = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Exercise::with(['lesson.topic.unit', 'template', 'attempts'])
            ->withCount(['attempts']);

        // Apply search,
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('slug', 'LIKE', "%{$search}%");
            });
        }

        // Apply filters
        if (!empty($filters['lesson_id'])) {
            $query->where('lesson_id', $filters['lesson_id']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['purpose'])) {
            $query->where('purpose', $filters['purpose']);
        }

        if (!empty($filters['difficulty_level'])) {
            $query->where('difficulty_level', $filters['difficulty_level']);
        }

        // Apply sorting
        foreach ($sorts as $field => $direction) {
            if (in_array($field, ['title', 'order', 'status', 'created_at', 'difficulty_level'])) {
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
     * Get exercises for a specific lesson.
     */
    public function getExercisesByLesson(int $lessonId, bool $includeStats = false): Collection
    {
        $query = Exercise::where('lesson_id', $lessonId)
            ->with(['template'])
            ->withCount(['attempts']);

        if ($includeStats) {
            $query->with(['attempts.user']);
        }

        return $query->orderBy('order')->orderBy('title')->get();
    }

    /**
     * Create a new exercise with validation and audit logging.
     */
    public function createExercise(array $data, User $user): Exercise
    {
        return DB::transaction(function () use ($data, $user) {
            // Validate lesson exists and user has access
            $lesson = Lesson::findOrFail($data['lesson_id']);

            // Generate slug if not provided
            if (empty($data['slug'])) {
                $data['slug'] = $this->generateUniqueSlug($data['title']);
            }

            // Set order if not provided
            if (empty($data['order'])) {
                $data['order'] = $this->getNextOrderForLesson($data['lesson_id']);
            }

            // Validate exercise content structure
            if (!empty($data['content'])) {
                $this->validateExerciseContent($data['type'], $data['content']);
            }

            // Set defaults
            $data['tenant_id'] = tenant('id');
            $data['status'] = $data['status'] ?? 'draft';
            $data['purpose'] = $data['purpose'] ?? 'practice';
            $data['difficulty_level'] = $data['difficulty_level'] ?? 1;
            $data['passing_score'] = $data['passing_score'] ?? 70;
            $data['max_attempts'] = $data['max_attempts'] ?? 3;
            $data['show_feedback'] = $data['show_feedback'] ?? true;
            $data['show_hints'] = $data['show_hints'] ?? true;
            $data['xp_reward'] = $data['xp_reward'] ?? 10;

            // Add audit fields
            $data['created_by'] = $user->id;
            $data['updated_by'] = $user->id;

            $exercise = Exercise::create($data);

            // Log the creation for audit trail
            AuditLog::log(
                'create',
                'exercises',
                $exercise,
                [],
                $data,
                ['user_id' => $user->id]
            );

            Log::info('Exercise created via service', [
                'exercise_id' => $exercise->id,
                'lesson_id' => $exercise->lesson_id,
                'type' => $exercise->type,
                'title' => $exercise->title,
                'created_by' => $user->id,
                'tenant_id' => tenant('id')
            ]);

            return $exercise->load(['lesson', 'template']);
        });
    }

    /**
     * Update an existing exercise with audit logging.
     */
    public function updateExercise(Exercise $exercise, array $data, User $user): Exercise
    {
        return DB::transaction(function () use ($exercise, $data, $user) {
            $originalData = $exercise->toArray();

            // Update slug if title changed
            if (isset($data['title']) && $data['title'] !== $exercise->title) {
                $data['slug'] = $this->generateUniqueSlug($data['title'], $exercise->id);
            }

            // Validate exercise content if being updated
            if (isset($data['content']) && isset($data['type'])) {
                $this->validateExerciseContent($data['type'], $data['content']);
            } elseif (isset($data['content'])) {
                $this->validateExerciseContent($exercise->type, $data['content']);
            }

            // Add audit field
            $data['updated_by'] = $user->id;

            $exercise->update($data);

            // Log the update for audit trail
            AuditLog::log(
                'update',
                'exercises',
                $exercise,
                $originalData,
                $data,
                ['user_id' => $user->id]
            );

            Log::info('Exercise updated via service', [
                'exercise_id' => $exercise->id,
                'changes' => array_keys($data),
                'updated_by' => $user->id,
                'tenant_id' => tenant('id')
            ]);

            return $exercise->fresh(['lesson', 'template']);
        });
    }

    /**
     * Delete an exercise with validation and audit logging.
     */
    public function deleteExercise(Exercise $exercise, User $user): bool
    {
        return DB::transaction(function () use ($exercise, $user) {
            $exerciseData = $exercise->toArray();

            // Reorder remaining exercises
            $this->reorderExercisesAfterDeletion($exercise->lesson_id, $exercise->order);

            // Log the deletion for audit trail before deleting
            AuditLog::log(
                'delete',
                'exercises',
                $exercise,
                $exerciseData,
                [],
                ['user_id' => $user->id]
            );

            $deleted = $exercise->delete();

            if ($deleted) {
                Log::info('Exercise deleted via service', [
                    'exercise_id' => $exercise->id,
                    'title' => $exercise->title,
                    'deleted_by' => $user->id,
                    'tenant_id' => tenant('id')
                ]);
            }

            return $deleted;
        });
    }

    /**
     * Reorder exercises within a lesson.
     */
    public function reorderExercises(int $lessonId, array $exerciseOrders, User $user): bool
    {
        return DB::transaction(function () use ($lessonId, $exerciseOrders, $user) {
            foreach ($exerciseOrders as $exerciseId => $order) {
                Exercise::where('id', $exerciseId)
                    ->where('lesson_id', $lessonId)
                    ->update(['order' => $order]);
            }

            Log::info('Exercises reordered via service', [
                'lesson_id' => $lessonId,
                'exercise_count' => count($exerciseOrders),
                'reordered_by' => $user->id,
                'tenant_id' => tenant('id')
            ]);

            return true;
        });
    }

    /**
     * Update exercise status with validation.
     */
    public function updateExerciseStatus(Exercise $exercise, string $status, User $user): Exercise
    {
        $validStatuses = ['draft', 'published', 'archived'];

        if (!in_array($status, $validStatuses)) {
            throw new Exception("Invalid status. Must be one of: " . implode(', ', $validStatuses));
        }

        return $this->updateExercise($exercise, ['status' => $status], $user);
    }

    /**
     * Validate exercise content structure using ExerciseTypeService.
     */
    public function validateExerciseContent(string $type, array $content): bool
    {
        try {
            $handler = $this->exerciseTypeService->getHandler($type);
            return $handler->validateContent($content);
        } catch (Exception $e) {
            throw new Exception("Invalid exercise content for type '{$type}': " . $e->getMessage());
        }
    }

    /**
     * Duplicate an exercise.
     */
    public function duplicateExercise(Exercise $exercise, array $overrides, User $user): Exercise
    {
        return DB::transaction(function () use ($exercise, $overrides, $user) {
            $exerciseData = $exercise->toArray();

            // Remove ID and timestamps
            unset($exerciseData['id'], $exerciseData['created_at'], $exerciseData['updated_at']);

            // Apply overrides
            $exerciseData = array_merge($exerciseData, $overrides);

            // Set new order if not specified
            if (!isset($overrides['order'])) {
                $exerciseData['order'] = $this->getNextOrderForLesson($exerciseData['lesson_id']);
            }

            // Update title to indicate it's a copy
            if (!isset($overrides['title'])) {
                $exerciseData['title'] = $exercise->title . ' (Copy)';
            }

            // Create new exercise
            $newExercise = $this->createExercise($exerciseData, $user);

            Log::info('Exercise duplicated via service', [
                'original_exercise_id' => $exercise->id,
                'new_exercise_id' => $newExercise->id,
                'duplicated_by' => $user->id,
                'tenant_id' => tenant('id')
            ]);

            return $newExercise;
        });
    }

    /**
     * Generate unique slug for exercise.
     */
    private function generateUniqueSlug(string $title, ?int $excludeId = null): string
    {
        $baseSlug = Str::slug($title);
        $slug = $baseSlug;
        $counter = 1;

        while ($this->slugExists($slug, $excludeId)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Check if slug exists.
     */
    private function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $query = Exercise::where('slug', $slug);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Get next order number for lesson.
     */
    private function getNextOrderForLesson(int $lessonId): int
    {
        return Exercise::where('lesson_id', $lessonId)->max('order') + 1;
    }

    /**
     * Reorder exercises after deletion.
     */
    private function reorderExercisesAfterDeletion(int $lessonId, int $deletedOrder): void
    {
        Exercise::where('lesson_id', $lessonId)
            ->where('order', '>', $deletedOrder)
            ->decrement('order');
    }

    // === STUDENT-SPECIFIC METHODS ===

    /**
     * Submit and check student answer for an exercise.
     */
    public function checkStudentAnswer(Exercise $exercise, User $user, array $answerData): array
    {
        // Start a database transaction for atomic operation
        return DB::transaction(function () use ($exercise, $user, $answerData) {
            // Get attempt number
            $attemptNumber = $this->getNextAttemptNumber($exercise, $user);

            // Validate answer based on exercise type
            $result = $this->validateAnswer($exercise, $answerData);

            // Record the attempt
            $attempt = ExerciseAttempt::create([
                'exercise_id' => $exercise->id,
                'user_id' => $user->id,
                'user_answer' => $answerData,
                'is_correct' => $result['is_correct'],
                'score' => $result['score'],
                'passed' => $result['passed'],
                'feedback' => $result['feedback'],
                'time_taken_seconds' => $answerData['time_taken'] ?? 0,
                'attempt_number' => $attemptNumber,
            ]);

            // Update user progress if exercise is completed
            if ($result['passed']) {
                $this->updateLessonProgress($exercise, $user);
            }

            return [
                'attempt_id' => $attempt->id,
                'is_correct' => $result['is_correct'],
                'score' => $result['score'],
                'passed' => $result['passed'],
                'feedback' => $result['feedback'],
                'attempt_number' => $attemptNumber,
                'exercise_completed' => $result['passed'],
                'next_action' => $this->getNextAction($exercise, $user, $result['passed'])
            ];
        });
    }

    /**
     * Get exercise statistics for a student.
     */
    public function getStudentStatistics(Exercise $exercise, User $user): array
    {
        $attempts = ExerciseAttempt::where('exercise_id', $exercise->id)
            ->where('user_id', $user->id)
            ->orderBy('created_at')
            ->get();

        if ($attempts->isEmpty()) {
            return [
                'total_attempts' => 0,
                'best_score' => 0,
                'average_score' => 0,
                'completed' => false,
                'total_time_spent' => 0,
                'improvement_trend' => 'no_data',
                'last_attempt_at' => null
            ];
        }

        $bestScore = $attempts->max('score');
        $averageScore = $attempts->avg('score');
        $totalTime = $attempts->sum('time_taken_seconds');
        $completed = $attempts->where('passed', true)->isNotEmpty();

        return [
            'total_attempts' => $attempts->count(),
            'best_score' => round($bestScore, 2),
            'average_score' => round($averageScore, 2),
            'completed' => $completed,
            'total_time_spent' => $totalTime,
            'improvement_trend' => $this->calculateImprovementTrend($attempts),
            'last_attempt_at' => $attempts->last()->created_at,
            'attempts_history' => $attempts->map(function ($attempt) {
                return [
                    'id' => $attempt->id,
                    'score' => $attempt->score,
                    'passed' => $attempt->passed,
                    'time_taken' => $attempt->time_taken_seconds,
                    'created_at' => $attempt->created_at
                ];
            })
        ];
    }

    /**
     * Get exercises filtered by type for students.
     */
    public function getExercisesByType(string $type, User $user, array $filters = []): Collection
    {
        $query = Exercise::where('type', $type)
            ->where('status', 'active')
            ->with(['lesson.topic.unit', 'attempts' => function ($q) use ($user) {
                $q->where('user_id', $user->id);
            }]);

        // Apply additional filters
        if (!empty($filters['lesson_id'])) {
            $query->where('lesson_id', $filters['lesson_id']);
        }

        if (!empty($filters['difficulty_level'])) {
            $query->where('difficulty_level', $filters['difficulty_level']);
        }

        return $query->orderBy('order')->get()->map(function ($exercise) use ($user) {
            $exerciseData = $exercise->toArray();
            $exerciseData['student_progress'] = $this->getStudentStatistics($exercise, $user);
            return (object) $exerciseData;
        });
    }

    /**
     * Get exercises filtered by language for students.
     */
    public function getExercisesByLanguage(string $languageCode, User $user, array $filters = []): Collection
    {
        $query = Exercise::whereHas('lesson.topic.unit.learningPath.language', function ($q) use ($languageCode) {
            $q->where('code', $languageCode);
        })
            ->where('status', 'active')
            ->with(['lesson.topic.unit.learningPath.language', 'attempts' => function ($q) use ($user) {
                $q->where('user_id', $user->id);
            }]);

        // Apply additional filters
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['difficulty_level'])) {
            $query->where('difficulty_level', $filters['difficulty_level']);
        }

        return $query->orderBy('order')->get()->map(function ($exercise) use ($user) {
            $exerciseData = $exercise->toArray();
            $exerciseData['student_progress'] = $this->getStudentStatistics($exercise, $user);
            return (object) $exerciseData;
        });
    }

    /**
     * Get next attempt number for user and exercise.
     */
    private function getNextAttemptNumber(Exercise $exercise, User $user): int
    {
        return ExerciseAttempt::where('exercise_id', $exercise->id)
            ->where('user_id', $user->id)
            ->max('attempt_number') + 1;
    }

    /**
     * Validate answer based on exercise type.
     */
    private function validateAnswer(Exercise $exercise, array $answerData): array
    {
        // Get the appropriate handler for the exercise type
        $handler = $this->exerciseTypeService->getHandler($exercise->type);

        // Extract user answer
        $userAnswer = $answerData['answer'] ?? '';

        // Use the type-specific handler to check the answer
        $isCorrect = $handler->checkAnswer($exercise, $userAnswer);
        $score = $isCorrect ? 100 : 0;

        // Get type-specific feedback
        $feedback = $handler->getFeedback($exercise, $isCorrect);

        return [
            'is_correct' => $isCorrect,
            'score' => $score,
            'passed' => $score >= ($exercise->passing_score ?? 70),
            'feedback' => [
                'type' => $isCorrect ? 'success' : 'error',
                'message' => $feedback,
                'explanation' => $isCorrect ? null : ($exercise->explanation ?? 'Review the lesson material and try again.')
            ]
        ];
    }

    /**
     * Generate feedback for student answer.
     */
    private function generateFeedback(bool $isCorrect, Exercise $exercise): array
    {
        if ($isCorrect) {
            return [
                'type' => 'success',
                'message' => 'Correct! Well done.',
                'explanation' => null
            ];
        } else {
            return [
                'type' => 'error',
                'message' => 'Not quite right. Try again!',
                'explanation' => $exercise->explanation ?? 'Review the lesson material and try again.'
            ];
        }
    }

    /**
     * Update lesson progress when exercise is completed.
     */
    private function updateLessonProgress(Exercise $exercise, User $user): void
    {
        // Check if all exercises in lesson are completed
        $lesson = $exercise->lesson;
        $totalExercises = $lesson->exercises()->count();
        $completedExercises = $lesson->exercises()
            ->whereHas('attempts', function ($q) use ($user) {
                $q->where('user_id', $user->id)->where('passed', true);
            })->count();

        // Update lesson progress
        UserProgress::updateOrCreate(
            [
                'user_id' => $user->id,
                'trackable_type' => Lesson::class,
                'trackable_id' => $lesson->id,
            ],
            [
                'status' => $completedExercises === $totalExercises
                    ? UserProgress::STATUS_COMPLETED
                    : UserProgress::STATUS_IN_PROGRESS,
                'completed_at' => $completedExercises === $totalExercises ? now() : null,
                'meta_data' => [
                    'exercises_completed' => $completedExercises,
                    'exercises_total' => $totalExercises,
                    'completion_percentage' => ($completedExercises / $totalExercises) * 100
                ]
            ]
        );
    }

    /**
     * Get next recommended action for student.
     */
    private function getNextAction(Exercise $exercise, User $user, bool $exerciseCompleted): string
    {
        if ($exerciseCompleted) {
            // Check if there's a next exercise in the lesson
            $nextExercise = Exercise::where('lesson_id', $exercise->lesson_id)
                ->where('order', '>', $exercise->order)
                ->orderBy('order')
                ->first();

            return $nextExercise ? 'next_exercise' : 'lesson_complete';
        }

        return 'retry_exercise';
    }

    /**
     * Calculate improvement trend from attempts.
     */
    private function calculateImprovementTrend(Collection $attempts): string
    {
        if ($attempts->count() < 2) {
            return 'insufficient_data';
        }

        $recentAttempts = $attempts->take(-3); // Last 3 attempts
        $scores = $recentAttempts->pluck('score')->toArray();

        if (count($scores) < 2) {
            return 'insufficient_data';
        }

        $firstScore = $scores[0];
        $lastScore = end($scores);
        $improvement = $lastScore - $firstScore;

        if ($improvement > 10) {
            return 'improving';
        } elseif ($improvement < -10) {
            return 'declining';
        } else {
            return 'stable';
        }
    }
}
