<?php
namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\AuditLog;
use App\Models\Topic;
use App\Models\Unit;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminTopicController extends BaseAPIController
{
    /**
     * Display a listing of all topics for admin.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Topic::query();

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('unit_id')) {
                $query->where('unit_id', $request->unit_id);
            }

            // Include relationships if requested
            if ($request->has('with_lessons')) {
                $query->with(['lessons' => function ($query) {
                    $query->orderBy('order');
                }]);
            }

            if ($request->has('with_unit')) {
                $query->with('unit');
            }

            $perPage = $request->input('per_page', 15);
            $topics  = $query->paginate($perPage);

            return $this->sendPaginatedResponse($topics);
        } catch (Exception $e) {
            Log::error('Failed to fetch topics: ' . $e->getMessage(), [
                'trace'   => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            return $this->sendError('Failed to fetch topics', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created topic.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'unit_id'     => 'required|exists:units,id',
                'title'       => 'required|string|max:255',
                'slug'        => 'nullable|string|max:255|unique:topics,slug',
                'description' => 'nullable|string',
                'icon'        => 'nullable|string|max:255',
                'color'       => 'nullable|string|max:50',
                'order'       => 'nullable|integer',
                'status'      => 'nullable|string|in:draft,published,archived',
                'xp_reward'   => 'nullable|integer',
                'max_level'   => 'nullable|integer',
                'is_bonus'    => 'nullable|boolean',
                'metadata'    => 'nullable|array',
            ]);

            // Generate slug if not provided
            if (! isset($validated['slug'])) {
                $validated['slug'] = \Illuminate\Support\Str::slug($validated['title']);
            }

            // Set default order if not provided
            if (! isset($validated['order'])) {
                $unit               = Unit::findOrFail($validated['unit_id']);
                $validated['order'] = $unit->topics()->max('order') + 1 ?? 1;
            }

            $topic = Topic::create($validated);

            // Log the creation for audit trail
            AuditLog::log(
                'create',
                'topics',
                $topic,
                [],
                $validated
            );

            return $this->sendCreatedResponse($topic, 'Topic created successfully.');
        } catch (Exception $e) {
            Log::error('Failed to create topic: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data'  => $request->all(),
            ]);
            return $this->sendError('Failed to create topic', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified topic for admin.
     */
    public function show(Request $request, Topic $topic): JsonResponse
    {
        try {
            // Load relationships if requested
            if ($request->has('with_lessons')) {
                $topic->load(['lessons' => function ($query) {
                    $query->orderBy('order');
                }]);
            }

            if ($request->has('with_unit')) {
                $topic->load('unit');
            }

            if ($request->has('with_versions')) {
                // Load version history if requested
                $topic->load('versions');
            }

            return $this->sendResponse($topic);
        } catch (Exception $e) {
            Log::error('Failed to fetch topic: ' . $e->getMessage(), [
                'trace'    => $e->getTraceAsString(),
                'topic_id' => $topic->id,
            ]);
            return $this->sendError('Failed to fetch topic', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified topic.
     */
    public function update(Request $request, Topic $topic): JsonResponse
    {
        try {
            $validated = $request->validate([
                'unit_id'     => 'sometimes|exists:units,id',
                'title'       => 'sometimes|string|max:255',
                'slug'        => 'sometimes|string|max:255|unique:topics,slug,' . $topic->id,
                'description' => 'nullable|string',
                'icon'        => 'nullable|string|max:255',
                'color'       => 'nullable|string|max:50',
                'order'       => 'nullable|integer',
                'status'      => 'nullable|string|in:draft,published,archived',
                'xp_reward'   => 'nullable|integer',
                'max_level'   => 'nullable|integer',
                'is_bonus'    => 'nullable|boolean',
                'metadata'    => 'nullable|array',
            ]);

            $oldData = $topic->toArray();
            $topic->update($validated);

            // Log the update for audit trail
            AuditLog::logChange(
                $topic,
                'update',
                $oldData,
                $topic->toArray()
            );

            return $this->sendResponse($topic, 'Topic updated successfully.');
        } catch (Exception $e) {
            Log::error('Failed to update topic: ' . $e->getMessage(), [
                'trace'    => $e->getTraceAsString(),
                'topic_id' => $topic->id,
                'data'     => $request->all(),
            ]);
            return $this->sendError('Failed to update topic', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified topic.
     */
    public function destroy(Request $request, Topic $topic): JsonResponse
    {
        try {
            // Prevent deletion of published topics
            if ($topic->status === 'published') {
                return $this->sendError('Cannot delete a published topic. Archive it first.', ['status' => 422]);
            }

            $data = $topic->toArray();

            DB::transaction(function () use ($topic, $data) {
                $topic->delete();

                // Log the deletion for audit trail
                AuditLog::log(
                    'delete',
                    'topics',
                    $topic,
                    $data,
                    []
                );
            });

            return $this->sendNoContentResponse();
        } catch (Exception $e) {
            Log::error('Failed to delete topic: ' . $e->getMessage(), [
                'trace'    => $e->getTraceAsString(),
                'topic_id' => $topic->id,
            ]);
            return $this->sendError('Failed to delete topic', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update the status of a topic.
     */
    public function updateStatus(Request $request, Topic $topic): JsonResponse
    {
        try {
            $request->validate([
                'status' => ['required', 'string', 'in:draft,published,archived'],
            ]);

            $oldStatus     = $topic->status;
            $topic->status = $request->status;
            $topic->save();

            // Log the status change for audit trail
            AuditLog::log(
                'status_update',
                'topics',
                $topic,
                ['status' => $oldStatus],
                ['status' => $request->status]
            );

            return $this->sendResponse($topic, 'Topic status updated successfully.');
        } catch (Exception $e) {
            Log::error('Failed to update topic status: ' . $e->getMessage(), [
                'trace'    => $e->getTraceAsString(),
                'topic_id' => $topic->id,
                'status'   => $request->status ?? null,
            ]);
            return $this->sendError('Failed to update topic status', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Reorder lessons within a topic.
     */
    public function reorderLessons(Request $request, Topic $topic): JsonResponse
    {
        try {
            $request->validate([
                'lesson_ids'   => ['required', 'array'],
                'lesson_ids.*' => ['exists:lessons,id'],
            ]);

            $lessonIds = $request->lesson_ids;

            DB::transaction(function () use ($topic, $lessonIds) {
                // Update the order of each lesson
                foreach ($lessonIds as $index => $lessonId) {
                    $topic->lessons()->where('id', $lessonId)->update(['order' => $index + 1]);
                }
            });

            return $this->sendResponse($topic->load('lessons'), 'Lessons reordered successfully.');
        } catch (Exception $e) {
            Log::error('Failed to reorder lessons: ' . $e->getMessage(), [
                'trace'      => $e->getTraceAsString(),
                'topic_id'   => $topic->id,
                'lesson_ids' => $request->lesson_ids ?? [],
            ]);
            return $this->sendError('Failed to reorder lessons', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Submit a topic for review.
     */
    public function submitForReview(Request $request, Topic $topic): JsonResponse
    {
        try {
            // Validate that the topic is in draft status
            if ($topic->status !== 'draft') {
                return $this->sendError('Only draft topics can be submitted for review.', ['error' => 'Only draft topics can be submitted for review.'], 422);
            }

            // Update the topic status to 'in_review'
            $topic->review_status = 'pending';
            $topic->save();

            // Create a review record
            $review = $topic->reviews()->create([
                'submitted_by' => $request->user()->id,
                'status'       => 'pending',
                'submitted_at' => now(),
            ]);

            // Log the review submission
            AuditLog::log(
                'submit_for_review',
                'topics',
                $topic,
                [],
                ['review_id' => $review->id]
            );

            return $this->sendResponse($topic, 'Topic submitted for review successfully.');
        } catch (Exception $e) {
            Log::error('Failed to submit topic for review: ' . $e->getMessage(), [
                'trace'    => $e->getTraceAsString(),
                'topic_id' => $topic->id,
                'user_id'  => $request->user() ? $request->user()->id : null,
            ]);
            return $this->sendError('Failed to submit topic for review', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Approve a topic review.
     */
    public function approveReview(Request $request, Topic $topic): JsonResponse
    {
        try {
            // Validate that the topic is in review status
            if ($topic->review_status !== 'pending') {
                return $this->sendError('This topic is not pending review.', ['error' => 'This topic is not pending review.'], 422);
            }

            $request->validate([
                'review_comment' => ['nullable', 'string', 'max:1000'],
            ]);

            // Get the latest pending review
            $review = $topic->reviews()->where('status', 'pending')->latest()->first();

            if (! $review) {
                return $this->sendError('No pending review found for this topic.', ['error' => 'No pending review found for this topic.'], 404);
            }

            // Update the review
            $review->update([
                'reviewed_by'    => $request->user()->id,
                'status'         => 'approved',
                'review_comment' => $request->review_comment,
                'reviewed_at'    => now(),
            ]);

            // Update the topic status
            $topic->review_status = 'approved';
            $topic->save();

            // Log the review approval
            AuditLog::log(
                'review_approved',
                'topics',
                $topic,
                [],
                ['review_id' => $review->id]
            );

            return $this->sendResponse($topic, 'Topic review approved successfully.');
        } catch (Exception $e) {
            Log::error('Failed to approve topic review: ' . $e->getMessage(), [
                'trace'    => $e->getTraceAsString(),
                'topic_id' => $topic->id,
                'user_id'  => $request->user() ? $request->user()->id : null,
            ]);
            return $this->sendError('Failed to approve topic review', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Reject a topic review.
     */
    public function rejectReview(Request $request, Topic $topic): JsonResponse
    {
        try {
            // Validate that the topic is in review status
            if ($topic->review_status !== 'pending') {
                return $this->sendError('This topic is not pending review.', ['error' => 'This topic is not pending review.'], 422);
            }

            $request->validate([
                'review_comment'   => ['required', 'string', 'max:1000'],
                'rejection_reason' => ['required', 'string', 'in:content_issues,formatting_issues,accuracy_issues,other'],
            ]);

            // Get the latest pending review
            $review = $topic->reviews()->where('status', 'pending')->latest()->first();

            if (! $review) {
                return $this->sendError('No pending review found for this topic.', ['error' => 'No pending review found for this topic.'], 404);
            }

            // Update the review
            $review->update([
                'reviewed_by'      => $request->user()->id,
                'status'           => 'rejected',
                'review_comment'   => $request->review_comment,
                'rejection_reason' => $request->rejection_reason,
                'reviewed_at'      => now(),
            ]);

            // Update the topic status
            $topic->review_status = 'rejected';
            $topic->save();

            // Log the review rejection
            AuditLog::log(
                'review_rejected',
                'topics',
                $topic,
                [],
                [
                    'review_id'        => $review->id,
                    'rejection_reason' => $request->rejection_reason,
                ]
            );

            return $this->sendResponse($topic, 'Topic review rejected successfully.');
        } catch (Exception $e) {
            Log::error('Failed to reject topic review: ' . $e->getMessage(), [
                'trace'            => $e->getTraceAsString(),
                'topic_id'         => $topic->id,
                'user_id'          => $request->user() ? $request->user()->id : null,
                'rejection_reason' => $request->rejection_reason ?? null,
            ]);
            return $this->sendError('Failed to reject topic review', ['error' => $e->getMessage()], 500);
        }
    }
}
