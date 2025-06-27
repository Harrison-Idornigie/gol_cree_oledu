<?php

namespace App\Http\Controllers\API\Tenant\Student;

use App\Http\Controllers\API\BaseAPIController;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Services\Tenants\Analytics\ProgressService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;
use App\Models\Tenants\UserProgress;
use App\Models\Tenants\Lesson;

/**
 * Student User Progress Controller
 * 
 * Handles progress tracking for students.
 * Access Level: Student
 * Scope: Tenant-specific
 * 
 * This controller allows students to track and update their
 * learning progress across all content types.
 */
class StudentUserProgressController extends BaseAPIController
{
    use BelongsToTenant;

    protected ProgressService $progressService;

    /**
     * Constructor - Apply student middleware
     */
    public function __construct(ProgressService $progressService)
    {
        $this->progressService = $progressService;
    }

    /**
     * Display student's overall progress.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Students can only view their own progress
        $this->authorize('viewOwnProfile', $request->user());

        try {
            $user = $request->user();

            // Use existing ProgressService method
            $myProgress = $this->progressService->getMyContentProgress($user->id, $request);

            return $this->sendResponse($myProgress, 'Progress overview retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendError('Failed to retrieve progress overview', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store progress for specific content.
     *
     * @param Request $request
     * @param string $tenant
     * @param string $type
     * @param string $id
     * @return JsonResponse
     */
    public function store(Request $request, string $tenant, string $type, string $id): JsonResponse
    {
        // Students can only create their own progress records
        $this->authorize('updateOwnProfile', $request->user());

        $request->validate([
            'completion_percentage' => 'required|numeric|min:0|max:100',
            'time_spent' => 'nullable|integer|min:0',
            'score' => 'nullable|numeric|min:0|max:100',
            'metadata' => 'nullable|array'
        ]);

        try {
            // Determine the trackable model class based on type
            $trackableClass = match ($type) {
                'lesson' => Lesson::class,
                default => throw new Exception("Unsupported content type: {$type}")
            };

            // Create or update progress record
            $progress = UserProgress::updateOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'trackable_type' => $trackableClass,
                    'trackable_id' => (int) $id,
                ],
                [
                    'status' => $request->completion_percentage >= 100 ? UserProgress::STATUS_COMPLETED : UserProgress::STATUS_IN_PROGRESS,
                    'meta_data' => [
                        'completion_percentage' => $request->completion_percentage,
                        'time_spent_minutes' => $request->time_spent ?? 0,
                        'score' => $request->score,
                        'metadata' => $request->metadata ?? []
                    ],
                    'completed_at' => $request->completion_percentage >= 100 ? now() : null,
                ]
            );

            // Return response data in the format expected by the API
            $progressData = [
                'user_id' => $progress->user_id,
                'content_type' => $type,
                'content_id' => (int) $id,
                'completion_percentage' => $request->completion_percentage,
                'time_spent_minutes' => $request->time_spent ?? 0,
                'score' => $request->score,
                'completed_at' => $progress->completed_at,
                'metadata' => $request->metadata ?? []
            ];

            return $this->sendCreatedResponse($progressData, 'Progress recorded successfully.');
        } catch (Exception $e) {
            return $this->sendError('Failed to record progress', ['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Display progress for specific content.
     *
     * @param Request $request
     * @param string $tenant
     * @param string $type
     * @param string $id
     * @return JsonResponse
     */
    public function show(Request $request, string $tenant, string $type, string $id): JsonResponse
    {
        // Students can only view their own progress
        $this->authorize('viewOwnProfile', $request->user());

        try {
            $user = $request->user();

            // Use existing ProgressService method
            $progress = $this->progressService->getContentProgressDetails($user->id, $type, $id, $request);

            return $this->sendResponse($progress, 'Progress retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendError('Failed to retrieve progress', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update progress for specific content.
     *
     * @param Request $request
     * @param string $tenant
     * @param string $type
     * @param string $id
     * @return JsonResponse
     */
    public function update(Request $request, string $tenant, string $type, string $id): JsonResponse
    {
        // Students can only update their own progress
        $this->authorize('updateOwnProfile', $request->user());

        $request->validate([
            'completion_percentage' => 'nullable|numeric|min:0|max:100',
            'time_spent' => 'nullable|integer|min:0',
            'score' => 'nullable|numeric|min:0|max:100',
            'metadata' => 'nullable|array'
        ]);

        try {
            // Determine the trackable model class based on type
            $trackableClass = match ($type) {
                'lesson' => Lesson::class,
                default => throw new Exception("Unsupported content type: {$type}")
            };

            // Find existing progress record
            $progress = UserProgress::where([
                'user_id' => $request->user()->id,
                'trackable_type' => $trackableClass,
                'trackable_id' => (int) $id,
            ])->first();

            if (!$progress) {
                return $this->sendError('Progress record not found', [], 404);
            }

            // Update progress record
            $updateData = [];
            $metaData = $progress->meta_data ?? [];

            if ($request->has('completion_percentage')) {
                $metaData['completion_percentage'] = $request->completion_percentage;
                $updateData['status'] = $request->completion_percentage >= 100 ? UserProgress::STATUS_COMPLETED : UserProgress::STATUS_IN_PROGRESS;
                $updateData['completed_at'] = $request->completion_percentage >= 100 ? now() : null;
            }

            if ($request->has('time_spent')) {
                $metaData['time_spent_minutes'] = $request->time_spent;
            }

            if ($request->has('score')) {
                $metaData['score'] = $request->score;
            }

            if ($request->has('metadata')) {
                $metaData['metadata'] = $request->metadata;
            }

            $updateData['meta_data'] = $metaData;
            $progress->update($updateData);

            // Return response data in the format expected by the API
            $responseData = [
                'user_id' => $progress->user_id,
                'content_type' => $type,
                'content_id' => (int) $id,
                'completion_percentage' => $metaData['completion_percentage'] ?? null,
                'time_spent_minutes' => $metaData['time_spent_minutes'] ?? null,
                'score' => $metaData['score'] ?? null,
                'completed_at' => $progress->completed_at,
                'metadata' => $metaData['metadata'] ?? []
            ];

            return $this->sendResponse($responseData, 'Progress updated successfully.');
        } catch (Exception $e) {
            return $this->sendError('Failed to update progress', ['error' => $e->getMessage()], 422);
        }
    }
}
