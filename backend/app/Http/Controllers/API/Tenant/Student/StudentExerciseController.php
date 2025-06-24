<?php

namespace App\Http\Controllers\API\Tenant\Student;

use App\Http\Controllers\API\BaseAPIController;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\Exercise;
use App\Services\Tenants\Exercise\ExerciseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Student Exercise Controller
 * 
 * Handles exercise interaction and completion for students.
 * Access Level: Student
 * Scope: Tenant-specific (read-only)
 * 
 * This controller allows students to access and complete exercises
 * within their learning progression.
 */
class StudentExerciseController extends BaseAPIController
{
    use BelongsToTenant;

    protected ExerciseService $exerciseService;

    /**
     * Constructor - Apply student middleware and inject service
     */
    public function __construct(ExerciseService $exerciseService)
    {
        $this->exerciseService = $exerciseService;
        // Apply policies - students can only view and complete exercises
        $this->authorizeResource(Exercise::class, 'exercise');
    }

    /**
     * Display a listing of exercises.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Exercise::class);

        try {
            // Use service to get filtered exercises for students
            $exercises = $this->exerciseService->getExercises(
                $request->all(),
                [],
                $request->input('per_page', 15)
            );

            return $this->sendResponse($exercises, 'Exercises retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve exercises.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified exercise.
     * 
     * @param Request $request
     * @param Exercise $exercise
     * @return JsonResponse
     */
    public function show(Request $request, Exercise $exercise): JsonResponse
    {
        $this->authorize('view', $exercise);

        try {
            // Use service to get exercise with proper relationships
            $exerciseData = $this->exerciseService->getExercise(
                $exercise->id,
                ['attempts' => function ($query) use ($request) {
                    $query->where('user_id', $request->user()->id);
                }]
            );

            if (!$exerciseData) {
                return $this->sendError('Exercise not found.', [], 404);
            }

            return $this->sendResponse($exerciseData, 'Exercise retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve exercise.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Check student's answer for an exercise.
     * 
     * @param Request $request
     * @param Exercise $exercise
     * @return JsonResponse
     */
    public function checkAnswer(Request $request, Exercise $exercise): JsonResponse
    {
        $this->authorize('view', $exercise);

        // TODO: Implement answer checking
        // - Validate student's answer
        // - Calculate score and feedback
        // - Update progress tracking
        // - Return results and explanations
        return $this->sendResponse([], 'Answer checked successfully.');
    }

    /**
     * Get exercise statistics for student.
     * 
     * @param Request $request
     * @param Exercise $exercise
     * @return JsonResponse
     */
    public function statistics(Request $request, Exercise $exercise): JsonResponse
    {
        $this->authorize('view', $exercise);

        // TODO: Implement exercise statistics
        // - Student's performance on this exercise
        // - Attempt history and scores
        // - Time spent and accuracy
        return $this->sendResponse([], 'Exercise statistics retrieved successfully.');
    }

    /**
     * Submit answer for any exercise type (consolidated from specialized controllers).
     * 
     * @param Request $request
     * @param Exercise $exercise
     * @return JsonResponse
     */
    public function submitAnswer(Request $request, Exercise $exercise): JsonResponse
    {
        $this->authorize('view', $exercise);

        // TODO: Implement type-aware answer submission
        // - Route to appropriate handler based on exercise type
        // - Handle file uploads for speaking exercises
        // - Process conversation progress tracking
        // - Validate and score answers
        return $this->sendResponse([], 'Answer submitted successfully.');
    }

    /**
     * Get exercises by type.
     * 
     * @param Request $request
     * @param string $type
     * @return JsonResponse
     */
    public function getByType(Request $request, string $type): JsonResponse
    {
        $this->authorize('viewAny', Exercise::class);

        // TODO: Implement type-specific exercise listing
        // - Filter exercises by type (listening, speaking, picture, etc.)
        // - Include type-specific metadata
        // - Apply student access controls
        return $this->sendResponse([], "Exercises of type '{$type}' retrieved successfully.");
    }

    /**
     * Get exercises by language.
     * 
     * @param Request $request
     * @param string $languageCode
     * @return JsonResponse
     */
    public function getByLanguage(Request $request, string $languageCode): JsonResponse
    {
        $this->authorize('viewAny', Exercise::class);

        // TODO: Implement language-specific exercise listing
        // - Filter exercises by language
        // - Include language-specific content
        // - Apply student access controls
        return $this->sendResponse([], "Exercises for language '{$languageCode}' retrieved successfully.");
    }
}
