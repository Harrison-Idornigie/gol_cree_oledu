<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'exercise_id',
        'last_step_completed',
        'is_completed',
    ];

    protected $casts = [
        'last_step_completed' => 'integer',
        'is_completed' => 'boolean',
    ];

    /**
     * Get the user that owns this progress.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the exercise that this progress belongs to.
     */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}