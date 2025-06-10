<?php

namespace App\Models\Tenants;

use App\Traits\Tenant\HasMedia;
use App\Traits\Tenant\HasAuditLog;
use App\Traits\Tenant\HasVersions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Achievement extends Model
{
    use HasFactory, HasMedia, HasAuditLog, HasVersions, BelongsToTenant;

    const AUDIT_AREA = 'achievements';

    protected $fillable = [
        'name',
        'description',
        'requirements',
        'rewards',
        'status'
    ];

    protected $casts = [
        'requirements' => 'array',
        'rewards' => 'array',
        'status' => 'string'
    ];

    protected array $versionedAttributes = [
        'name',
        'description',
        'requirements',
        'rewards',
        'status'
    ];

    /**
     * Get the users who have earned this achievement.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_achievements')
            ->withTimestamps('earned_at');
    }

    /**
     * Check if a user has earned this achievement.
     */
    public function isEarnedBy(User $user): bool
    {
        return $this->users()->where('user_id', $user->id)->exists();
    }

    /**
     * Get preview data for the achievement.
     */
    public function getPreviewData(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'rewards' => $this->rewards,
            'requirements' => $this->requirements,
            'icon' => $this->getFirstMediaUrl('icon'),
            'earned_count' => $this->users()->count(),
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }

    /**
     * Get the rules for achievement requirements validation.
     */
    public static function getRequirementRules(): array
    {
        return [
            'type' => 'required|string|in:perfect_lessons,time_based,streak,completion',
            'count' => 'required_unless:type,time_based|integer|min:1',
            'consecutive' => 'boolean',
            'before_hour' => 'required_if:type,time_based|integer|between:0,23',
            'timeframe' => 'string|in:daily,weekly,monthly,all_time'
        ];
    }

    /**
     * Get the rules for achievement rewards validation.
     */
    public static function getRewardRules(): array
    {
        return [
            'xp' => 'required|integer|min:0',
            'gems' => 'required|integer|min:0',
            'badges' => 'array',
            'badges.*' => 'string'
        ];
    }
}