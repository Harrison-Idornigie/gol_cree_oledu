<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use App\Models\Traits\HasVersions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class XpRule extends Model
{
    use HasFactory, HasAuditLog, HasVersions;

    const AUDIT_AREA = 'xp_rules';

    protected $fillable = [
        'action',
        'base_xp',
        'multipliers',
        'is_active'
    ];

    protected $casts = [
        'multipliers' => 'array',
        'base_xp' => 'integer',
        'is_active' => 'boolean'
    ];

    protected array $versionedAttributes = [
        'action',
        'base_xp',
        'multipliers',
        'is_active'
    ];

    /**
     * Calculate XP for an action with all applicable multipliers.
     */
    public function calculateXp(array $context = []): int
    {
        if (!$this->is_active) {
            return 0;
        }

        $xp = $this->base_xp;

        // Apply multipliers if any
        if ($this->multipliers && !empty($context)) {
            foreach ($this->multipliers as $type => $value) {
                if (isset($context[$type])) {
                    if (is_array($value) && isset($value[$context[$type]])) {
                        $xp *= $value[$context[$type]];
                    } else if (is_numeric($value) && $context[$type]) {
                        $xp *= $value;
                    }
                }
            }
        }

        return (int) round($xp);
    }

    /**
     * Get the standard XP rule types.
     */
    public static function getStandardRules(): array
    {
        return [
            'lesson_completion' => [
                'base_xp' => 10,
                'multipliers' => [
                    'perfect_score' => 1.5,
                    'streak_bonus' => 1.2
                ]
            ],
            'exercise_completion' => [
                'base_xp' => 5,
                'multipliers' => [
                    'difficulty' => [
                        'easy' => 1.0,
                        'medium' => 1.2,
                        'hard' => 1.5
                    ]
                ]
            ],
            'achievement_earned' => [
                'base_xp' => 50,
                'multipliers' => []
            ],
            'daily_goal_reached' => [
                'base_xp' => 20,
                'multipliers' => [
                    'streak_days' => [
                        '7' => 1.1,
                        '30' => 1.25,
                        '365' => 2.0
                    ]
                ]
            ]
        ];
    }

    /**
     * Get preview data for the XP rule.
     */
    public function getPreviewData(): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'base_xp' => $this->base_xp,
            'multipliers' => $this->multipliers,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}