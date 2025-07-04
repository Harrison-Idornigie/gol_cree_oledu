<?php

namespace App\Models\Tenants;

use App\Traits\Tenant\HasAuditLog;
use App\Traits\Tenant\HasVersions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, BelongsToMany};
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Word extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, HasAuditLog, HasVersions, BelongsToTenant;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\Tenant\WordFactory::new();
    }

    protected $fillable = [
        'language_id',
        'text',
        'pronunciation_key',
        'part_of_speech',
        'metadata',
        'status',
        'created_by',
        'tenant_id'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    protected array $auditLogEvents = [
        'created' => 'Created new word: :text (:language)',
        'updated' => 'Updated word: :text',
        'deleted' => 'Deleted word: :text',
    ];

    protected array $auditLogProperties = [
        'text',
        'pronunciation_key',
        'part_of_speech',
        'metadata'
    ];

    /**
     * The attributes that should be version controlled.
     */
    protected array $versionedAttributes = [
        'text',
        'pronunciation_key',
        'part_of_speech',
        'metadata',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('pronunciation')
            ->singleFile()
            ->acceptsMimeTypes(['audio/mpeg', 'audio/mp3', 'audio/wav']);
    }

    /**
     * Get the language this word belongs to.
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * Get the translations of this word.
     */
    public function translations(): HasMany
    {
        return $this->hasMany(WordTranslation::class);
    }

    /**
     * Get the user who created this word.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the sentences this word appears in.
     */
    public function sentences(): BelongsToMany
    {
        return $this->belongsToMany(Sentence::class, 'sentence_words')
            ->withPivot(['position', 'start_time', 'end_time', 'metadata'])
            ->orderBy('position');
    }

    /**
     * Get this word's usage examples.
     */
    public function usageExamples(): HasMany
    {
        return $this->hasMany(UsageExample::class);
    }

    /**
     * Custom attribute for audit log message.
     */
    public function getLanguageAttribute(): string
    {
        return $this->language?->code ?? 'unknown';
    }

    /**
     * Get the URL for the pronunciation audio.
     */
    public function getPronunciationUrl(): ?string
    {
        return $this->hasMedia('pronunciation') ?
            $this->getFirstMediaUrl('pronunciation') : null;
    }

    /**
     * Get preview data for this word.
     * 
     * @param string|null $targetLanguage Optional target language code for translation
     * @return array
     */
    public function getPreviewData(?string $targetLanguage = null): array
    {
        $data = [
            'id' => $this->id,
            'language_id' => $this->language_id,
            'language_code' => null,
            'text' => $this->text,
            'pronunciation_key' => $this->pronunciation_key,
            'part_of_speech' => $this->part_of_speech,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];

        // Set language code if available
        if ($this->language && is_object($this->language) && isset($this->language->code)) {
            $data['language_code'] = $this->language->code;
        }

        // Add media URLs if available
        if ($this->hasMedia('pronunciation')) {
            $data['pronunciation_url'] = $this->getFirstMediaUrl('pronunciation');
        }

        // Add translations if loaded
        if ($this->relationLoaded('translations')) {
            $translations = $this->translations;

            // Filter by target language if specified
            if ($targetLanguage) {
                $translations = $translations->filter(function ($translation) use ($targetLanguage) {
                    if (!$translation->language || !is_object($translation->language)) {
                        return false;
                    }
                    return $translation->language->code === $targetLanguage;
                });
            }

            $data['translations'] = $translations->map(function ($translation) {
                $translationData = [
                    'id' => $translation->id,
                    'language_id' => $translation->language_id,
                    'language_code' => null,
                    'text' => $translation->text,
                    'pronunciation_key' => $translation->pronunciation_key,
                    'context_notes' => $translation->context_notes,
                    'pronunciation_url' => null
                ];

                // Set language code if available
                if ($translation->language && is_object($translation->language) && isset($translation->language->code)) {
                    $translationData['language_code'] = $translation->language->code;
                }

                // Add pronunciation URL if available
                if ($translation->hasMedia('pronunciation')) {
                    $translationData['pronunciation_url'] = $translation->getFirstMediaUrl('pronunciation');
                }

                return $translationData;
            })->values()->toArray();
        }

        return $data;
    }
}
