<?php

namespace App\Models;

use App\Models\Traits\{HasVersions, HasAuditLog};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageExample extends Model
{
    use HasFactory, HasAuditLog, HasVersions;

    protected $fillable = [
        'word_id',
        'sentence_id',
        'type',
        'difficulty_level',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'difficulty_level' => 'integer'
    ];

    protected array $auditLogEvents = [
        'created' => 'Created new usage example for word :word',
        'updated' => 'Updated usage example for word :word',
        'deleted' => 'Deleted usage example for word :word'
    ];

    protected array $auditLogProperties = [
        'type',
        'difficulty_level',
        'metadata'
    ];

    /**
     * Get the word this example belongs to.
     */
    public function word(): BelongsTo
    {
        return $this->belongsTo(Word::class);
    }

    /**
     * Get the sentence used as an example.
     */
    public function sentence(): BelongsTo
    {
        return $this->belongsTo(Sentence::class);
    }

    /**
     * Custom attribute for audit log message.
     */
    public function getWordAttribute(): string
    {
        return $this->word?->text ?? 'unknown';
    }
}