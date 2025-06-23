<?php

namespace App\Services\Tenants\Course;

use App\Models\Tenants\Topic;
use App\Models\Tenants\Unit;
use App\Models\Tenants\User;
use App\Models\Tenants\AuditLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

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
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Apply filters
        if (!empty($filters['unit_id'])) {
            $query->where('unit_id', $filters['unit_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['is_bonus'])) {
            $query->where('is_bonus', $filters['is_bonus']);
        }

        if (!empty($filters['created_by'])) {
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
            $data['status'] = $data['status'] ?? 'draft';
            $data['xp_reward'] = $data['xp_reward'] ?? 10;
            $data['max_level'] = $data['max_level'] ?? 5;
            $data['is_bonus'] = $data['is_bonus'] ?? false;

            $topic = Topic::create($data);

            // Log the creation for audit trail
            AuditLog::log(
                'create',
                'topics',
                $topic,
                [],
                $data,
                $user->id
            );

            Log::info('Topic created via service', [
                'topic_id' => $topic->id,
                'unit_id' => $topic->unit_id,
                'title' => $topic->title,
                'created_by' => $user->id,
                'tenant_id' => tenant('id')
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
            $originalData = $topic->toArray();

            // Update slug if title changed
            if (isset($data['title']) && $data['title'] !== $topic->title) {
                $data['slug'] = $this->generateUniqueSlug($data['title'], $topic->id);
            }

            $topic->update($data);

            // Log the update for audit trail
            AuditLog::log(
                'update',
                'topics',
                $topic,
                $originalData,
                $data,
                $user->id
            );

            Log::info('Topic updated via service', [
                'topic_id' => $topic->id,
                'changes' => array_keys($data),
                'updated_by' => $user->id,
                'tenant_id' => tenant('id')
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

            $topicData = $topic->toArray();

            // Reorder remaining topics
            $this->reorderTopicsAfterDeletion($topic->unit_id, $topic->order);

            $deleted = $topic->delete();

            if ($deleted) {
                // Log the deletion for audit trail
                AuditLog::log(
                    'delete',
                    'topics',
                    null,
                    $topicData,
                    [],
                    $user->id
                );

                Log::info('Topic deleted via service', [
                    'topic_id' => $topic->id,
                    'title' => $topic->title,
                    'deleted_by' => $user->id,
                    'tenant_id' => tenant('id')
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
                'unit_id' => $unitId,
                'topic_count' => count($topicOrders),
                'reordered_by' => $user->id,
                'tenant_id' => tenant('id')
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
        
        if (!in_array($status, $validStatuses)) {
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

        return [
            'lessons_count' => $topic->lessons->count(),
            'exercises_count' => $topic->lessons->sum(function ($lesson) {
                return $lesson->exercises->count();
            }),
            'total_xp' => $topic->xp_reward,
            'status' => $topic->status,
            'completion_rate' => 0, // TODO: Calculate based on user progress
            'average_difficulty' => 0, // TODO: Calculate based on exercises
        ];
    }
}
