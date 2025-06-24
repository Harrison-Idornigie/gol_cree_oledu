<?php

namespace App\Http\Controllers\API\Tenant\Team;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\Tenants\Course\LessonService;
use App\Services\Tenants\Course\ReviewService;
use App\Services\Tenants\Exercise\ExerciseService;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\Lesson;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Exception;

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
    protected ReviewService $reviewService;
    protected ExerciseService $exerciseService;

    /**
     * Constructor - Apply team middleware
     */
    public function __construct(
        LessonService $lessonService,
        ReviewService $reviewService,
        ExerciseService $exerciseService
    ) {
        $this->lessonService = $lessonService;
        $this->reviewService = $reviewService;
        $this->exerciseService = $exerciseService;
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

        try {
            $validatedData = $request->validate([
                'review_notes' => 'nullable|string|max:1000',
            ]);

            // Check if lesson has minimum required content
            if (!$lesson->exercises()->exists()) {
                return $this->sendError('Cannot submit for review. Lesson must have at least one exercise.');
            }

            $updatedLesson = $this->lessonService->updateLessonStatus($lesson, 'under_review', Auth::user());

            // Create review entry if review notes provided
            if (!empty($validatedData['review_notes'])) {
                $this->reviewService->createReview([
                    'content_type' => 'lesson',
                    'content_id' => $lesson->id,
                    'reviewer_id' => Auth::id(),
                    'status' => 'pending',
                    'comment' => $validatedData['review_notes'],
                ], Auth::user());
            }

            return $this->sendResponse($updatedLesson, 'Lesson submitted for review successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Failed to submit for review.', ['error' => $e->getMessage()]);
        }
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

        try {
            $validatedData = $request->validate([
                'status' => 'required|string|in:draft,under_review,published,archived',
                'status_notes' => 'nullable|string|max:1000',
            ]);

            $updatedLesson = $this->lessonService->updateLessonStatus(
                $lesson,
                $validatedData['status'],
                Auth::user()
            );

            // Log status change with notes if provided
            if (!empty($validatedData['status_notes'])) {
                $this->reviewService->createReview([
                    'content_type' => 'lesson',
                    'content_id' => $lesson->id,
                    'reviewer_id' => Auth::id(),
                    'status' => 'completed',
                    'comment' => $validatedData['status_notes'],
                ], Auth::user());
            }

            return $this->sendResponse($updatedLesson, 'Lesson status updated successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Failed to update status.', ['error' => $e->getMessage()]);
        }
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

        try {
            $validatedData = $request->validate([
                'exercise_orders' => 'required|array|min:1',
                'exercise_orders.*.id' => 'required|integer|exists:exercises,id',
                'exercise_orders.*.order' => 'required|integer|min:1',
            ]);

            $success = $this->exerciseService->reorderExercises(
                $lesson->id,
                $validatedData['exercise_orders'],
                Auth::user()
            );

            if (!$success) {
                return $this->sendError('Failed to reorder exercises. Please verify all exercises belong to this lesson.');
            }

            return $this->sendResponse([], 'Exercises reordered successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Failed to reorder exercises.', ['error' => $e->getMessage()]);
        }
    }
}
