<?php

namespace App\Models\Tenants;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use App\Traits\Tenant\HasAuditLog;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\HasApiTokens;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Traits\Tenant\HasPermissions;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, BelongsToTenant, HasPermissions, HasAuditLog;

    public const AUDIT_AREA = 'users';

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\Tenant\UserFactory::new();
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'membership',
        'interface_language',
        'google_id',
        'avatar_url',
        'total_points',
        'tenant_id',
        'central_user_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'total_points'      => 'integer',
    ];

    protected $attributes = [
        'membership' => 'student',
    ];

    protected array $auditLogEvents = [
        'created' => 'Created user account: :name (:email)',
        'updated' => 'Updated user account: :name',
        'deleted' => 'Deleted user account: :name',
    ];

    protected array $auditLogProperties = [
        'name',
        'email',
        'membership',
        'interface_language',
        'total_points',
    ];

    /**
     * Get all progress records for the user.
     */
    public function progress(): HasMany
    {
        return $this->hasMany(UserProgress::class);
    }

    /**
     * Helper methods for checking progress
     */
    public function getProgressFor($model): ?UserProgress
    {
        return $this->progress()
            ->where('trackable_type', get_class($model))
            ->where('trackable_id', $model->id)
            ->first();
    }

    /**
     * Get the user's streak information.
     */
    public function streak(): HasMany
    {
        return $this->hasMany(UserStreak::class);
    }

    /**
     * Get the user's XP history.
     */
    public function xpHistory(): HasMany
    {
        return $this->hasMany(XpHistory::class);
    }


    /**
     * Override trait method: Roles that belong to the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot(['conditions', 'is_active', 'assigned_at', 'expires_at'])
            ->withTimestamps();
    }

    /**
     * Override trait method: Get only active roles for this user.
     */
    public function activeRoles(): BelongsToMany
    {
        return $this->roles()
            ->wherePivot('is_active', true)
            ->where(function ($query) {
                $query->whereNull('user_roles.expires_at')
                    ->orWhere('user_roles.expires_at', '>', now());
            });
    }

    /**
     * Get the user's selected languages.
     */
    public function userLanguages(): HasMany
    {
        return $this->hasMany(UserLanguage::class);
    }

    /**
     * Get the languages selected by the user.
     */
    public function selectedLanguages(): BelongsToMany
    {
        return $this->belongsToMany(Language::class, 'user_languages')
            ->withPivot('is_primary', 'proficiency_level', 'metadata')
            ->withTimestamps();
    }

    /**
     * XP Management
     */
    public function awardXp(int $amount, string $source, ?int $lessonId = null): void
    {
        $this->total_points += $amount;
        $this->save();

        // Record XP history
        $this->xpHistory()->create([
            'amount'    => $amount,
            'source'    => $source,
            'lesson_id' => $lessonId,
        ]);
    }

    // Progress Summary
    public function getProgressSummary(): array
    {
        $progress = $this->progress();

        $completedLessons = $progress
            ->where('trackable_type', Lesson::class)
            ->where('status', UserProgress::STATUS_COMPLETED)
            ->count();

        $totalUnits     = Unit::count();
        $completedUnits = $progress
            ->where('trackable_type', Unit::class)
            ->where('status', UserProgress::STATUS_COMPLETED)
            ->count();

        $vocabularyMastered = $progress
            ->where('trackable_type', VocabularyItem::class)
            ->where('status', UserProgress::STATUS_COMPLETED)
            ->count();

        $exercisesCompleted = $progress
            ->where('trackable_type', Exercise::class)
            ->where('status', UserProgress::STATUS_COMPLETED)
            ->count();

        return [
            'completed_lessons'   => $completedLessons,
            'total_points'        => $this->total_points,
            'completed_units'     => $completedUnits,
            'total_units'         => $totalUnits,
            'vocabulary_mastered' => $vocabularyMastered,
            'exercises_completed' => $exercisesCompleted,
        ];
    }




    /**
     * Check if user has a specific membership.
     */
    public function hasMembership(string $membership): bool
    {
        return $this->membership === $membership;
    }

    /**
     * Admin Membership Check
     */
    public function isAdmin(): bool
    {
        return $this->membership === 'admin';
    }

    /**
     * Super Admin Membership Check
     */
    public function isSuperAdmin(): bool
    {
        return $this->membership === 'super-admin';
    }

    /**
     * Tenant Admin Membership Check
     */
    public function isTenantAdmin(): bool
    {
        return $this->membership === 'tenant-admin' || $this->membership === 'admin';
    }

    /**
     * Team Membership Check
     */
    public function isTeam(): bool
    {
        return $this->membership === 'team' || $this->membership === 'admin';
    }

    /**
     * Student Membership Check
     */
    public function isStudent(): bool
    {
        return $this->membership === 'student';
    }

    /**
     * System User Membership Check
     */
    public function isSystemUser(): bool
    {
        return $this->membership === 'system';
    }

    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmailNotification);
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Get fixed permissions based on membership for backward compatibility
     */
    public function getFixedPermissions(): array
    {
        $fixedPermissions = [
            'admin' => [
                'system.manage',
                'tenants.manage',
                'users.manage',
                'content.manage',
                'team',
                'admin',
                'words.manage',
                'lessons.manage'
            ],
            'tenant-admin' => [
                'tenant-admin',
                'users.manage',
                'content.view',
                'settings.manage',
                'analytics.view',
                'reports.view'
            ],
            'team' => [
                'content.create',
                'words.manage',
                'team',
                'lessons.manage'
            ],
            'student' => [
                'content.view',
                'progress.track',
                'exercises.attempt'
            ],
            'system' => [
                'system.seed',
                'system.migrate',
                'content.create',
                'content.manage'
            ]
        ];

        return $fixedPermissions[$this->membership] ?? [];
    }
}
