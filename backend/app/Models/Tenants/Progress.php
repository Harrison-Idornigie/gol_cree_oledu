<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Progress extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'content_type',
        'content_id',
        'status',
        'progress_percentage',
        'last_position',
        'completed_at',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'progress_percentage' => 'integer',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the user that owns the progress.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent content model (polymorphic).
     */
    public function content()
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to only include progress for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include progress for a specific content.
     */
    public function scopeForContent($query, $contentType, $contentId)
    {
        return $query->where('content_type', $contentType)
                    ->where('content_id', $contentId);
    }

    /**
     * Scope a query to only include completed progress.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to only include in-progress items.
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }
}
