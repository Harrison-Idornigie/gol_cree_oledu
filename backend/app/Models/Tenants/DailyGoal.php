<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use App\Models\Traits\HasVersions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyGoal extends Model
{
    use HasFactory, HasAuditLog, HasVersions;

    const AUDIT_AREA = 'daily_goals';

    protected $fillable = [
        'name',
        'xp_target',
        'rewards',
        'is_active'
    ];

    protected $casts = [
        'xp_target' => 'integer',
        'rewards' => 'array',
        'is_active' => 'boolean'
    ];

    protected array $versionedAttributes = [
        'name',
        'xp_target',
        'rewards',
        'is_active'
    ];

    /**
     * Get the user progress records for this goal.
     */
    public function userProgress(): HasMany
    {
        return $this->hasMany(UserDailyGoal::class);
    }

    /**
     * Get the default daily goals.
     */
    public static function getDefaultGoals(): array
    {
        return [
            [
                'name' => 'Casual',
                'xp_target' => 20,
                'rewards' => [
                    'streak_points' => 1
                ]
            ],
            [
                'name' => 'Regular',
                'xp_target' => 50,
                'rewards' => [
                    'streak_points' => 2,
                    'gems' => 1
                ]
            ],
            [
                'name' => 'Serious',
                'xp_target' => 100,
                'rewards' => [
                    'streak_points' => 3,
                    'gems' => 2,
                    'xp_bonus' => 10
                ]
            ],
            [
                'name' => 'Intense',
                'xp_target' => 200,
                'rewards' => [
                    'streak_points' => 4,
                    'gems' => 4,
                    'xp_bonus' => 20
                ]
            ]
        ];
    }

    /**
     * Get completion rate for a date range.
     */
    public function getCompletionRate(
        \DateTime $startDate,
        \DateTime $endDate
    ): float {
        $progress = $this->userProgress()
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('COUNT(*) as total, SUM(completed) as completed')
            ->first();

        if (!$progress->total) {
            return 0;
        }

        return round(($progress->completed / $progress->total) * 100, 2);
    }

    /**
     * Get users currently working on this goal.
     */
    public function getActiveUsers(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->userProgress()
            ->where('date', now()->format('Y-m-d'))
            ->with('user')
            ->get()
            ->map(function ($progress) {
                return [
                    'user_id' => $progress->user_id,
                    'user_name' => $progress->user->name,
                    'progress' => $progress->progress,
                    'completed' => $progress->completed,
                    'completed_at' => $progress->completed_at
                ];
            });
    }

    /**
     * Get preview data for the daily goal.
     */
    public function getPreviewData(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'xp_target' => $this->xp_target,
            'rewards' => $this->rewards,
            'is_active' => $this->is_active,
            'completion_rate_7d' => $this->getCompletionRate(
                now()->subDays(7),
                now()
            ),
            'active_users_count' => $this->userProgress()
                ->where('date', now()->format('Y-m-d'))
                ->count(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }

    /**
     * Calculate rewards based on completion time.
     */
    public function calculateRewards(\DateTime $completedAt): array
    {
        $rewards = $this->rewards;

        // Add time-based bonuses (e.g., early bird bonus)
        $hour = (int) $completedAt->format('H');
        if ($hour < 9) { // Early bird bonus before 9 AM
            $rewards['gems'] = ($rewards['gems'] ?? 0) + 1;
            $rewards['xp_bonus'] = ($rewards['xp_bonus'] ?? 0) + 5;
        }

        return $rewards;
    }
}