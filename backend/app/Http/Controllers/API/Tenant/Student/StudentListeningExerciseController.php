<?php

namespace App\Http\Controllers\API\Tenant\Student;

use App\Http\Controllers\API\BaseAPIController;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Student Listening Exercise Controller
 * 
 * Handles listening exercise interaction for students.
 * Access Level: Student
 * Scope: Tenant-specific (read-only)
 * 
 * This controller allows students to complete listening exercises
 * and improve their listening comprehension skills.
 */
class StudentListeningExerciseController extends BaseAPIController
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
     * Check answer for listening exercise.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function checkAnswer(Request $request): JsonResponse
    {
        // TODO: Implement listening exercise answer checking
        // - Validate listening exercise access
        // - Check student's transcription or comprehension answer
        // - Evaluate accuracy and understanding
        // - Provide feedback and correct answers
        return $this->sendResponse([], 'Listening exercise answer checked successfully.');
    }

    /**
     * Get listening exercises by language.
     * 
     * @param Request $request
     * @param string $languageCode
     * @return JsonResponse
     */
    public function getByLanguage(Request $request, string $languageCode): JsonResponse
    {
        // TODO: Implement language-specific listening exercises
        // - All listening exercises for specific language
        // - Filter by difficulty and topic
        // - Include audio metadata and duration
        return $this->sendResponse([], 'Listening exercises retrieved successfully.');
    }
}
