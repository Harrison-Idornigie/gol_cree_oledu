<?php
namespace App\Models;

use App\Models\Traits\HasAuditLog;
use App\Models\Traits\HasMedia;
use App\Models\Traits\HasVersions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Exercise extends Model
{
    use HasFactory, HasVersions, HasAuditLog, HasMedia;

    public const AUDIT_AREA = 'exercises';

    // Exercise types
    public const TYPE_MULTIPLE_CHOICE = 'multiple_choice';
    public const TYPE_FILL_BLANK      = 'fill_blank';
    public const TYPE_MATCHING        = 'matching';
    public const TYPE_WRITING         = 'writing';
    public const TYPE_SPEAKING        = 'speaking';
    public const TYPE_CONVERSATION    = 'conversation';
    public const TYPE_LISTENING       = 'listening';
    public const TYPE_PICTURE         = 'picture';

    // Exercise purposes/modes
    public const PURPOSE_PRACTICE   = 'practice';
    public const PURPOSE_CHECKPOINT = 'checkpoint';
    public const PURPOSE_REVIEW     = 'review';
    public const PURPOSE_ASSESSMENT = 'assessment';

    protected $fillable = [
        'lesson_id',
        'title',
        'slug',
        'type',
        'content',
        'answers',
        'order',
        'status',
        'review_status',
        'purpose',
        'difficulty_level',
        'passing_score',
        'time_limit',
        'max_attempts',
        'show_feedback',
        'show_hints',
        'xp_reward',
        'is_checkpoint',
        'requires_previous',
        'show_solutions_after',
        'min_correct_required',
        'metadata',
    ];

    protected $casts = [
        'content'              => 'array',
        'answers'              => 'array',
        'metadata'             => 'array',
        'order'                => 'integer',
        'status'               => 'string',
        'review_status'        => 'string',
        'passing_score'        => 'integer',
        'time_limit'           => 'integer',
        'max_attempts'         => 'integer',
        'show_feedback'        => 'boolean',
        'show_hints'           => 'boolean',
        'xp_reward'            => 'integer',
        'is_checkpoint'        => 'boolean',
        'requires_previous'    => 'boolean',
        'show_solutions_after' => 'boolean',
        'min_correct_required' => 'integer',
    ];

    /**
     * The attributes that should be version controlled.
     */
    protected array $versionedAttributes = [
        'type',
        'content',
        'answers',
        'order',
        'purpose',
        'passing_score',
        'time_limit',
        'max_attempts',
    ];

    /**
     * Get the lesson that owns the exercise.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Get all progress records for this exercise.
     */
    public function progress(): MorphMany
    {
        return $this->morphMany(UserProgress::class, 'trackable');
    }

    /**
     * Get all attempts for this exercise.
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(ExerciseAttempt::class);
    }

    /**
     * Calculate the success rate for this exercise
     */
    public function getSuccessRate(): float
    {
        $totalAttempts = $this->attempts()->count();
        if ($totalAttempts === 0) {
            return 0.0;
        }

        $successfulAttempts = $this->attempts()->where('is_correct', true)->count();
        return round(($successfulAttempts / $totalAttempts) * 100, 2);
    }

    /**
     * Get average completion time in seconds
     */
    public function getAverageCompletionTime(): ?float
    {
        return $this->attempts()
            ->whereNotNull('time_taken_seconds')
            ->avg('time_taken_seconds');
    }

    /**
     * Scope query to exercises that might need review based on low success rates
     */
    public function scopeNeedsReview($query, int $minimumAttempts = 10, float $successThreshold = 0.5)
    {
        return $query->withCount(['attempts', 'attempts as successful_attempts' => function ($query) {
            $query->where('is_correct', true);
        }])
            ->having('attempts_count', '>=', $minimumAttempts)
            ->havingRaw('(successful_attempts / attempts_count) < ?', [$successThreshold]);
    }

    /**
     * Check if the given answer is correct
     *
     * @deprecated Use ExerciseTypeService instead
     */
    public function checkAnswer($userAnswer): bool
    {
        // This method is kept for backward compatibility
        // Use app(ExerciseTypeService::class)->checkAnswer($this, $userAnswer) instead
        return app(\App\Services\ExerciseTypeService::class)->checkAnswer($this, $userAnswer);
    }

    /**
     * Get hint for the exercise
     *
     * @deprecated Use ExerciseTypeService instead
     */
    public function getHint(): mixed
    {
        // This method is kept for backward compatibility
        // Use app(ExerciseTypeService::class)->getHint($this) instead
        return app(\App\Services\ExerciseTypeService::class)->getHint($this);
    }

    /**
     * Get the export data structure
     */
    public function getExportData(): array
    {
        return [
            'type'                 => $this->type,
            'purpose'              => $this->purpose,
            'content'              => $this->content,
            'answers'              => $this->answers,
            'order'                => $this->order,
            'passing_score'        => $this->passing_score,
            'time_limit'           => $this->time_limit,
            'max_attempts'         => $this->max_attempts,
            'show_feedback'        => $this->show_feedback,
            'show_hints'           => $this->show_hints,
            'is_checkpoint'        => $this->is_checkpoint,
            'requires_previous'    => $this->requires_previous,
            'show_solutions_after' => $this->show_solutions_after,
            'min_correct_required' => $this->min_correct_required,
            'difficulty_level'     => $this->difficulty_level,
            'metadata'             => $this->metadata,
            'media'                => $this->media->groupBy('collection_name')->toArray(),
        ];
    }

    /**
     * Import data from an export structure
     */
    public static function importData(array $data, Lesson $lesson): self
    {
        return static::create([
            'lesson_id'            => $lesson->id,
            'type'                 => $data['type'],
            'purpose'              => $data['purpose'] ?? self::PURPOSE_PRACTICE,
            'content'              => $data['content'],
            'answers'              => $data['answers'],
            'order'                => $data['order'],
            'passing_score'        => $data['passing_score'] ?? 70,
            'time_limit'           => $data['time_limit'] ?? null,
            'max_attempts'         => $data['max_attempts'] ?? null,
            'show_feedback'        => $data['show_feedback'] ?? true,
            'show_hints'           => $data['show_hints'] ?? true,
            'is_checkpoint'        => $data['is_checkpoint'] ?? false,
            'requires_previous'    => $data['requires_previous'] ?? false,
            'show_solutions_after' => $data['show_solutions_after'] ?? true,
            'min_correct_required' => $data['min_correct_required'] ?? null,
            'difficulty_level'     => $data['difficulty_level'] ?? 'beginner',
            'metadata'             => $data['metadata'] ?? [],
        ]);
    }

    /**
     * Get all media collections available for exercises
     */
    public static function getMediaCollections(): array
    {
        return [
            'question_images' => [
                'max_files'   => 3,
                'conversions' => [
                    'thumb'   => ['width' => 100, 'height' => 100],
                    'display' => ['width' => 600, 'height' => null],
                ],
            ],
            'audio_prompts'   => [
                'max_files'     => 1,
                'allowed_types' => ['audio/mpeg', 'audio/wav'],
            ],
            'answer_images'   => [
                'max_files'   => 4,
                'conversions' => [
                    'thumb'   => ['width' => 100, 'height' => 100],
                    'display' => ['width' => 400, 'height' => null],
                ],
            ],
        ];
    }

    /**
     * Check if this exercise is a checkpoint
     */
    public function isCheckpoint(): bool
    {
        return $this->is_checkpoint || $this->purpose === self::PURPOSE_CHECKPOINT;
    }

    /**
     * Scope a query to only include checkpoint exercises
     */
    public function scopeCheckpoints($query)
    {
        return $query->where(function ($q) {
            $q->where('is_checkpoint', true)
                ->orWhere('purpose', self::PURPOSE_CHECKPOINT);
        });
    }

    /**
     * Check if this exercise is for assessment
     */
    public function isAssessment(): bool
    {
        return $this->purpose === self::PURPOSE_ASSESSMENT;
    }

    /**
     * Check if this exercise is for review
     */
    public function isReview(): bool
    {
        return $this->purpose === self::PURPOSE_REVIEW;
    }

    /**
     * Check if this exercise is for practice
     */
    public function isPractice(): bool
    {
        return $this->purpose === self::PURPOSE_PRACTICE || $this->purpose === null;
    }

    /**
     * Calculate the score for a given answer
     */
    public function calculateScore($userAnswer): float
    {
        // Simple implementation - either 100% or 0%
        return $this->checkAnswer($userAnswer) ? 100.0 : 0.0;
    }

    /**
     * Check if the user has passed this exercise
     */
    public function hasPassed(float $score): bool
    {
        return $score >= ($this->passing_score ?? 70);
    }

    /**
     * Get all checkpoint exercises for a lesson
     */
    public static function getCheckpointsForLesson($lessonId)
    {
        return self::where('lesson_id', $lessonId)
            ->checkpoints()
            ->orderBy('order')
            ->get();
    }

    /**
     * Get all exercises that should be included in a checkpoint assessment
     * This could include exercises from previous lessons that are being tested
     */
    public static function getExercisesForCheckpoint($checkpointId)
    {
        $checkpoint = self::findOrFail($checkpointId);

        if (! $checkpoint->isCheckpoint()) {
            return collect([$checkpoint]);
        }

        // Get metadata about which exercises to include
        $exerciseIds = $checkpoint->metadata['included_exercises'] ?? [];

        if (empty($exerciseIds)) {
            // If no specific exercises are defined, return just the checkpoint
            return collect([$checkpoint]);
        }

        // Return the checkpoint plus all included exercises
        return self::whereIn('id', $exerciseIds)
            ->orderBy('order')
            ->get()
            ->prepend($checkpoint);
    }

    /**
     * Get exercise validation rules by type
     */
    public static function getValidationRules(string $type): array
    {
        return match ($type) {
            self::TYPE_MULTIPLE_CHOICE => [
                'content.question'  => 'required|string',
                'content.options'   => 'required|array|min:2',
                'content.options.*' => 'required|string',
                'answers.correct'   => 'required|string|in_array:content.options.*',
            ],
            self::TYPE_FILL_BLANK   => [
                'content.text'     => 'required|string',
                'content.blanks'   => 'required|array|min:1',
                'content.blanks.*' => 'required|integer',
                'answers.correct'  => 'required|array|size:content.blanks',
            ],
            self::TYPE_MATCHING     => [
                'content.items'     => 'required|array|min:2',
                'content.items.*'   => 'required|string',
                'content.matches'   => 'required|array|size:content.items',
                'content.matches.*' => 'required|string',
                'answers.correct'   => 'required|array|size:content.items',
            ],
            self::TYPE_WRITING      => [
                'content.prompt'     => 'required|string',
                'content.word_ids'   => 'required|array|min:2',
                'content.word_ids.*' => 'required|integer|exists:words,id',
                'answers.correct'    => 'required|array|min:2',
                'answers.correct.*'  => 'required|string',
            ],
            self::TYPE_SPEAKING     => [
                'content.prompt'   => 'required|string',
                'content.duration' => 'required|integer|min:5|max:300',
            ],
            self::TYPE_CONVERSATION => [
                'content.title'           => 'required|string',
                'content.description'     => 'required|string',
                'content.steps'           => 'required|array|min:2',
                'content.steps.*.type'    => 'required|string|in:dialogue,question,choice',
                'content.steps.*.content' => 'required',
                'content.word_mapping'    => 'nullable|array',
            ],
            self::TYPE_LISTENING    => [
                'content.audio_url'    => 'required|string',
                'content.transcript'   => 'required|string',
                'content.prompt'       => 'required|string',
                'content.language'     => 'required|string',
                'content.difficulty'   => 'required|string|in:beginner,intermediate,advanced',
                'content.word_mapping' => 'nullable|array',
                'answers.correct'      => 'required|array',
                'answers.alternatives' => 'nullable|array',
            ],
            self::TYPE_PICTURE      => [
                'content.question'     => 'required|string',
                'content.mode'         => 'required|string|in:word_to_image,image_to_word',
                'content.images'       => 'required_if:content.mode,word_to_image|array',
                'content.images.*.url' => 'required_if:content.mode,word_to_image|string',
                'content.images.*.alt' => 'required_if:content.mode,word_to_image|string',
                'content.words'        => 'required_if:content.mode,image_to_word|array',
                'content.target_image' => 'required_if:content.mode,image_to_word|string',
                'content.language'     => 'required|string',
                'content.word_mapping' => 'nullable|array',
                'answers.correct'      => 'required|integer',
            ],
            default                 => []
        };
    }
}