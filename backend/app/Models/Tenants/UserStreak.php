<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserStreak extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'current_streak',
        'longest_streak',
        'last_activity_date',
        'freeze_used',
        'freeze_expires_at'
    ];

    protected $casts = [
        'current_streak' => 'integer',
        'longest_streak' => 'integer',
        'last_activity_date' => 'date',
        'freeze_used' => 'boolean',
        'freeze_expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function updateStreak(): void
    {
        $today = now()->startOfDay();
        $lastActivity = $this->last_activity_date?->startOfDay();

        // If this is the first activity or it's been more than a day
        if (!$lastActivity || $lastActivity->diffInDays($today) > 1) {
            // Check if freeze is active
            if ($this->freeze_used && $this->freeze_expires_at?->isAfter(now())) {
                // Keep the streak but consume the freeze
                $this->freeze_used = false;
                $this->freeze_expires_at = null;
            } else {
                $this->current_streak = 1;
            }
        } 
        // If the last activity was yesterday
        elseif ($lastActivity->diffInDays($today) === 1) {
            $this->current_streak++;
        }
        // If it's the same day, don't update the streak

        // Update longest streak if current is higher
        if ($this->current_streak > $this->longest_streak) {
            $this->longest_streak = $this->current_streak;
        }

        $this->last_activity_date = $today;
        $this->save();
    }
}