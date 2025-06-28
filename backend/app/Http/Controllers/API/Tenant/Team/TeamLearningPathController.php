<?php

namespace App\Http\Controllers\API\Tenant\Team;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\Tenants\Course\LearningPathService;
use App\Services\Tenants\Course\ReviewService;
use App\Services\Tenants\Course\UnitService;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\LearningPath;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Exception;

/**
 * Team Learning Path Controller
 *
 * Handles learning path management operations for team members.
 * Access Level: Team (Teams/Content Creators)
 * Scope: Tenant-specific
 *
 * This controller allows team members to create and manage learning paths
 * within their tenant scope, including content organization and review workflows.
 */
class TeamLearningPathController extends BaseAPIController
{
    use BelongsToTenant;

    protected LearningPathService $learningPathService;
    protected ReviewService $reviewService;
    protected UnitService $unitService;

    /**
     * Constructor - Apply team middleware
     */
    public function __construct(
        LearningPathService $learningPathService,
        ReviewService $reviewService,
        UnitService $unitService
    ) {
        $this->learningPathService = $learningPathService;
        $this->reviewService = $reviewService;
        $this->unitService = $unitService;

        // Apply policies
        $this->authorizeResource(LearningPath::class, 'learningPath');
    }

    /**
     * Display a listing of learning paths.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LearningPath::class);

        try {
            $learningPaths = $this->learningPathService->getFilteredLearningPaths($request, 'team');
            return $this->sendResponse($learningPaths, 'Learning paths retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendError('Failed to retrieve learning paths.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Store a newly created learning path.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', LearningPath::class);

        try {
            $validatedData = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'language_id' => 'nullable|exists:languages,id', // Legacy support
                'language_pair_id' => 'nullable|exists:language_pairs,id',
                'target_level' => 'required|string|in:beginner,elementary,intermediate,upper_intermediate,advanced,proficiency',
                'estimated_duration_hours' => 'nullable|integer|min:1',
                'difficulty_level' => 'nullable|integer|between:1,10',
                'prerequisites' => 'nullable|array',
                'learning_objectives' => 'nullable|array',
                'tags' => 'nullable|array',
            ]);

            // Ensure either language_id or language_pair_id is provided
            if (!$request->has('language_id') && !$request->has('language_pair_id')) {
                return $this->sendError('Either language_id or language_pair_id must be provided.', [], 422);
            }

            $learningPath = $this->learningPathService->createLearningPath($validatedData, Auth::user());

            return $this->sendCreatedResponse($learningPath, 'Learning path created successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Failed to create learning path.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified learning path.
     * 
     * @param Request $request
     * @param LearningPath $learningPath
     * @return JsonResponse
     */
    public function show(Request $request, LearningPath $learningPath): JsonResponse
    {
        $this->authorize('view', $learningPath);

        try {
            $withRelations = [];

            if ($request->has('with_units')) {
                $withRelations[] = 'units';
            }
            if ($request->has('with_language')) {
                $withRelations[] = 'language';
            }
            if ($request->has('with_reviews')) {
                $withRelations[] = 'reviews';
            }
            if ($request->has('with_statistics')) {
                $withRelations[] = 'enrollments';
            }

            $learningPath = $this->learningPathService->getLearningPath(
                $learningPath->id,
                'team',
                $withRelations
            );

            if (!$learningPath) {
                return $this->sendError('Learning path not found.', [], 404);
            }

            return $this->sendResponse($learningPath, 'Learning path retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendError('Failed to retrieve learning path.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Update the specified learning path.
     * 
     * @param Request $request
     * @param LearningPath $learningPath
     * @return JsonResponse
     */
    public function update(Request $request, LearningPath $learningPath): JsonResponse
    {
        $this->authorize('update', $learningPath);

        try {
            $validatedData = $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'target_level' => 'sometimes|required|string|in:beginner,elementary,intermediate,upper_intermediate,advanced,proficiency',
                'estimated_duration_hours' => 'nullable|integer|min:1',
                'difficulty_level' => 'nullable|integer|between:1,10',
                'prerequisites' => 'nullable|array',
                'learning_objectives' => 'nullable|array',
                'tags' => 'nullable|array',
            ]);

            $updatedLearningPath = $this->learningPathService->updateLearningPath(
                $learningPath,
                $validatedData,
                Auth::user()
            );

            return $this->sendResponse($updatedLearningPath, 'Learning path updated successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Failed to update learning path.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified learning path.
     * 
     * @param Request $request
     * @param LearningPath $learningPath
     * @return JsonResponse
     */
    public function destroy(Request $request, LearningPath $learningPath): JsonResponse
    {
        $this->authorize('delete', $learningPath);

        try {
            $deleted = $this->learningPathService->deleteLearningPath($learningPath, Auth::user());

            if (!$deleted) {
                return $this->sendError('Failed to delete learning path. It may have active enrollments.');
            }

            return $this->sendNoContentResponse();
        } catch (Exception $e) {
            return $this->sendError('Failed to delete learning path.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Submit learning path for review.
     * 
     * @param Request $request
     * @param LearningPath $learningPath
     * @return JsonResponse
     */
    public function submitForReview(Request $request, LearningPath $learningPath): JsonResponse
    {
        $this->authorize('update', $learningPath);

        try {
            $validatedData = $request->validate([
                'review_notes' => 'nullable|string|max:1000',
            ]);

            // Check if learning path has minimum required content
            if (!$learningPath->units()->exists()) {
                return $this->sendError('Cannot submit for review. Learning path must have at least one unit.');
            }

            $updatedLearningPath = $this->learningPathService->updateStatus(
                $learningPath,
                'under_review',
                Auth::user()
            );

            // Create review entry if review notes provided
            if (!empty($validatedData['review_notes'])) {
                $this->reviewService->createReview([
                    'content_type' => 'learning_path',
                    'content_id' => $learningPath->id,
                    'reviewer_id' => Auth::id(),
                    'status' => 'pending',
                    'comment' => $validatedData['review_notes'],
                ], Auth::user());
            }

            return $this->sendResponse($updatedLearningPath, 'Learning path submitted for review successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Failed to submit for review.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Update learning path status.
     * 
     * @param Request $request
     * @param LearningPath $learningPath
     * @return JsonResponse
     */
    public function updateStatus(Request $request, LearningPath $learningPath): JsonResponse
    {
        $this->authorize('update', $learningPath);

        try {
            $validatedData = $request->validate([
                'status' => 'required|string|in:draft,under_review,published,archived',
                'status_notes' => 'nullable|string|max:1000',
            ]);

            $updatedLearningPath = $this->learningPathService->updateStatus(
                $learningPath,
                $validatedData['status'],
                Auth::user()
            );

            // Log status change with notes if provided
            if (!empty($validatedData['status_notes'])) {
                $this->reviewService->createReview([
                    'content_type' => 'learning_path',
                    'content_id' => $learningPath->id,
                    'reviewer_id' => Auth::id(),
                    'status' => 'completed',
                    'comment' => $validatedData['status_notes'],
                ], Auth::user());
            }

            return $this->sendResponse($updatedLearningPath, 'Learning path status updated successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Failed to update status.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Reorder units within learning path.
     * 
     * @param Request $request
     * @param LearningPath $learningPath
     * @return JsonResponse
     */
    public function reorderUnits(Request $request, LearningPath $learningPath): JsonResponse
    {
        $this->authorize('update', $learningPath);

        try {
            $validatedData = $request->validate([
                'unit_ids' => 'required|array|min:1',
                'unit_ids.*' => 'required|integer|exists:units,id',
            ]);

            $success = $this->unitService->reorderUnits(
                $learningPath,
                $validatedData['unit_ids'],
                Auth::user()
            );

            if (!$success) {
                return $this->sendError('Failed to reorder units. Please verify all units belong to this learning path.');
            }

            return $this->sendResponse([], 'Units reordered successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Failed to reorder units.', ['error' => $e->getMessage()]);
        }
    }
}
