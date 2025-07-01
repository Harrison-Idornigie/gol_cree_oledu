<?php

namespace App\Models\Tenants;

use App\Traits\Tenant\HasAuditLog;
use App\Traits\Tenant\HasMedia;
use App\Traits\Tenant\HasVersions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class GuideBookEntry extends Model
{
    use HasFactory, HasVersions, HasAuditLog, HasMedia, BelongsToTenant;

    const AUDIT_AREA = 'guide_book';

    protected $fillable = [
        'title',
        'slug',
        'content',
        'description',
        'lesson_id',
        'unit_id',
        'language_id',
        'words_introduced',
        'words_reused',
        'word_count',
        'difficulty_level',
        'tags',
        'references',
        'order',
        'category',
        'status',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'words_introduced' => 'array',
        'words_reused' => 'array', 
        'word_count' => 'integer',
        'difficulty_level' => 'integer',
        'tags' => 'array',
        'references' => 'array',
        'order' => 'integer'
    ];

    /**
     * The attributes that should be version controlled.
     */
    protected array $versionedAttributes = [
        'title',
        'content', 
        'words_introduced',
        'words_reused',
        'difficulty_level',
        'tags',
        'references'
    ];

    /**
     * Get the lesson that owns the guide book entry.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Get the unit that owns the guide book entry.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the language that owns the guide book entry.
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * Generate the entry slug when creating or updating
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($entry) {
            if (empty($entry->slug)) {
                $entry->slug = Str::slug($entry->title);
            }
            
            // Auto-calculate word count
            $entry->word_count = count($entry->words_introduced ?? []) + count($entry->words_reused ?? []);
        });
    }

    /**
     * Get all words in this lesson (new + reused)
     */
    public function getAllWords(): array
    {
        return array_merge(
            $this->words_introduced ?? [],
            $this->words_reused ?? []
        );
    }

    /**
     * Get word by ID from predefined words table
     */
    public function getWordDetails(int $wordId): ?Word
    {
        return Word::find($wordId);
    }

    /**
     * Get detailed word information for this lesson
     */
    public function getWordsWithDetails(): array
    {
        $newWords = collect($this->words_introduced ?? [])
            ->map(fn($wordId) => $this->getWordDetails($wordId))
            ->filter()
            ->map(fn($word) => array_merge($word->toArray(), ['is_new' => true]));

        $reusedWords = collect($this->words_reused ?? [])
            ->map(fn($wordId) => $this->getWordDetails($wordId))
            ->filter()
            ->map(fn($word) => array_merge($word->toArray(), ['is_new' => false]));

        return $newWords->concat($reusedWords)->toArray();
    }

    /**
     * Check if student has learned all words in this lesson
     */
    public function isCompletedByStudent(User $student): bool
    {
        $allWordIds = $this->getAllWords();
        
        // Check if student has progress records for all words
        $learnedWordIds = UserProgress::where('user_id', $student->id)
            ->where('trackable_type', Word::class)
            ->whereIn('trackable_id', $allWordIds)
            ->where('status', '>=', UserProgress::STATUS_PRACTICING)
            ->pluck('trackable_id')
            ->toArray();

        return count($learnedWordIds) === count($allWordIds);
    }

    /**
     * Get student's word progress for this lesson
     */
    public function getStudentWordProgress(User $student): array
    {
        $allWordIds = $this->getAllWords();
        
        $progress = UserProgress::where('user_id', $student->id)
            ->where('trackable_type', Word::class)
            ->whereIn('trackable_id', $allWordIds)
            ->get()
            ->keyBy('trackable_id');

        return collect($allWordIds)->map(function ($wordId) use ($progress) {
            $wordProgress = $progress->get($wordId);
            $word = $this->getWordDetails($wordId);
            
            return [
                'word_id' => $wordId,
                'word' => $word?->word,
                'translation' => $word?->translation,
                'status' => $wordProgress?->status ?? 'not_started',
                'strength' => $wordProgress?->strength ?? 0,
                'last_practiced' => $wordProgress?->updated_at,
                'mistakes' => $wordProgress?->mistake_count ?? 0,
                'streak' => $wordProgress?->streak ?? 0,
            ];
        })->toArray();
    }

    /**
     * Get related guide book entries based on tags
     */
    public function getRelatedEntries(int $limit = 3): array
    {
        if (empty($this->tags)) {
            return [];
        }

        return static::where('id', '!=', $this->id)
            ->where(function ($query) {
                foreach ($this->tags as $tag) {
                    $query->orWhereJsonContains('tags', $tag);
                }
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'topic' => $entry->topic,
                    'slug' => $entry->slug,
                    'difficulty_level' => $entry->difficulty_level
                ];
            })
            ->toArray();
    }

    /**
     * Get the export data structure
     */
    public function getExportData(): array
    {
        return [
            'topic' => $this->topic,
            'content' => $this->content,
            'difficulty_level' => $this->difficulty_level,
            'tags' => $this->tags,
            'references' => $this->references,
            'order' => $this->order,
            'media' => $this->media->groupBy('collection_name')->toArray()
        ];
    }

    /**
     * Search guide book entries
     */
    public static function search(string $query, array $filters = []): array
    {
        $entries = static::query();

        // Apply search query
        if ($query) {
            $entries->where(function ($q) use ($query) {
                $q->where('topic', 'like', "%{$query}%")
                    ->orWhere('content', 'like', "%{$query}%")
                    ->orWhereJsonContains('tags', $query);
            });
        }

        // Apply filters
        if (!empty($filters['difficulty_level'])) {
            $entries->where('difficulty_level', $filters['difficulty_level']);
        }

        if (!empty($filters['tags'])) {
            foreach ($filters['tags'] as $tag) {
                $entries->whereJsonContains('tags', $tag);
            }
        }

        if (!empty($filters['unit_id'])) {
            $entries->where('unit_id', $filters['unit_id']);
        }

        return $entries->orderBy('order')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'topic' => $entry->topic,
                    'slug' => $entry->slug,
                    'excerpt' => Str::limit(strip_tags($entry->content), 200),
                    'difficulty_level' => $entry->difficulty_level,
                    'tags' => $entry->tags,
                    'unit' => [
                        'id' => $entry->unit->id,
                        'title' => $entry->unit->title
                    ]
                ];
            })
            ->toArray();
    }

    /**
     * Get all media collections available for guide book entries
     */
    public static function getMediaCollections(): array
    {
        return [
            'content_images' => [
                'max_files' => 10,
                'conversions' => [
                    'thumb' => ['width' => 100, 'height' => 100],
                    'content' => ['width' => 800, 'height' => null]
                ]
            ],
            'diagrams' => [
                'max_files' => 5,
                'conversions' => [
                    'thumb' => ['width' => 100, 'height' => 100],
                    'display' => ['width' => 1200, 'height' => null]
                ]
            ],
            'attachments' => [
                'max_files' => 5,
                'allowed_types' => [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                ]
            ]
        ];
    }
}
