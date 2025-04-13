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

    public const TYPE_MULTIPLE_CHOICE = 'multiple_choice';
    public const TYPE_FILL_BLANK      = 'fill_blank';
    public const TYPE_MATCHING        = 'matching';
    public const TYPE_WRITING         = 'writing';
    public const TYPE_SPEAKING        = 'speaking';
    public const TYPE_CONVERSATION    = 'conversation';
    public const TYPE_LISTENING       = 'listening';
    public const TYPE_PICTURE         = 'picture';

    protected $fillable = [
        'section_id',
        'lesson_id',
        'title',
        'slug',
        'type',
        'content',
        'answers',
        'order',
        'status',
        'review_status',
    ];

    protected $casts = [
        'content'       => 'array',
        'answers'       => 'array',
        'order'         => 'integer',
        'status'        => 'string',
        'review_status' => 'string',
    ];

    /**
     * The attributes that should be version controlled.
     */
    protected array $versionedAttributes = [
        'type',
        'content',
        'answers',
        'order',
    ];

    /**
     * Get the section that owns the exercise.
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
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
            'type'    => $this->type,
            'content' => $this->content,
            'answers' => $this->answers,
            'order'   => $this->order,
            'media'   => $this->media->groupBy('collection_name')->toArray(),
        ];
    }

    /**
     * Import data from an export structure
     */
    public static function importData(array $data, Section $section): self
    {
        return static::create([
            'section_id' => $section->id,
            'type'       => $data['type'],
            'content'    => $data['content'],
            'answers'    => $data['answers'],
            'order'      => $data['order'],
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
