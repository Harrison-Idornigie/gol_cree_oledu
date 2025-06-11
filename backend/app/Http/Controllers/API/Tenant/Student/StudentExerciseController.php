<?php

namespace App\Http\Controllers\API\Tenant\Student;

use App\Http\Controllers\API\BaseAPIController;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\Exercise;
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

    /**
     * Constructor - Apply student middleware
     */
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'verified', 'tenant', 'membership:student']);
    }

    /**
     * Display a listing of exercises.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // TODO: Implement exercises listing
        // - Available exercises for student
        // - Filter by lesson, type, difficulty
        // - Include completion status
        return $this->sendResponse([], 'Exercises retrieved successfully.');
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
        // TODO: Implement exercise details
        // - Validate exercise is accessible
        // - Include exercise content and options
        // - Show previous attempts if any
        return $this->sendResponse($exercise, 'Exercise retrieved successfully.');
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
        // TODO: Implement exercise statistics
        // - Student's performance on this exercise
        // - Attempt history and scores
        // - Time spent and accuracy
        return $this->sendResponse([], 'Exercise statistics retrieved successfully.');
    }
}
