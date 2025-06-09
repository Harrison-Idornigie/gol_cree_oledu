<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'area',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'metadata',
        'ip_address',
        'user_agent',
        'is_system_action',
        'status',
        'status_message',
        'performed_at'
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'is_system_action' => 'boolean',
        'performed_at' => 'datetime'
    ];

    /**
     * The user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The model that was audited.
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Create a new audit log entry.
     */
    public static function log(
        string $action,
        string $area,
        ?Model $model = null,
        array $oldValues = [],
        array $newValues = [],
        array $options = []
    ): self {
        $log = new static([
            'user_id' => auth()->id(),
            'action' => $action,
            'area' => $area,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $options['metadata'] ?? [],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'is_system_action' => $options['is_system'] ?? false,
            'status' => $options['status'] ?? 'success',
            'status_message' => $options['message'] ?? null,
            'performed_at' => now(),
        ]);

        if ($model) {
            $log->auditable()->associate($model);
        }

        $log->save();
        return $log;
    }

    /**
     * Log a system action.
     */
    public static function logSystem(
        string $action,
        string $area,
        array $metadata = [],
        string $status = 'success'
    ): self {
        return static::log($action, $area, null, [], [], [
            'is_system' => true,
            'metadata' => $metadata,
            'status' => $status
        ]);
    }

    /**
     * Log a model change.
     */
    public static function logChange(
        Model $model,
        string $action,
        array $oldValues,
        array $newValues,
        array $options = []
    ): self {
        return static::log(
            $action,
            get_class($model),
            $model,
            $oldValues,
            $newValues,
            $options
        );
    }

    /**
     * Get the changes made in this audit log.
     */
    public function getChanges(): array
    {
        $changes = [];
        foreach ($this->new_values as $key => $newValue) {
            $oldValue = $this->old_values[$key] ?? null;
            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue
                ];
            }
        }
        return $changes;
    }

    /**
     * Scope a query to only include logs for a specific action.
     */
    public function scopeOfAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope a query to only include logs for a specific area.
     */
    public function scopeInArea($query, string $area)
    {
        return $query->where('area', $area);
    }

    /**
     * Scope a query to only include system actions.
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system_action', true);
    }

    /**
     * Scope a query to only include user actions.
     */
    public function scopeUser($query)
    {
        return $query->where('is_system_action', false);
    }

    /**
     * Scope a query to only include successful actions.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope a query to only include failed actions.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failure');
    }

    /**
     * Get a readable description of the audit log entry.
     */
    public function getDescription(): string
    {
        $actor = $this->is_system_action ? 'System' : 
            ($this->user ? $this->user->name : 'Unknown user');
        
        return sprintf(
            '%s %s in %s (%s)',
            $actor,
            $this->action,
            $this->area,
            $this->status
        );
    }
}