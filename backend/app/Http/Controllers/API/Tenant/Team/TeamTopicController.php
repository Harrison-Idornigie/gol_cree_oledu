<?php

namespace App\Http\Controllers\API\Tenant\Team;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\Tenants\Course\TopicService;
use App\Services\Tenants\Course\LessonService;
use App\Services\Tenants\Course\ReviewService;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\Topic;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Exception;

/**
 * Team Topic Controller
 * 
 * Handles topic management operations for team members.
 * Access Level: Team (Teams/Content Creators)
 * Scope: Tenant-specific
 * 
 * This controller allows team members to create and manage topics
 * within units in their tenant scope.
 */
class TeamTopicController extends BaseAPIController
{
    use BelongsToTenant;

    protected TopicService $topicService;
    protected LessonService $lessonService;
    protected ReviewService $reviewService;

    /**
     * Constructor - Apply team middleware
     */
    public function __construct(
        TopicService $topicService,
        LessonService $lessonService,
        ReviewService $reviewService
    ) {
        $this->topicService = $topicService;
        $this->lessonService = $lessonService;
        $this->reviewService = $reviewService;

        // Apply policies
        $this->authorizeResource(Topic::class, 'topic');
    }

    /**
     * Display a listing of topics.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Topic::class);

        try {
            $filters = [
                'search' => $request->get('search'),
                'unit_id' => $request->get('unit_id'),
                'status' => $request->get('status'),
                'is_bonus' => $request->get('is_bonus'),
                'created_by' => $request->get('created_by'),
            ];

            $sorts = [];
            if ($request->has('sort_by')) {
                $sorts[$request->get('sort_by')] = $request->get('sort_direction', 'asc');
            }

            $perPage = $request->get('per_page', 15);
            $topics = $this->topicService->getTopics($filters, $sorts, $perPage);

            return $this->sendResponse($topics, 'Topics retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve topics.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Store a newly created topic.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'unit_id' => 'required|exists:units,id',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'icon' => 'nullable|string|max:100',
                'color' => 'nullable|string|max:7',
                'order' => 'nullable|integer|min:0',
                'xp_reward' => 'nullable|integer|min:0',
                'max_level' => 'nullable|integer|min:1|max:10',
                'is_bonus' => 'nullable|boolean',
            ]);

            $topic = $this->topicService->createTopic($validated, Auth::user());

            return $this->sendCreatedResponse($topic, 'Topic created successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to create topic.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified topic.
     *
     * @param Request $request
     * @param Topic $topic
     * @return JsonResponse
     */
    public function show(Request $request, Topic $topic): JsonResponse
    {
        try {
            // Load relationships and statistics
            $topic->load(['unit.learningPath', 'lessons.exercises', 'template']);

            // Get topic statistics
            $stats = $this->topicService->getTopicStats($topic);
            $topic->stats = $stats;

            return $this->sendResponse($topic, 'Topic retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve topic.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Update the specified topic.
     *
     * @param Request $request
     * @param Topic $topic
     * @return JsonResponse
     */
    public function update(Request $request, Topic $topic): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'icon' => 'nullable|string|max:100',
                'color' => 'nullable|string|max:7',
                'order' => 'nullable|integer|min:0',
                'status' => 'nullable|string|in:draft,published,archived',
                'xp_reward' => 'nullable|integer|min:0',
                'max_level' => 'nullable|integer|min:1|max:10',
                'is_bonus' => 'nullable|boolean',
            ]);

            $updatedTopic = $this->topicService->updateTopic($topic, $validated, Auth::user());

            return $this->sendResponse($updatedTopic, 'Topic updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to update topic.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified topic.
     *
     * @param Request $request
     * @param Topic $topic
     * @return JsonResponse
     */
    public function destroy(Request $request, Topic $topic): JsonResponse
    {
        try {
            $this->topicService->deleteTopic($topic, Auth::user());

            return $this->sendNoContentResponse();
        } catch (\Exception $e) {
            return $this->sendError('Failed to delete topic.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Submit topic for review.
     * 
     * @param Request $request
     * @param Topic $topic
     * @return JsonResponse
     */
    public function submitForReview(Request $request, Topic $topic): JsonResponse
    {
        $this->authorize('update', $topic);

        try {
            $validatedData = $request->validate([
                'review_notes' => 'nullable|string|max:1000',
            ]);

            // Check if topic has minimum required content
            if (!$topic->lessons()->exists()) {
                return $this->sendError('Cannot submit for review. Topic must have at least one lesson.');
            }

            $updatedTopic = $this->topicService->updateTopicStatus($topic, 'under_review', Auth::user());

            // Create review entry if review notes provided
            if (!empty($validatedData['review_notes'])) {
                $this->reviewService->createReview([
                    'content_type' => 'topic',
                    'content_id' => $topic->id,
                    'reviewer_id' => Auth::id(),
                    'status' => 'pending',
                    'comment' => $validatedData['review_notes'],
                ], Auth::user());
            }

            return $this->sendResponse($updatedTopic, 'Topic submitted for review successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Failed to submit for review.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Update topic status.
     * 
     * @param Request $request
     * @param Topic $topic
     * @return JsonResponse
     */
    public function updateStatus(Request $request, Topic $topic): JsonResponse
    {
        $this->authorize('update', $topic);

        try {
            $validatedData = $request->validate([
                'status' => 'required|string|in:draft,under_review,published,archived',
                'status_notes' => 'nullable|string|max:1000',
            ]);

            $updatedTopic = $this->topicService->updateTopicStatus(
                $topic,
                $validatedData['status'],
                Auth::user()
            );

            // Log status change with notes if provided
            if (!empty($validatedData['status_notes'])) {
                $this->reviewService->createReview([
                    'content_type' => 'topic',
                    'content_id' => $topic->id,
                    'reviewer_id' => Auth::id(),
                    'status' => 'completed',
                    'comment' => $validatedData['status_notes'],
                ], Auth::user());
            }

            return $this->sendResponse($updatedTopic, 'Topic status updated successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Failed to update status.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Reorder lessons within topic.
     * 
     * @param Request $request
     * @param Topic $topic
     * @return JsonResponse
     */
    public function reorderLessons(Request $request, Topic $topic): JsonResponse
    {
        $this->authorize('update', $topic);

        try {
            $validatedData = $request->validate([
                'lesson_orders' => 'required|array|min:1',
                'lesson_orders.*.id' => 'required|integer|exists:lessons,id',
                'lesson_orders.*.order' => 'required|integer|min:1',
            ]);

            $success = $this->lessonService->reorderLessons(
                $topic->id,
                $validatedData['lesson_orders'],
                Auth::user()
            );

            if (!$success) {
                return $this->sendError('Failed to reorder lessons. Please verify all lessons belong to this topic.');
            }

            return $this->sendResponse([], 'Lessons reordered successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Failed to reorder lessons.', ['error' => $e->getMessage()]);
        }
    }
}
