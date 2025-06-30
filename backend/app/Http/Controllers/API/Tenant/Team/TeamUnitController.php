<?php

namespace App\Http\Controllers\API\Tenant\Team;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\Tenants\Course\UnitService;
use App\Services\Tenants\Course\TopicService;
use App\Services\Tenants\Course\ReviewService;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Exception;

/**
 * Team Unit Controller
 * 
 * Handles unit management operations for team members.
 * Access Level: Team (Teams/Content Creators)
 * Scope: Tenant-specific
 * 
 * This controller allows team members to create and manage units
 * within learning paths in their tenant scope.
 */
class TeamUnitController extends BaseAPIController
{
    use BelongsToTenant;

    protected UnitService $unitService;
    protected TopicService $topicService;
    protected ReviewService $reviewService;

    /**
     * Constructor - Apply team middleware
     */
    public function __construct(
        UnitService $unitService,
        TopicService $topicService,
        ReviewService $reviewService
    ) {
        $this->unitService = $unitService;
        $this->topicService = $topicService;
        $this->reviewService = $reviewService;

        // Note: We handle authorization manually in each method since we need to handle string IDs
    }

    /**
     * Display a listing of units.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Check if user is team member for team endpoints
        if (!$request->user()->isTeam()) {
            return $this->sendError('Access denied. Team membership required.', [], 403);
        }

        try {
            $units = $this->unitService->getFilteredUnits($request, 'team');
            return $this->sendResponse($units, 'Units retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendError('Failed to retrieve units.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Store a newly created unit.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        // Check if user is team member for team endpoints
        if (!$request->user()->isTeam()) {
            return $this->sendError('Access denied. Team membership required.', [], 403);
        }

        try {
            $validatedData = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'learning_path_id' => 'required|exists:learning_paths,id',
                'order' => 'nullable|integer|min:1',
                'estimated_duration_minutes' => 'nullable|integer|min:1',
                'objectives' => 'nullable|array',
                'prerequisites' => 'nullable|array',
            ]);

            $unit = $this->unitService->createUnit($validatedData, Auth::user());

            return $this->sendCreatedResponse($unit, 'Unit created successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Failed to create unit.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified unit.
     *
     * @param Request $request
     * @param string $tenant
     * @param string $unit
     * @return JsonResponse
     */
    public function show(Request $request, string $tenant, string $unit): JsonResponse
    {


        // Check if user is team member for team endpoints
        if (!$request->user()->isTeam()) {
            return $this->sendError('Access denied. Team membership required.', [], 403);
        }

        try {
            // Find the unit within the current tenant context
            $unitModel = Unit::with(['learningPath', 'topics'])->findOrFail($unit);

            $withRelations = [];

            if ($request->has('with_topics')) {
                $withRelations[] = 'topics';
            }
            if ($request->has('with_learning_path')) {
                $withRelations[] = 'learningPath';
            }
            if ($request->has('with_statistics')) {
                $withRelations[] = 'progress';
            }

            $unitData = $this->unitService->getUnit($unitModel->id, 'team', $withRelations);

            if (!$unitData) {
                return $this->sendError('Unit not found.', [], 404);
            }

            return $this->sendResponse($unitData, 'Unit retrieved successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('Unit not found.', [], 404);
        } catch (Exception $e) {
            return $this->sendError('Failed to retrieve unit.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Update the specified unit.
     *
     * @param Request $request
     * @param string $tenant
     * @param string $unit
     * @return JsonResponse
     */
    public function update(Request $request, string $tenant, string $unit): JsonResponse
    {
        // Check if user is team member for team endpoints
        if (!$request->user()->isTeam()) {
            return $this->sendError('Access denied. Team membership required.', [], 403);
        }

        try {
            // Find the unit within the current tenant context
            $unitModel = Unit::findOrFail($unit);

            $validatedData = $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'order' => 'nullable|integer|min:1',
                'estimated_duration_minutes' => 'nullable|integer|min:1',
                'objectives' => 'nullable|array',
                'prerequisites' => 'nullable|array',
            ]);

            $updatedUnit = $this->unitService->updateUnit($unitModel, $validatedData, Auth::user());

            return $this->sendResponse($updatedUnit, 'Unit updated successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('Unit not found.', [], 404);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Failed to update unit.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified unit.
     *
     * @param Request $request
     * @param string $tenant
     * @param string $unit
     * @return JsonResponse
     */
    public function destroy(Request $request, string $tenant, string $unit): JsonResponse
    {
        // Check if user is team member for team endpoints
        if (!$request->user()->isTeam()) {
            return $this->sendError('Access denied. Team membership required.', [], 403);
        }

        try {
            // Find the unit within the current tenant context
            $unitModel = Unit::findOrFail($unit);

            $deleted = $this->unitService->deleteUnit($unitModel, Auth::user());

            if (!$deleted) {
                return $this->sendError('Failed to delete unit. It may have dependent content.');
            }

            return $this->sendNoContentResponse();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('Unit not found.', [], 404);
        } catch (Exception $e) {
            return $this->sendError('Failed to delete unit.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Submit unit for review.
     *
     * @param Request $request
     * @param string $tenant
     * @param string $unit
     * @return JsonResponse
     */
    public function submitForReview(Request $request, string $tenant, string $unit): JsonResponse
    {
        try {
            // Find the unit within the current tenant context
            $unitModel = Unit::findOrFail($unit);
            $this->authorize('update', $unitModel);

            $validatedData = $request->validate([
                'review_notes' => 'nullable|string|max:1000',
            ]);

            // Check if unit has minimum required content
            if (!$unitModel->topics()->exists()) {
                return $this->sendError('Cannot submit for review. Unit must have at least one topic.');
            }

            // Update review status instead of main status
            $unitModel->review_status = 'pending_review';
            $unitModel->save();
            $updatedUnit = $unitModel;

            // Create review entry if review notes provided
            if (!empty($validatedData['review_notes'])) {
                $this->reviewService->createReview([
                    'content_type' => 'unit',
                    'content_id' => $unitModel->id,
                    'reviewer_id' => Auth::id(),
                    'status' => 'pending',
                    'comment' => $validatedData['review_notes'],
                ], Auth::user());
            }

            return $this->sendResponse($updatedUnit, 'Unit submitted for review successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('Unit not found.', [], 404);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Failed to submit for review.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Update unit status.
     *
     * @param Request $request
     * @param string $tenant
     * @param string $unit
     * @return JsonResponse
     */
    public function updateStatus(Request $request, string $tenant, string $unit): JsonResponse
    {
        try {
            // Find the unit within the current tenant context
            $unitModel = Unit::findOrFail($unit);
            $this->authorize('update', $unitModel);

            $validatedData = $request->validate([
                'status' => 'required|string|in:draft,under_review,published,archived',
                'status_notes' => 'nullable|string|max:1000',
            ]);

            $updatedUnit = $this->unitService->updateStatus($unitModel, $validatedData['status'], Auth::user());

            // Log status change with notes if provided
            if (!empty($validatedData['status_notes'])) {
                $this->reviewService->createReview([
                    'content_type' => 'unit',
                    'content_id' => $unitModel->id,
                    'reviewer_id' => Auth::id(),
                    'status' => 'completed',
                    'comment' => $validatedData['status_notes'],
                ], Auth::user());
            }

            return $this->sendResponse($updatedUnit, 'Unit status updated successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('Unit not found.', [], 404);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Failed to update status.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Reorder topics within unit.
     *
     * @param Request $request
     * @param string $tenant
     * @param string $unit
     * @return JsonResponse
     */
    public function reorderTopics(Request $request, string $tenant, string $unit): JsonResponse
    {
        // Check if user is team member for team endpoints
        if (!$request->user()->isTeam()) {
            return $this->sendError('Access denied. Team membership required.', [], 403);
        }

        try {
            // Find the unit within the current tenant context
            $unitModel = Unit::findOrFail($unit);

            $validatedData = $request->validate([
                'topic_ids' => 'required|array|min:1',
                'topic_ids.*' => 'required|integer|exists:topics,id',
            ]);

            // Convert topic IDs array to associative array with order
            $topicOrders = [];
            foreach ($validatedData['topic_ids'] as $index => $topicId) {
                $topicOrders[$topicId] = $index + 1;
            }

            $success = $this->topicService->reorderTopics(
                $unitModel->id,
                $topicOrders,
                Auth::user()
            );

            if (!$success) {
                return $this->sendError('Failed to reorder topics. Please verify all topics belong to this unit.');
            }

            return $this->sendResponse(['reordered_topics' => $validatedData['topic_ids']], 'Topics reordered successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('Unit not found.', [], 404);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Failed to reorder topics.', ['error' => $e->getMessage()]);
        }
    }
}
