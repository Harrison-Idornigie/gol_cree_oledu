<?php
namespace App\Http\Controllers\API;

use App\Models\Topic;
use App\Models\Unit;
use App\Services\SequentialLearningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TopicController extends BaseAPIController
{
    protected $sequentialLearningService;

    /**
     * Create a new controller instance.
     */
    public function __construct(SequentialLearningService $sequentialLearningService)
    {
        $this->sequentialLearningService = $sequentialLearningService;
    }

    /**
     * Display a listing of topics for a unit.
     */
    public function index(Request $request, Unit $unit): JsonResponse
    {
        $query = $unit->topics()->orderBy('order');

        // Only show published topics for students
        $query->where('status', 'published');

        // Include relationships if requested
        if ($request->has('with_lessons')) {
            $query->with(['lessons' => function ($query) {
                $query->where('status', 'published')
                    ->orderBy('order');
            }]);
        }

        $perPage = $request->input('per_page', 15);
        $topics  = $query->paginate($perPage);

        return $this->sendPaginatedResponse($topics);
    }

    /**
     * Display the specified topic.
     */
    public function show(Request $request, Topic $topic): JsonResponse
    {
        // Check if the topic is unlocked for the user
        $isUnlocked = $this->sequentialLearningService->isTopicUnlocked($topic);

        // Include relationships if requested
        if ($request->has('with_lessons')) {
            $topic->load(['lessons' => function ($query) {
                $query->where('status', 'published')
                    ->orderBy('order');
            }]);
        }

        if ($request->has('with_unit')) {
            $topic->load('unit');
        }

        if ($request->has('with_progress') && $request->user()) {
            $topic->load(['progress' => function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            }]);
        }

        // Add unlocked status to the response
        $topicData                  = $topic->toArray();
        $topicData['is_unlocked']   = $isUnlocked;
        $topicData['current_level'] = $topic->getCurrentLevel($request->user()->id);

        return $this->sendResponse($topicData);
    }

    /**
     * Get topic progress for the authenticated user.
     */
    public function progress(Request $request, Topic $topic): JsonResponse
    {
        $progress = $topic->progress()
            ->where('user_id', $request->user()->id)
            ->first();

        $lessonsProgress = $topic->lessons()
            ->with(['progress' => function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            }])
            ->get()
            ->map(function ($lesson) {
                $progress = $lesson->progress->first();
                return [
                    'lesson_id' => $lesson->id,
                    'status'    => $progress ? $progress->status : 'not_started',
                ];
            });

        // Get unlocked lessons for this topic
        $unlockedLessons = $this->sequentialLearningService->getUnlockedLessonsForTopic($topic);

        return $this->sendResponse([
            'topic_progress'        => $progress ? $progress->status : 'not_started',
            'completion_percentage' => $topic->getCompletionPercentage($request->user()->id),
            'current_level'         => $topic->getCurrentLevel($request->user()->id),
            'max_level'             => $topic->max_level,
            'lessons_progress'      => $lessonsProgress,
            'is_unlocked'           => $this->sequentialLearningService->isTopicUnlocked($topic),
            'unlocked_lessons'      => $unlockedLessons,
        ]);
    }
}