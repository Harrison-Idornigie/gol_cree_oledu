<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Traits\Tenant\HasAuditLog;
use App\Traits\Tenant\HasVersions;

class CurriculumTemplate extends Model
{
    use HasFactory, HasAuditLog, HasVersions, BelongsToTenant;

    public const AUDIT_AREA = 'curriculum_templates';

    // Proficiency levels
    public const LEVEL_A1 = 'A1';
    public const LEVEL_A2 = 'A2';
    public const LEVEL_B1 = 'B1';
    public const LEVEL_B2 = 'B2';
    public const LEVEL_C1 = 'C1';
    public const LEVEL_C2 = 'C2';

    public const PROFICIENCY_LEVELS = [
        self::LEVEL_A1 => 'Beginner',
        self::LEVEL_A2 => 'Elementary',
        self::LEVEL_B1 => 'Intermediate',
        self::LEVEL_B2 => 'Upper Intermediate',
        self::LEVEL_C1 => 'Advanced',
        self::LEVEL_C2 => 'Proficient',
    ];

    protected $fillable = [
        'name',
        'description',
        'language_pair_id',
        'proficiency_level',
        'estimated_hours',
        'prerequisites',
        'template_data',
        'is_official',
        'created_by',
        'usage_count',
        'effectiveness_score',
        'tenant_id',
    ];

    protected $casts = [
        'prerequisites' => 'array',
        'template_data' => 'array',
        'is_official' => 'boolean',
        'usage_count' => 'integer',
        'effectiveness_score' => 'decimal:2',
        'estimated_hours' => 'integer',
    ];

    /**
     * The attributes that should be version controlled.
     */
    protected array $versionedAttributes = [
        'name',
        'description',
        'template_data',
        'prerequisites',
        'estimated_hours',
        'effectiveness_score',
    ];

    /**
     * Get the language pair this template is for.
     */
    public function languagePair(): BelongsTo
    {
        return $this->belongsTo(LanguagePair::class, 'language_pair_id');
    }

    /**
     * Get the user who created this template.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get learning paths created from this template.
     */
    public function instantiations(): HasMany
    {
        return $this->hasMany(LearningPath::class, 'template_id');
    }

    /**
     * Get reviews for this template.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'content_id')->where('content_type', self::class);
    }

    /**
     * Scope to filter by proficiency level.
     */
    public function scopeByLevel($query, string $level)
    {
        return $query->where('proficiency_level', $level);
    }

    /**
     * Scope to filter by language pair.
     */
    public function scopeByLanguagePair($query, int $languagePairId)
    {
        return $query->where('language_pair_id', $languagePairId);
    }

    /**
     * Scope to filter official templates.
     */
    public function scopeOfficial($query)
    {
        return $query->where('is_official', true);
    }

    /**
     * Scope to filter custom templates.
     */
    public function scopeCustom($query)
    {
        return $query->where('is_official', false);
    }

    /**
     * Scope to order by effectiveness.
     */
    public function scopeByEffectiveness($query, string $direction = 'desc')
    {
        return $query->orderBy('effectiveness_score', $direction);
    }

    /**
     * Scope to order by usage.
     */
    public function scopeByUsage($query, string $direction = 'desc')
    {
        return $query->orderBy('usage_count', $direction);
    }

    /**
     * Check if template is highly effective.
     */
    public function isHighlyEffective(): bool
    {
        return $this->effectiveness_score && $this->effectiveness_score >= 80;
    }

    /**
     * Check if template is popular.
     */
    public function isPopular(): bool
    {
        return $this->usage_count >= 10;
    }

    /**
     * Get template complexity level.
     */
    public function getComplexityLevel(): string
    {
        $unitCount = count($this->template_data['units'] ?? []);

        if ($unitCount <= 3) {
            return 'simple';
        } elseif ($unitCount <= 6) {
            return 'moderate';
        } else {
            return 'complex';
        }
    }

    /**
     * Get estimated completion time in hours.
     */
    public function getEstimatedCompletionTime(): int
    {
        return $this->estimated_hours ?? $this->calculateEstimatedHours();
    }

    /**
     * Calculate estimated hours based on template content.
     */
    private function calculateEstimatedHours(): int
    {
        $units = $this->template_data['units'] ?? [];
        $totalHours = 0;

        foreach ($units as $unit) {
            $topics = $unit['topics'] ?? [];
            foreach ($topics as $topic) {
                $lessons = $topic['lessons'] ?? [];
                $totalHours += count($lessons) * 0.5; // Assume 30 minutes per lesson
            }
        }

        return max(1, (int) ceil($totalHours));
    }

    /**
     * Get template statistics.
     */
    public function getStatistics(): array
    {
        $templateData = $this->template_data;
        $units = $templateData['units'] ?? [];

        $stats = [
            'units_count' => count($units),
            'topics_count' => 0,
            'lessons_count' => 0,
            'exercises_count' => 0,
        ];

        foreach ($units as $unit) {
            $topics = $unit['topics'] ?? [];
            $stats['topics_count'] += count($topics);

            foreach ($topics as $topic) {
                $lessons = $topic['lessons'] ?? [];
                $stats['lessons_count'] += count($lessons);

                foreach ($lessons as $lesson) {
                    $exercises = $lesson['exercises'] ?? [];
                    $stats['exercises_count'] += count($exercises);
                }
            }
        }

        return $stats;
    }

    /**
     * Validate template data structure.
     */
    public function validateTemplateData(): array
    {
        $errors = [];
        $templateData = $this->template_data;

        if (empty($templateData['units'])) {
            $errors[] = 'Template must contain at least one unit';
        }

        foreach ($templateData['units'] ?? [] as $unitIndex => $unit) {
            if (empty($unit['title'])) {
                $errors[] = "Unit {$unitIndex}: title is required";
            }

            if (empty($unit['topics'])) {
                $errors[] = "Unit {$unitIndex}: must contain at least one topic";
            }

            foreach ($unit['topics'] ?? [] as $topicIndex => $topic) {
                if (empty($topic['title'])) {
                    $errors[] = "Unit {$unitIndex}, Topic {$topicIndex}: title is required";
                }

                if (empty($topic['lessons'])) {
                    $errors[] = "Unit {$unitIndex}, Topic {$topicIndex}: must contain at least one lesson";
                }
            }
        }

        return $errors;
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
        $clone->effectiveness_score = null;
        $clone->is_official = false;
        $clone->created_by = auth()->id();

        return $clone;
    }

    /**
     * Get proficiency level display name.
     */
    public function getProficiencyLevelDisplayAttribute(): string
    {
        return self::PROFICIENCY_LEVELS[$this->proficiency_level] ?? $this->proficiency_level;
    }

    /**
     * Get template preview data.
     */
    public function getPreviewData(): array
    {
        $stats = $this->getStatistics();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'proficiency_level' => $this->proficiency_level,
            'proficiency_level_display' => $this->proficiency_level_display,
            'estimated_hours' => $this->getEstimatedCompletionTime(),
            'complexity_level' => $this->getComplexityLevel(),
            'is_official' => $this->is_official,
            'usage_count' => $this->usage_count,
            'effectiveness_score' => $this->effectiveness_score,
            'statistics' => $stats,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
