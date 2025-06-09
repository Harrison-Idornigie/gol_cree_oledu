<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDailyGoal extends Model
{
    use HasFactory, HasAuditLog;

    const AUDIT_AREA = 'user_daily_goals';

    protected $fillable = [
        'user_id',
        'daily_goal_id',
        'date',
        'progress',
        'completed',
        'completed_at'
    ];

    protected $casts = [
        'date' => 'date',
        'progress' => 'integer',
        'completed' => 'boolean',
        'completed_at' => 'datetime'
    ];

    /**
     * Get the user that owns the daily goal progress.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the daily goal that owns the progress.
     */
    public function dailyGoal(): BelongsTo
    {
        return $this->belongsTo(DailyGoal::class);
    }

    /**
     * Add XP progress to the daily goal.
     */
    public function addProgress(int $xp): void
    {
        $this->progress += $xp;

        if (!$this->completed && $this->progress >= $this->dailyGoal->xp_target) {
            $this->markAsCompleted();
        } else {
            $this->save();
        }
    }

    /**
     * Mark the daily goal as completed.
     */
    protected function markAsCompleted(): void
    {
        $this->completed = true;
        $this->completed_at = now();
        $this->save();

        // Award rewards
        $rewards = $this->dailyGoal->calculateRewards($this->completed_at);
        
        // Add gems
        if (isset($rewards['gems'])) {
            $this->user->increment('gems', $rewards['gems']);
        }

        // Add streak points
        if (isset($rewards['streak_points'])) {
            $userStreak = $this->user->streak;
            if ($userStreak) {
                $userStreak->addPoints($rewards['streak_points']);
            }
        }

        // Add XP bonus
        if (isset($rewards['xp_bonus'])) {
            XpHistory::create([
                'user_id' => $this->user_id,
                'amount' => $rewards['xp_bonus'],
                'source' => 'daily_goal_bonus',
                'metadata' => [
                    'goal_id' => $this->daily_goal_id,
                    'date' => $this->date->format('Y-m-d')
                ]
            ]);
        }
    }

    /**
     * Get the progress percentage.
     */
    public function getProgressPercentage(): float
    {
        return round(($this->progress / $this->dailyGoal->xp_target) * 100, 2);
    }

    /**
     * Get the remaining XP needed.
     */
    public function getRemainingXp(): int
    {
        $remaining = $this->dailyGoal->xp_target - $this->progress;
        return max(0, $remaining);
    }

    /**
     * Check if goal can still be completed today.
     */
    public function canBeCompletedToday(): bool
    {
        return $this->date->isToday() && !$this->completed;
    }

    /**
     * Get preview data for the user's daily goal progress.
     */
    public function getPreviewData(): array
    {
        return [
            'goal' => [
                'id' => $this->daily_goal_id,
                'name' => $this->dailyGoal->name,
                'xp_target' => $this->dailyGoal->xp_target
            ],
            'progress' => $this->progress,
            'progress_percentage' => $this->getProgressPercentage(),
            'remaining_xp' => $this->getRemainingXp(),
            'completed' => $this->completed,
            'completed_at' => $this->completed_at,
            'can_be_completed' => $this->canBeCompletedToday()
        ];
    }
}