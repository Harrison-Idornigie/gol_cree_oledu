<?php

namespace App\Services\Tenants\Admin;

use App\Models\Tenants\AuditLog;
use App\Models\Tenants\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Exception;

/**
 * Audit Service
 * 
 * Handles audit log management, retrieval, and analysis for tenant administrators.
 */
class AuditService
{
    /**
     * Get paginated audit logs with filters.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAuditLogs(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = AuditLog::with(['user'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (isset($filters['user_id']) && $filters['user_id']) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['action']) && $filters['action']) {
            $query->where('action', 'like', '%' . $filters['action'] . '%');
        }

        if (isset($filters['area']) && $filters['area']) {
            $query->where('area', $filters['area']);
        }

        if (isset($filters['date_from']) && $filters['date_from']) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if (isset($filters['date_to']) && $filters['date_to']) {
            $query->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        if (isset($filters['search']) && $filters['search']) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', '%' . $search . '%')
                  ->orWhere('area', 'like', '%' . $search . '%')
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('name', 'like', '%' . $search . '%')
                               ->orWhere('email', 'like', '%' . $search . '%');
                  });
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Get detailed audit log entry.
     *
     * @param int $logId
     * @return AuditLog
     * @throws Exception
     */
    public function getAuditLogDetails(int $logId): AuditLog
    {
        $auditLog = AuditLog::with(['user'])
            ->find($logId);

        if (!$auditLog) {
            throw new Exception('Audit log not found');
        }

        return $auditLog;
    }

    /**
     * Get audit logs summary.
     *
     * @param array $filters
     * @return array
     */
    public function getAuditSummary(array $filters = []): array
    {
        $dateFrom = isset($filters['date_from']) 
            ? Carbon::parse($filters['date_from'])->startOfDay()
            : Carbon::now()->subDays(30)->startOfDay();
        
        $dateTo = isset($filters['date_to'])
            ? Carbon::parse($filters['date_to'])->endOfDay()
            : Carbon::now()->endOfDay();

        // Activity by type
        $activityByType = AuditLog::where('created_at', '>=', $dateFrom)
            ->where('created_at', '<=', $dateTo)
            ->select('area', DB::raw('count(*) as count'))
            ->groupBy('area')
            ->orderBy('count', 'desc')
            ->get()
            ->toArray();

        // Most active users
        $mostActiveUsers = AuditLog::with('user')
            ->where('created_at', '>=', $dateFrom)
            ->where('created_at', '<=', $dateTo)
            ->select('user_id', DB::raw('count(*) as activity_count'))
            ->groupBy('user_id')
            ->orderBy('activity_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($log) {
                return [
                    'user_id' => $log->user_id,
                    'user_name' => $log->user ? $log->user->name : 'Unknown',
                    'user_email' => $log->user ? $log->user->email : 'Unknown',
                    'activity_count' => $log->activity_count
                ];
            })
            ->toArray();

        // Recent significant changes
        $significantActions = ['created', 'updated', 'deleted', 'login', 'logout', 'settings_changed'];
        $recentChanges = AuditLog::with('user')
            ->whereIn('action', $significantActions)
            ->where('created_at', '>=', $dateFrom)
            ->where('created_at', '<=', $dateTo)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->toArray();

        // Security-related events
        $securityActions = ['login', 'logout', 'failed_login', 'password_reset', 'account_locked'];
        $securityEvents = AuditLog::with('user')
            ->whereIn('action', $securityActions)
            ->where('created_at', '>=', $dateFrom)
            ->where('created_at', '<=', $dateTo)
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get()
            ->toArray();

        // Daily activity chart data
        $dailyActivity = AuditLog::where('created_at', '>=', $dateFrom)
            ->where('created_at', '<=', $dateTo)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('count(*) as count')
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->toArray();

        return [
            'summary_period' => [
                'date_from' => $dateFrom->format('Y-m-d'),
                'date_to' => $dateTo->format('Y-m-d'),
            ],
            'activity_by_type' => $activityByType,
            'most_active_users' => $mostActiveUsers,
            'recent_changes' => $recentChanges,
            'security_events' => $securityEvents,
            'daily_activity' => $dailyActivity,
            'total_events' => AuditLog::where('created_at', '>=', $dateFrom)
                ->where('created_at', '<=', $dateTo)
                ->count()
        ];
    }

    /**
     * Export audit logs to file.
     *
     * @param array $filters
     * @param string $format
     * @return string
     * @throws Exception
     */
    public function exportAuditLogs(array $filters = [], string $format = 'csv'): string
    {
        $query = AuditLog::with(['user'])
            ->orderBy('created_at', 'desc');

        // Apply filters (same as getAuditLogs)
        if (isset($filters['user_id']) && $filters['user_id']) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['action']) && $filters['action']) {
            $query->where('action', 'like', '%' . $filters['action'] . '%');
        }

        if (isset($filters['area']) && $filters['area']) {
            $query->where('area', $filters['area']);
        }

        if (isset($filters['date_from']) && $filters['date_from']) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if (isset($filters['date_to']) && $filters['date_to']) {
            $query->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        $auditLogs = $query->get();

        // Generate filename
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $filename = "audit_logs_export_{$timestamp}.{$format}";
        $filepath = "exports/audit/{$filename}";

        // Generate export based on format
        switch ($format) {
            case 'csv':
                $content = $this->generateCsvExport($auditLogs);
                break;
            case 'json':
                $content = $this->generateJsonExport($auditLogs);
                break;
            default:
                throw new Exception("Unsupported export format: {$format}");
        }

        // Store file
        Storage::disk('local')->put($filepath, $content);

        return $filepath;
    }

    /**
     * Generate CSV export content.
     *
     * @param Collection $auditLogs
     * @return string
     */
    private function generateCsvExport(Collection $auditLogs): string
    {
        $csv = "ID,User,Email,Area,Action,Record ID,Metadata,IP Address,User Agent,Created At\n";

        foreach ($auditLogs as $log) {
            $row = [
                $log->id,
                $log->user ? $log->user->name : 'System',
                $log->user ? $log->user->email : 'N/A',
                $log->area,
                $log->action,
                $log->record_id ?? 'N/A',
                json_encode($log->metadata ?? []),
                $log->ip_address ?? 'N/A',
                $log->user_agent ?? 'N/A',
                $log->created_at->format('Y-m-d H:i:s')
            ];

            $csv .= '"' . implode('","', array_map('str_replace', array_fill(0, count($row), '"'), array_fill(0, count($row), '""'), $row)) . "\"\n";
        }

        return $csv;
    }

    /**
     * Generate JSON export content.
     *
     * @param Collection $auditLogs
     * @return string
     */
    private function generateJsonExport(Collection $auditLogs): string
    {
        $data = $auditLogs->map(function ($log) {
            return [
                'id' => $log->id,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email
                ] : null,
                'area' => $log->area,
                'action' => $log->action,
                'record_id' => $log->record_id,
                'metadata' => $log->metadata,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at->toISOString()
            ];
        });

        return json_encode([
            'export_date' => Carbon::now()->toISOString(),
            'total_records' => $data->count(),
            'data' => $data
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Create audit log entry.
     *
     * @param string $area
     * @param string $action
     * @param int|null $recordId
     * @param array $metadata
     * @param int|null $userId
     * @return AuditLog
     */
    public function createAuditLog(
        string $area,
        string $action,
        ?int $recordId = null,
        array $metadata = [],
        ?int $userId = null
    ): AuditLog {
        return AuditLog::create([
            'area' => $area,
            'action' => $action,
            'record_id' => $recordId,
            'user_id' => $userId ?? Auth::id(),
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }

    /**
     * Clean up old audit logs based on retention policy.
     *
     * @param int $retentionDays
     * @return int
     */
    public function cleanupOldLogs(int $retentionDays = 365): int
    {
        $cutoffDate = Carbon::now()->subDays($retentionDays);
        
        return AuditLog::where('created_at', '<', $cutoffDate)->delete();
    }

    /**
     * Get audit statistics.
     *
     * @return array
     */
    public function getAuditStatistics(): array
    {
        $totalLogs = AuditLog::count();
        $logsToday = AuditLog::whereDate('created_at', Carbon::today())->count();
        $logsThisWeek = AuditLog::where('created_at', '>=', Carbon::now()->startOfWeek())->count();
        $logsThisMonth = AuditLog::where('created_at', '>=', Carbon::now()->startOfMonth())->count();

        return [
            'total_logs' => $totalLogs,
            'logs_today' => $logsToday,
            'logs_this_week' => $logsThisWeek,
            'logs_this_month' => $logsThisMonth,
            'oldest_log' => AuditLog::oldest()->first()?->created_at,
            'newest_log' => AuditLog::latest()->first()?->created_at
        ];
    }
}
