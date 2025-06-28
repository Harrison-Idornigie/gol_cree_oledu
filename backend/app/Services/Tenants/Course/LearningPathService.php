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
     * Get a single learning path with relationships for students.
     */
    public function getLearningPath(int $learningPathId, string $membership = 'student', array $with = []): ?LearningPath
    {
        $query = LearningPath::query();

        // Apply membership-based filtering
        if (!in_array($membership, ['super-admin', 'tenant-admin', 'team'])) {
            // Students can only see published content
            $query->where('status', 'published');
        }

        // Default relationships for students
        $defaultWith = [
            'language', // Legacy support
            'languagePair.sourceLanguage',
            'languagePair.targetLanguage',
            'units' => function ($query) {
                $query->orderBy('order');
            }
        ];

        $with = array_merge($defaultWith, $with);

        return $query->with($with)->find($learningPathId);
    }

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
            \Log::info('Filtering by target_level: ' . $request->target_level);
            $query->where('target_level', $request->target_level);
        }

        if ($request->has('language_id')) {
            // Support both legacy language_id and new language_pair filtering
            $query->forTargetLanguage($request->language_id);
        }

        if ($request->has('language_pair_id')) {
            $query->where('language_pair_id', $request->language_pair_id);
        }

        if ($request->has('source_language_id')) {
            $query->whereHas('languagePair', function ($q) use ($request) {
                $q->where('source_language_id', $request->source_language_id);
            });
        }

        if ($request->has('target_language_id')) {
            $query->forTargetLanguage($request->target_language_id);
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

        // Debug: Log the final query and results
        \Log::info('Final query SQL: ' . $query->toSql());
        \Log::info('Query bindings: ' . json_encode($query->getBindings()));

        $result = $query->paginate($perPage);
        \Log::info('Paginated result count: ' . $result->count());
        \Log::info('Total items: ' . $result->total());

        return $result;
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
     * Enroll user in a learning path.
     */
    public function enrollUser(LearningPath $learningPath, User $user): array
    {
        return DB::transaction(function () use ($learningPath, $user) {
            // Check if already enrolled
            $existingProgress = $learningPath->progress()
                ->where('user_id', $user->id)
                ->first();

            if ($existingProgress) {
                return [
                    'success' => false,
                    'message' => 'User is already enrolled in this learning path.',
                    'enrollment' => $existingProgress
                ];
            }

            // Create enrollment record
            $enrollment = $learningPath->progress()->create([
                'user_id' => $user->id,
                'status' => 'in_progress',
                'started_at' => now(),
                'completion_percentage' => 0
            ]);

            // Log enrollment
            AuditLog::log(
                'enroll',
                'learning_paths',
                $learningPath,
                [],
                ['user_id' => $user->id, 'enrolled_at' => now()],
                ['user_id' => $user->id]
            );

            return [
                'success' => true,
                'message' => 'Successfully enrolled in learning path.',
                'enrollment' => $enrollment
            ];
        });
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

    /**
     * Get learning paths for a specific target language.
     */
    public function getLearningPathsForLanguage(int $languageId, string $membership = 'student'): \Illuminate\Database\Eloquent\Collection
    {
        $query = LearningPath::forTargetLanguage($languageId);

        // Apply membership-based filtering
        if (!in_array($membership, ['super-admin', 'tenant-admin', 'team'])) {
            // Students can only see published content
            $query->where('status', 'published');
        }

        return $query->with([
            'language', // Legacy support
            'languagePair.sourceLanguage',
            'languagePair.targetLanguage',
            'units' => function ($query) {
                $query->orderBy('order');
            }
        ])
            ->orderBy('target_level')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Get learning paths for a specific language pair.
     */
    public function getLearningPathsForLanguagePair(int $languagePairId, string $membership = 'student'): \Illuminate\Database\Eloquent\Collection
    {
        $query = LearningPath::where('language_pair_id', $languagePairId);

        // Apply membership-based filtering
        if (!in_array($membership, ['super-admin', 'tenant-admin', 'team'])) {
            // Students can only see published content
            $query->where('status', 'published');
        }

        return $query->with([
            'languagePair.sourceLanguage',
            'languagePair.targetLanguage',
            'units' => function ($query) {
                $query->orderBy('order');
            }
        ])
            ->orderBy('target_level')
            ->orderBy('created_at')
            ->get();
    }
}
