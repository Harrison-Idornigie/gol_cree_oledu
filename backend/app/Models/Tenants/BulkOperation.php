<?php

namespace App\Models\Tenants;

use App\Traits\Tenant\HasAuditLog;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class BulkOperation extends Model
{
    use HasFactory, HasUuids, HasAuditLog, BelongsToTenant;

    public const AUDIT_AREA = 'bulk_operations';

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'file_path',
        'total_items',
        'processed_items',
        'successful_items',
        'failed_items',
        'errors',
        'options',
        'completed_at',
    ];

    protected $casts = [
        'errors' => 'array',
        'options' => 'array',
        'completed_at' => 'datetime',
    ];

    protected array $auditLogEvents = [
        'created' => 'Created bulk operation: :type',
        'updated' => 'Updated bulk operation: :type (:status)',
        'deleted' => 'Deleted bulk operation: :type',
    ];

    protected array $auditLogProperties = [
        'type',
        'status',
        'total_items',
        'processed_items',
        'successful_items',
        'failed_items',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function getProgressPercentage(): float
    {
        if ($this->total_items === 0) {
            return 0;
        }

        return round(($this->processed_items / $this->total_items) * 100, 2);
    }
}
