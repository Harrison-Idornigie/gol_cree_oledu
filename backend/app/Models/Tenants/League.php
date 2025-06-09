<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use App\Models\Traits\HasVersions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class League extends Model
{
    use HasFactory, HasAuditLog, HasVersions;

    const AUDIT_AREA = 'leagues';

    protected $fillable = [
        'name',
        'tier',
        'requirements',
        'rewards',
        'is_active'
    ];

    protected $casts = [
        'requirements' => 'array',
        'rewards' => 'array',
        'tier' => 'integer',
        'is_active' => 'boolean'
    ];

    protected array $versionedAttributes = [
        'name',
        'tier',
        'requirements',
        'rewards',
        'is_active'
    ];

    /**
     * Get the standard league tiers.
     */
    public static function getStandardTiers(): array
    {
        return [
            [
                'name' => 'Bronze',
                'tier' => 1,
                'requirements' => [
                    'min_xp' => 0,
                    'promotion_rank' => 20
                ],
                'rewards' => [
                    'weekly_gems' => 5
                ]
            ],
            [
                'name' => 'Silver',
                'tier' => 2,
                'requirements' => [
                    'min_xp' => 1000,
                    'promotion_rank' => 15
                ],
                'rewards' => [
                    'weekly_gems' => 10
                ]
            ],
            [
                'name' => 'Gold',
                'tier' => 3,
                'requirements' => [
                    'min_xp' => 5000,
                    'promotion_rank' => 10
                ],
                'rewards' => [
                    'weekly_gems' => 20
                ]
            ],
            [
                'name' => 'Sapphire',
                'tier' => 4,
                'requirements' => [
                    'min_xp' => 15000,
                    'promotion_rank' => 5
                ],
                'rewards' => [
                    'weekly_gems' => 30
                ]
            ],
            [
                'name' => 'Ruby',
                'tier' => 5,
                'requirements' => [
                    'min_xp' => 30000,
                    'promotion_rank' => 3
                ],
                'rewards' => [
                    'weekly_gems' => 40
                ]
            ],
            [
                'name' => 'Diamond',
                'tier' => 6,
                'requirements' => [
                    'min_xp' => 50000,
                    'promotion_rank' => 1
                ],
                'rewards' => [
                    'weekly_gems' => 50
                ]
            ]
        ];
    }

    /**
     * Get memberships for this league.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(LeagueMembership::class);
    }

    /**
     * Get users in this league.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'league_memberships')
            ->withPivot(['current_rank', 'weekly_xp', 'joined_at', 'promoted_at'])
            ->orderByPivot('weekly_xp', 'desc');
    }

    /**
     * Get top performers in this league.
     */
    public function getTopPerformers(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return $this->users()
            ->wherePivot('joined_at', '>=', now()->startOfWeek())
            ->orderByPivot('weekly_xp', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Check if a user is eligible for promotion.
     */
    public function isEligibleForPromotion(User $user): bool
    {
        $membership = $this->memberships()
            ->where('user_id', $user->id)
            ->first();

        if (!$membership) {
            return false;
        }

        return $membership->current_rank <= $this->requirements['promotion_rank'];
    }

    /**
     * Get preview data for the league.
     */
    public function getPreviewData(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tier' => $this->tier,
            'requirements' => $this->requirements,
            'rewards' => $this->rewards,
            'is_active' => $this->is_active,
            'members_count' => $this->memberships()->count(),
            'top_performers' => $this->getTopPerformers(3),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}