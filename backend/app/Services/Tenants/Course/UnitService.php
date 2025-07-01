<?php

namespace App\Services\Tenants\Course;

use App\Models\Tenants\Unit;
use App\Models\Tenants\LearningPath;
use App\Models\Tenants\AuditLog;
use App\Models\Tenants\User;
use App\Models\Tenants\UserProgress;
use App\Models\Tenants\Topic;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UnitService
{
    /**
     * Get a single unit with relationships for students.
     */
    public function getUnit(int $unitId, string $membership = 'student', array $with = [], User $user = null): ?array
    {
        $query = Unit::query();

        // Apply membership-based filtering
        if (!in_array($membership, ['super-admin', 'tenant-admin', 'team'])) {
            // Students can only see published content
            $query->where('status', 'published');
        }

        // Default relationships for students
        $defaultWith = ['learningPath', 'topics' => function ($query) {
            $query->orderBy('order');
        }];

        $with = array_merge($defaultWith, $with);

        $unit = $query->with($with)->find($unitId);

        if (!$unit) {
            return null;
        }

        $unitArray = $unit->toArray();

        // Add progress information for students
        if ($user && $membership === 'student') {
            $progress = UserProgress::where('user_id', $user->id)
                ->where('trackable_type', Unit::class)
                ->where('trackable_id', $unit->id)
                ->first();

            $unitArray['progress'] = $progress ? ($progress->meta_data['completion_percentage'] ?? 0) : 0;
        }

        return $unitArray;
    }

    /**
     * Get filtered units with membership-based access.
     */
    public function getFilteredUnits(Request $request, string $membership = 'student'): LengthAwarePaginator
    {
        $query = Unit::query();

        // Apply membership-based filtering
        if (!in_array($membership, ['super-admin', 'tenant-admin', 'team'])) {
            // Students can only see published content
            $query->where('status', 'published');
        }

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('learning_path_id')) {
            $query->where('learning_path_id', $request->learning_path_id);
        }

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Include relationships if requested
        if ($request->has('with_learning_path')) {
            $query->with('learningPath');
        }

        if ($request->has('with_topics')) {
            $query->with(['topics' => function ($query) {
                $query->orderBy('order');
            }]);
        }

        // Admin-specific relationships
        if (in_array($membership, ['super-admin', 'tenant-admin', 'team'])) {
            if ($request->has('with_versions')) {
                $query->with('versions');
            }

            if ($request->has('with_reviews')) {
                $query->with('reviews');
            }
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'order');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->input('per_page', 15);
        return $query->paginate($perPage);
    }

    /**
     * Get units for a specific learning path.
     */
    public function getUnitsForLearningPath(LearningPath $learningPath, string $membership = 'student', User $user = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = $learningPath->units()->orderBy('order');

        // Apply membership-based filtering
        if (!in_array($membership, ['super-admin', 'tenant-admin', 'team'])) {
            $query->where('status', 'published');
        }

        $units = $query->get();

        // Add progress information for students
        if ($user && $membership === 'student') {
            // Transform each unit to include progress, but keep as Eloquent models
            foreach ($units as $unit) {
                $progress = UserProgress::where('user_id', $user->id)
                    ->where('trackable_type', Unit::class)
                    ->where('trackable_id', $unit->id)
                    ->first();

                // Add progress as an attribute to the model
                $unit->setAttribute('progress', $progress ? ($progress->meta_data['completion_percentage'] ?? 0) : 0);
            }
        }

        return $units;
    }

    /**
     * Create a new unit with audit logging.
     */
    public function createUnit(array $data, User $user): Unit
    {
        return DB::transaction(function () use ($data, $user) {
            // Set default status if not provided
            $data['status'] = $data['status'] ?? 'draft';

            $unit = Unit::create($data);

            // Log the creation for audit trail
            AuditLog::log(
                'create',
                'units',
                $unit,
                [],
                $data,
                ['user_id' => $user->id]
            );

            return $unit;
        });
    }

    /**
     * Update a unit with audit logging.
     */
    public function updateUnit(Unit $unit, array $data, User $user): Unit
    {
        return DB::transaction(function () use ($unit, $data, $user) {
            $oldData = $unit->toArray();
            $unit->update($data);

            // Log the update for audit trail
            AuditLog::log(
                'update',
                'units',
                $unit,
                $oldData,
                $data,
                ['user_id' => $user->id]
            );

            return $unit;
        });
    }

    /**
     * Delete a unit with validation and audit logging.
     */
    public function deleteUnit(Unit $unit, User $user): bool
    {
        // Prevent deletion of published units
        if ($unit->status === 'published') {
            throw new \Exception('Cannot delete a published unit. Archive it first.');
        }

        return DB::transaction(function () use ($unit, $user) {
            $data = $unit->toArray();

            // Delete related topics and lessons
            $unit->topics()->delete();
            $unit->delete();

            // Log the deletion for audit trail
            AuditLog::log(
                'delete',
                'units',
                $unit,
                $data,
                [],
                ['user_id' => $user->id]
            );

            return true;
        });
    }

    /**
     * Update unit status with validation and audit logging.
     */
    public function updateStatus(Unit $unit, string $status, User $user): Unit
    {
        $validStatuses = ['draft', 'published', 'archived'];
        if (!in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException('Invalid status provided.');
        }

        // Prevent publishing content that's under review
        if ($status === 'published' && $unit->review_status === 'pending') {
            throw new \InvalidArgumentException('Cannot publish content while it is under review. Please wait for review approval.');
        }

        // Require review approval for publishing (except for drafts being published by admins)
        if ($status === 'published' && $unit->review_status === 'none' && !$user->isTenantAdmin()) {
            throw new \InvalidArgumentException('Content must be reviewed before publishing.');
        }

        return DB::transaction(function () use ($unit, $status, $user) {
            $oldStatus = $unit->status;
            $unit->status = $status;
            $unit->save();

            // Log the status change for audit trail
            AuditLog::log(
                'status_update',
                'units',
                $unit,
                ['status' => $oldStatus],
                ['status' => $status],
                ['user_id' => $user->id]
            );

            return $unit;
        });
    }

    /**
     * Reorder units within a learning path.
     */
    public function reorderUnits(LearningPath $learningPath, array $unitIds, User $user): bool
    {
        return DB::transaction(function () use ($learningPath, $unitIds, $user) {
            // Verify all units belong to this learning path
            $existingUnitIds = $learningPath->units()->pluck('id')->toArray();
            $invalidUnits = array_diff($unitIds, $existingUnitIds);

            if (!empty($invalidUnits)) {
                throw new \InvalidArgumentException('Some units do not belong to this learning path.');
            }

            // Update the order of each unit
            foreach ($unitIds as $index => $unitId) {
                Unit::where('id', $unitId)->update(['order' => $index + 1]);
            }

            // Log the reordering
            AuditLog::log(
                'reorder',
                'units',
                $learningPath,
                [],
                ['unit_order' => $unitIds],
                ['user_id' => $user->id]
            );

            return true;
        });
    }

    /**
     * Get user progress for a unit.
     */
    public function getUserProgress(Unit $unit, User $user): array
    {
        $progress = UserProgress::where('user_id', $user->id)
            ->where('trackable_type', Unit::class)
            ->where('trackable_id', $unit->id)
            ->first();

        $topicsProgress = $unit->topics()
            ->get()
            ->map(function ($topic) use ($user) {
                $topicProgress = UserProgress::where('user_id', $user->id)
                    ->where('trackable_type', Topic::class)
                    ->where('trackable_id', $topic->id)
                    ->first();

                return [
                    'id' => $topic->id,
                    'title' => $topic->title,
                    'progress' => $topicProgress ? ($topicProgress->meta_data['completion_percentage'] ?? 0) : 0,
                ];
            });

        return [
            'unit_id' => $unit->id,
            'progress' => $progress ? ($progress->meta_data['completion_percentage'] ?? 0) : 0,
            'completed' => $progress ? $progress->status === 'completed' : false,
            'last_accessed_at' => $progress ? $progress->updated_at : null,
            'topics' => $topicsProgress,
        ];
    }

    /**
     * Check if user can access unit based on membership and tenant.
     */
    public function canUserAccess(Unit $unit, User $user): bool
    {
        // Super admins can access everything
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Check learning path access first
        $learningPath = $unit->learningPath;
        if ($learningPath && $user->tenant_id && $learningPath->tenant_id) {
            return $user->tenant_id === $learningPath->tenant_id;
        }

        // For non-tenant content, only published content is accessible to students
        if ($user->isStudent()) {
            return $unit->status === 'published';
        }

        return true;
    }

    /**
     * Get topics for a unit with student-specific information.
     */
    public function getTopicsForUnit(Unit $unit, string $membership = 'student', User $user = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = $unit->topics()->orderBy('order');

        // Apply membership-based filtering
        if (!in_array($membership, ['super-admin', 'tenant-admin', 'team'])) {
            $query->where('status', 'published');
        }

        $topics = $query->get();

        // Add progress information for students
        if ($user && $membership === 'student') {
            // Transform each topic to include progress, but keep as Eloquent models
            foreach ($topics as $topic) {
                $progress = UserProgress::where('user_id', $user->id)
                    ->where('trackable_type', Topic::class)
                    ->where('trackable_id', $topic->id)
                    ->first();

                // Add progress as an attribute to the model
                $topic->setAttribute('progress', $progress ? ($progress->meta_data['completion_percentage'] ?? 0) : 0);
            }
        }

        return $topics;
    }

    /**
     * Get unit with contents (topics and lessons).
     */
    public function getUnitWithContents(Unit $unit, string $membership = 'student', User $user = null): array
    {
        $unitData = $unit->toArray();

        $query = $unit->topics()->with(['lessons' => function ($query) use ($membership) {
            if (!in_array($membership, ['super-admin', 'tenant-admin', 'team'])) {
                $query->where('status', 'published');
            }
            $query->orderBy('order');
        }])->orderBy('order');

        if (!in_array($membership, ['super-admin', 'tenant-admin', 'team'])) {
            $query->where('status', 'published');
        }

        $topics = $query->get();
        $unitData['topics'] = $topics->toArray();

        // Add progress information for students
        if ($user && $membership === 'student') {
            // Unit progress
            $progress = UserProgress::where('user_id', $user->id)
                ->where('trackable_type', Unit::class)
                ->where('trackable_id', $unit->id)
                ->first();

            $unitData['progress'] = $progress ? ($progress->meta_data['completion_percentage'] ?? 0) : 0;

            // Topic and lesson progress
            foreach ($unitData['topics'] as $topicIndex => $topicData) {
                // Get topic progress
                $topicProgress = UserProgress::where('user_id', $user->id)
                    ->where('trackable_type', Topic::class)
                    ->where('trackable_id', $topicData['id'])
                    ->first();

                $unitData['topics'][$topicIndex]['progress'] = $topicProgress ? ($topicProgress->meta_data['completion_percentage'] ?? 0) : 0;

                // Get lesson progress for each lesson in this topic
                foreach ($unitData['topics'][$topicIndex]['lessons'] as $lessonIndex => $lessonData) {
                    $lessonProgress = UserProgress::where('user_id', $user->id)
                        ->where('trackable_type', \App\Models\Tenants\Lesson::class)
                        ->where('trackable_id', $lessonData['id'])
                        ->first();

                    $unitData['topics'][$topicIndex]['lessons'][$lessonIndex]['progress'] = $lessonProgress ? ($lessonProgress->meta_data['completion_percentage'] ?? 0) : 0;
                }
            }
        }

        return $unitData;
    }

    /**
     * Start a unit for a user.
     */
    public function startUnit(Unit $unit, User $user, string $deviceType = 'web'): array
    {
        $progress = UserProgress::updateOrCreate(
            [
                'user_id' => $user->id,
                'trackable_type' => Unit::class,
                'trackable_id' => $unit->id,
            ],
            [
                'status' => 'in_progress',
                'meta_data' => [
                    'completion_percentage' => 0,
                    'device_type' => $deviceType,
                    'started_at' => now(),
                ]
            ]
        );

        return [
            'unit_id' => $unit->id,
            'progress' => 0,
            'started_at' => $progress->meta_data['started_at'] ?? now(),
            'last_accessed_at' => $progress->updated_at,
        ];
    }

    /**
     * Update unit progress for a user.
     */
    public function updateProgress(Unit $unit, User $user, int $progressPercentage, bool $completed = false): array
    {
        $progress = UserProgress::updateOrCreate(
            [
                'user_id' => $user->id,
                'trackable_type' => Unit::class,
                'trackable_id' => $unit->id,
            ],
            [
                'status' => $completed ? 'completed' : 'in_progress',
                'meta_data' => [
                    'completion_percentage' => $progressPercentage,
                    'completed_at' => $completed ? now() : null,
                ]
            ]
        );

        return [
            'unit_id' => $unit->id,
            'progress' => $progressPercentage,
            'completed' => $completed,
            'last_accessed_at' => $progress->updated_at,
        ];
    }

    /**
     * Mark unit as completed for a user.
     */
    public function completeUnit(Unit $unit, User $user, string $deviceType = 'web'): array
    {
        $completedAt = now();

        $progress = UserProgress::updateOrCreate(
            [
                'user_id' => $user->id,
                'trackable_type' => Unit::class,
                'trackable_id' => $unit->id,
            ],
            [
                'status' => 'completed',
                'completed_at' => $completedAt,
                'meta_data' => [
                    'completion_percentage' => 100,
                    'device_type' => $deviceType,
                ]
            ]
        );

        return [
            'unit_id' => $unit->id,
            'progress' => 100,
            'completed' => true,
            'completed_at' => $progress->completed_at,
            'last_accessed_at' => $progress->updated_at,
        ];
    }

    /**
     * Get unit recommendations for a user.
     */
    public function getRecommendations(User $user): array
    {
        // Get units in progress
        $inProgressUnits = UserProgress::where('user_id', $user->id)
            ->where('trackable_type', Unit::class)
            ->where('status', 'in_progress')
            ->with('trackable')
            ->get()
            ->map(function ($progress) {
                return [
                    'id' => $progress->trackable->id,
                    'title' => $progress->trackable->title,
                    'progress' => $progress->meta_data['completion_percentage'] ?? 0,
                ];
            });

        // Get recommended next units (simplified logic)
        $recommendedUnits = Unit::where('status', 'published')
            ->whereNotIn('id', UserProgress::where('user_id', $user->id)
                ->where('trackable_type', Unit::class)
                ->pluck('trackable_id'))
            ->limit(5)
            ->get()
            ->map(function ($unit) {
                return [
                    'id' => $unit->id,
                    'title' => $unit->title,
                ];
            });

        return [
            'in_progress' => $inProgressUnits,
            'recommended' => $recommendedUnits,
        ];
    }

    /**
     * Get next unit in a learning path for a user.
     */
    public function getNextUnit(LearningPath $learningPath, User $user): ?array
    {
        // Get completed units for this user in this learning path
        $completedUnitIds = UserProgress::where('user_id', $user->id)
            ->where('trackable_type', Unit::class)
            ->where('status', 'completed')
            ->whereIn('trackable_id', $learningPath->units()->pluck('id'))
            ->pluck('trackable_id');

        // Find the next unit in order that hasn't been completed
        $nextUnit = $learningPath->units()
            ->where('status', 'published')
            ->whereNotIn('id', $completedUnitIds)
            ->orderBy('order')
            ->first();

        if (!$nextUnit) {
            return null;
        }

        return [
            'id' => $nextUnit->id,
            'title' => $nextUnit->title,
            'description' => $nextUnit->description,
            'order' => $nextUnit->order,
            'progress' => 0,
            'learning_path_id' => $learningPath->id,
        ];
    }
}
