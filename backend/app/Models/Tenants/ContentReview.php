<?php

namespace App\Models\Tenants;

use App\Traits\Tenant\HasAuditLog;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class ContentReview extends Model
{
    use HasFactory, HasUuids, HasAuditLog, BelongsToTenant;

    public const AUDIT_AREA = 'content_reviews';

    protected $fillable = [
        'content_type',
        'content_id',
        'submitted_by',
        'assigned_to',
        'review_type',
        'status',
        'priority',
        'notes',
        'reviewer_feedback',
        'reviewer_notes',
        'submitted_at',
        'completed_at',
        'due_date',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'due_date' => 'datetime',
    ];

    protected array $auditLogEvents = [
        'created' => 'Created content review for :content_type',
        'updated' => 'Updated content review status to :status',
        'deleted' => 'Deleted content review for :content_type',
    ];

    protected array $auditLogProperties = [
        'content_type',
        'content_id',
        'review_type',
        'status',
        'priority',
        'assigned_to',
    ];

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function content(): MorphTo
    {
        return $this->morphTo();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast() && $this->isPending();
    }

    public function isHighPriority(): bool
    {
        return $this->priority === 'high';
    }
}
