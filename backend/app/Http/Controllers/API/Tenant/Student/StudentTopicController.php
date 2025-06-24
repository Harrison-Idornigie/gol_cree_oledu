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
        // Apply policies - students can view topics
        $this->authorizeResource(Topic::class, 'topic');
    }

    /**
     * Display topics for a unit.
     * 
     * @param Request $request
     * @param Unit $unit
     * @return JsonResponse
     */
    public function index(Request $request, Unit $unit): JsonResponse
    {
        $this->authorize('viewAny', Topic::class);

        try {
            $user = $request->user();
            $topics = $this->topicService->getTopicsForStudents($unit->id, $user);

            return $this->sendResponse($topics, 'Topics retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve topics', ['error' => $e->getMessage()], 500);
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
        $this->authorize('view', $topic);

        try {
            $user = $request->user();
            
            // Check if topic is accessible to the student
            if (!$this->topicService->isTopicAccessible($topic, $user)) {
                return $this->sendErrorResponse('Topic not accessible. Complete previous topics first.', [], 403);
            }

            $topicDetails = $topic->load(['lessons' => function ($query) {
                $query->where('status', 'published')->orderBy('order');
            }, 'unit', 'language']);

            $topicArray = $topicDetails->toArray();
            $topicArray['user_progress'] = $this->topicService->getUserTopicProgress($topic, $user);
            $topicArray['completion_status'] = $this->topicService->getTopicCompletionStatus($topic, $user);

            return $this->sendResponse($topicArray, 'Topic retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve topic', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get student's progress in a topic.
     * 
     * @param Request $request
     * @param Topic $topic
     * @return JsonResponse
     */
    public function progress(Request $request, Topic $topic): JsonResponse
    {
        $this->authorize('view', $topic);

        try {
            $user = $request->user();
            $progress = $this->topicService->getUserTopicProgress($topic, $user);

            return $this->sendResponse($progress, 'Topic progress retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve topic progress', ['error' => $e->getMessage()], 500);
        }
    }
}
