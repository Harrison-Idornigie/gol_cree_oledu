<?php

namespace App\Http\Controllers\API\Tenant\Team;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\Tenants\Course\LessonService;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\Lesson;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Team Lesson Controller
 * 
 * Handles lesson management operations for team members.
 * Access Level: Team (Teams/Content Creators)
 * Scope: Tenant-specific
 * 
 * This controller allows team members to create and manage lessons
 * within topics in their tenant scope.
 */
class TeamLessonController extends BaseAPIController
{
    use BelongsToTenant;

    protected LessonService $lessonService;

    /**
     * Constructor - Apply team middleware
     */
    public function __construct(LessonService $lessonService)
    {
        $this->lessonService = $lessonService;
    }

    /**
     * Display a listing of lessons.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Lesson::class);

        try {
            $filters = [
                'search' => $request->get('search'),
                'topic_id' => $request->get('topic_id'),
                'unit_id' => $request->get('unit_id'),
                'status' => $request->get('status'),
                'created_by' => $request->get('created_by'),
            ];

            $sorts = [];
            if ($request->has('sort_by')) {
                $sorts[$request->get('sort_by')] = $request->get('sort_direction', 'asc');
            }

            $perPage = $request->get('per_page', 15);
            $lessons = $this->lessonService->getLessons($filters, $sorts, $perPage);

            return $this->sendResponse($lessons, 'Lessons retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve lessons.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Store a newly created lesson.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Lesson::class);

        try {
            $validated = $request->validate([
                'topic_id' => 'required|exists:topics,id',
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'order' => 'nullable|integer|min:0',
                'status' => 'nullable|string|in:draft,published,archived',
            ]);

            $lesson = $this->lessonService->createLesson($validated, Auth::user());

            return $this->sendCreatedResponse($lesson, 'Lesson created successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to create lesson.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified lesson.
     *
     * @param Request $request
     * @param Lesson $lesson
     * @return JsonResponse
     */
    public function show(Request $request, Lesson $lesson): JsonResponse
    {
        $this->authorize('view', $lesson);

        try {
            // Load relationships and statistics
            $lesson->load(['topic.unit.learningPath', 'exercises', 'template']);

            // Get lesson statistics
            $stats = $this->lessonService->getLessonStats($lesson);
            $lesson->stats = $stats;

            return $this->sendResponse($lesson, 'Lesson retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve lesson.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Update the specified lesson.
     *
     * @param Request $request
     * @param Lesson $lesson
     * @return JsonResponse
     */
    public function update(Request $request, Lesson $lesson): JsonResponse
    {
        $this->authorize('update', $lesson);

        try {
            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
                'order' => 'nullable|integer|min:0',
                'status' => 'nullable|string|in:draft,published,archived',
            ]);

            $updatedLesson = $this->lessonService->updateLesson($lesson, $validated, Auth::user());

            return $this->sendResponse($updatedLesson, 'Lesson updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to update lesson.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified lesson.
     *
     * @param Request $request
     * @param Lesson $lesson
     * @return JsonResponse
     */
    public function destroy(Request $request, Lesson $lesson): JsonResponse
    {
        $this->authorize('delete', $lesson);

        try {
            $this->lessonService->deleteLesson($lesson, Auth::user());

            return $this->sendNoContentResponse();
        } catch (\Exception $e) {
            return $this->sendError('Failed to delete lesson.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Submit lesson for review.
     * 
     * @param Request $request
     * @param Lesson $lesson
     * @return JsonResponse
     */
    public function submitForReview(Request $request, Lesson $lesson): JsonResponse
    {
        $this->authorize('submitForReview', $lesson);

        // TODO: Implement review submission
        // - Validate lesson completeness
        // - Check all exercises are complete
        // - Change status to under review
        // - Notify reviewers
        return $this->sendResponse($lesson, 'Lesson submitted for review successfully.');
    }

    /**
     * Update lesson status.
     * 
     * @param Request $request
     * @param Lesson $lesson
     * @return JsonResponse
     */
    public function updateStatus(Request $request, Lesson $lesson): JsonResponse
    {
        $this->authorize('update', $lesson);

        // TODO: Implement status update
        // - Validate permissions for status change
        // - Update lesson status
        // - Handle publication implications
        // - Update topic status if needed
        return $this->sendResponse($lesson, 'Lesson status updated successfully.');
    }

    /**
     * Reorder exercises within lesson.
     * 
     * @param Request $request
     * @param Lesson $lesson
     * @return JsonResponse
     */
    public function reorderExercises(Request $request, Lesson $lesson): JsonResponse
    {
        $this->authorize('manageExercises', $lesson);

        // TODO: Implement exercise reordering
        // - Validate exercise ownership
        // - Update exercise order within lesson
        // - Maintain learning progression logic
        // - Update sequential access rules
        return $this->sendResponse([], 'Exercises reordered successfully.');
    }
}
