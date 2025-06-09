<?php

namespace App\Models;

use App\Models\Traits\{HasVersions, HasAuditLog, BelongsToTenant};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, BelongsToMany};
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Sentence extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, HasAuditLog, HasVersions, BelongsToTenant;

    protected $fillable = [
        'language_id',
        'text',
        'pronunciation_key',
        'metadata',
        'tenant_id'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    protected array $auditLogEvents = [
        'created' => 'Created new sentence in :language: :text',
        'updated' => 'Updated sentence: :text',
        'deleted' => 'Deleted sentence: :text'
    ];

    protected array $auditLogProperties = [
        'text',
        'pronunciation_key',
        'metadata'
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('audio')
            ->singleFile()
            ->acceptsMimeTypes(['audio/mpeg', 'audio/mp3', 'audio/wav']);

        $this->addMediaCollection('audio_slow')
            ->singleFile()
            ->acceptsMimeTypes(['audio/mpeg', 'audio/mp3', 'audio/wav']);
    }

    /**
     * Get the language this sentence belongs to.
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * Get the translations of this sentence.
     */
    public function translations(): HasMany
    {
        return $this->hasMany(SentenceTranslation::class);
    }

    /**
     * Get the words in this sentence with their position and timing information.
     */
    public function words(): BelongsToMany
    {
        return $this->belongsToMany(Word::class, 'sentence_words')
            ->withPivot(['position', 'start_time', 'end_time', 'metadata'])
            ->orderBy('position');
    }

    /**
     * Get the usage examples that use this sentence.
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
     * Get preview data for the sentence.
     */
    public function getPreviewData(string $targetLanguageCode = null): array
    {
        $data = [
            'id' => $this->id,
            'language_id' => $this->language_id,
            'text' => $this->text,
            'pronunciation_key' => $this->pronunciation_key,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];

        // Add audio URLs if available
        if ($this->hasMedia('audio')) {
            $data['audio_url'] = $this->getFirstMediaUrl('audio');
        }

        if ($this->hasMedia('audio_slow')) {
            $data['audio_slow_url'] = $this->getFirstMediaUrl('audio_slow');
        }

        // Add translations if they're loaded
        if ($this->relationLoaded('translations')) {
            $translations = $this->translations;
            
            if ($targetLanguageCode) {
                $targetLanguage = Language::where('code', $targetLanguageCode)->first();
                if ($targetLanguage) {
                    $translation = $translations->where('language_id', $targetLanguage->id)->first();
                    if ($translation) {
                        $data['translation'] = $translation->text;
                    }
                }
            }
            
            $data['translations'] = $translations->map(function ($translation) {
                return [
                    'id' => $translation->id,
                    'language_id' => $translation->language_id,
                    'text' => $translation->text,
                    'pronunciation_key' => $translation->pronunciation_key,
                    'context_notes' => $translation->context_notes
                ];
            });
        }

        // Add words if they're loaded
        if ($this->relationLoaded('words')) {
            $data['words'] = $this->words->map(function ($word) {
                return [
                    'id' => $word->id,
                    'text' => $word->text,
                    'position' => $word->pivot->position,
                    'start_time' => $word->pivot->start_time,
                    'end_time' => $word->pivot->end_time
                ];
            });
        }

        return $data;
    }

    /**
     * Add audio file to the sentence, preventing duplicates.
     *
     * @param \Illuminate\Http\UploadedFile|string $file
     * @param bool $isSlow Whether this is a slow version of the audio
     * @param array $customProperties Additional properties to store
     * @return \Spatie\MediaLibrary\MediaCollections\Models\Media
     */
    public function addAudioFile($file, bool $isSlow = false, array $customProperties = [])
    {
        $collection = $isSlow ? 'audio_slow' : 'audio';
        
        // Add language information to custom properties
        if ($this->language && isset($this->language->code)) {
            $customProperties['language_code'] = $this->language->code;
        }
        $customProperties['text'] = $this->text;
        
        // Use app's MediaService to add the file
        return app(\App\Services\MediaService::class)->addMedia(
            $this, 
            $file, 
            $collection, 
            $customProperties
        );
    }

    /**
     * Get word timings for this sentence.
     *
     * @return array
     */
    public function getWordTimings(): array
    {
        return $this->words()
            ->with('translations')
            ->get()
            ->map(function ($word) {
                return [
                    'id' => $word->id,
                    'word_id' => $word->id,
                    'text' => $word->text,
                    'position' => $word->pivot->position,
                    'start_time' => $word->pivot->start_time,
                    'end_time' => $word->pivot->end_time,
                    'metadata' => $word->pivot->metadata
                ];
            })
            ->sortBy('position')
            ->values()
            ->toArray();
    }
}