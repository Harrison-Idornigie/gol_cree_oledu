<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class AdminAuditController extends BaseAPIController
{
    /**
     * Display a listing of audit logs.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Debug logging to see what's being passed
            Log::debug('Audit log filter request data:', [
                'raw_start_date' => $request->input('start_date'),
                'raw_end_date' => $request->input('end_date'),
                'all_parameters' => $request->all()
            ]);

            // Extract parameters first before validation
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            // Validate other parameters that aren't dates
            $validatedData = $request->validate([
                'action' => 'nullable|string',
                'area' => 'nullable|string',
                'user_id' => 'nullable|integer|exists:users,id',
                'status' => 'nullable|string|in:success,failure,error',
                'per_page' => 'nullable|integer|min:1|max:100',
                'sort_by' => 'nullable|string|in:performed_at,action,area,status',
                'sort_order' => 'nullable|string|in:asc,desc'
            ]);

            $query = AuditLog::query()->with('user');

            // Apply date filters
            if (!empty($startDate)) {
                try {
                    $parsedStartDate = Carbon::parse($startDate)->startOfDay();
                    Log::debug('Parsed start date', ['original' => $startDate, 'parsed' => $parsedStartDate->toDateTimeString()]);
                    $query->where('performed_at', '>=', $parsedStartDate);
                } catch (Exception $e) {
                    Log::error("Error parsing start_date: " . $e->getMessage(), [
                        'input' => $startDate,
                        'exception' => $e->getMessage()
                    ]);
                    // Continue with the query without this filter
                }
            }

            if (!empty($endDate)) {
                try {
                    $parsedEndDate = Carbon::parse($endDate)->endOfDay();
                    Log::debug('Parsed end date', ['original' => $endDate, 'parsed' => $parsedEndDate->toDateTimeString()]);
                    $query->where('performed_at', '<=', $parsedEndDate);
                } catch (Exception $e) {
                    Log::error("Error parsing end_date: " . $e->getMessage(), [
                        'input' => $endDate,
                        'exception' => $e->getMessage()
                    ]);
                    // Continue with the query without this filter
                }
            }

            // Apply other filters
            if (!empty($validatedData['action'])) {
                $query->where('action', $validatedData['action']);
            }
            if (!empty($validatedData['area'])) {
                $query->where('area', $validatedData['area']);
            }
            if (!empty($validatedData['user_id'])) {
                $query->where('user_id', $validatedData['user_id']);
            }
            if (!empty($validatedData['status'])) {
                $query->where('status', $validatedData['status']);
            }

            // Apply sorting
            $sortBy = $request->input('sort_by', 'performed_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->input('per_page', 15);
            $logs = $query->paginate($perPage);

            return $this->sendPaginatedResponse($logs);
        } catch (ValidationException $e) {
            Log::error('Validation error in index method: ' . $e->getMessage(), [
                'errors' => $e->errors(),
                'request' => $request->all()
            ]);
            return $this->sendError('Validation error', $e->errors(), 422);
        } catch (Exception $e) {
            Log::error('Error in index method: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return $this->sendError('Failed to retrieve audit logs: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified audit log.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $log = AuditLog::with(['user', 'auditable'])->findOrFail($id);
            return $this->sendResponse($log);
        } catch (ModelNotFoundException $e) {
            Log::error('Audit log not found', ['id' => $id]);
            return $this->sendError('Audit log not found', [], 404);
        } catch (Exception $e) {
            Log::error('Error in show method: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'id' => $id
            ]);
            return $this->sendError('Failed to retrieve audit log: ' . $e->getMessage());
        }
    }

    /**
     * Get audit logs for a specific user.
     */
    public function userActivity(Request $request, int $userId): JsonResponse
    {
        try {
            $request->validate([
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'per_page' => 'nullable|integer|min:1|max:100'
            ]);

            $query = AuditLog::where('user_id', $userId)->with('auditable');

            if ($request->has('start_date')) {
                try {
                    $startDate = Carbon::parse($request->start_date)->startOfDay();
                    $query->where('performed_at', '>=', $startDate);
                } catch (Exception $e) {
                    Log::warning('Invalid start date in userActivity', [
                        'input' => $request->start_date,
                        'error' => $e->getMessage()
                    ]);
                    return $this->sendError('Invalid start date format', [], 400);
                }
            }
            
            if ($request->has('end_date')) {
                try {
                    $endDate = Carbon::parse($request->end_date)->endOfDay();
                    $query->where('performed_at', '<=', $endDate);
                } catch (Exception $e) {
                    Log::warning('Invalid end date in userActivity', [
                        'input' => $request->end_date,
                        'error' => $e->getMessage()
                    ]);
                    return $this->sendError('Invalid end date format', [], 400);
                }
            }

            $logs = $query->orderBy('performed_at', 'desc')
                ->paginate($request->input('per_page', 15));

            return $this->sendPaginatedResponse($logs);
        } catch (ValidationException $e) {
            Log::error('Validation error in userActivity method: ' . $e->getMessage(), [
                'errors' => $e->errors(),
                'request' => $request->all()
            ]);
            return $this->sendError('Validation error', $e->errors(), 422);
        } catch (Exception $e) {
            Log::error('Error in userActivity method: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'userId' => $userId,
                'request' => $request->all()
            ]);
            return $this->sendError('Failed to retrieve user activity logs: ' . $e->getMessage());
        }
    }

    /**
     * Get audit history for a specific content item.
     */
    public function contentHistory(Request $request, string $type, int $id): JsonResponse
    {
        try {
            $request->validate([
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'per_page' => 'nullable|integer|min:1|max:100'
            ]);

            $modelClass = 'App\\Models\\' . ucfirst($type);
            if (!class_exists($modelClass)) {
                Log::warning('Invalid content type in contentHistory', ['type' => $type]);
                return $this->sendError('Invalid content type.', ['error' => 'Invalid content type'], 404);
            }

            $query = AuditLog::where('auditable_type', $modelClass)
                ->where('auditable_id', $id)
                ->with('user');

            if ($request->has('start_date')) {
                try {
                    $startDate = Carbon::parse($request->start_date)->startOfDay();
                    $query->where('performed_at', '>=', $startDate);
                } catch (Exception $e) {
                    Log::warning('Invalid start date in contentHistory', [
                        'input' => $request->start_date,
                        'error' => $e->getMessage()
                    ]);
                    return $this->sendError('Invalid start date format', [], 400);
                }
            }
            
            if ($request->has('end_date')) {
                try {
                    $endDate = Carbon::parse($request->end_date)->endOfDay();
                    $query->where('performed_at', '<=', $endDate);
                } catch (Exception $e) {
                    Log::warning('Invalid end date in contentHistory', [
                        'input' => $request->end_date,
                        'error' => $e->getMessage()
                    ]);
                    return $this->sendError('Invalid end date format', [], 400);
                }
            }

            $logs = $query->orderBy('performed_at', 'desc')
                ->paginate($request->input('per_page', 15));

            return $this->sendPaginatedResponse($logs);
        } catch (ValidationException $e) {
            Log::error('Validation error in contentHistory method: ' . $e->getMessage(), [
                'errors' => $e->errors(),
                'request' => $request->all()
            ]);
            return $this->sendError('Validation error', $e->errors(), 422);
        } catch (Exception $e) {
            Log::error('Error in contentHistory method: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'type' => $type,
                'id' => $id,
                'request' => $request->all()
            ]);
            return $this->sendError('Failed to retrieve content history: ' . $e->getMessage());
        }
    }

    /**
     * Export audit logs to CSV.
     */
    public function export(Request $request)
    {
        try {
            // Extract parameters first before validation
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            $validatedData = $request->validate([
                'format' => 'nullable|string|in:csv,json',
                'action' => 'nullable|string',
                'area' => 'nullable|string',
                'user_id' => 'nullable|integer|exists:users,id',
            ]);

            if (empty($startDate)) {
                return $this->sendError('Start date is required for export', [], 400);
            }

            if (empty($endDate)) {
                return $this->sendError('End date is required for export', [], 400);
            }

            $query = AuditLog::with('user');

            // Apply date filters
            try {
                $parsedStartDate = Carbon::parse($startDate)->startOfDay();
                Log::debug('Export - Parsed start date', ['original' => $startDate, 'parsed' => $parsedStartDate->toDateTimeString()]);
                $query->where('performed_at', '>=', $parsedStartDate);
            } catch (Exception $e) {
                Log::error("Export - Error parsing start_date: " . $e->getMessage(), [
                    'input' => $startDate,
                    'exception' => $e->getMessage()
                ]);
                return $this->sendError('Invalid start date format', [], 400);
            }

            try {
                $parsedEndDate = Carbon::parse($endDate)->endOfDay();
                Log::debug('Export - Parsed end date', ['original' => $endDate, 'parsed' => $parsedEndDate->toDateTimeString()]);
                $query->where('performed_at', '<=', $parsedEndDate);
            } catch (Exception $e) {
                Log::error("Export - Error parsing end_date: " . $e->getMessage(), [
                    'input' => $endDate,
                    'exception' => $e->getMessage()
                ]);
                return $this->sendError('Invalid end date format', [], 400);
            }

            // Check if the date range is reasonable
            if ($parsedStartDate->diffInDays($parsedEndDate) > 366) {
                return $this->sendError('Date range too large. Maximum range is 366 days.', [], 400);
            }

            if (!empty($validatedData['action'])) {
                $query->where('action', $validatedData['action']);
            }
            if (!empty($validatedData['area'])) {
                $query->where('area', $validatedData['area']);
            }
            if (!empty($validatedData['user_id'])) {
                $query->where('user_id', $validatedData['user_id']);
            }

            $logs = $query->orderBy('performed_at')->get();

            if ($logs->isEmpty()) {
                return $this->sendError('No data available for the selected criteria', [], 404);
            }

            $format = $validatedData['format'] ?? 'csv';

            if ($format === 'json') {
                return $this->sendResponse($logs);
            }

            // Generate CSV
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename=audit-logs.csv'
            ];

            $callback = function () use ($logs) {
                $file = fopen('php://output', 'w');

                // Add headers
                fputcsv($file, [
                    'ID',
                    'User',
                    'Action',
                    'Area',
                    'Status',
                    'IP Address',
                    'User Agent',
                    'Performed At',
                    'Details'
                ]);

                // Add data
                foreach ($logs as $log) {
                    fputcsv($file, [
                        $log->id,
                        $log->user ? $log->user->name : 'System',
                        $log->action,
                        $log->area,
                        $log->status,
                        $log->ip_address,
                        $log->user_agent,
                        $log->performed_at,
                        json_encode($log->changes)
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (ValidationException $e) {
            Log::error('Validation error in export method: ' . $e->getMessage(), [
                'errors' => $e->errors(),
                'request' => $request->all()
            ]);
            return $this->sendError('Validation error', $e->errors(), 422);
        } catch (Exception $e) {
            Log::error('Error in export method: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return $this->sendError('Failed to export audit logs: ' . $e->getMessage());
        }
    }

    /**
     * Get audit log statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date'
            ]);

            try {
                $startDate = $request->input('start_date') 
                    ? Carbon::parse($request->input('start_date'))
                    : Carbon::now()->subDays(30);
            } catch (Exception $e) {
                Log::warning('Invalid start date in statistics', [
                    'input' => $request->input('start_date'),
                    'error' => $e->getMessage()
                ]);
                return $this->sendError('Invalid start date format', [], 400);
            }

            try {
                $endDate = $request->input('end_date')
                    ? Carbon::parse($request->input('end_date'))
                    : Carbon::now();
            } catch (Exception $e) {
                Log::warning('Invalid end date in statistics', [
                    'input' => $request->input('end_date'),
                    'error' => $e->getMessage()
                ]);
                return $this->sendError('Invalid end date format', [], 400);
            }

            // Check if the date range is reasonable
            if ($startDate->diffInDays($endDate) > 366) {
                return $this->sendError('Date range too large. Maximum range is 366 days.', [], 400);
            }

            $query = AuditLog::whereBetween('performed_at', [$startDate, $endDate]);

            $totalLogs = $query->count();
            
            if ($totalLogs === 0) {
                return $this->sendResponse([
                    'total_logs' => 0,
                    'by_status' => [],
                    'by_action' => [],
                    'by_area' => [],
                    'by_user' => [],
                    'timeline' => []
                ]);
            }

            $byStatus = $query->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status');

            $byAction = $query->selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->orderByDesc('count')
                ->limit(10)
                ->get();

            $byArea = $query->selectRaw('area, COUNT(*) as count')
                ->groupBy('area')
                ->orderByDesc('count')
                ->get();

            $byUser = $query->selectRaw('user_id, COUNT(*) as count')
                ->with('user:id,name')
                ->groupBy('user_id')
                ->orderByDesc('count')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    return [
                        'user' => $item->user ? $item->user->name : 'System',
                        'count' => $item->count
                    ];
                });

            $timeline = $query->selectRaw('DATE(performed_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->pluck('count', 'date');

            $stats = [
                'total_logs' => $totalLogs,
                'by_status' => $byStatus,
                'by_action' => $byAction,
                'by_area' => $byArea,
                'by_user' => $byUser,
                'timeline' => $timeline
            ];

            return $this->sendResponse($stats);
        } catch (ValidationException $e) {
            Log::error('Validation error in statistics method: ' . $e->getMessage(), [
                'errors' => $e->errors(),
                'request' => $request->all()
            ]);
            return $this->sendError('Validation error', $e->errors(), 422);
        } catch (Exception $e) {
            Log::error('Error in statistics method: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return $this->sendError('Failed to retrieve audit log statistics: ' . $e->getMessage());
        }
    }
}
