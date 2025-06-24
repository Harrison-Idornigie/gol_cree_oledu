<?php

namespace App\Http\Controllers\API\Tenant\Student;

use App\Http\Controllers\API\BaseAPIController;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\Topic;
use App\Models\Tenants\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Student Topic Controller
 * 
 * Handles topic access and progression for students.
 * Access Level: Student
 * Scope: Tenant-specific (read-only)
 * 
 * This controller allows students to access topics within units
 * with sequential learning enforcement.
 */
class StudentTopicController extends BaseAPIController
{
    use BelongsToTenant;

    /**
     * Constructor - Apply student middleware
     */
    public function __construct()
    {
        // Apply policies - students can view topics
        $this->authorizeResource(Topic::class, 'topic');
    }

    /**
     * Display topics for a unit.
     * 
     * @param Request $request
     * @param Unit $unit
     * @return JsonResponse
     */
    public function index(Request $request, Unit $unit): JsonResponse
    {
        $this->authorize('viewAny', Topic::class);

        // TODO: Implement topics listing
        // - All topics in the unit
        // - Show accessibility based on sequential learning
        // - Include progress and completion status
        return $this->sendResponse([], 'Topics retrieved successfully.');
    }

    /**
     * Display the specified topic.
     * 
     * @param Request $request
     * @param Topic $topic
     * @return JsonResponse
     */
    public function show(Request $request, Topic $topic): JsonResponse
    {
        $this->authorize('view', $topic);

        // TODO: Implement topic details
        // - Validate topic is accessible (sequential learning)
        // - Include lessons and exercises structure
        // - Show progress and next steps
        return $this->sendResponse($topic, 'Topic retrieved successfully.');
    }

    /**
     * Get student's progress in a topic.
     * 
     * @param Request $request
     * @param Topic $topic
     * @return JsonResponse
     */
    public function progress(Request $request, Topic $topic): JsonResponse
    {
        $this->authorize('view', $topic);

        // TODO: Implement topic progress
        // - Student's progress in the topic
        // - Completed lessons and exercises
        // - Next recommended content
        return $this->sendResponse([], 'Topic progress retrieved successfully.');
    }
}
