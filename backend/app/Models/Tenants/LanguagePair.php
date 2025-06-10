<?php

namespace App\Models\Tenants;

use App\Traits\Tenant\{HasVersions, HasAuditLog};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class LanguagePair extends Model
{
    use HasFactory, HasAuditLog, HasVersions, BelongsToTenant;

    protected $fillable = [
        'source_language_id',
        'target_language_id',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    protected array $auditLogEvents = [
        'created' => 'Created new language pair: :source -> :target',
        'updated' => 'Updated language pair: :source -> :target',
        'deleted' => 'Deleted language pair: :source -> :target',
    ];

    protected array $auditLogProperties = [
        'source_language_id',
        'target_language_id',
        'is_active'
    ];

    /**
     * Get the source language.
     */
    public function sourceLanguage(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'source_language_id');
    }

    /**
     * Get the target language.
     */
    public function targetLanguage(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'target_language_id');
    }

    /**
     * Custom attribute for audit log message.
     */
    public function getSourceAttribute(): string
    {
        return $this->sourceLanguage?->code ?? 'unknown';
    }

    /**
     * Custom attribute for audit log message.
     */
    public function getTargetAttribute(): string
    {
        return $this->targetLanguage?->code ?? 'unknown';
    }
}