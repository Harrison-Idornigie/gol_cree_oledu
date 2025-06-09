<?php

namespace App\Models;

use App\Models\Traits\{HasVersions, HasAuditLog};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class SentenceWord extends Pivot
{
    use HasFactory, HasAuditLog, HasVersions;

    public $incrementing = true;
    protected $table = 'sentence_words';

    protected $fillable = [
        'sentence_id',
        'word_id',
        'position',
        'start_time',
        'end_time',
        'metadata'
    ];

    protected $casts = [
        'position' => 'integer',
        'start_time' => 'float',
        'end_time' => 'float',
        'metadata' => 'array'
    ];

    protected array $auditLogEvents = [
        'created' => 'Added word ":word" to sentence ":sentence" at position :position',
        'updated' => 'Updated word ":word" timing in sentence ":sentence"',
        'deleted' => 'Removed word ":word" from sentence ":sentence"'
    ];

    protected array $auditLogProperties = [
        'position',
        'start_time',
        'end_time',
        'metadata'
    ];

    /**
     * Get the sentence this word belongs to.
     */
    public function sentence()
    {
        return $this->belongsTo(Sentence::class);
    }

    /**
     * Get the word in this sentence.
     */
    public function word()
    {
        return $this->belongsTo(Word::class);
    }

    /**
     * Custom attributes for audit log message.
     */
    public function getWordAttribute(): string
    {
        return $this->word?->text ?? 'unknown';
    }

    public function getSentenceAttribute(): string
    {
        return $this->sentence?->text ?? 'unknown';
    }
}