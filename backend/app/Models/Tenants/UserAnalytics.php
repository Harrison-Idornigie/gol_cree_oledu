<?php

namespace App\Models\Tenants;

use App\Traits\Tenant\HasAuditLog;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class UserAnalytics extends Model
{
    use HasFactory, HasUuids, HasAuditLog, BelongsToTenant;

    public const AUDIT_AREA = 'user_analytics';

    protected $table = 'user_analytics';

    protected $fillable = [
        'user_id',
        'language_id',
        'metric_type',
        'metric_value',
        'recorded_at',
    ];

    protected $casts = [
        'metric_value' => 'array',
        'recorded_at' => 'datetime',
    ];

    protected array $auditLogEvents = [
        'created' => 'Created analytics record for user :user_id (:metric_type)',
        'updated' => 'Updated analytics record for user :user_id (:metric_type)',
        'deleted' => 'Deleted analytics record for user :user_id (:metric_type)',
    ];

    protected array $auditLogProperties = [
        'user_id',
        'language_id',
        'metric_type',
        'metric_value',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function scopeByMetricType($query, string $metricType)
    {
        return $query->where('metric_type', $metricType);
    }

    public function scopeByLanguage($query, string $languageId)
    {
        return $query->where('language_id', $languageId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('recorded_at', [$startDate, $endDate]);
    }
}
