<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueMembership extends Model
{
    use HasFactory, HasAuditLog;

    const AUDIT_AREA = 'league_memberships';

    protected $fillable = [
        'user_id',
        'league_id',
        'current_rank',
        'weekly_xp',
        'joined_at',
        'promoted_at'
    ];

    protected $casts = [
        'current_rank' => 'integer',
        'weekly_xp' => 'integer',
        'joined_at' => 'datetime',
        'promoted_at' => 'datetime'
    ];

    /**
     * Get the user that owns the membership.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the league that owns the membership.
     */
    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    /**
     * Update weekly XP and rank.
     */
    public function addXp(int $amount): void
    {
        $this->weekly_xp += $amount;
        $this->updateRank();
        $this->save();
    }

    /**
     * Update the member's rank based on XP.
     */
    protected function updateRank(): void
    {
        $rank = $this->league->memberships()
            ->where('weekly_xp', '>', $this->weekly_xp)
            ->count() + 1;

        $this->current_rank = $rank;
    }

    /**
     * Check if member is eligible for promotion.
     */
    public function isEligibleForPromotion(): bool
    {
        if (!$this->league) {
            return false;
        }

        $requirements = $this->league->requirements;
        return $this->current_rank <= $requirements['promotion_rank']
            && $this->weekly_xp >= $requirements['min_xp'];
    }

    /**
     * Reset weekly progress.
     */
    public function resetWeeklyProgress(): void
    {
        $this->update([
            'weekly_xp' => 0,
            'current_rank' => null
        ]);
    }

    /**
     * Get historical performance.
     */
    public function getPerformanceHistory(int $weeks = 4): array
    {
        return XpHistory::where('user_id', $this->user_id)
            ->where('created_at', '>=', now()->subWeeks($weeks))
            ->selectRaw('WEEK(created_at) as week, SUM(amount) as total_xp')
            ->groupBy('week')
            ->orderBy('week')
            ->get()
            ->mapWithKeys(function ($record) {
                return [$record->week => $record->total_xp];
            })
            ->toArray();
    }

    /**
     * Get membership summary data.
     */
    public function getSummaryData(): array
    {
        return [
            'user' => [
                'id' => $this->user_id,
                'name' => $this->user->name
            ],
            'league' => [
                'id' => $this->league_id,
                'name' => $this->league->name,
                'tier' => $this->league->tier
            ],
            'current_rank' => $this->current_rank,
            'weekly_xp' => $this->weekly_xp,
            'joined_at' => $this->joined_at,
            'promoted_at' => $this->promoted_at,
            'eligible_for_promotion' => $this->isEligibleForPromotion()
        ];
    }
}