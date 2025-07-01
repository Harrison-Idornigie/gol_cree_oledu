<?php

namespace App\Http\Controllers\API\Tenant\Student;

use App\Http\Controllers\API\BaseAPIController;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\Exercise;
use App\Services\Tenants\Exercise\ExerciseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;

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
     * @param string $tenant
     * @param string $exercise
     * @return JsonResponse
     */
    public function show(Request $request, string $tenant, string $exercise): JsonResponse
    {
        try {
            // Manually resolve the exercise in tenant context
            $exerciseModel = Exercise::findOrFail($exercise);
            $this->authorize('view', $exerciseModel);

            // Use service to get exercise with proper relationships
            $exerciseData = $this->exerciseService->getExercise(
                $exerciseModel->id,
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
     * @param string $tenant
     * @param string $exercise
     * @return JsonResponse
     */
    public function checkAnswer(Request $request, string $tenant, string $exercise): JsonResponse
    {
        try {
            // Manually resolve the exercise in tenant context
            $exerciseModel = Exercise::findOrFail($exercise);
            $this->authorize('view', $exerciseModel);

            $validated = $request->validate([
                'answer' => 'required',
                'time_taken' => 'nullable|integer|min:0'
            ]);

            $user = Auth::user();
            $result = $this->exerciseService->checkStudentAnswer($exerciseModel, $user, $validated);

            return $this->sendResponse($result, 'Answer checked successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to check answer.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get exercise statistics for student.
     *
     * @param Request $request
     * @param string $tenant
     * @param string $exercise
     * @return JsonResponse
     */
    public function statistics(Request $request, string $tenant, string $exercise): JsonResponse
    {
        try {
            // Manually resolve the exercise in tenant context
            $exerciseModel = Exercise::findOrFail($exercise);
            $this->authorize('view', $exerciseModel);

            $user = Auth::user();
            $statistics = $this->exerciseService->getStudentStatistics($exerciseModel, $user);

            return $this->sendResponse([
                'exercise' => [
                    'id' => $exerciseModel->id,
                    'title' => $exerciseModel->title,
                    'type' => $exerciseModel->type,
                    'difficulty_level' => $exerciseModel->difficulty_level
                ],
                'statistics' => $statistics
            ], 'Exercise statistics retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve statistics.', ['error' => $e->getMessage()], 500);
        }
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
        try {
            $this->authorize('view', $exercise);

            $validated = $request->validate([
                'answer' => 'required',
                'time_taken' => 'nullable|integer|min:0',
                'files' => 'nullable|array', // For audio/speaking exercises
                'files.*' => 'file|mimes:mp3,wav,m4a|max:10240' // 10MB limit
            ]);

            $user = Auth::user();

            // Handle file uploads if present
            if ($request->hasFile('files')) {
                $validated['files'] = [];
                foreach ($request->file('files') as $file) {
                    // Store file and add path to answer data
                    $path = $file->store('exercise-submissions/' . $exercise->id, 'public');
                    $validated['files'][] = $path;
                }
            }

            $result = $this->exerciseService->checkStudentAnswer($exercise, $user, $validated);

            return $this->sendResponse($result, 'Answer submitted successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to submit answer.', ['error' => $e->getMessage()], 500);
        }
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
        try {
            $this->authorize('viewAny', Exercise::class);

            // Validate exercise type
            $allowedTypes = ['listening', 'speaking', 'picture', 'multiple_choice', 'fill_blank', 'matching'];
            if (!in_array($type, $allowedTypes)) {
                return $this->sendError('Invalid exercise type.', ['type' => $type], 400);
            }

            $user = Auth::user();
            $filters = $request->only(['lesson_id', 'difficulty_level']);
            $exercises = $this->exerciseService->getExercisesByType($type, $user, $filters);

            return $this->sendResponse([
                'type' => $type,
                'exercises' => $exercises,
                'meta' => [
                    'total_count' => $exercises->count(),
                    'completed_count' => $exercises->where('student_progress.completed', true)->count()
                ]
            ], "Exercises of type '{$type}' retrieved successfully.");
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve exercises by type.', ['error' => $e->getMessage()], 500);
        }
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
        try {
            $this->authorize('viewAny', Exercise::class);

            $user = Auth::user();
            $filters = $request->only(['type', 'difficulty_level']);
            $exercises = $this->exerciseService->getExercisesByLanguage($languageCode, $user, $filters);

            return $this->sendResponse([
                'language_code' => $languageCode,
                'exercises' => $exercises,
                'meta' => [
                    'total_count' => $exercises->count(),
                    'completed_count' => $exercises->where('student_progress.completed', true)->count(),
                    'types_available' => $exercises->pluck('type')->unique()->values()
                ]
            ], "Exercises for language '{$languageCode}' retrieved successfully.");
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve exercises by language.', ['error' => $e->getMessage()], 500);
        }
    }
}
