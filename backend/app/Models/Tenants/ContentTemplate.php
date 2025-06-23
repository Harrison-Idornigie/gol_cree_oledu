<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Traits\Tenant\HasAuditLog;
use App\Traits\Tenant\HasVersions;

class ContentTemplate extends Model
{
    use HasFactory, HasAuditLog, HasVersions, BelongsToTenant;

    public const AUDIT_AREA = 'content_templates';

    // Template types
    public const TYPE_UNIT = 'unit';
    public const TYPE_TOPIC = 'topic';
    public const TYPE_LESSON = 'lesson';
    public const TYPE_EXERCISE = 'exercise';

    public const TEMPLATE_TYPES = [
        self::TYPE_UNIT => 'Unit Template',
        self::TYPE_TOPIC => 'Topic Template',
        self::TYPE_LESSON => 'Lesson Template',
        self::TYPE_EXERCISE => 'Exercise Template',
    ];

    // Skill focus areas
    public const SKILL_VOCABULARY = 'vocabulary';
    public const SKILL_GRAMMAR = 'grammar';
    public const SKILL_LISTENING = 'listening';
    public const SKILL_SPEAKING = 'speaking';
    public const SKILL_READING = 'reading';
    public const SKILL_WRITING = 'writing';
    public const SKILL_CONVERSATION = 'conversation';

    public const SKILL_AREAS = [
        self::SKILL_VOCABULARY => 'Vocabulary',
        self::SKILL_GRAMMAR => 'Grammar',
        self::SKILL_LISTENING => 'Listening',
        self::SKILL_SPEAKING => 'Speaking',
        self::SKILL_READING => 'Reading',
        self::SKILL_WRITING => 'Writing',
        self::SKILL_CONVERSATION => 'Conversation',
    ];

    protected $fillable = [
        'template_type',
        'name',
        'description',
        'template_data',
        'difficulty_level',
        'skill_focus',
        'exercise_types',
        'vocabulary_requirements',
        'created_by',
        'usage_count',
        'tenant_id',
    ];

    protected $casts = [
        'template_data' => 'array',
        'exercise_types' => 'array',
        'vocabulary_requirements' => 'array',
        'difficulty_level' => 'integer',
        'usage_count' => 'integer',
    ];

    /**
     * The attributes that should be version controlled.
     */
    protected array $versionedAttributes = [
        'name',
        'description',
        'template_data',
        'difficulty_level',
        'skill_focus',
        'exercise_types',
        'vocabulary_requirements',
    ];

    /**
     * Get the user who created this template.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get content created from this template.
     * This is a polymorphic relationship that can point to different content types.
     */
    public function instantiations()
    {
        // Return different relationships based on template type
        return match ($this->template_type) {
            self::TYPE_UNIT => $this->hasMany(Unit::class, 'template_id'),
            self::TYPE_TOPIC => $this->hasMany(Topic::class, 'template_id'),
            self::TYPE_LESSON => $this->hasMany(Lesson::class, 'template_id'),
            self::TYPE_EXERCISE => $this->hasMany(Exercise::class, 'template_id'),
            default => $this->hasMany(Exercise::class, 'template_id') // fallback
        };
    }

    /**
     * Scope to filter by template type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('template_type', $type);
    }

    /**
     * Scope to filter by skill focus.
     */
    public function scopeBySkill($query, string $skill)
    {
        return $query->where('skill_focus', $skill);
    }

    /**
     * Scope to filter by difficulty level.
     */
    public function scopeByDifficulty($query, int $level)
    {
        return $query->where('difficulty_level', $level);
    }

    /**
     * Scope to filter by difficulty range.
     */
    public function scopeByDifficultyRange($query, int $min, int $max)
    {
        return $query->whereBetween('difficulty_level', [$min, $max]);
    }

    /**
     * Get template type display name.
     */
    public function getTemplateTypeDisplayAttribute(): string
    {
        return self::TEMPLATE_TYPES[$this->template_type] ?? $this->template_type;
    }

    /**
     * Get skill focus display name.
     */
    public function getSkillFocusDisplayAttribute(): string
    {
        return self::SKILL_AREAS[$this->skill_focus] ?? $this->skill_focus;
    }

    /**
     * Get difficulty level description.
     */
    public function getDifficultyDescription(): string
    {
        return match ($this->difficulty_level) {
            1, 2 => 'Beginner',
            3, 4 => 'Elementary',
            5, 6 => 'Intermediate',
            7, 8 => 'Advanced',
            9, 10 => 'Expert',
            default => 'Unknown'
        };
    }

    /**
     * Check if template supports specific exercise type.
     */
    public function supportsExerciseType(string $exerciseType): bool
    {
        return in_array($exerciseType, $this->exercise_types ?? []);
    }

    /**
     * Get required vocabulary count.
     */
    public function getRequiredVocabularyCount(): int
    {
        return $this->vocabulary_requirements['min_words'] ?? 0;
    }

    /**
     * Get vocabulary difficulty requirements.
     */
    public function getVocabularyDifficultyRequirements(): array
    {
        return $this->vocabulary_requirements['difficulty_distribution'] ?? [];
    }

    /**
     * Validate template data structure.
     */
    public function validateTemplateData(): array
    {
        $errors = [];
        $templateData = $this->template_data;

        // Validate based on template type
        switch ($this->template_type) {
            case self::TYPE_LESSON:
                $errors = array_merge($errors, $this->validateLessonTemplate($templateData));
                break;
            case self::TYPE_EXERCISE:
                $errors = array_merge($errors, $this->validateExerciseTemplate($templateData));
                break;
            case self::TYPE_UNIT:
                $errors = array_merge($errors, $this->validateUnitTemplate($templateData));
                break;
            case self::TYPE_TOPIC:
                $errors = array_merge($errors, $this->validateTopicTemplate($templateData));
                break;
        }

        return $errors;
    }

    /**
     * Validate lesson template structure.
     */
    private function validateLessonTemplate(array $templateData): array
    {
        $errors = [];

        if (empty($templateData['title'])) {
            $errors[] = 'Lesson template must have a title';
        }

        if (empty($templateData['exercise_patterns'])) {
            $errors[] = 'Lesson template must define exercise patterns';
        }

        foreach ($templateData['exercise_patterns'] ?? [] as $index => $pattern) {
            if (empty($pattern['type'])) {
                $errors[] = "Exercise pattern {$index}: type is required";
            }

            if (!isset($pattern['count']) || $pattern['count'] < 1) {
                $errors[] = "Exercise pattern {$index}: count must be at least 1";
            }
        }

        return $errors;
    }

    /**
     * Validate exercise template structure.
     */
    private function validateExerciseTemplate(array $templateData): array
    {
        $errors = [];

        if (empty($templateData['exercise_type'])) {
            $errors[] = 'Exercise template must specify exercise type';
        }

        if (empty($templateData['content_structure'])) {
            $errors[] = 'Exercise template must define content structure';
        }

        return $errors;
    }

    /**
     * Validate unit template structure.
     */
    private function validateUnitTemplate(array $templateData): array
    {
        $errors = [];

        if (empty($templateData['title'])) {
            $errors[] = 'Unit template must have a title';
        }

        if (empty($templateData['topic_patterns'])) {
            $errors[] = 'Unit template must define topic patterns';
        }

        return $errors;
    }

    /**
     * Validate topic template structure.
     */
    private function validateTopicTemplate(array $templateData): array
    {
        $errors = [];

        if (empty($templateData['title'])) {
            $errors[] = 'Topic template must have a title';
        }

        if (empty($templateData['lesson_patterns'])) {
            $errors[] = 'Topic template must define lesson patterns';
        }

        return $errors;
    }

    /**
     * Get template complexity score.
     */
    public function getComplexityScore(): float
    {
        $score = 0;

        // Base score from difficulty level
        $score += $this->difficulty_level * 10;

        // Add complexity based on template data
        $templateData = $this->template_data;

        if ($this->template_type === self::TYPE_LESSON) {
            $exercisePatterns = $templateData['exercise_patterns'] ?? [];
            $score += count($exercisePatterns) * 5;
        }

        // Add complexity based on vocabulary requirements
        $vocabRequirements = $this->vocabulary_requirements ?? [];
        $score += ($vocabRequirements['min_words'] ?? 0) * 0.5;

        return min(100, $score);
    }

    /**
     * Get template usage statistics.
     */
    public function getUsageStatistics(): array
    {
        return [
            'usage_count' => $this->usage_count,
            'instantiations' => $this->instantiations()->count(),
            'success_rate' => $this->calculateSuccessRate(),
            'average_completion_time' => $this->calculateAverageCompletionTime(),
        ];
    }

    /**
     * Calculate success rate of content created from this template.
     */
    private function calculateSuccessRate(): float
    {
        // This would be calculated based on actual usage data
        // For now, return a placeholder
        return 0.0;
    }

    /**
     * Calculate average completion time for content created from this template.
     */
    private function calculateAverageCompletionTime(): float
    {
        // This would be calculated based on actual usage data
        // For now, return a placeholder
        return 0.0;
    }

    /**
     * Clone template with modifications.
     */
    public function cloneWithModifications(array $modifications = []): self
    {
        $clone = $this->replicate();

        // Apply modifications
        foreach ($modifications as $key => $value) {
            if (in_array($key, $this->fillable)) {
                $clone->$key = $value;
            }
        }

        // Reset usage statistics
        $clone->usage_count = 0;
        $clone->created_by = auth()->id();

        return $clone;
    }

    /**
     * Get template preview data.
     */
    public function getPreviewData(): array
    {
        return [
            'id' => $this->id,
            'template_type' => $this->template_type,
            'template_type_display' => $this->template_type_display,
            'name' => $this->name,
            'description' => $this->description,
            'difficulty_level' => $this->difficulty_level,
            'difficulty_description' => $this->getDifficultyDescription(),
            'skill_focus' => $this->skill_focus,
            'skill_focus_display' => $this->skill_focus_display,
            'exercise_types' => $this->exercise_types,
            'vocabulary_requirements' => $this->vocabulary_requirements,
            'complexity_score' => $this->getComplexityScore(),
            'usage_count' => $this->usage_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
