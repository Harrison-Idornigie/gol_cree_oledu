<?php

namespace App\Models\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasAuditLog
{
    /**
     * Boot the trait.
     */
    protected static function bootHasAuditLog()
    {
        static::created(function (Model $model) {
            $model->logAction('create');
        });

        static::updated(function (Model $model) {
            $model->logAction('update');
        });

        static::deleted(function (Model $model) {
            $model->logAction('delete');
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) {
                $model->logAction('restore');
            });
        }
    }

    /**
     * Get all audit logs for this model.
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /**
     * Log an action on this model.
     */
    public function logAction(
        string $action,
        array $metadata = [],
        array $options = []
    ): AuditLog {
        $area = $this->getAuditArea();
        
        return AuditLog::logChange(
            $this,
            $action,
            $this->getOriginal(),
            $this->getAttributes(),
            array_merge([
                'metadata' => array_merge(
                    $this->getAuditMetadata(),
                    $metadata
                )
            ], $options)
        );
    }

    /**
     * Get the audit area for this model.
     */
    protected function getAuditArea(): string
    {
        return defined(static::class . '::AUDIT_AREA') ? 
            static::AUDIT_AREA : 
            class_basename($this);
    }

    /**
     * Get additional metadata for audit logs.
     */
    protected function getAuditMetadata(): array
    {
        return [
            'model_type' => get_class($this),
            'model_id' => $this->getKey()
        ];
    }

    /**
     * Get the audit history with changes.
     */
    public function getAuditHistory(array $options = []): array
    {
        $query = $this->auditLogs()
            ->with('user')
            ->orderByDesc('performed_at');

        if (isset($options['action'])) {
            $query->where('action', $options['action']);
        }

        if (isset($options['user_id'])) {
            $query->where('user_id', $options['user_id']);
        }

        if (isset($options['start_date'])) {
            $query->where('performed_at', '>=', $options['start_date']);
        }

        if (isset($options['end_date'])) {
            $query->where('performed_at', '<=', $options['end_date']);
        }

        return $query->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'user' => $log->user?->name ?? 'System',
                    'changes' => $log->getChanges(),
                    'metadata' => $log->metadata,
                    'status' => $log->status,
                    'performed_at' => $log->performed_at,
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent
                ];
            })
            ->toArray();
    }

    /**
     * Get the most recent audit log.
     */
    public function getLastAudit(): ?AuditLog
    {
        return $this->auditLogs()
            ->latest('performed_at')
            ->first();
    }

    /**
     * Get audit logs by action.
     */
    public function getAuditsByAction(string $action): array
    {
        return $this->getAuditHistory(['action' => $action]);
    }

    /**
     * Get audit logs by user.
     */
    public function getAuditsByUser(int $userId): array
    {
        return $this->getAuditHistory(['user_id' => $userId]);
    }

    /**
     * Get audit logs for a date range.
     */
    public function getAuditsForDateRange(string $startDate, string $endDate): array
    {
        return $this->getAuditHistory([
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
    }

    /**
     * Log a custom action.
     */
    public function logCustomAction(
        string $action,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = [],
        array $options = []
    ): AuditLog {
        return AuditLog::logChange(
            $this,
            $action,
            $oldValues,
            $newValues,
            array_merge([
                'metadata' => array_merge(
                    $this->getAuditMetadata(),
                    $metadata
                )
            ], $options)
        );
    }
}