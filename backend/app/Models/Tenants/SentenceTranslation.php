<?php

namespace App\Models;

use App\Models\Traits\{HasVersions, HasAuditLog};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class SentenceTranslation extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, HasAuditLog, HasVersions;

    protected $fillable = [
        'sentence_id',
        'language_id',
        'text',
        'pronunciation_key',
        'context_notes'
    ];

    protected array $auditLogEvents = [
        'created' => 'Created translation for sentence ":original" in :language: :text',
        'updated' => 'Updated translation for sentence ":original" in :language',
        'deleted' => 'Deleted translation for sentence ":original" in :language'
    ];

    protected array $auditLogProperties = [
        'text',
        'pronunciation_key',
        'context_notes'
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('audio')
            ->singleFile()
            ->acceptsMimeTypes(['audio/mpeg', 'audio/mp3', 'audio/wav']);
    }

    /**
     * Get the original sentence this translation belongs to.
     */
    public function sentence(): BelongsTo
    {
        return $this->belongsTo(Sentence::class);
    }

    /**
     * Get the target language of this translation.
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * Custom attributes for audit log message.
     */
    public function getOriginalAttribute(): string
    {
        return $this->sentence?->text ?? 'unknown';
    }

    public function getLanguageAttribute(): string
    {
        return $this->language?->code ?? 'unknown';
    }

    /**
     * Get preview data for the sentence translation.
     */
    public function getPreviewData(): array
    {
        $data = [
            'id' => $this->id,
            'sentence_id' => $this->sentence_id,
            'language_id' => $this->language_id,
            'text' => $this->text,
            'pronunciation_key' => $this->pronunciation_key,
            'context_notes' => $this->context_notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];

        // Add audio URL if available
        if ($this->hasMedia('audio')) {
            $data['audio_url'] = $this->getFirstMediaUrl('audio');
        }

        // Add language information if it's loaded
        if ($this->relationLoaded('language')) {
            $data['language'] = [
                'id' => $this->language->id,
                'code' => $this->language->code,
                'name' => $this->language->name
            ];
        }

        return $data;
    }
}