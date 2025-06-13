<?php

namespace App\Http\Controllers\API\Tenant\Student;

use App\Http\Controllers\API\BaseAPIController;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Student Picture Exercise Controller
 * 
 * Handles picture-based exercise interaction for students.
 * Access Level: Student
 * Scope: Tenant-specific (read-only)
 * 
 * This controller allows students to complete picture-based exercises
 * and improve their visual vocabulary recognition.
 */
class StudentPictureExerciseController extends BaseAPIController
{
    use BelongsToTenant;

    /**
     * Constructor - Apply student middleware
     */
    public function __construct()
    {

    }

    /**
     * Check answer for picture exercise.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function checkAnswer(Request $request): JsonResponse
    {
        // TODO: Implement picture exercise answer checking
        // - Validate picture exercise access
        // - Check student's identification or description
        // - Evaluate accuracy of visual recognition
        // - Provide feedback and correct answers
        return $this->sendResponse([], 'Picture exercise answer checked successfully.');
    }

    /**
     * Get picture exercises by language.
     * 
     * @param Request $request
     * @param string $languageCode
     * @return JsonResponse
     */
    public function getByLanguage(Request $request, string $languageCode): JsonResponse
    {
        // TODO: Implement language-specific picture exercises
        // - All picture exercises for specific language
        // - Filter by difficulty and category
        // - Include image metadata and descriptions
        return $this->sendResponse([], 'Picture exercises retrieved successfully.');
    }
}
