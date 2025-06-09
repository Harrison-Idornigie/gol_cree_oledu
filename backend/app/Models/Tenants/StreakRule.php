<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use App\Models\Traits\HasVersions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StreakRule extends Model
{
    use HasFactory, HasAuditLog, HasVersions;

    const AUDIT_AREA = 'streak_rules';

    protected $fillable = [
        'freeze_cost',
        'repair_window_hours',
        'bonus_schedule',
        'xp_multipliers'
    ];

    protected $casts = [
        'freeze_cost' => 'integer',
        'repair_window_hours' => 'integer',
        'bonus_schedule' => 'array',
        'xp_multipliers' => 'array'
    ];

    protected array $versionedAttributes = [
        'freeze_cost',
        'repair_window_hours',
        'bonus_schedule',
        'xp_multipliers'
    ];

    /**
     * Get the default streak rules.
     */
    public static function getDefaultRules(): array
    {
        return [
            'freeze_cost' => 10, // gems cost
            'repair_window_hours' => 48,
            'bonus_schedule' => [
                ['days' => 5, 'gems' => 5],
                ['days' => 10, 'gems' => 10],
                ['days' => 30, 'gems' => 50],
                ['days' => 100, 'gems' => 100],
                ['days' => 365, 'gems' => 500]
            ],
            'xp_multipliers' => [
                ['days' => 7, 'multiplier' => 1.1],
                ['days' => 14, 'multiplier' => 1.15],
                ['days' => 30, 'multiplier' => 1.25],
                ['days' => 100, 'multiplier' => 1.5],
                ['days' => 365, 'multiplier' => 2.0]
            ]
        ];
    }

    /**
     * Calculate bonus rewards for a given streak length.
     */
    public function calculateBonus(int $streakDays): array
    {
        $rewards = [
            'gems' => 0,
            'xp_multiplier' => 1.0
        ];

        // Calculate gem rewards
        foreach ($this->bonus_schedule as $bonus) {
            if ($streakDays >= $bonus['days']) {
                $rewards['gems'] = max($rewards['gems'], $bonus['gems']);
            }
        }

        // Calculate XP multiplier
        foreach ($this->xp_multipliers as $multiplier) {
            if ($streakDays >= $multiplier['days']) {
                $rewards['xp_multiplier'] = max($rewards['xp_multiplier'], $multiplier['multiplier']);
            }
        }

        return $rewards;
    }

    /**
     * Check if a streak can be repaired based on the last activity date.
     */
    public function canRepairStreak(\DateTime $lastActivityDate): bool
    {
        $hoursSinceLastActivity = now()->diffInHours($lastActivityDate);
        return $hoursSinceLastActivity <= $this->repair_window_hours;
    }

    /**
     * Get preview data for the streak rules.
     */
    public function getPreviewData(): array
    {
        return [
            'id' => $this->id,
            'freeze_cost' => $this->freeze_cost,
            'repair_window_hours' => $this->repair_window_hours,
            'bonus_schedule' => $this->bonus_schedule,
            'xp_multipliers' => $this->xp_multipliers,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }

    /**
     * Get next milestone for a given streak length.
     */
    public function getNextMilestone(int $currentStreak): ?array
    {
        $nextBonus = null;
        $nextMultiplier = null;

        foreach ($this->bonus_schedule as $bonus) {
            if ($bonus['days'] > $currentStreak) {
                $nextBonus = $bonus;
                break;
            }
        }

        foreach ($this->xp_multipliers as $multiplier) {
            if ($multiplier['days'] > $currentStreak) {
                $nextMultiplier = $multiplier;
                break;
            }
        }

        if (!$nextBonus && !$nextMultiplier) {
            return null;
        }

        return [
            'days_required' => min(
                $nextBonus['days'] ?? PHP_INT_MAX,
                $nextMultiplier['days'] ?? PHP_INT_MAX
            ),
            'rewards' => [
                'gems' => $nextBonus['gems'] ?? 0,
                'xp_multiplier' => $nextMultiplier['multiplier'] ?? 1.0
            ]
        ];
    }
}