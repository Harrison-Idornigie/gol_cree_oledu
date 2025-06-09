<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use App\Models\Traits\HasMedia;
use App\Models\Traits\HasVersions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VocabularyItem extends Model
{
    use HasFactory, HasVersions, HasAuditLog, HasMedia;

    const AUDIT_AREA = 'vocabulary';

    protected $fillable = [
        'lesson_id',
        'word',
        'translation',
        'example',
        'phonetic',
        'part_of_speech',
        'difficulty_level'
    ];

    protected $casts = [
        'difficulty_level' => 'integer'
    ];

    /**
     * The attributes that should be version controlled.
     */
    protected array $versionedAttributes = [
        'word',
        'translation',
        'example',
        'phonetic',
        'part_of_speech'
    ];

    /**
     * Get the lesson that owns the vocabulary item.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Generate phonetic transcription if not provided
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            if (empty($item->phonetic)) {
                $item->phonetic = $item->generatePhonetic();
            }

            if (empty($item->difficulty_level)) {
                $item->difficulty_level = $item->calculateDifficulty();
            }
        });
    }

    /**
     * Generate a simple phonetic transcription
     * Note: In a real application, you would use a proper phonetic library
     */
    protected function generatePhonetic(): string
    {
        // This is a very simplified example
        $phonetic = Str::lower($this->word);
        $phonetic = preg_replace('/[aeiou]+/', 'ə', $phonetic);
        return "/$phonetic/";
    }

    /**
     * Calculate the difficulty level of the word
     */
    protected function calculateDifficulty(): int
    {
        $factors = [
            strlen($this->word), // Length
            count(preg_split('/[aeiou]+/i', $this->word)) - 1, // Syllables (approximate)
            strlen(preg_replace('/[a-zA-Z]/', '', $this->word)), // Special characters
        ];

        $score = array_sum($factors);

        // Convert score to 1-5 scale
        return min(5, max(1, ceil($score / 3)));
    }

    /**
     * Get the export data structure
     */
    public function getExportData(): array
    {
        return [
            'word' => $this->word,
            'translation' => $this->translation,
            'example' => $this->example,
            'phonetic' => $this->phonetic,
            'part_of_speech' => $this->part_of_speech,
            'difficulty_level' => $this->difficulty_level,
            'media' => $this->media->groupBy('collection_name')->toArray(),
        ];
    }

    /**
     * Check if the translation is correct
     */
    public function checkTranslation(string $translation): bool
    {
        return Str::lower(trim($translation)) === Str::lower(trim($this->translation));
    }

    /**
     * Get similar words based on difficulty level
     */
    public function getSimilarWords(int $limit = 5): array
    {
        return static::where('difficulty_level', $this->difficulty_level)
            ->where('id', '!=', $this->id)
            ->where('part_of_speech', $this->part_of_speech)
            ->inRandomOrder()
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get all media collections available for vocabulary items
     */
    public static function getMediaCollections(): array
    {
        return [
            'pronunciation' => [
                'max_files' => 1,
                'allowed_types' => ['audio/mpeg', 'audio/wav']
            ],
            'illustrations' => [
                'max_files' => 1,
                'conversions' => [
                    'thumb' => ['width' => 100, 'height' => 100],
                    'display' => ['width' => 400, 'height' => null]
                ]
            ]
        ];
    }

    /**
     * Get vocabulary item with usage examples
     */
    public function getWithExamples(): array
    {
        return [
            'id' => $this->id,
            'word' => $this->word,
            'translation' => $this->translation,
            'phonetic' => $this->phonetic,
            'part_of_speech' => $this->part_of_speech,
            'example' => $this->example,
            'difficulty_level' => $this->difficulty_level,
            'pronunciation_url' => $this->getMedia('pronunciation')->first()?->getUrl(),
            'illustration_url' => $this->getMedia('illustrations')->first()?->getUrl(),
            'similar_words' => $this->getSimilarWords(3)
        ];
    }
}