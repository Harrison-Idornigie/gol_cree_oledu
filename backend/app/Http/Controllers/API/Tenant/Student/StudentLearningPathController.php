<?php

namespace App\Http\Controllers\API\Tenant\Student;

use App\Http\Controllers\API\BaseAPIController;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\LearningPath;
use App\Services\Tenants\Course\LearningPathService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Student Learning Path Controller
 * 
 * Handles learning path access and enrollment for students.
 * Access Level: Student
 * Scope: Tenant-specific (read-only)
 * 
 * This controller allows students to browse and enroll in learning paths
 * within their tenant scope.
 */
class StudentLearningPathController extends BaseAPIController
{
    use BelongsToTenant;

    protected LearningPathService $learningPathService;

    /**
     * Constructor - Apply student middleware
     */
    public function __construct(LearningPathService $learningPathService)
    {
        $this->learningPathService = $learningPathService;
        // Apply policies - students can view and enroll in learning paths
        $this->authorizeResource(LearningPath::class, 'learningPath');
    }

    /**
     * Display a listing of available learning paths.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LearningPath::class);

        $learningPaths = $this->learningPathService->getFilteredLearningPaths($request, 'student');

        return $this->sendResponse($learningPaths, 'Learning paths retrieved successfully.');
    }

    /**
     * Display the specified learning path.
     *
     * @param Request $request
     * @param string|LearningPath $learningPath
     * @return JsonResponse
     */
    public function show(Request $request, $learningPath): JsonResponse
    {
        // Handle both string ID and model binding
        if (is_string($learningPath) || is_numeric($learningPath)) {
            $learningPath = LearningPath::where('status', 'published')->findOrFail($learningPath);
        }

        $this->authorize('view', $learningPath);

        // Use service to get learning path with proper filtering and relationships
        $learningPathData = $this->learningPathService->getLearningPath(
            $learningPath->id,
            'student',
            ['units.topics.lessons']
        );

        if (!$learningPathData) {
            return $this->sendError('Learning path not found or not accessible.', [], 404);
        }

        return $this->sendResponse($learningPathData, 'Learning path retrieved successfully.');
    }

    /**
     * Get student's progress in a learning path.
     *
     * @param Request $request
     * @param string|LearningPath $learningPath
     * @return JsonResponse
     */
    public function progress(Request $request, $learningPath): JsonResponse
    {
        // Handle both string ID and model binding
        if (is_string($learningPath) || is_numeric($learningPath)) {
            $learningPath = LearningPath::where('status', 'published')->findOrFail($learningPath);
        }

        $this->authorize('view', $learningPath);

        $progress = $this->learningPathService->getUserProgress($learningPath, $request->user());

        return $this->sendResponse($progress, 'Learning path progress retrieved successfully.');
    }

    /**
     * Enroll student in a learning path.
     *
     * @param Request $request
     * @param string|LearningPath $learningPath
     * @return JsonResponse
     */
    public function enroll(Request $request, $learningPath): JsonResponse
    {
        // Handle both string ID and model binding
        if (is_string($learningPath) || is_numeric($learningPath)) {
            // Debug: Log what we're looking for
            \Log::info("Looking for learning path ID: " . $learningPath);
            \Log::info("Available learning paths: " . LearningPath::pluck('id', 'title')->toJson());

            $learningPath = LearningPath::where('status', 'published')->findOrFail($learningPath);
        }

        $this->authorize('enroll', $learningPath);

        $result = $this->learningPathService->enrollUser($learningPath, $request->user());

        if ($result['success']) {
            return $this->sendCreatedResponse($result['enrollment'], $result['message']);
        } else {
            return $this->sendError($result['message'], $result['enrollment'], 400);
        }
    }

    /**
     * Get learning paths by difficulty level.
     * 
     * @param Request $request
     * @param string $level
     * @return JsonResponse
     */
    public function byLevel(Request $request, string $level): JsonResponse
    {
        $this->authorize('viewAny', LearningPath::class);

        // Create a new request with the level filter
        $filteredRequest = $request->duplicate();
        $filteredRequest->merge(['target_level' => $level, 'with_language' => true]);

        $learningPaths = $this->learningPathService->getFilteredLearningPaths($filteredRequest, 'student');

        return $this->sendResponse($learningPaths, 'Learning paths by level retrieved successfully.');
    }
}
