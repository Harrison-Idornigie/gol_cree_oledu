<?php

namespace App\Http\Controllers\API\Tenant\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\Tenants\Admin\TenantAnalyticsService;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

/**
 * Tenant Admin Content Controller
 *
 * Handles content overview and management for tenant administrators.
 * Access Level: Tenant Admin
 * Scope: Tenant-specific
 *
 * This controller provides read-only content management capabilities
 * for tenant administrators to oversee content creation and quality.
 */
class TenantAdminContentController extends BaseAPIController
{
    use BelongsToTenant;

    protected TenantAnalyticsService $analyticsService;

    /**
     * Constructor - Apply tenant admin middleware and inject services
     */
    public function __construct(TenantAnalyticsService $analyticsService)
    {
        $this->middleware(['auth:sanctum', 'verified', 'tenant']);
        $this->analyticsService = $analyticsService;
    }

    /**
     * Get content overview for tenant.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function contentOverview(Request $request): JsonResponse
    {
        $this->authorize('viewOverview', 'tenant-analytics');

        try {
            $overview = $this->analyticsService->getContentOverview($request);
            return $this->sendResponse($overview, 'Content overview retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve content overview', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get learning paths in tenant.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getLearningPaths(Request $request): JsonResponse
    {
        $this->authorize('viewLearningPaths', 'tenant-analytics');

        try {
            $learningPaths = $this->analyticsService->getLearningPaths($request);
            return $this->sendResponse($learningPaths, 'Learning paths retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve learning paths', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get languages available in tenant.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getLanguages(Request $request): JsonResponse
    {
        $this->authorize('viewLanguages', 'tenant-analytics');

        try {
            $languages = $this->analyticsService->getLanguages($request);
            return $this->sendResponse($languages, 'Languages retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve languages', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get content statistics for tenant.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getContentStatistics(Request $request): JsonResponse
    {
        $this->authorize('viewStatistics', 'tenant-analytics');

        try {
            $statistics = $this->analyticsService->getContentStatistics($request);
            return $this->sendResponse($statistics, 'Content statistics retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve content statistics', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Perform content health check.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function contentHealthCheck(Request $request): JsonResponse
    {
        $this->authorize('performHealthCheck', 'tenant-analytics');

        try {
            $healthCheck = $this->analyticsService->performContentHealthCheck($request);
            return $this->sendResponse($healthCheck, 'Content health check completed successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to perform content health check', ['error' => $e->getMessage()], 500);
        }
    }
}
