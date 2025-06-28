<?php

namespace App\Http\Controllers\API\Tenant\Student;

use App\Http\Controllers\API\BaseAPIController;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\Topic;
use App\Models\Tenants\Unit;
use App\Services\Tenants\Course\TopicService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

/**
 * Student Topic Controller
 * 
 * Handles topic access and progression for students.
 * Access Level: Student
 * Scope: Tenant-specific (read-only)
 * 
 * This controller allows students to access topics within units
 * with sequential learning enforcement.
 */
class StudentTopicController extends BaseAPIController
{
    use BelongsToTenant;

    protected TopicService $topicService;

    /**
     * Constructor - Apply student middleware
     */
    public function __construct(TopicService $topicService)
    {
        $this->topicService = $topicService;
    }

    /**
     * Display topics for a unit.
     *
     * @param Request $request
     * @param string $tenant
     * @param string $unit
     * @return JsonResponse
     */
    public function index(Request $request, string $tenant, string $unit): JsonResponse
    {
        $this->authorize('viewAny', Topic::class);

        try {
            // Find the unit within tenant context
            $unitModel = Unit::findOrFail((int) $unit);
            $this->authorize('view', $unitModel);

            $user = $request->user();
            $topics = $this->topicService->getTopicsForStudents($unitModel->id, $user);

            return $this->sendResponse($topics, 'Topics retrieved successfully.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->sendError('Unit not accessible.', ['error' => $e->getMessage()], 403);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('Unit not found.', ['error' => 'The requested unit does not exist.'], 404);
        } catch (Exception $e) {
            return $this->sendError('Failed to retrieve topics.', ['error' => $e->getMessage()], 500);
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
            $topicModel = Topic::findOrFail((int) $topic);
            $this->authorize('view', $topicModel);

            $user = $request->user();

            // Check if topic is accessible to the student
            if (!$this->topicService->isTopicAccessible($topicModel, $user)) {
                return $this->sendError('Topic not accessible. Complete previous topics first.', [], 403);
            }

            $topicDetails = $topicModel->load(['lessons' => function ($query) {
                $query->where('status', 'published')->orderBy('order');
            }, 'unit']);

            $topicArray = $topicDetails->toArray();
            $topicArray['progress'] = $this->topicService->getUserTopicProgress($topicModel, $user)['overall_progress'];
            $topicArray['user_progress'] = $this->topicService->getUserTopicProgress($topicModel, $user);
            $topicArray['completion_status'] = $this->topicService->getTopicCompletionStatus($topicModel, $user);

            return $this->sendResponse($topicArray, 'Topic retrieved successfully.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->sendError('Topic not accessible.', ['error' => $e->getMessage()], 403);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('Topic not found.', ['error' => 'The requested topic does not exist.'], 404);
        } catch (Exception $e) {
            return $this->sendError('Failed to retrieve topic.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get student's progress in a topic.
     *
     * @param Request $request
     * @param string $tenant
     * @param string $topic
     * @return JsonResponse
     */
    public function progress(Request $request, string $tenant, string $topic): JsonResponse
    {
        try {
            // Find the topic within tenant context
            $topicModel = Topic::findOrFail((int) $topic);
            $this->authorize('view', $topicModel);

            $user = $request->user();
            $progress = $this->topicService->getUserTopicProgress($topicModel, $user);

            return $this->sendResponse($progress, 'Topic progress retrieved successfully.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->sendError('Topic not accessible.', ['error' => $e->getMessage()], 403);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('Topic not found.', ['error' => 'The requested topic does not exist.'], 404);
        } catch (Exception $e) {
            return $this->sendError('Failed to retrieve topic progress.', ['error' => $e->getMessage()], 500);
        }
    }
}
