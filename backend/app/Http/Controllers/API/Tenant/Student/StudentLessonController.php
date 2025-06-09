<?php

namespace App\Http\Controllers\API\Tenant\Student;

use App\Http\Controllers\API\BaseAPIController;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Lesson;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Student Lesson Controller
 * 
 * Handles lesson access and completion for students.
 * Access Level: Student
 * Scope: Tenant-specific (read-only)
 * 
 * This controller allows students to access lessons within topics
 * with sequential learning enforcement.
 */
class StudentLessonController extends BaseAPIController
{
    use BelongsToTenant;

    /**
     * Constructor - Apply student middleware
     */
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'verified', 'tenant', 'role:student']);
    }

    /**
     * Display lessons for a topic.
     * 
     * @param Request $request
     * @param Topic $topic
     * @return JsonResponse
     */
    public function index(Request $request, Topic $topic): JsonResponse
    {
        // TODO: Implement lessons listing
        // - All lessons in the topic
        // - Show accessibility based on sequential learning
        // - Include progress and completion status
        return $this->sendResponse([], 'Lessons retrieved successfully.');
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
        // TODO: Implement lesson details
        // - Validate lesson is accessible (sequential learning)
        // - Include exercises and content
        // - Show progress and next steps
        return $this->sendResponse($lesson, 'Lesson retrieved successfully.');
    }

    /**
     * Get student's progress in a lesson.
     * 
     * @param Request $request
     * @param Lesson $lesson
     * @return JsonResponse
     */
    public function progress(Request $request, Lesson $lesson): JsonResponse
    {
        // TODO: Implement lesson progress
        // - Student's progress in the lesson
        // - Completed exercises
        // - Next recommended exercise
        return $this->sendResponse([], 'Lesson progress retrieved successfully.');
    }
}
