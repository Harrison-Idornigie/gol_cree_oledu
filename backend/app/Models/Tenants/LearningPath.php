<?php

namespace App\Models\Tenants;

use App\Traits\Tenant\HasAuditLog;
use App\Traits\Tenant\HasMedia;
use App\Traits\Tenant\HasVersions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class LearningPath extends Model
{
    use HasFactory, HasVersions, HasAuditLog, HasMedia, BelongsToTenant;

    public const AUDIT_AREA = 'learning_paths';

    protected $fillable = [
        'title',
        'language_id', // Legacy - use language_pair_id instead
        'language_pair_id',
        'description',
        'target_level',
        'status',
        'review_status',
        'tenant_id',
        'created_by',
    ];

    protected $casts = [
        'status'        => 'string',
        'review_status' => 'string',
    ];

    /**
     * The attributes that should be version controlled.
     */
    protected array $versionedAttributes = [
        'title',
        'language_id', // Legacy
        'language_pair_id',
        'description',
        'target_level',
        'status',
        'review_status',
    ];

    /**
     * Get the language this learning path belongs to (legacy).
     * @deprecated Use languagePair() instead
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * Get the language pair for this learning path.
     */
    public function languagePair(): BelongsTo
    {
        return $this->belongsTo(LanguagePair::class);
    }

    /**
     * Get the source language (what the user already knows).
     */
    public function sourceLanguage(): ?Language
    {
        return $this->languagePair?->sourceLanguage;
    }

    /**
     * Get the target language (what the user is learning).
     */
    public function targetLanguage(): ?Language
    {
        return $this->languagePair?->targetLanguage;
    }

    /**
     * Get the user who created this learning path.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the units for the learning path.
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class)->orderBy('order');
    }

    /**
     * Get all progress records for this learning path.
     */
    public function progress(): MorphMany
    {
        return $this->morphMany(UserProgress::class, 'trackable');
    }

    /**
     * Get all reviews for this learning path.
     */
    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable', 'content_type', 'content_id');
    }

    /**
     * Get the published scope.
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Check if the learning path is published.
     */
    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Get the total number of lessons in this learning path.
     */
    public function getLessonCount(): int
    {
        return $this->units()->withCount('lessons')->get()->sum('lessons_count');
    }

    /**
     * Get the completion percentage for a specific user
     */
    public function getCompletionPercentage(int $userId): float
    {
        $units = $this->units()->with(['progress' => function ($query) use ($userId) {
            $query->where('user_id', $userId);
        }])->get();

        if ($units->isEmpty()) {
            return 0;
        }

        $completedUnits = $units->filter(function ($unit) {
            return $unit->progress->contains('status', 'completed');
        })->count();

        return ($completedUnits / $units->count()) * 100;
    }

    /**
     * Get content preview data
     */
    public function getPreviewData(): array
    {
        return [
            'id'            => $this->id,
            'title'         => $this->title,
            'language_id'   => $this->language_id,
            'language'      => $this->language?->name,
            'description'   => $this->description,
            'target_level'  => $this->target_level,
            'status'        => $this->status,
            'units_count'   => $this->units()->count(),
            'lessons_count' => $this->getLessonCount(),
            'thumbnail'     => collect($this->getMedia('thumbnail'))->first()?->getUrl(),
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }

    /**
     * Get the export data structure
     */
    public function getExportData(): array
    {
        return [
            'id'               => $this->id,
            'title'            => $this->title,
            'language_id'      => $this->language_id, // Legacy
            'language_pair_id' => $this->language_pair_id,
            'description'      => $this->description,
            'target_level'     => $this->target_level,
            'status'           => $this->status,
            'units'            => $this->units->map->getExportData()->toArray(),
            'media'            => $this->media->groupBy('collection_name')->toArray(),
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }

    /**
     * Import data from an export structure
     */
    public static function importData(array $data): self
    {
        $learningPath = static::create([
            'title'            => $data['title'],
            'language_id'      => $data['language_id'] ?? null, // Legacy fallback
            'language_pair_id' => $data['language_pair_id'] ?? null,
            'description'      => $data['description'],
            'target_level'     => $data['target_level'],
            'status'           => 'draft',
        ]);

        foreach ($data['units'] ?? [] as $unitData) {
            Unit::importData($unitData, $learningPath);
        }

        return $learningPath;
    }

    /**
     * Helper method to get the effective target language.
     * Uses language_pair_id if available, falls back to language_id.
     */
    public function getTargetLanguage(): ?Language
    {
        if ($this->language_pair_id && $this->languagePair) {
            return $this->languagePair->targetLanguage;
        }

        return $this->language;
    }

    /**
     * Helper method to get the effective source language.
     * Uses language_pair_id if available, falls back to English as default.
     */
    public function getSourceLanguage(): ?Language
    {
        if ($this->language_pair_id && $this->languagePair) {
            return $this->languagePair->sourceLanguage;
        }

        // Fallback to English for legacy learning paths
        return Language::where('code', 'en')->first();
    }

    /**
     * Scope to filter by language pair.
     */
    public function scopeForLanguagePair($query, int $languagePairId)
    {
        return $query->where('language_pair_id', $languagePairId);
    }

    /**
     * Scope to filter by target language (works with both new and legacy structure).
     */
    public function scopeForTargetLanguage($query, int $languageId)
    {
        return $query->where(function ($q) use ($languageId) {
            $q->whereHas('languagePair', function ($lpQuery) use ($languageId) {
                $lpQuery->where('target_language_id', $languageId);
            })->orWhere('language_id', $languageId);
        });
    }
}
