<?php

namespace App\Http\Controllers\API\Tenant\Student;

use App\Http\Controllers\API\BaseAPIController;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\Unit;
use App\Models\Tenants\LearningPath;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Student Unit Controller
 * 
 * Handles unit access and progression for students.
 * Access Level: Student
 * Scope: Tenant-specific (read-only)
 * 
 * This controller allows students to access units within learning paths
 * with sequential learning enforcement.
 */
class StudentUnitController extends BaseAPIController
{
    use BelongsToTenant;

    /**
     * Constructor - Apply student middleware
     */
    public function __construct()
    {

    }

    /**
     * Display units for a learning path.
     * 
     * @param Request $request
     * @param LearningPath $learningPath
     * @return JsonResponse
     */
    public function index(Request $request, LearningPath $learningPath): JsonResponse
    {
        // TODO: Implement units listing
        // - All units in the learning path
        // - Show accessibility based on sequential learning
        // - Include progress and completion status
        return $this->sendResponse([], 'Units retrieved successfully.');
    }

    /**
     * Display the specified unit.
     * 
     * @param Request $request
     * @param Unit $unit
     * @return JsonResponse
     */
    public function show(Request $request, Unit $unit): JsonResponse
    {
        // TODO: Implement unit details
        // - Validate unit is accessible (sequential learning)
        // - Include topics and lessons structure
        // - Show progress and next steps
        return $this->sendResponse($unit, 'Unit retrieved successfully.');
    }

    /**
     * Get student's progress in a unit.
     * 
     * @param Request $request
     * @param Unit $unit
     * @return JsonResponse
     */
    public function progress(Request $request, Unit $unit): JsonResponse
    {
        // TODO: Implement unit progress
        // - Student's progress in the unit
        // - Completed topics and lessons
        // - Next recommended content
        return $this->sendResponse([], 'Unit progress retrieved successfully.');
    }
}
