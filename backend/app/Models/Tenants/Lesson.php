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

class Lesson extends Model
{
    use HasFactory, HasVersions, HasAuditLog, HasMedia, BelongsToTenant;

    public const AUDIT_AREA = 'lessons';

    protected $fillable = [
        'topic_id',
        'template_id',
        'title',
        'description',
        'order',
        'status',
        'review_status',
        'created_by',
        'tenant_id',
    ];

    protected $casts = [
        'order'         => 'integer',
        'status'        => 'string',
        'review_status' => 'string',
    ];

    /**
     * The attributes that should be version controlled.
     */
    protected array $versionedAttributes = [
        'title',
        'description',
        'order',
        'status',
        'review_status',
    ];

    /**
     * Get the topic that owns the lesson.
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    /**
     * Get the user who created the lesson.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the template used to create this lesson.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ContentTemplate::class, 'template_id');
    }

    /**
     * Get the unit through the topic.
     */
    public function unit()
    {
        return $this->topic->unit();
    }

    /**
     * Get the learning path through the topic and unit.
     */
    public function learningPath()
    {
        return $this->topic->unit->learningPath();
    }

    /**
     * Get the exercises for the lesson.
     */
    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class)->orderBy('order');
    }

    /**
     * Get the vocabulary items for the lesson.
     */
    public function vocabularyItems(): HasMany
    {
        return $this->hasMany(VocabularyItem::class);
    }

    /**
     * Get all progress records for this lesson.
     */
    public function progress(): MorphMany
    {
        return $this->morphMany(UserProgress::class, 'trackable');
    }

    /**
     * Get all reviews for this lesson.
     */
    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable', 'content_type', 'content_id');
    }

    /**
     * Check if all exercises are completed for a user
     */
    public function isCompletedByUser(int $userId): bool
    {
        $totalExercises = $this->exercises()->count();
        if ($totalExercises === 0) {
            return false;
        }

        $completedExercises = $this->exercises()
            ->whereHas('attempts', function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->where('is_correct', true);
            })
            ->count();

        return $completedExercises === $totalExercises;
    }

    /**
     * Get content preview data
     */
    public function getPreviewData(): array
    {
        return [
            'id'               => $this->id,
            'title'            => $this->title,
            'description'      => $this->description,
            'order'            => $this->order,
            'exercises_count'  => $this->exercises()->count(),
            'vocabulary_count' => $this->vocabularyItems()->count(),
            'thumbnail'        => collect($this->getMedia('thumbnail'))->first()?->getUrl(),
            'topic'            => [
                'id'    => $this->topic->id,
                'title' => $this->topic->title,
                'unit'  => [
                    'id'            => $this->topic->unit->id,
                    'title'         => $this->topic->unit->title,
                    'learning_path' => [
                        'id'    => $this->topic->unit->learningPath->id,
                        'title' => $this->topic->unit->learningPath->title,
                    ],
                ],
            ],
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }

    /**
     * Get the export data structure
     */
    public function getExportData(): array
    {
        return [
            'title'            => $this->title,
            'description'      => $this->description,
            'order'            => $this->order,
            'exercises'        => $this->exercises->map->getExportData()->toArray(),
            'vocabulary_items' => $this->vocabularyItems->map->getExportData()->toArray(),
            'media'            => $this->media->groupBy('collection_name')->toArray(),
        ];
    }

    /**
     * Import data from an export structure
     */
    public static function importData(array $data, Topic $topic): self
    {
        $lesson = static::create([
            'topic_id'    => $topic->id,
            'title'       => $data['title'],
            'description' => $data['description'],
            'order'       => $data['order'],
        ]);

        foreach ($data['exercises'] ?? [] as $exerciseData) {
            Exercise::importData($exerciseData, $lesson);
        }

        foreach ($data['vocabulary_items'] ?? [] as $itemData) {
            VocabularyItem::create([
                'lesson_id'   => $lesson->id,
                'word'        => $itemData['word'],
                'translation' => $itemData['translation'],
                'example'     => $itemData['example'] ?? null,
            ]);
        }

        return $lesson;
    }

    /**
     * Get the next lesson in the topic
     */
    public function getNextLesson(): ?self
    {
        return static::where('topic_id', $this->topic_id)
            ->where('order', '>', $this->order)
            ->orderBy('order')
            ->first();
    }

    /**
     * Get the previous lesson in the topic
     */
    public function getPreviousLesson(): ?self
    {
        return static::where('topic_id', $this->topic_id)
            ->where('order', '<', $this->order)
            ->orderBy('order', 'desc')
            ->first();
    }

    /**
     * Get all media collections available for lessons
     */
    public static function getMediaCollections(): array
    {
        return [
            'thumbnail'      => [
                'max_files'   => 1,
                'conversions' => [
                    'thumb'   => ['width' => 100, 'height' => 100],
                    'preview' => ['width' => 300, 'height' => 300],
                ],
            ],
            'content_images' => [
                'max_files'   => 10,
                'conversions' => [
                    'thumb'   => ['width' => 100, 'height' => 100],
                    'content' => ['width' => 800, 'height' => null],
                ],
            ],
            'audio'          => [
                'max_files'     => 5,
                'allowed_types' => ['audio/mpeg', 'audio/wav'],
            ],
        ];
    }
}
