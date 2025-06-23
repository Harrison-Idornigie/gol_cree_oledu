<?php

namespace App\Http\Controllers\API\Tenant\Team;

use App\Http\Controllers\API\BaseAPIController;
// use App\Services\Tenants\Exercise\ExerciseService;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\Exercise;
use App\Services\Tenants\Exercise\ExerciseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Team Exercise Controller
 * 
 * Handles exercise creation and management operations for team members.
 * Access Level: Team (Teams/Content Creators)
 * Scope: Tenant-specific
 * 
 * This controller allows team members to create and manage exercises
 * within lessons in their tenant scope.
 */
class TeamExerciseController extends BaseAPIController
{
    use BelongsToTenant;

    protected ExerciseService $exerciseService;

    /**
     * Constructor - Apply team middleware
     */
    public function __construct(ExerciseService $exerciseService)
    {
        $this->exerciseService = $exerciseService;
    }

    /**
     * Display a listing of exercises.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'search' => $request->get('search'),
                'lesson_id' => $request->get('lesson_id'),
                'type' => $request->get('type'),
                'status' => $request->get('status'),
                'purpose' => $request->get('purpose'),
                'difficulty_level' => $request->get('difficulty_level'),
            ];

            $sorts = [];
            if ($request->has('sort_by')) {
                $sorts[$request->get('sort_by')] = $request->get('sort_direction', 'asc');
            }

            $perPage = $request->get('per_page', 15);
            $exercises = $this->exerciseService->getExercises($filters, $sorts, $perPage);

            return $this->sendResponse($exercises, 'Exercises retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve exercises.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Store a newly created exercise.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'lesson_id' => 'required|exists:lessons,id',
                'title' => 'required|string|max:255',
                'type' => 'required|string|in:multiple_choice,fill_blank,matching,writing,speaking,conversation,listening,picture',
                'content' => 'required|array',
                'answers' => 'nullable|array',
                'purpose' => 'nullable|string|in:practice,checkpoint,review,assessment',
                'difficulty_level' => 'nullable|integer|min:1|max:10',
                'passing_score' => 'nullable|integer|min:0|max:100',
                'time_limit' => 'nullable|integer|min:0',
                'max_attempts' => 'nullable|integer|min:1',
                'show_feedback' => 'nullable|boolean',
                'show_hints' => 'nullable|boolean',
                'xp_reward' => 'nullable|integer|min:0',
                'order' => 'nullable|integer|min:0',
            ]);

            $exercise = $this->exerciseService->createExercise($validated, Auth::user());

            return $this->sendCreatedResponse($exercise, 'Exercise created successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to create exercise.', ['error' => $e->getMessage()]);
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
        try {
            // Load relationships
            $exercise->load(['lesson.topic.unit', 'template', 'attempts']);

            return $this->sendResponse($exercise, 'Exercise retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve exercise.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Update the specified exercise.
     *
     * @param Request $request
     * @param Exercise $exercise
     * @return JsonResponse
     */
    public function update(Request $request, Exercise $exercise): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'type' => 'sometimes|string|in:multiple_choice,fill_blank,matching,writing,speaking,conversation,listening,picture',
                'content' => 'sometimes|array',
                'answers' => 'nullable|array',
                'purpose' => 'nullable|string|in:practice,checkpoint,review,assessment',
                'difficulty_level' => 'nullable|integer|min:1|max:10',
                'passing_score' => 'nullable|integer|min:0|max:100',
                'time_limit' => 'nullable|integer|min:0',
                'max_attempts' => 'nullable|integer|min:1',
                'show_feedback' => 'nullable|boolean',
                'show_hints' => 'nullable|boolean',
                'xp_reward' => 'nullable|integer|min:0',
                'order' => 'nullable|integer|min:0',
                'status' => 'nullable|string|in:draft,published,archived',
            ]);

            $updatedExercise = $this->exerciseService->updateExercise($exercise, $validated, Auth::user());

            return $this->sendResponse($updatedExercise, 'Exercise updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to update exercise.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified exercise.
     *
     * @param Request $request
     * @param Exercise $exercise
     * @return JsonResponse
     */
    public function destroy(Request $request, Exercise $exercise): JsonResponse
    {
        try {
            $this->exerciseService->deleteExercise($exercise, Auth::user());

            return $this->sendNoContentResponse();
        } catch (\Exception $e) {
            return $this->sendError('Failed to delete exercise.', ['error' => $e->getMessage()]);
        }
    }
}
