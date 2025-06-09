<?php
namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Http\Controllers\API\UnitController as BaseUnitController;
use App\Http\Requests\API\Unit\StoreUnitRequest;
use App\Http\Requests\API\Unit\UpdateUnitRequest;
use App\Models\AuditLog;
use App\Models\LearningPath;
use App\Models\Unit;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminUnitController extends BaseAPIController
{
    /**
     * The base unit controller instance.
     */
    protected $baseUnitController;

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->baseUnitController = new BaseUnitController();
    }

    /**
     * Display a listing of all units for admin.
     * Admins can see all units including drafts and archived.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Unit::query();

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('learning_path_id')) {
                $query->whereHas('learningPaths', function ($query) use ($request) {
                    $query->where('learning_paths.id', $request->learning_path_id);
                });
            }

            // Include relationships if requested
            if ($request->has('with_topics')) {
                $query->with(['topics' => function ($query) {
                    $query->orderBy('order');
                }]);
            }

            if ($request->has('with_lessons')) {
                $query->with(['topics.lessons' => function ($query) {
                    $query->orderBy('order');
                }]);
            }

            if ($request->has('with_learning_paths')) {
                $query->with('learningPaths');
            }

            $perPage = $request->input('per_page', 15);
            $units   = $query->paginate($perPage);

            return $this->sendPaginatedResponse($units);
        } catch (Exception $e) {
            Log::error('Failed to fetch units: ' . $e->getMessage(), [
                'trace'   => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            return $this->sendError('Failed to fetch units', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created unit.
     */
    public function store(StoreUnitRequest $request): JsonResponse
    {
        try {
            $unit = Unit::create($request->validated());

            // Attach to learning path if specified
            if ($request->has('learning_path_id')) {
                $learningPath = LearningPath::findOrFail($request->learning_path_id);

                // Get the highest order in the learning path
                $maxOrder = $learningPath->units()->max('order') ?? 0;

                // Attach with the next order
                $learningPath->units()->attach($unit->id, ['order' => $maxOrder + 1]);
            }

            // Log the creation for audit trail
            AuditLog::log(
                'create',
                'units',
                $unit,
                [],
                $request->validated()
            );

            return $this->sendCreatedResponse($unit, 'Unit created successfully.');
        } catch (Exception $e) {
            Log::error('Failed to create unit: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data'  => $request->validated(),
            ]);
            return $this->sendError('Failed to create unit', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified unit for admin.
     * Admins can see additional information like version history.
     */
    public function show(Request $request, Unit $unit): JsonResponse
    {
        try {
            // Load relationships if requested
            if ($request->has('with_topics')) {
                $unit->load(['topics' => function ($query) {
                    $query->orderBy('order');
                }]);
            }

            if ($request->has('with_lessons')) {
                $unit->load(['topics.lessons' => function ($query) {
                    $query->orderBy('order');
                }]);
            }

            if ($request->has('with_learning_paths')) {
                $unit->load('learningPaths');
            }

            if ($request->has('with_versions')) {
                // Load version history if requested
                $unit->load('versions');
            }

            if ($request->has('with_reviews')) {
                // Load reviews if requested
                $unit->load('reviews');
            }

            return $this->sendResponse($unit);
        } catch (Exception $e) {
            Log::error('Failed to fetch unit: ' . $e->getMessage(), [
                'trace'   => $e->getTraceAsString(),
                'unit_id' => $unit->id,
            ]);
            return $this->sendError('Failed to fetch unit', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified unit.
     */
    public function update(UpdateUnitRequest $request, Unit $unit): JsonResponse
    {
        try {
            $oldData = $unit->toArray();
            $unit->update($request->validated());

            // Log the update for audit trail
            AuditLog::logChange(
                $unit,
                'update',
                $oldData,
                $unit->toArray()
            );

            return $this->sendResponse($unit, 'Unit updated successfully.');
        } catch (Exception $e) {
            Log::error('Failed to update unit: ' . $e->getMessage(), [
                'trace'   => $e->getTraceAsString(),
                'unit_id' => $unit->id,
                'data'    => $request->validated(),
            ]);
            return $this->sendError('Failed to update unit', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified unit.
     * Admins can delete units that aren't published.
     */
    public function destroy(Request $request, Unit $unit): JsonResponse
    {
        try {
            // Prevent deletion of published units
            if ($unit->status === 'published') {
                return $this->sendError('Cannot delete a published unit. Archive it first.', ['status' => 422]);
            }

            $data = $unit->toArray();

            // Detach from all learning paths
            $unit->learningPaths()->detach();

            $unit->delete();

            // Log the deletion for audit trail
            AuditLog::log(
                'delete',
                'units',
                $unit,
                $data,
                []
            );

            return $this->sendNoContentResponse();
        } catch (Exception $e) {
            Log::error('Failed to delete unit: ' . $e->getMessage(), [
                'trace'   => $e->getTraceAsString(),
                'unit_id' => $unit->id,
            ]);
            return $this->sendError('Failed to delete unit', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update the status of a unit.
     * Admin-specific method to change unit status.
     */
    public function updateStatus(Request $request, Unit $unit): JsonResponse
    {
        try {
            $request->validate([
                'status' => ['required', 'string', 'in:draft,published,archived'],
            ]);

            $oldStatus    = $unit->status;
            $unit->status = $request->status;
            $unit->save();

            // Log the status change for audit trail
            AuditLog::log(
                'status_update',
                'units',
                $unit,
                ['status' => $oldStatus],
                ['status' => $request->status]
            );

            return $this->sendResponse($unit, 'Unit status updated successfully.');
        } catch (Exception $e) {
            Log::error('Failed to update unit status: ' . $e->getMessage(), [
                'trace'   => $e->getTraceAsString(),
                'unit_id' => $unit->id,
                'status'  => $request->status ?? null,
            ]);
            return $this->sendError('Failed to update unit status', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Reorder topics within a unit.
     * Admin-specific method to reorder topics.
     */
    public function reorderTopics(Request $request, Unit $unit): JsonResponse
    {
        try {
            $request->validate([
                'topic_ids'   => ['required', 'array'],
                'topic_ids.*' => ['exists:topics,id'],
            ]);

            $topicIds = $request->topic_ids;

            // Update the order of each topic
            foreach ($topicIds as $index => $topicId) {
                $unit->topics()->where('id', $topicId)->update(['order' => $index + 1]);
            }

            return $this->sendResponse($unit->load('topics'), 'Topics reordered successfully.');
        } catch (Exception $e) {
            Log::error('Failed to reorder topics: ' . $e->getMessage(), [
                'trace'     => $e->getTraceAsString(),
                'unit_id'   => $unit->id,
                'topic_ids' => $request->topic_ids ?? [],
            ]);
            return $this->sendError('Failed to reorder topics', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Submit a unit for review.
     */
    public function submitForReview(Request $request, Unit $unit): JsonResponse
    {
        try {
            // Validate that the unit is in draft status
            if ($unit->status !== 'draft') {
                return $this->sendError('Only draft units can be submitted for review.', ['error' => 'Only draft units can be submitted for review.'], 422);
            }

            // Update the unit status to 'in_review'
            $unit->review_status = 'pending';
            $unit->save();

            // Create a review record
            $review = $unit->reviews()->create([
                'submitted_by' => $request->user()->id,
                'status'       => 'pending',
                'submitted_at' => now(),
            ]);

            // Notify reviewers (to be implemented)
            // $this->notifyReviewers($unit, $review);

            // Log the review submission
            AuditLog::log(
                'submit_for_review',
                'units',
                $unit,
                [],
                ['review_id' => $review->id]
            );

            return $this->sendResponse($unit, 'Unit submitted for review successfully.');
        } catch (Exception $e) {
            Log::error('Failed to submit unit for review: ' . $e->getMessage(), [
                'trace'   => $e->getTraceAsString(),
                'unit_id' => $unit->id,
                'user_id' => $request->user() ? $request->user()->id : null,
            ]);
            return $this->sendError('Failed to submit unit for review', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Approve a unit review.
     */
    public function approveReview(Request $request, Unit $unit): JsonResponse
    {
        try {
            // Validate that the unit is in review status
            if ($unit->review_status !== 'pending') {
                return $this->sendError('This unit is not pending review.', ['error' => 'This unit is not pending review.'], 422);
            }

            $request->validate([
                'review_comment' => ['nullable', 'string', 'max:1000'],
            ]);

            // Get the latest pending review
            $review = $unit->reviews()->where('status', 'pending')->latest()->first();

            if (! $review) {
                return $this->sendError('No pending review found for this unit.', ['error' => 'No pending review found for this unit.'], 404);
            }

            // Update the review
            $review->update([
                'reviewed_by'    => $request->user()->id,
                'status'         => 'approved',
                'review_comment' => $request->review_comment,
                'reviewed_at'    => now(),
            ]);

            // Update the unit status
            $unit->review_status = 'approved';
            $unit->save();

            // Log the review approval
            AuditLog::log(
                'review_approved',
                'units',
                $unit,
                [],
                ['review_id' => $review->id]
            );

            return $this->sendResponse($unit, 'Unit review approved successfully.');
        } catch (Exception $e) {
            Log::error('Failed to approve unit review: ' . $e->getMessage(), [
                'trace'   => $e->getTraceAsString(),
                'unit_id' => $unit->id,
                'user_id' => $request->user() ? $request->user()->id : null,
            ]);
            return $this->sendError('Failed to approve unit review', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Reject a unit review.
     */
    public function rejectReview(Request $request, Unit $unit): JsonResponse
    {
        try {
            // Validate that the unit is in review status
            if ($unit->review_status !== 'pending') {
                return $this->sendError('This unit is not pending review.', ['error' => 'This unit is not pending review.'], 422);
            }

            $request->validate([
                'review_comment'   => ['required', 'string', 'max:1000'],
                'rejection_reason' => ['required', 'string', 'in:content_issues,formatting_issues,accuracy_issues,other'],
            ]);

            // Get the latest pending review
            $review = $unit->reviews()->where('status', 'pending')->latest()->first();

            if (! $review) {
                return $this->sendError('No pending review found for this unit.', ['error' => 'No pending review found for this unit.'], 404);
            }

            // Update the review
            $review->update([
                'reviewed_by'      => $request->user()->id,
                'status'           => 'rejected',
                'review_comment'   => $request->review_comment,
                'rejection_reason' => $request->rejection_reason,
                'reviewed_at'      => now(),
            ]);

            // Update the unit status
            $unit->review_status = 'rejected';
            $unit->save();

            // Log the review rejection
            AuditLog::log(
                'review_rejected',
                'units',
                $unit,
                [],
                [
                    'review_id'        => $review->id,
                    'rejection_reason' => $request->rejection_reason,
                ]
            );

            return $this->sendResponse($unit, 'Unit review rejected successfully.');
        } catch (Exception $e) {
            Log::error('Failed to reject unit review: ' . $e->getMessage(), [
                'trace'            => $e->getTraceAsString(),
                'unit_id'          => $unit->id,
                'user_id'          => $request->user() ? $request->user()->id : null,
                'rejection_reason' => $request->rejection_reason ?? null,
            ]);
            return $this->sendError('Failed to reject unit review', ['error' => $e->getMessage()], 500);
        }
    }
}