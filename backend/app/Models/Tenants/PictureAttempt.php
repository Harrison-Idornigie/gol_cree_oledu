<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PictureAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'exercise_id',
        'selected_option',
        'is_correct',
        'attempt_number',
    ];

    protected $casts = [
        'selected_option' => 'integer',
        'is_correct' => 'boolean',
        'attempt_number' => 'integer',
    ];

    /**
     * Get the user that owns this attempt.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the exercise that this attempt belongs to.
     */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
