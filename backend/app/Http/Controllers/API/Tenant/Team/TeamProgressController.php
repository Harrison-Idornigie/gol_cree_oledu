<?php

namespace App\Http\Controllers\API\Tenant\Team;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\Tenants\Analytics\ProgressService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Team Progress Controller
 * 
 * Handles progress tracking and analytics for team-created content.
 * Access Level: Team (Teams/Content Creators)
 * Scope: Tenant-specific
 * 
 * This controller allows team members to track progress and analytics
 * for content they have created within their tenant scope.
 */
class TeamProgressController extends BaseAPIController
{
    use BelongsToTenant;

    protected ProgressService $progressService;

    /**
     * Constructor - Apply team middleware and inject services
     */
    public function __construct(ProgressService $progressService)
    {
        $this->progressService = $progressService;

        // Team members can view progress analytics for their content
        $this->middleware(function ($request, $next) {
            $this->authorize('viewAny', 'App\Models\Tenants\Progress');
            return $next($request);
        });
    }

    /**
     * Get progress overview for team member.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function overview(Request $request): JsonResponse
    {
        try {
            // Get the authenticated user ID
            $userId = Auth::id();

            $overview = $this->progressService->getTeamProgressOverview($userId, $request);

            return $this->sendResponse($overview, 'Progress overview retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve progress overview.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get progress for team member's content.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function myContentProgress(Request $request): JsonResponse
    {
        try {
            // Validate request parameters
            $validated = $request->validate([
                'per_page' => 'sometimes|integer|min:1|max:100',
                'content_type' => 'sometimes|string|in:App\Models\Tenants\LearningPath,App\Models\Tenants\Unit,App\Models\Tenants\Topic,App\Models\Tenants\Lesson',
                'status' => 'sometimes|string|in:not_started,in_progress,completed'
            ]);

            $userId = Auth::id();

            $contentProgress = $this->progressService->getMyContentProgress($userId, $request);

            return $this->sendResponse($contentProgress, 'Content progress retrieved successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve content progress.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get students' progress on team member's content.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function studentsProgress(Request $request): JsonResponse
    {
        try {
            // Validate request parameters
            $validated = $request->validate([
                'per_page' => 'sometimes|integer|min:1|max:100',
                'student_id' => 'sometimes|integer|exists:users,id',
                'content_type' => 'sometimes|string|in:App\Models\Tenants\LearningPath,App\Models\Tenants\Unit,App\Models\Tenants\Topic,App\Models\Tenants\Lesson'
            ]);

            $userId = Auth::id();

            $studentsProgress = $this->progressService->getStudentsProgressOnMyContent($userId, $request);

            return $this->sendResponse($studentsProgress, 'Students progress retrieved successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve students progress.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get progress for specific content item.
     * 
     * @param Request $request
     * @param string $type
     * @param int $id
     * @return JsonResponse
     */
    public function contentProgress(Request $request, string $type, int $id): JsonResponse
    {
        try {
            // Validate content type
            $allowedTypes = [
                'learning-paths' => 'App\Models\Tenants\LearningPath',
                'units' => 'App\Models\Tenants\Unit',
                'topics' => 'App\Models\Tenants\Topic',
                'lessons' => 'App\Models\Tenants\Lesson'
            ];

            if (!isset($allowedTypes[$type])) {
                return $this->sendError('Invalid content type.', ['type' => $type], 400);
            }

            $contentType = $allowedTypes[$type];
            $userId = Auth::id();

            $progressDetails = $this->progressService->getContentProgressDetails($userId, $contentType, $id, $request);

            return $this->sendResponse($progressDetails, 'Content progress retrieved successfully.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->sendError('Unauthorized access.', ['error' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve content progress.', ['error' => $e->getMessage()], 500);
        }
    }
}
