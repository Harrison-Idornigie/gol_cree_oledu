<?php
namespace App\Models;

use App\Models\Review;
use App\Models\Traits\HasAuditLog;
use App\Models\Traits\HasVersions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Topic extends Model
{
    use HasFactory, HasAuditLog, HasVersions;

    public const AUDIT_AREA = 'topics';

    protected $fillable = [
        'unit_id',
        'title',
        'slug',
        'description',
        'icon',
        'color',
        'order',
        'status',
        'xp_reward',
        'max_level',
        'is_bonus',
        'metadata',
    ];

    protected $casts = [
        'metadata'  => 'array',
        'is_bonus'  => 'boolean',
        'max_level' => 'integer',
        'order'     => 'integer',
        'xp_reward' => 'integer',
    ];

    /**
     * The attributes that should be version controlled.
     */
    protected array $versionedAttributes = [
        'title',
        'description',
        'icon',
        'color',
        'order',
        'status',
        'xp_reward',
        'max_level',
        'is_bonus',
        'metadata',
    ];

    /**
     * Get the unit that owns the topic.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the lessons for the topic.
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('order');
    }

    /**
     * Get the published lessons for the topic.
     */
    public function publishedLessons(): HasMany
    {
        return $this->lessons()->where('status', 'published');
    }

    /**
     * Get the topic's completion percentage for a user.
     */
    public function getCompletionPercentage(int $userId): float
    {
        $lessonCount = $this->publishedLessons()->count();

        if ($lessonCount === 0) {
            return 0;
        }

        $completedLessons = 0;

        foreach ($this->publishedLessons as $lesson) {
            $progress = UserProgress::getProgress($userId, $lesson);

            if ($progress && $progress->status === UserProgress::STATUS_COMPLETED) {
                $completedLessons++;
            }
        }

        return ($completedLessons / $lessonCount) * 100;
    }

    /**
     * Check if the topic is completed by a user.
     */
    public function isCompletedByUser(int $userId): bool
    {
        return $this->getCompletionPercentage($userId) === 100;
    }

    /**
     * Get the current level of the topic for a user.
     */
    public function getCurrentLevel(int $userId): int
    {
        // In Duolingo, each skill can have multiple levels (crowns)
        // This is a simplified implementation
        $progress = UserProgress::getProgress($userId, $this);

        if (! $progress) {
            return 0;
        }

        return $progress->meta_data['current_level'] ?? 0;
    }

    /**
     * Get all reviews for this topic.
     */
    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable', 'content_type', 'content_id');
    }
}
