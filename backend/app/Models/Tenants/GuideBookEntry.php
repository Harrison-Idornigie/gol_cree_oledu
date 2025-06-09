<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use App\Models\Traits\HasMedia;
use App\Models\Traits\HasVersions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GuideBookEntry extends Model
{
    use HasFactory, HasVersions, HasAuditLog, HasMedia;

    const AUDIT_AREA = 'guide_book';

    protected $fillable = [
        'unit_id',
        'topic',
        'content',
        'difficulty_level',
        'tags',
        'references',
        'order'
    ];

    protected $casts = [
        'difficulty_level' => 'integer',
        'tags' => 'array',
        'references' => 'array',
        'order' => 'integer'
    ];

    /**
     * The attributes that should be version controlled.
     */
    protected array $versionedAttributes = [
        'topic',
        'content',
        'difficulty_level',
        'tags',
        'references'
    ];

    /**
     * Get the unit that owns the guide book entry.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Generate the entry slug when creating or updating
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($entry) {
            $entry->slug = Str::slug($entry->topic);
        });
    }

    /**
     * Get the entry's full content with all related materials
     */
    public function getFullContent(): array
    {
        return [
            'id' => $this->id,
            'topic' => $this->topic,
            'slug' => $this->slug,
            'content' => $this->content,
            'difficulty_level' => $this->difficulty_level,
            'tags' => $this->tags,
            'references' => $this->references,
            'images' => $this->getMedia('content_images')->map->getUrl(),
            'diagrams' => $this->getMedia('diagrams')->map->getUrl(),
            'attachments' => $this->getMedia('attachments')->map(function ($media) {
                return [
                    'name' => $media->file_name,
                    'url' => $media->getUrl(),
                    'size' => $media->getHumanReadableSize(),
                    'type' => $media->mime_type
                ];
            }),
            'related_entries' => $this->getRelatedEntries(),
            'unit' => [
                'id' => $this->unit->id,
                'title' => $this->unit->title
            ]
        ];
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