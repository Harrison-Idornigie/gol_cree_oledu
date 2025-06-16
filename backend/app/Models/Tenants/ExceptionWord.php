<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasAuditLog;
use App\Traits\HasVersions;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class ExceptionWord extends Model
{
    use HasFactory, HasAuditLog, HasVersions, BelongsToTenant;

    protected $fillable = [
        'language_id',
        'text',
        'type',
        'description',
        'metadata',
        'is_active',
        'tenant_id'
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean'
    ];

    protected array $auditLogEvents = [
        'created' => 'Created exception word: :text (:type)',
        'updated' => 'Updated exception word: :text',
        'deleted' => 'Deleted exception word: :text',
    ];

    protected array $auditLogProperties = [
        'text',
        'type',
        'description',
        'metadata'
    ];

    // Exception word types
    public const TYPE_PROPER_NOUN = 'proper_noun';
    public const TYPE_TECHNICAL_TERM = 'technical_term';
    public const TYPE_BORROWED_WORD = 'borrowed_word';
    public const TYPE_NUMBER = 'number';
    public const TYPE_DATE = 'date';
    public const TYPE_CUSTOM = 'custom';

    public const TYPES = [
        self::TYPE_PROPER_NOUN => 'Proper Noun',
        self::TYPE_TECHNICAL_TERM => 'Technical Term',
        self::TYPE_BORROWED_WORD => 'Borrowed Word',
        self::TYPE_NUMBER => 'Number',
        self::TYPE_DATE => 'Date',
        self::TYPE_CUSTOM => 'Custom'
    ];

    /**
     * Get the language this exception word belongs to.
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * Scope to get active exception words.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get the display name for the type.
     */
    public function getTypeDisplayAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Check if this exception word can be used in sentences.
     */
    public function canBeUsedInSentences(): bool
    {
        return $this->is_active && in_array($this->type, [
            self::TYPE_PROPER_NOUN,
            self::TYPE_TECHNICAL_TERM,
            self::TYPE_BORROWED_WORD,
            self::TYPE_CUSTOM
        ]);
    }

    /**
     * Get preview data for the exception word.
     */
    public function getPreviewData(): array
    {
        return [
            'id' => $this->id,
            'language_id' => $this->language_id,
            'text' => $this->text,
            'type' => $this->type,
            'type_display' => $this->type_display,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'is_active' => $this->is_active,
            'language' => $this->language ? [
                'id' => $this->language->id,
                'name' => $this->language->name,
                'code' => $this->language->code
            ] : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
