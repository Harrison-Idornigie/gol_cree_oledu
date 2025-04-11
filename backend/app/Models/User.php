<?php
namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'google_id',
        'avatar',
        'total_points',
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
     * Get the roles that belong to the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
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
     * Check if user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return $this->roles()->where('slug', $role)->exists();
    }

    /**
     * Admin Role Check
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
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
}
