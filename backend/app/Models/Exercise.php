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
     */
    public function checkAnswer($userAnswer): bool
    {
        return match ($this->type) {
            self::TYPE_MULTIPLE_CHOICE => $this->checkMultipleChoice($userAnswer),
            self::TYPE_FILL_BLANK      => $this->checkFillBlank($userAnswer),
            self::TYPE_MATCHING        => $this->checkMatching($userAnswer),
            self::TYPE_WRITING, self::TYPE_SPEAKING => false, // Requires manual review
            default                    => false
        };
    }

    /**
     * Check multiple choice answer
     */
    private function checkMultipleChoice($answer): bool
    {
        return $answer === $this->answers['correct'];
    }

    /**
     * Check fill in the blank answer
     */
    private function checkFillBlank($answer): bool
    {
        $correct = $this->answers['correct'];

        if (is_array($correct)) {
            // Multiple acceptable answers
            return collect($correct)
                ->contains(
                    fn($value) =>
                    strtolower(trim($answer)) === strtolower(trim($value))
                );
        }

        return strtolower(trim($answer)) === strtolower(trim($correct));
    }

    /**
     * Check matching answer
     */
    private function checkMatching($answers): bool
    {
        // Get the correct answers from the content field
        $correctAnswers = $this->content['answers']['correct'] ?? null;

        if (! $correctAnswers || ! is_array($answers) || count($answers) !== count($correctAnswers)) {
            return false;
        }

        foreach ($correctAnswers as $key => $value) {
            if (! isset($answers[$key]) || $answers[$key] !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get hint for the exercise
     */
    public function getHint(): ?array
    {
        if ($this->type === self::TYPE_MATCHING) {
            return $this->content['answers']['correct'] ?? null;
        }

        return null;
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
            self::TYPE_FILL_BLANK      => [
                'content.text'     => 'required|string',
                'content.blanks'   => 'required|array|min:1',
                'content.blanks.*' => 'required|integer',
                'answers.correct'  => 'required|array|size:content.blanks',
            ],
            self::TYPE_MATCHING        => [
                'content.items'     => 'required|array|min:2',
                'content.items.*'   => 'required|string',
                'content.matches'   => 'required|array|size:content.items',
                'content.matches.*' => 'required|string',
                'answers.correct'   => 'required|array|size:content.items',
            ],
            self::TYPE_WRITING         => [
                'content.prompt'    => 'required|string',
                'content.min_words' => 'required|integer|min:1',
                'content.max_words' => 'required|integer|gt:content.min_words',
            ],
            self::TYPE_SPEAKING        => [
                'content.prompt'   => 'required|string',
                'content.duration' => 'required|integer|min:5|max:300',
            ],
            default                    => []
        };
    }
}
