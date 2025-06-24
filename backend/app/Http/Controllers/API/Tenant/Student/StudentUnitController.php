<?php

namespace App\Http\Controllers\API\Tenant\Student;

use App\Http\Controllers\API\BaseAPIController;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\Unit;
use App\Models\Tenants\LearningPath;
use App\Services\Tenants\Course\UnitService;
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

    protected UnitService $unitService;

    /**
     * Constructor - Apply student middleware and inject service
     */
    public function __construct(UnitService $unitService)
    {
        $this->unitService = $unitService;
        // Apply policies - students can only view units
        $this->authorizeResource(Unit::class, 'unit');
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
        $this->authorize('viewAny', Unit::class);

        $units = $this->unitService->getUnitsForLearningPath($learningPath, 'student');

        return $this->sendResponse($units, 'Units retrieved successfully.');
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
        $this->authorize('view', $unit);

        $unitData = $this->unitService->getUnit($unit->id, 'student', ['topics.lessons']);

        if (!$unitData) {
            return $this->sendError('Unit not found or not accessible.', [], 404);
        }

        return $this->sendResponse($unitData, 'Unit retrieved successfully.');
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
        $this->authorize('view', $unit);

        $progress = $this->unitService->getUserProgress($unit, $request->user());

        return $this->sendResponse($progress, 'Unit progress retrieved successfully.');
    }
}
