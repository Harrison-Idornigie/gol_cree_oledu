<?php

namespace App\Services\Tenants\Exercise;

use App\Models\Tenants\Exercise;
use App\Models\Tenants\Lesson;
use App\Models\Tenants\User;
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
     * Get paginated exercises with filters and relationships.
     */
    public function getExercises(array $filters = [], array $sorts = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Exercise::with(['lesson.topic.unit', 'template', 'attempts'])
            ->withCount(['attempts']);

        // Apply search
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

            $exercise = Exercise::create($data);

            // Log the creation for audit trail
            AuditLog::log(
                'create',
                'exercises',
                $exercise,
                [],
                $data,
                $user->id
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

            $exercise->update($data);

            // Log the update for audit trail
            AuditLog::log(
                'update',
                'exercises',
                $exercise,
                $originalData,
                $data,
                $user->id
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

            $deleted = $exercise->delete();

            if ($deleted) {
                // Log the deletion for audit trail
                AuditLog::log(
                    'delete',
                    'exercises',
                    null,
                    $exerciseData,
                    [],
                    $user->id
                );

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
}
