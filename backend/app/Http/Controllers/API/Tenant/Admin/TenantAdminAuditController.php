<?php

namespace App\Http\Controllers\API\Tenant\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\Tenants\Admin\AuditService;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

/**
 * Tenant Admin Audit Controller
 * 
 * Handles audit log management within tenant scope.
 * Access Level: Tenant Admin
 * Scope: Tenant-specific
 * 
 * This controller manages audit logs and activity tracking
 * for tenant administrators to monitor system usage and changes.
 */
class TenantAdminAuditController extends BaseAPIController
{
    use BelongsToTenant;

    protected AuditService $auditService;

    /**
     * Constructor - Apply tenant admin middleware and inject dependencies
     */
    public function __construct(AuditService $auditService)
    {
        $this->middleware(['auth:sanctum', 'verified', 'tenant']);
        $this->auditService = $auditService;
    }

    /**
     * Display a listing of audit logs for the tenant.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'user_id' => 'nullable|integer|exists:users,id',
            'action' => 'nullable|string|max:255',
            'area' => 'nullable|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'search' => 'nullable|string|max:255'
        ]);

        try {
            $this->authorize('viewAny', \App\Models\Tenants\AuditLog::class);

            $filters = $request->only(['user_id', 'action', 'area', 'date_from', 'date_to', 'search']);
            $perPage = $request->get('per_page', 25);

            $auditLogs = $this->auditService->getAuditLogs($filters, $perPage);

            return $this->sendResponse($auditLogs, 'Audit logs retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve audit logs', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Export audit logs.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'format' => 'required|string|in:csv,json',
            'user_id' => 'nullable|integer|exists:users,id',
            'action' => 'nullable|string|max:255',
            'area' => 'nullable|string|max:255',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from'
        ]);

        try {
            $this->authorize('export', \App\Models\Tenants\AuditLog::class);

            $filters = $request->only(['user_id', 'action', 'area', 'date_from', 'date_to']);
            $format = $request->get('format', 'csv');

            $filePath = $this->auditService->exportAuditLogs($filters, $format);

            // Log the export action
            $this->auditService->createAuditLog(
                'audit_management',
                'export_logs',
                null,
                [
                    'format' => $format,
                    'filters' => $filters,
                    'file_path' => $filePath
                ]
            );

            return $this->sendResponse([
                'file_path' => $filePath,
                'download_url' => route('tenant.admin.audit.download', ['file' => basename($filePath)])
            ], 'Audit logs export initiated successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to export audit logs', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified audit log entry.
     * 
     * @param Request $request
     * @param int $logId
     * @return JsonResponse
     */
    public function show(Request $request, int $logId): JsonResponse
    {
        try {
            $auditLog = $this->auditService->getAuditLogDetails($logId);

            $this->authorize('view', $auditLog);

            return $this->sendResponse($auditLog, 'Audit log retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve audit log', ['error' => $e->getMessage()], 404);
        }
    }

    /**
     * Get audit logs summary for tenant.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getSummary(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from'
        ]);

        try {
            $this->authorize('viewAny', \App\Models\Tenants\AuditLog::class);

            $filters = $request->only(['date_from', 'date_to']);
            $summary = $this->auditService->getAuditSummary($filters);

            return $this->sendResponse($summary, 'Audit summary retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve audit summary', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get audit statistics.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', \App\Models\Tenants\AuditLog::class);

            $statistics = $this->auditService->getAuditStatistics();

            return $this->sendResponse($statistics, 'Audit statistics retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve audit statistics', ['error' => $e->getMessage()], 500);
        }
    }
}
