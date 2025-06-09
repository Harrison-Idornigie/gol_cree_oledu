<?php
namespace App\Models;

use App\Models\Exercise;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserProgress extends Model
{
    use HasFactory, HasAuditLog;

    public const AUDIT_AREA = 'user_progress';

    public const STATUS_NOT_STARTED = 'not_started';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_FAILED      = 'failed';

    protected $fillable = [
        'user_id',
        'trackable_type',
        'trackable_id',
        'status',
        'meta_data',
        'completed_at',
    ];

    protected $casts = [
        'meta_data'    => 'array',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the user that owns the progress.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent trackable model.
     */
    public function trackable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Update progress status
     */
    public function updateStatus(string $status, array $metadata = []): bool
    {
        $this->status = $status;

        if ($status === self::STATUS_COMPLETED && ! $this->completed_at) {
            $this->completed_at = now();
        }

        if (! empty($metadata)) {
            $this->meta_data = array_merge($this->meta_data ?? [], $metadata);
        }

        return $this->save();
    }

    /**
     * Mark as completed
     */
    public function complete(array $metadata = []): bool
    {
        return $this->updateStatus(self::STATUS_COMPLETED, $metadata);
    }

    /**
     * Mark as failed
     */
    public function fail(array $metadata = []): bool
    {
        return $this->updateStatus(self::STATUS_FAILED, $metadata);
    }

    /**
     * Mark as in progress
     */
    public function startProgress(array $metadata = []): bool
    {
        return $this->updateStatus(self::STATUS_IN_PROGRESS, $metadata);
    }

    /**
     * Reset progress
     */
    public function reset(): bool
    {
        return $this->updateStatus(self::STATUS_NOT_STARTED, []);
    }

    /**
     * Check if completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if failed
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if in progress
     */
    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /**
     * Get time spent on this item
     */
    public function getTimeSpent(): int
    {
        return $this->meta_data['time_spent'] ?? 0;
    }

    /**
     * Add time spent
     */
    public function addTimeSpent(int $seconds): bool
    {
        $currentTime = $this->getTimeSpent();
        return $this->updateStatus($this->status, [
            'time_spent' => $currentTime + $seconds,
        ]);
    }

    /**
     * Get attempt count
     */
    public function getAttemptCount(): int
    {
        return $this->meta_data['attempts'] ?? 0;
    }

    /**
     * Increment attempt count
     */
    public function incrementAttempts(): bool
    {
        $attempts = $this->getAttemptCount();
        return $this->updateStatus($this->status, [
            'attempts' => $attempts + 1,
        ]);
    }

    /**
     * Get the last score
     */
    public function getLastScore(): ?float
    {
        return $this->meta_data['last_score'] ?? null;
    }

    /**
     * Get the best score
     */
    public function getBestScore(): ?float
    {
        return $this->meta_data['best_score'] ?? null;
    }

    /**
     * Update score
     */
    public function updateScore(float $score): bool
    {
        $bestScore = $this->getBestScore();
        return $this->updateStatus($this->status, [
            'last_score' => $score,
            'best_score' => $bestScore === null ? $score : max($bestScore, $score),
        ]);
    }

    /**
     * Scope a query to only include completed progress.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope a query to only include failed progress.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope a query to only include in progress items.
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    /**
     * Get progress summary
     */
    public function getSummary(): array
    {
        return [
            'status'       => $this->status,
            'completed_at' => $this->completed_at,
            'time_spent'   => $this->getTimeSpent(),
            'attempts'     => $this->getAttemptCount(),
            'last_score'   => $this->getLastScore(),
            'best_score'   => $this->getBestScore(),
            'meta_data'    => $this->meta_data,
        ];
    }

    /**
     * Check if this is a checkpoint progress
     */
    public function isCheckpointProgress(): bool
    {
        if ($this->trackable_type !== Exercise::class) {
            return false;
        }

        return $this->trackable->isCheckpoint();
    }

    /**
     * Get all checkpoint progress for a user
     */
    public static function getCheckpointProgress($userId)
    {
        return self::where('user_id', $userId)
            ->where('trackable_type', Exercise::class)
            ->whereHas('trackable', function ($query) {
                $query->checkpoints();
            })
            ->with('trackable')
            ->get();
    }
}