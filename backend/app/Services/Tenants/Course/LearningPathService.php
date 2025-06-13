<?php

namespace App\Services\Tenants\Course;

use App\Models\Tenants\LearningPath;
use App\Models\Tenants\AuditLog;
use App\Models\Tenants\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class LearningPathService
{
    /**
     * Get filtered learning paths with membership-based access.
     */
    public function getFilteredLearningPaths(Request $request, string $membership = 'student'): LengthAwarePaginator
    {
        $query = LearningPath::query();

        // Apply membership-based filtering
        if (!in_array($membership, ['super-admin', 'tenant-admin', 'team'])) {
            // Students can only see published content
            $query->where('status', 'published');
        }


        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('target_level')) {
            $query->where('target_level', $request->target_level);
        }

        if ($request->has('language_id')) {
            $query->where('language_id', $request->language_id);
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
        if ($request->has('with_units')) {
            $query->with(['units' => function ($query) {
                $query->orderBy('order');
            }]);
        }

        if ($request->has('with_language')) {
            $query->with('language');
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
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->input('per_page', 15);
        return $query->paginate($perPage);
    }

    /**
     * Create a new learning path with audit logging.
     */
    public function createLearningPath(array $data, User $user): LearningPath
    {
        return DB::transaction(function () use ($data, $user) {
            $learningPath = LearningPath::create($data);

            // Log the creation for audit trail
            AuditLog::log(
                'create',
                'learning_paths',
                $learningPath,
                [],
                $data,
                ['user_id' => $user->id]
            );

            return $learningPath;
        });
    }

    /**
     * Update a learning path with audit logging.
     */
    public function updateLearningPath(LearningPath $learningPath, array $data, User $user): LearningPath
    {
        return DB::transaction(function () use ($learningPath, $data, $user) {
            $oldData = $learningPath->toArray();
            $learningPath->update($data);

            // Log the update for audit trail
            AuditLog::log(
                'update',
                'learning_paths',
                $learningPath,
                $oldData,
                $data,
                ['user_id' => $user->id]
            );

            return $learningPath;
        });
    }

    /**
     * Delete a learning path with validation and audit logging.
     */
    public function deleteLearningPath(LearningPath $learningPath, User $user): bool
    {
        // Prevent deletion of published learning paths
        if ($learningPath->status === 'published') {
            throw new \Exception('Cannot delete a published learning path. Archive it first.');
        }

        return DB::transaction(function () use ($learningPath, $user) {
            $data = $learningPath->toArray();
            
            // Detach from all units
            $learningPath->units()->detach();
            
            $learningPath->delete();

            // Log the deletion for audit trail
            AuditLog::log(
                'delete',
                'learning_paths',
                $learningPath,
                $data,
                [],
                ['user_id' => $user->id]
            );

            return true;
        });
    }

    /**
     * Update learning path status with validation and audit logging.
     */
    public function updateStatus(LearningPath $learningPath, string $status, User $user): LearningPath
    {
        $validStatuses = ['draft', 'published', 'archived'];
        if (!in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException('Invalid status provided.');
        }

        return DB::transaction(function () use ($learningPath, $status, $user) {
            $oldStatus = $learningPath->status;
            $learningPath->status = $status;
            $learningPath->save();

            // Log the status change for audit trail
            AuditLog::log(
                'status_update',
                'learning_paths',
                $learningPath,
                ['status' => $oldStatus],
                ['status' => $status],
                ['user_id' => $user->id]
            );

            return $learningPath;
        });
    }

    /**
     * Get learning paths by target level (published only).
     */
    public function getByLevel(string $level): \Illuminate\Database\Eloquent\Collection
    {
        return LearningPath::where('target_level', $level)
            ->where('status', 'published')
            ->with(['units' => function ($query) {
                $query->orderBy('order');
            }])
            ->get();
    }

    /**
     * Get learning paths by language (published only).
     */
    public function getByLanguage(int $languageId): \Illuminate\Database\Eloquent\Collection
    {
        return LearningPath::where('language_id', $languageId)
            ->where('status', 'published')
            ->with(['units' => function ($query) {
                $query->orderBy('order');
            }])
            ->with('language')
            ->get();
    }

    /**
     * Get user progress for a learning path.
     */
    public function getUserProgress(LearningPath $learningPath, User $user): array
    {
        $progress = $learningPath->progress()
            ->where('user_id', $user->id)
            ->first();

        $unitsProgress = $learningPath->units()
            ->with(['progress' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }])
            ->get()
            ->map(function ($unit) {
                $progress = $unit->progress->first();
                return [
                    'unit_id'               => $unit->id,
                    'status'                => $progress ? $progress->status : 'not_started',
                    'completion_percentage' => $unit->getCompletionPercentage($unit->progress->first()?->user_id ?? 0),
                ];
            });

        return [
            'learning_path_progress' => $progress ? $progress->status : 'not_started',
            'units_progress'         => $unitsProgress,
        ];
    }

    /**
     * Check if user can access learning path based on membership and tenant.
     */
    public function canUserAccess(LearningPath $learningPath, User $user): bool
    {
        // Super admins can access everything
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Tenant-scoped access for other memberships
        if ($user->tenant_id && $learningPath->tenant_id) {
            return $user->tenant_id === $learningPath->tenant_id;
        }

        // For non-tenant content, only published content is accessible to students
        if ($user->isStudent()) {
            return $learningPath->status === 'published';
        }

        return true;
    }
}
