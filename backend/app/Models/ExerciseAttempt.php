<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseAttempt extends Model
{
    protected $fillable = [
        'exercise_id',
        'user_id',
        'is_correct',
        'user_answer',
        'time_taken_seconds',
        'score',
        'passed',
        'feedback',
        'attempt_number',
    ];

    protected $casts = [
        'user_answer'        => 'array',
        'is_correct'         => 'boolean',
        'time_taken_seconds' => 'integer',
        'score'              => 'float',
        'passed'             => 'boolean',
        'feedback'           => 'array',
        'attempt_number'     => 'integer',
    ];

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the pass rate for this attempt
     */
    public function getPassRate(): float
    {
        return $this->score ?? 0.0;
    }

    /**
     * Check if this attempt was successful
     */
    public function isSuccessful(): bool
    {
        return $this->passed ?? $this->is_correct;
    }

    /**
     * Get the feedback for this attempt
     */
    public function getFeedback(): array
    {
        return $this->feedback ?? [];
    }
}
