<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAnalytics extends Model
{
    use HasFactory, HasUuids;

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
