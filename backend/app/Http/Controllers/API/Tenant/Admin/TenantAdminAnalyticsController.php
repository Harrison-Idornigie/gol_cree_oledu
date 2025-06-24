<?php

namespace App\Http\Controllers\API\Tenant\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\Tenants\Admin\TenantAnalyticsService;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

/**
 * Tenant Admin Analytics Controller
 *
 * Handles tenant-specific analytics and reporting operations.
 * Access Level: Tenant Admin
 * Scope: Tenant-specific
 *
 * This controller provides analytics functionality for tenant administrators,
 * including user analytics, content analytics, and engagement metrics.
 */
class TenantAdminAnalyticsController extends BaseAPIController
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
     * Get general analytics overview.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewOverview', 'tenant-analytics');

        try {
            $analytics = $this->analyticsService->getAnalyticsOverview($request);
            return $this->sendResponse($analytics, 'Analytics overview retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve analytics overview', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get user analytics for tenant.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function userAnalytics(Request $request): JsonResponse
    {
        $this->authorize('viewUserAnalytics', 'tenant-analytics');

        try {
            $analytics = $this->analyticsService->getUserAnalytics($request);
            return $this->sendResponse($analytics, 'User analytics retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve user analytics', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get content analytics for tenant.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function contentAnalytics(Request $request): JsonResponse
    {
        $this->authorize('viewContentAnalytics', 'tenant-analytics');

        try {
            $analytics = $this->analyticsService->getContentAnalytics($request);
            return $this->sendResponse($analytics, 'Content analytics retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve content analytics', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get engagement analytics for tenant.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function engagementAnalytics(Request $request): JsonResponse
    {
        $this->authorize('viewEngagementAnalytics', 'tenant-analytics');

        try {
            $analytics = $this->analyticsService->getEngagementAnalytics($request);
            return $this->sendResponse($analytics, 'Engagement analytics retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve engagement analytics', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate user progress report.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function userProgressReport(Request $request): JsonResponse
    {
        $this->authorize('generateUserProgressReport', 'tenant-analytics');

        try {
            $report = $this->analyticsService->getUserProgressReport($request);
            return $this->sendResponse($report, 'User progress report generated successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to generate user progress report', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate content usage report.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function contentUsageReport(Request $request): JsonResponse
    {
        $this->authorize('generateContentUsageReport', 'tenant-analytics');

        try {
            $report = $this->analyticsService->getContentUsageReport($request);
            return $this->sendResponse($report, 'Content usage report generated successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to generate content usage report', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate engagement report.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function engagementReport(Request $request): JsonResponse
    {
        $this->authorize('generateEngagementReport', 'tenant-analytics');

        try {
            $report = $this->analyticsService->getEngagementReport($request);
            return $this->sendResponse($report, 'Engagement report generated successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to generate engagement report', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Export analytics report.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function exportReport(Request $request): JsonResponse
    {
        $this->authorize('exportReport', 'tenant-analytics');

        try {
            $export = $this->analyticsService->exportReport($request);
            return $this->sendResponse($export, 'Report export initiated successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to export report', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate performance report.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function performanceReport(Request $request): JsonResponse
    {
        $this->authorize('generatePerformanceReport', 'tenant-analytics');

        try {
            $report = $this->analyticsService->getPerformanceReport($request);
            return $this->sendResponse($report, 'Performance report generated successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to generate performance report', ['error' => $e->getMessage()], 500);
        }
    }

    // Dashboard methods (consolidated from TenantAdminDashboardController)

    /**
     * Get tenant dashboard overview.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getDashboardOverview(Request $request): JsonResponse
    {
        $this->authorize('viewDashboard', 'tenant-analytics');

        try {
            $dashboard = $this->analyticsService->getDashboardOverview($request);
            return $this->sendResponse($dashboard, 'Dashboard data retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve dashboard data', ['error' => $e->getMessage()], 500);
        }
    }

    // Content methods (consolidated from TenantAdminContentController)

    /**
     * Get content overview for tenant.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getContentOverview(Request $request): JsonResponse
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

    /**
     * Perform content health check.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function performContentHealthCheck(Request $request): JsonResponse
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
