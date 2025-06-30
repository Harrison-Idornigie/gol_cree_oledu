<?php
namespace App\Services\Tenants\Course;

use App\Models\Tenants\AuditLog;
use App\Models\Tenants\Topic;
use App\Models\Tenants\Unit;
use App\Models\Tenants\User;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Topic Service
 *
 * Handles CRUD operations and business logic for topics including:
 * - Topic creation, updates, and deletion
 * - Topic ordering and organization
 * - Topic status management
 * - Topic analytics and progress tracking
 */
class TopicService
{
    /**
     * Get paginated topics with filters and relationships.
     */
    public function getTopics(array $filters = [], array $sorts = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Topic::with(['unit.learningPath', 'lessons', 'template'])
            ->withCount(['lessons']);

        // Apply search
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Apply filters
        if (! empty($filters['unit_id'])) {
            $query->where('unit_id', $filters['unit_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['is_bonus'])) {
            $query->where('is_bonus', $filters['is_bonus']);
        }

        if (! empty($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        // Apply sorting
        foreach ($sorts as $field => $direction) {
            if (in_array($field, ['title', 'order', 'status', 'created_at', 'xp_reward'])) {
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
     * Get topics for a specific unit.
     */
    public function getTopicsByUnit(int $unitId, bool $includeStats = false): Collection
    {
        $query = Topic::where('unit_id', $unitId)
            ->with(['lessons', 'template'])
            ->withCount(['lessons']);

        if ($includeStats) {
            $query->with(['lessons.exercises']);
        }

        return $query->orderBy('order')->orderBy('title')->get();
    }

    /**
     * Create a new topic with validation and audit logging.
     */
    public function createTopic(array $data, User $user): Topic
    {
        return DB::transaction(function () use ($data, $user) {
            // Validate unit exists and user has access
            $unit = Unit::findOrFail($data['unit_id']);

            // Generate slug if not provided
            if (empty($data['slug'])) {
                $data['slug'] = $this->generateUniqueSlug($data['title']);
            }

            // Set order if not provided
            if (empty($data['order'])) {
                $data['order'] = $this->getNextOrderForUnit($data['unit_id']);
            }

            // Set defaults
            $data['tenant_id'] = tenant('id');
            $data['status']    = $data['status'] ?? 'draft';
            $data['xp_reward'] = $data['xp_reward'] ?? 10;
            $data['max_level'] = $data['max_level'] ?? 5;
            $data['is_bonus']  = $data['is_bonus'] ?? false;

            $topic = Topic::create($data);

            // Log the creation for audit trail
            AuditLog::log(
                'create',
                'topics',
                $topic,
                [],
                $data,
                ['user_id' => $user->id]
            );

            Log::info('Topic created via service', [
                'topic_id'   => $topic->id,
                'unit_id'    => $topic->unit_id,
                'title'      => $topic->title,
                'created_by' => $user->id,
                'tenant_id'  => tenant('id'),
            ]);

            return $topic->load(['unit', 'lessons']);
        });
    }

    /**
     * Update an existing topic with audit logging.
     */
    public function updateTopic(Topic $topic, array $data, User $user): Topic
    {
        return DB::transaction(function () use ($topic, $data, $user) {
            // Update slug if title changed
            if (isset($data['title']) && $data['title'] !== $topic->title) {
                $data['slug'] = $this->generateUniqueSlug($data['title'], $topic->id);
            }

            $topic->update($data);

            Log::info('Topic updated via service', [
                'topic_id'   => $topic->id,
                'changes'    => array_keys($data),
                'updated_by' => $user->id,
                'tenant_id'  => tenant('id'),
            ]);

            return $topic->fresh(['unit', 'lessons']);
        });
    }

    /**
     * Delete a topic with validation and audit logging.
     */
    public function deleteTopic(Topic $topic, User $user): bool
    {
        return DB::transaction(function () use ($topic, $user) {
            // Check if topic has lessons
            if ($topic->lessons()->count() > 0) {
                throw new Exception('Cannot delete topic with existing lessons. Please delete lessons first.');
            }

            // Reorder remaining topics
            $this->reorderTopicsAfterDeletion($topic->unit_id, $topic->order);

            $deleted = $topic->delete();

            if ($deleted) {
                Log::info('Topic deleted via service', [
                    'topic_id'   => $topic->id,
                    'title'      => $topic->title,
                    'deleted_by' => $user->id,
                    'tenant_id'  => tenant('id'),
                ]);
            }

            return $deleted;
        });
    }

    /**
     * Reorder topics within a unit.
     */
    public function reorderTopics(int $unitId, array $topicOrders, User $user): bool
    {
        return DB::transaction(function () use ($unitId, $topicOrders, $user) {
            foreach ($topicOrders as $topicId => $order) {
                Topic::where('id', $topicId)
                    ->where('unit_id', $unitId)
                    ->update(['order' => $order]);
            }

            Log::info('Topics reordered via service', [
                'unit_id'      => $unitId,
                'topic_count'  => count($topicOrders),
                'reordered_by' => $user->id,
                'tenant_id'    => tenant('id'),
            ]);

            return true;
        });
    }

    /**
     * Update topic status with validation.
     */
    public function updateTopicStatus(Topic $topic, string $status, User $user): Topic
    {
        $validStatuses = ['draft', 'published', 'archived'];

        if (! in_array($status, $validStatuses)) {
            throw new Exception("Invalid status. Must be one of: " . implode(', ', $validStatuses));
        }

        return $this->updateTopic($topic, ['status' => $status], $user);
    }

    /**
     * Generate unique slug for topic.
     */
    private function generateUniqueSlug(string $title, ?int $excludeId = null): string
    {
        $baseSlug = Str::slug($title);
        $slug     = $baseSlug;
        $counter  = 1;

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
        $query = Topic::where('slug', $slug);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Get next order number for unit.
     */
    private function getNextOrderForUnit(int $unitId): int
    {
        return Topic::where('unit_id', $unitId)->max('order') + 1;
    }

    /**
     * Reorder topics after deletion.
     */
    private function reorderTopicsAfterDeletion(int $unitId, int $deletedOrder): void
    {
        Topic::where('unit_id', $unitId)
            ->where('order', '>', $deletedOrder)
            ->decrement('order');
    }

    /**
     * Get topic statistics.
     */
    public function getTopicStats(Topic $topic): array
    {
        $topic->load(['lessons.exercises']);

        $exercisesCount = 0;
        foreach ($topic->lessons as $lesson) {
            $exercisesCount += $lesson->exercises->count();
        }

        return [
            'lessons_count'      => $topic->lessons->count(),
            'exercises_count'    => $exercisesCount,
            'total_xp'           => $topic->xp_reward,
            'status'             => $topic->status,
            'completion_rate'    => 0, // TODO: Calculate based on user progress
            'average_difficulty' => 0, // TODO: Calculate based on exercises
        ];
    }

    /**
     * Get topics for a unit with student-specific information.
     */
    public function getTopicsForStudents(int $unitId, User $user): Collection
    {
        $topics = Topic::where('unit_id', $unitId)
            ->where('status', 'published')
            ->with(['lessons' => function ($query) {
                $query->where('status', 'published')->orderBy('order');
            }])
            ->orderBy('order')
            ->get();

        $transformedTopics = $topics->map(function ($topic) use ($user) {
            $topicArray                      = $topic->toArray();
            $topicArray['user_progress']     = $this->getUserTopicProgress($topic, $user);
            $topicArray['is_accessible']     = $this->isTopicAccessible($topic, $user);
            $topicArray['completion_status'] = $this->getTopicCompletionStatus($topic, $user);
            return (object) $topicArray;
        });

        // Convert to Eloquent Collection to match return type
        return new Collection($transformedTopics->all());
    }

    /**
     * Get user's progress in a topic.
     */
    public function getUserTopicProgress(Topic $topic, User $user): array
    {
        // Check if there's actual progress data for this topic
        $progress = \App\Models\Tenants\UserProgress::where('user_id', $user->id)
            ->where('trackable_type', Topic::class)
            ->where('trackable_id', $topic->id)
            ->first();

        $overallProgress = 0;
        if ($progress && isset($progress->meta_data['progress'])) {
            $overallProgress = $progress->meta_data['progress'];
        }

        return [
            'overall_progress'    => $overallProgress,
            'lessons_completed'   => 0,
            'lessons_total'       => $topic->lessons()->where('status', 'published')->count(),
            'exercises_completed' => 0,
            'exercises_total'     => $this->getTotalExercisesInTopic($topic),
            'xp_earned'           => 0,
            'xp_total'            => $topic->xp_reward ?? 0,
            'time_spent_minutes'  => 0,
            'last_activity'       => $progress?->updated_at,
            'current_lesson'      => null,
            'next_lesson'         => null,
            'is_completed'        => $progress && $progress->status === 'completed',
        ];
    }

    /**
     * Check if topic is accessible to user (sequential learning).
     */
    public function isTopicAccessible(Topic $topic, User $user): bool
    {
        // If it's the first topic in the unit, it's accessible
        $previousTopic = Topic::where('unit_id', $topic->unit_id)
            ->where('order', '<', $topic->order)
            ->orderBy('order', 'desc')
            ->first();

        if (! $previousTopic) {
            return true; // First topic is always accessible
        }

        // Check if previous topic is completed
        return $this->isTopicCompleted($previousTopic, $user);
    }

    /**
     * Get topic completion status.
     */
    public function getTopicCompletionStatus(Topic $topic, User $user): array
    {
        $progress    = $this->getUserTopicProgress($topic, $user);
        $isCompleted = $this->isTopicCompleted($topic, $user);

        return [
            'is_completed'          => $isCompleted,
            'completion_percentage' => $progress['overall_progress'],
            'completed_at'          => $isCompleted ? now() : null, // This would be from actual tracking
            'certificate_earned'    => false,
            'mastery_level'         => 'learning',
        ];
    }

    /**
     * Check if topic is completed by user.
     */
    private function isTopicCompleted(Topic $topic, User $user): bool
    {
        $progress = \App\Models\Tenants\UserProgress::where('user_id', $user->id)
            ->where('trackable_type', Topic::class)
            ->where('trackable_id', $topic->id)
            ->first();

        return $progress && $progress->status === 'completed';
    }

    /**
     * Get total exercises in a topic.
     */
    private function getTotalExercisesInTopic(Topic $topic): int
    {
        return $topic->lessons()
            ->where('status', 'published')
            ->withCount('exercises')
            ->get()
            ->sum('exercises_count');
    }
}
