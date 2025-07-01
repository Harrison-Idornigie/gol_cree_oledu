<?php

namespace App\Http\Controllers\API\Tenant\Team;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\Tenants\Topic;
use App\Services\Tenants\Course\LessonService;
use App\Services\Tenants\Course\ReviewService;
use App\Services\Tenants\Course\TopicService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

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
        $this->topicService  = $topicService;
        $this->lessonService = $lessonService;
        $this->reviewService = $reviewService;
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
                'search'     => $request->get('search'),
                'unit_id'    => $request->get('unit_id'),
                'status'     => $request->get('status'),
                'is_bonus'   => $request->get('is_bonus'),
                'created_by' => $request->get('created_by'),
            ];

            $sorts = [];
            if ($request->has('sort_by')) {
                $sorts[$request->get('sort_by')] = $request->get('sort_direction', 'asc');
            }

            $perPage = $request->get('per_page', 15);
            $topics  = $this->topicService->getTopics($filters, $sorts, $perPage);

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
        $this->authorize('create', Topic::class);
        try {
            $validated = $request->validate([
                'unit_id'     => 'required|exists:units,id',
                'title'       => 'required|string|max:255',
                'description' => 'nullable|string',
                'icon'        => 'nullable|string|max:100',
                'color'       => 'nullable|string|max:7',
                'order'       => 'nullable|integer|min:0',
                'xp_reward'   => 'nullable|integer|min:0',
                'max_level'   => 'nullable|integer|min:1|max:10',
                'is_bonus'    => 'nullable|boolean',
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
     * @param string $tenant
     * @param string $topic
     * @return JsonResponse
     */
    public function show(Request $request, string $tenant, string $topic): JsonResponse
    {
        try {
            // Find the topic within tenant context
            $topicModel = Topic::withCount('lessons')->findOrFail((int) $topic);
            $this->authorize('view', $topicModel);

            // Load relationships and statistics
            $topicModel->load(['unit.learningPath', 'lessons.exercises', 'template']);

            // Get topic statistics
            $stats             = $this->topicService->getTopicStats($topicModel);
            $topicModel->stats = $stats;

            return $this->sendResponse($topicModel, 'Topic retrieved successfully.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->sendError('Topic not accessible.', ['error' => $e->getMessage()], 403);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('Topic not found.', ['error' => 'The requested topic does not exist.'], 404);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve topic.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Update the specified topic.
     *
     * @param Request $request
     * @param string $tenant
     * @param string $topic
     * @return JsonResponse
     */
    public function update(Request $request, string $tenant, string $topic): JsonResponse
    {
        try {
            // Find the topic within tenant context
            $topicModel = Topic::findOrFail((int) $topic);
            $this->authorize('update', $topicModel);

            $validated = $request->validate([
                'title'       => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'icon'        => 'nullable|string|max:100',
                'color'       => 'nullable|string|max:7',
                'order'       => 'nullable|integer|min:0',
                'status'      => 'nullable|string|in:draft,published,archived',
                'xp_reward'   => 'nullable|integer|min:0',
                'max_level'   => 'nullable|integer|min:1|max:10',
                'is_bonus'    => 'nullable|boolean',
            ]);

            $updatedTopic = $this->topicService->updateTopic($topicModel, $validated, Auth::user());

            return $this->sendResponse($updatedTopic, 'Topic updated successfully.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->sendError('Topic not accessible.', ['error' => $e->getMessage()], 403);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('Topic not found.', ['error' => 'The requested topic does not exist.'], 404);
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
     * @param string $tenant
     * @param string $topic
     * @return JsonResponse
     */
    public function destroy(Request $request, string $tenant, string $topic): JsonResponse
    {
        try {
            // Find the topic within tenant context
            $topicModel = Topic::findOrFail((int) $topic);
            $this->authorize('delete', $topicModel);

            $this->topicService->deleteTopic($topicModel, Auth::user());

            return $this->sendNoContentResponse();
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->sendError('Topic not accessible.', ['error' => $e->getMessage()], 403);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('Topic not found.', ['error' => 'The requested topic does not exist.'], 404);
        } catch (\Exception $e) {
            return $this->sendError('Failed to delete topic.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Submit topic for review.
     *
     * @param Request $request
     * @param string $tenant
     * @param string $topic
     * @return JsonResponse
     */
    public function submitForReview(Request $request, string $tenant, string $topic): JsonResponse
    {
        try {
            // Find the topic within tenant context
            $topicModel = Topic::findOrFail((int) $topic);
            $this->authorize('update', $topicModel);

            $validatedData = $request->validate([
                'review_notes' => 'nullable|string|max:1000',
            ]);

            // Check if topic has minimum required content
            if (! $topicModel->lessons()->exists()) {
                return $this->sendError('Cannot submit for review. Topic must have at least one lesson.');
            }

            // Update review status instead of main status
            $updatedTopic = $this->topicService->updateTopic($topicModel, ['review_status' => 'pending'], Auth::user());

            // Create review entry if review notes provided
            if (! empty($validatedData['review_notes'])) {
                $this->reviewService->createReview([
                    'content_type'    => 'topic',
                    'content_id'      => $topicModel->id,
                    'submitted_by'    => Auth::id(),
                    'status'          => 'pending',
                    'review_comment'  => $validatedData['review_notes'],
                    'submitted_at'    => now(),
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
     * @param string $tenant
     * @param string $topic
     * @return JsonResponse
     */
    public function updateStatus(Request $request, string $tenant, string $topic): JsonResponse
    {
        try {
            // Find the topic within tenant context
            $topicModel = Topic::findOrFail((int) $topic);
            $this->authorize('update', $topicModel);

            $validatedData = $request->validate([
                'status'       => 'required|string|in:draft,under_review,published,archived',
                'status_notes' => 'nullable|string|max:1000',
            ]);

            $updatedTopic = $this->topicService->updateTopicStatus(
                $topicModel,
                $validatedData['status'],
                Auth::user()
            );

            // Log status change with notes if provided
            if (! empty($validatedData['status_notes'])) {
                $this->reviewService->createReview([
                    'content_type' => 'topic',
                    'content_id'   => $topicModel->id,
                    'reviewer_id'  => Auth::id(),
                    'status'       => 'completed',
                    'comment'      => $validatedData['status_notes'],
                ], Auth::user());
            }

            return $this->sendResponse($updatedTopic, 'Topic status updated successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (\InvalidArgumentException $e) {
            return $this->sendError('Validation failed.', ['status' => [$e->getMessage()]], 422);
        } catch (Exception $e) {
            return $this->sendError('Failed to update status.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Reorder lessons within topic.
     *
     * @param Request $request
     * @param string $tenant
     * @param string $topic
     * @return JsonResponse
     */
    public function reorderLessons(Request $request, string $tenant, string $topic): JsonResponse
    {
        try {
            // Find the topic within tenant context
            $topicModel = Topic::findOrFail((int) $topic);
            $this->authorize('update', $topicModel);

            $validatedData = $request->validate([
                'lesson_orders'         => 'required|array|min:1',
                'lesson_orders.*.id'    => 'required|integer|exists:lessons,id',
                'lesson_orders.*.order' => 'required|integer|min:1',
            ]);

            $success = $this->lessonService->reorderLessons(
                $topicModel->id,
                $validatedData['lesson_orders'],
                Auth::user()
            );

            if (! $success) {
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
