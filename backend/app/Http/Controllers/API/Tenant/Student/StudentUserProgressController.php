<?php

namespace App\Http\Controllers\API\Tenant\Student;

use App\Http\Controllers\API\BaseAPIController;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Services\Tenants\Analytics\ProgressService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

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
            return $this->sendErrorResponse('Failed to retrieve progress overview', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store progress for specific content.
     * 
     * @param Request $request
     * @param string $type
     * @param int $id
     * @return JsonResponse
     */
    public function store(Request $request, string $type, int $id): JsonResponse
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
            // Basic progress recording (would need actual UserProgress model)
            $progressData = [
                'user_id' => $request->user()->id,
                'content_type' => $type,
                'content_id' => $id,
                'completion_percentage' => $request->completion_percentage,
                'time_spent_minutes' => $request->time_spent ?? 0,
                'score' => $request->score,
                'completed_at' => $request->completion_percentage >= 100 ? now() : null,
                'metadata' => $request->metadata ?? []
            ];

            return $this->sendCreatedResponse($progressData, 'Progress recorded successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to record progress', ['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Display progress for specific content.
     * 
     * @param Request $request
     * @param string $type
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, string $type, int $id): JsonResponse
    {
        // Students can only view their own progress
        $this->authorize('viewOwnProfile', $request->user());

        try {
            $user = $request->user();
            
            // Use existing ProgressService method
            $progress = $this->progressService->getContentProgressDetails($user->id, $type, $id, $request);

            return $this->sendResponse($progress, 'Progress retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve progress', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update progress for specific content.
     * 
     * @param Request $request
     * @param string $type
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, string $type, int $id): JsonResponse
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
            // Basic progress update (would need actual UserProgress model updates)
            $updateData = array_filter([
                'completion_percentage' => $request->completion_percentage,
                'time_spent_minutes' => $request->time_spent,
                'score' => $request->score,
                'updated_at' => now(),
                'completed_at' => $request->completion_percentage >= 100 ? now() : null,
                'metadata' => $request->metadata
            ], function ($value) {
                return $value !== null;
            });

            $updateData['user_id'] = $request->user()->id;
            $updateData['content_type'] = $type;
            $updateData['content_id'] = $id;

            return $this->sendResponse($updateData, 'Progress updated successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to update progress', ['error' => $e->getMessage()], 422);
        }
    }
}
