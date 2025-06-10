<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class ListeningAttempt extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'user_id',
        'exercise_id',
        'user_transcript',
        'is_correct',
        'attempt_number',
    ];

    protected $casts = [
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
