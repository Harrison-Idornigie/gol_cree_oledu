<?php

namespace App\Models\Tenants;

use App\Traits\Tenant\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Review extends Model
{
    use HasFactory, BelongsToTenant, HasAuditLog;

    public const AUDIT_AREA = 'reviews';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'content_type',
        'content_id',
        'submitted_by',
        'reviewed_by',
        'status',
        'review_comment',
        'rejection_reason',
        'submitted_at',
        'reviewed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    protected array $auditLogEvents = [
        'created' => 'Created review for :content_type by user :submitted_by',
        'updated' => 'Updated review status to :status',
        'deleted' => 'Deleted review for :content_type',
    ];

    protected array $auditLogProperties = [
        'content_type',
        'content_id',
        'submitted_by',
        'reviewed_by',
        'status',
    ];

    /**
     * Get the reviewable model (polymorphic relationship).
     */
    public function reviewable(): MorphTo
    {
        return $this->morphTo('reviewable', 'content_type', 'content_id');
    }

    /**
     * Get the user who submitted the content for review.
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Get the user who reviewed the content.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
