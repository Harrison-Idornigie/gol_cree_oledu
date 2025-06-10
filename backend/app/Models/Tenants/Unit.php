<?php
namespace App\Models\Tenants;

use App\Models\Tenants\Review;
use App\Traits\Tenant\HasAuditLog;
use App\Traits\Tenant\HasMedia;
use App\Traits\Tenant\HasVersions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Unit extends Model
{
    use HasFactory, HasVersions, HasAuditLog, HasMedia, BelongsToTenant;

    const AUDIT_AREA = 'units';

    protected $fillable = [
        'learning_path_id',
        'title',
        'description',
        'order',
        'status',
        'review_status',
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
     * Get the learning path that owns the unit.
     */
    public function learningPath(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class);
    }

    /**
     * Get the topics for the unit.
     */
    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class)->orderBy('order');
    }

    /**
     * Get the lessons for the unit through topics.
     */
    public function lessons()
    {
        return Lesson::whereHas('topic', function ($query) {
            $query->where('unit_id', $this->id);
        })->orderBy('order');
    }

    /**
     * Get the guide book entries for the unit.
     */
    public function guideBookEntries(): HasMany
    {
        return $this->hasMany(GuideBookEntry::class);
    }

    /**
     * Get all progress records for this unit.
     */
    public function progress(): MorphMany
    {
        return $this->morphMany(UserProgress::class, 'trackable');
    }

    /**
     * Get all reviews for this unit.
     */
    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable', 'content_type', 'content_id');
    }

    /**
     * Get the completion percentage for a specific user
     */
    public function getCompletionPercentage(int $userId): float
    {
        $topics = $this->topics()->with(['lessons.progress' => function ($query) use ($userId) {
            $query->where('user_id', $userId);
        }])->get();

        if ($topics->isEmpty()) {
            return 0;
        }

        $totalLessons     = 0;
        $completedLessons = 0;

        foreach ($topics as $topic) {
            foreach ($topic->lessons as $lesson) {
                $totalLessons++;
                if ($lesson->progress->contains('status', 'completed')) {
                    $completedLessons++;
                }
            }
        }

        if ($totalLessons === 0) {
            return 0;
        }

        return ($completedLessons / $totalLessons) * 100;
    }

    /**
     * Get content preview data
     */
    public function getPreviewData(): array
    {
        return [
            'id'            => $this->id,
            'title'         => $this->title,
            'description'   => $this->description,
            'order'         => $this->order,
            'topics_count'  => $this->topics()->count(),
            'lessons_count' => $this->lessons()->count(),
            'has_guide'     => $this->guideBookEntries()->exists(),
            'thumbnail'     => collect($this->getMedia('thumbnail'))->first()?->getUrl(),
            'learning_path' => [
                'id'    => $this->learningPath->id,
                'title' => $this->learningPath->title,
            ],
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
            'title'              => $this->title,
            'description'        => $this->description,
            'order'              => $this->order,
            'topics'             => $this->topics->map->getExportData()->toArray(),
            'guide_book_entries' => $this->guideBookEntries->map->getExportData()->toArray(),
            'media'              => $this->media->groupBy('collection_name')->toArray(),
        ];
    }

    /**
     * Import data from an export structure
     */
    public static function importData(array $data, LearningPath $learningPath): self
    {
        $unit = static::create([
            'learning_path_id' => $learningPath->id,
            'title'            => $data['title'],
            'description'      => $data['description'],
            'order'            => $data['order'],
        ]);

        foreach ($data['topics'] ?? [] as $topicData) {
            $topic = Topic::create([
                'unit_id'     => $unit->id,
                'title'       => $topicData['title'],
                'description' => $topicData['description'] ?? '',
                'order'       => $topicData['order'] ?? 0,
                'icon'        => $topicData['icon'] ?? null,
                'color'       => $topicData['color'] ?? null,
                'status'      => $topicData['status'] ?? 'draft',
            ]);

            foreach ($topicData['lessons'] ?? [] as $lessonData) {
                Lesson::importData($lessonData, $topic);
            }
        }

        foreach ($data['guide_book_entries'] ?? [] as $entryData) {
            GuideBookEntry::create([
                'unit_id' => $unit->id,
                'topic'   => $entryData['topic'],
                'content' => $entryData['content'],
            ]);
        }

        return $unit;
    }

    /**
     * Get the next unit in the learning path
     */
    public function getNextUnit(): ?self
    {
        return static::where('learning_path_id', $this->learning_path_id)
            ->where('order', '>', $this->order)
            ->orderBy('order')
            ->first();
    }

    /**
     * Get the previous unit in the learning path
     */
    public function getPreviousUnit(): ?self
    {
        return static::where('learning_path_id', $this->learning_path_id)
            ->where('order', '<', $this->order)
            ->orderBy('order', 'desc')
            ->first();
    }
}
