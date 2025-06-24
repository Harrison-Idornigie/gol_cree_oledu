<?php

namespace App\Http\Controllers\API\Tenant\Team;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\Tenants\Course\TopicService;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\Topic;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

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

    /**
     * Constructor - Apply team middleware
     */
    public function __construct(TopicService $topicService)
    {
        $this->topicService = $topicService;

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
        // TODO: Implement review submission
        // - Validate topic completeness
        // - Check all lessons have content
        // - Change status to under review
        // - Notify reviewers
        return $this->sendResponse($topic, 'Topic submitted for review successfully.');
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
        // TODO: Implement status update
        // - Validate permissions for status change
        // - Update topic status
        // - Handle publication implications
        // - Update unit status if needed
        return $this->sendResponse($topic, 'Topic status updated successfully.');
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
        // TODO: Implement lesson reordering
        // - Validate lesson ownership
        // - Update lesson order within topic
        // - Maintain learning progression logic
        // - Update sequential access rules
        return $this->sendResponse([], 'Lessons reordered successfully.');
    }
}
