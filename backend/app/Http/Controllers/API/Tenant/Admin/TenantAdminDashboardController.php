<?php

namespace App\Http\Controllers\API\Tenant\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\Tenants\Admin\TenantAnalyticsService;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

/**
 * Tenant Admin Dashboard Controller
 *
 * Handles tenant dashboard and overview operations.
 * Access Level: Tenant Admin
 * Scope: Tenant-specific
 *
 * This controller provides dashboard functionality for tenant administrators,
 * including overview statistics, content summaries, and tenant settings.
 */
class TenantAdminDashboardController extends BaseAPIController
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
     * Get tenant dashboard overview.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewDashboard', 'tenant-analytics');

        try {
            $dashboard = $this->analyticsService->getDashboardOverview($request);
            return $this->sendResponse($dashboard, 'Dashboard data retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve dashboard data', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get content overview for tenant.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function contentOverview(Request $request): JsonResponse
    {
        $this->authorize('viewContentOverview', 'tenant-analytics');

        try {
            $overview = $this->analyticsService->getContentOverview($request);
            return $this->sendResponse($overview, 'Content overview retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve content overview', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get learning paths for tenant.
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
        $this->authorize('viewContentStatistics', 'tenant-analytics');

        try {
            $statistics = $this->analyticsService->getContentStatistics($request);
            return $this->sendResponse($statistics, 'Content statistics retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve content statistics', ['error' => $e->getMessage()], 500);
        }
    }
}
