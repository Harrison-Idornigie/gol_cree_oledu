<?php

namespace App\Services\Tenants\Course;

use App\Models\Tenants\Unit;
use App\Models\Tenants\LearningPath;
use App\Models\Tenants\AuditLog;
use App\Models\Tenants\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UnitService
{
    /**
     * Get a single unit with relationships for students.
     */
    public function getUnit(int $unitId, string $membership = 'student', array $with = []): ?Unit
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

        return $query->with($with)->find($unitId);
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
    public function getUnitsForLearningPath(LearningPath $learningPath, string $membership = 'student'): \Illuminate\Database\Eloquent\Collection
    {
        $query = $learningPath->units()->orderBy('order');

        // Apply membership-based filtering
        if (!in_array($membership, ['super-admin', 'tenant-admin', 'team'])) {
            $query->where('status', 'published');
        }

        return $query->get();
    }

    /**
     * Create a new unit with audit logging.
     */
    public function createUnit(array $data, User $user): Unit
    {
        return DB::transaction(function () use ($data, $user) {
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
        $progress = $unit->progress()
            ->where('user_id', $user->id)
            ->first();

        $topicsProgress = $unit->topics()
            ->with(['progress' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }])
            ->get()
            ->map(function ($topic) {
                $progress = $topic->progress->first();
                return [
                    'topic_id'              => $topic->id,
                    'status'                => $progress ? $progress->status : 'not_started',
                    'completion_percentage' => $topic->getCompletionPercentage($topic->progress->first()?->user_id ?? 0),
                ];
            });

        return [
            'unit_progress'   => $progress ? $progress->status : 'not_started',
            'topics_progress' => $topicsProgress,
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
}
