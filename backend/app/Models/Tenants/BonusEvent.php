<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use App\Models\Traits\HasVersions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class BonusEvent extends Model
{
    use HasFactory, HasAuditLog, HasVersions;

    const AUDIT_AREA = 'bonus_events';

    protected $fillable = [
        'name',
        'description',
        'start_date',
        'end_date',
        'bonuses',
        'conditions',
        'is_active'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'bonuses' => 'array',
        'conditions' => 'array',
        'is_active' => 'boolean'
    ];

    protected array $versionedAttributes = [
        'name',
        'description',
        'bonuses',
        'conditions',
        'is_active'
    ];

    /**
     * Scope a query to only include active events.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }

    /**
     * Get the multiplier for a specific bonus type.
     */
    public function getMultiplier(string $type): float
    {
        if (!$this->isActive() || !isset($this->bonuses["{$type}_multiplier"])) {
            return 1.0;
        }

        return (float) $this->bonuses["{$type}_multiplier"];
    }

    /**
     * Check if the event is currently active.
     */
    public function isActive(): bool
    {
        return $this->is_active &&
            $this->start_date <= now() &&
            $this->end_date >= now();
    }

    /**
     * Check if user is eligible for this bonus event.
     */
    public function isUserEligible(User $user): bool
    {
        if (!$this->conditions) {
            return true;
        }

        // Check level requirement
        if (isset($this->conditions['min_level']) &&
            $user->level < $this->conditions['min_level']) {
            return false;
        }

        // Check days of week
        if (isset($this->conditions['days_of_week'])) {
            $currentDay = (int) now()->format('N'); // 1 (Monday) to 7 (Sunday)
            if (!in_array($currentDay, $this->conditions['days_of_week'])) {
                return false;
            }
        }

        // Check time of day
        if (isset($this->conditions['time_range'])) {
            $currentHour = (int) now()->format('G');
            if ($currentHour < $this->conditions['time_range']['start'] ||
                $currentHour >= $this->conditions['time_range']['end']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Apply bonuses to a reward.
     */
    public function applyBonuses(array $rewards): array
    {
        if (!$this->isActive()) {
            return $rewards;
        }

        $bonusedRewards = $rewards;

        foreach ($rewards as $type => $amount) {
            if (isset($this->bonuses["{$type}_multiplier"])) {
                $bonusedRewards[$type] = (int) round(
                    $amount * $this->bonuses["{$type}_multiplier"]
                );
            }
        }

        return $bonusedRewards;
    }

    /**
     * Get upcoming events.
     */
    public static function getUpcoming(int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('start_date', '>', now())
            ->where('is_active', true)
            ->orderBy('start_date')
            ->limit($limit)
            ->get();
    }

    /**
     * Get preview data for the bonus event.
     */
    public function getPreviewData(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'bonuses' => $this->bonuses,
            'conditions' => $this->conditions,
            'is_active' => $this->is_active,
            'status' => $this->getStatus(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }

    /**
     * Get the current status of the event.
     */
    public function getStatus(): string
    {
        if (!$this->is_active) {
            return 'inactive';
        }

        if ($this->start_date > now()) {
            return 'scheduled';
        }

        if ($this->end_date < now()) {
            return 'ended';
        }

        return 'active';
    }
}