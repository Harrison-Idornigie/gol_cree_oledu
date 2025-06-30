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
        // Note: We handle authorization manually in each method since we need to handle string IDs
    }

    /**
     * Helper method to extract the correct parameter from route parameters.
     * Laravel is passing the first route parameter (tenant) to our methods instead of the named parameters.
     */
    private function getRouteParameter(Request $request, string $parameterName): string
    {
        $routeParams = $request->route()->parameters();

        if (isset($routeParams[$parameterName])) {
            return $routeParams[$parameterName];
        }

        // Fallback: try to extract from URL based on parameter name
        $url = $request->getPathInfo();

        if ($parameterName === 'learningPathId' && preg_match('/learning-paths\/(\d+)\//', $url, $matches)) {
            return $matches[1];
        } elseif ($parameterName === 'unitId' && preg_match('/units\/(\d+)(?:\/|$)/', $url, $matches)) {
            return $matches[1];
        }

        throw new \Exception("Could not determine {$parameterName} from request");
    }

    /**
     * Display units for a learning path.
     *
     * @param Request $request
     * @param string $learningPathId
     * @return JsonResponse
     */
    public function index(Request $request, string $learningPathId): JsonResponse
    {
        $actualLearningPathId = $this->getRouteParameter($request, 'learningPathId');

        // Find the learning path by ID
        $learningPath = LearningPath::where('status', 'published')->findOrFail($actualLearningPathId);

        $this->authorize('view', $learningPath);

        $units = $this->unitService->getUnitsForLearningPath($learningPath, 'student', $request->user());

        return $this->sendResponse($units, 'Units retrieved successfully.');
    }

    /**
     * Display the specified unit.
     *
     * @param Request $request
     * @param string $unitId
     * @return JsonResponse
     */
    public function show(Request $request, string $unitId): JsonResponse
    {
        $actualUnitId = $this->getRouteParameter($request, 'unitId');

        // Find the unit by ID
        $unit = Unit::where('status', 'published')->findOrFail($actualUnitId);

        $this->authorize('view', $unit);

        $unitData = $this->unitService->getUnit($unit->id, 'student', ['topics.lessons'], $request->user());

        if (!$unitData) {
            return $this->sendError('Unit not found or not accessible.', [], 404);
        }

        return $this->sendResponse($unitData, 'Unit retrieved successfully.');
    }

    /**
     * Get student's progress in a unit.
     *
     * @param Request $request
     * @param string $unitId
     * @return JsonResponse
     */
    public function progress(Request $request, string $unitId): JsonResponse
    {
        $actualUnitId = $this->getRouteParameter($request, 'unitId');

        // Find the unit by ID
        $unit = Unit::where('status', 'published')->findOrFail($actualUnitId);

        $this->authorize('view', $unit);

        $progress = $this->unitService->getUserProgress($unit, $request->user());

        return $this->sendResponse($progress, 'Unit progress retrieved successfully.');
    }

    /**
     * Get topics for a unit.
     *
     * @param Request $request
     * @param string $unitId
     * @return JsonResponse
     */
    public function topics(Request $request, string $unitId): JsonResponse
    {
        $actualUnitId = $this->getRouteParameter($request, 'unitId');

        // Find the unit by ID
        $unit = Unit::where('status', 'published')->findOrFail($actualUnitId);

        $this->authorize('view', $unit);

        $topics = $this->unitService->getTopicsForUnit($unit, 'student', $request->user());

        return $this->sendResponse($topics, 'Unit topics retrieved successfully.');
    }

    /**
     * Get unit contents with topics and lessons.
     *
     * @param Request $request
     * @param string $unitId
     * @return JsonResponse
     */
    public function contents(Request $request, string $unitId): JsonResponse
    {
        $actualUnitId = $this->getRouteParameter($request, 'unitId');

        // Find the unit by ID
        $unit = Unit::where('status', 'published')->findOrFail($actualUnitId);

        $this->authorize('view', $unit);

        $unitData = $this->unitService->getUnitWithContents($unit, 'student', $request->user());

        return $this->sendResponse($unitData, 'Unit contents retrieved successfully.');
    }

    /**
     * Start a unit for the student.
     *
     * @param Request $request
     * @param string $unitId
     * @return JsonResponse
     */
    public function start(Request $request, string $unitId): JsonResponse
    {
        $actualUnitId = $this->getRouteParameter($request, 'unitId');

        // Find the unit by ID
        $unit = Unit::where('status', 'published')->findOrFail($actualUnitId);

        $this->authorize('view', $unit);

        $progress = $this->unitService->startUnit($unit, $request->user(), $request->input('device_type', 'web'));

        return $this->sendResponse($progress, 'Unit started successfully.');
    }

    /**
     * Update unit progress.
     *
     * @param Request $request
     * @param string $unitId
     * @return JsonResponse
     */
    public function updateProgress(Request $request, string $unitId): JsonResponse
    {
        $actualUnitId = $this->getRouteParameter($request, 'unitId');

        // Find the unit by ID
        $unit = Unit::where('status', 'published')->findOrFail($actualUnitId);

        $this->authorize('view', $unit);

        $request->validate([
            'progress' => 'required|integer|min:0|max:100',
            'completed' => 'boolean'
        ]);

        $progress = $this->unitService->updateProgress(
            $unit,
            $request->user(),
            $request->input('progress'),
            $request->input('completed', false)
        );

        return $this->sendResponse($progress, 'Unit progress updated successfully.');
    }

    /**
     * Mark unit as completed.
     *
     * @param Request $request
     * @param string $unitId
     * @return JsonResponse
     */
    public function complete(Request $request, string $unitId): JsonResponse
    {
        $actualUnitId = $this->getRouteParameter($request, 'unitId');

        // Find the unit by ID
        $unit = Unit::where('status', 'published')->findOrFail($actualUnitId);

        $this->authorize('view', $unit);

        $progress = $this->unitService->completeUnit($unit, $request->user(), $request->input('device_type', 'web'));

        return $this->sendResponse($progress, 'Unit completed successfully.');
    }

    /**
     * Get recommended units for the student.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function recommendations(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Unit::class);

        $recommendations = $this->unitService->getRecommendations($request->user());

        return $this->sendResponse($recommendations, 'Unit recommendations retrieved successfully.');
    }

    /**
     * Get next unit recommendation for a learning path.
     *
     * @param Request $request
     * @param string $learningPathId
     * @return JsonResponse
     */
    public function nextUnit(Request $request, string $learningPathId): JsonResponse
    {
        $actualLearningPathId = $this->getRouteParameter($request, 'learningPathId');

        // Find the learning path by ID
        $learningPath = LearningPath::where('status', 'published')->findOrFail($actualLearningPathId);

        $this->authorize('view', $learningPath);

        $nextUnit = $this->unitService->getNextUnit($learningPath, $request->user());

        return $this->sendResponse($nextUnit, 'Next unit retrieved successfully.');
    }
}
