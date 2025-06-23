<?php

namespace App\Services\Tenants\Language;

use App\Models\Tenants\Word;
use App\Models\Tenants\WordTranslation;
use App\Models\Tenants\Language;
use App\Services\Tenants\Media\AudioProcessingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Exception;

/**
 * Word Management Service
 * 
 * Handles all word and translation management operations including
 * creation, updates, audio processing, and bulk operations.
 */
class WordManagementService
{
    protected WordConstraintService $constraintService;
    protected AudioProcessingService $audioService;

    public function __construct(
        WordConstraintService $constraintService,
        AudioProcessingService $audioService
    ) {
        $this->constraintService = $constraintService;
        $this->audioService = $audioService;
    }

    /**
     * Get paginated words with filters and search.
     */
    public function getWords(array $filters = [], array $sorts = [], int $perPage = 15): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = Word::with(['language', 'translations.language', 'media'])
            ->withCount('translations');

        // Apply search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('text', 'LIKE', "%{$search}%")
                    ->orWhere('pronunciation_key', 'LIKE', "%{$search}%")
                    ->orWhereHas('translations', function ($tq) use ($search) {
                        $tq->where('text', 'LIKE', "%{$search}%");
                    });
            });
        }

        // Apply filters
        if (!empty($filters['language_id'])) {
            $query->where('language_id', $filters['language_id']);
        }

        if (!empty($filters['difficulty'])) {
            $query->whereJsonContains('metadata->difficulty', $filters['difficulty']);
        }

        if (!empty($filters['tags'])) {
            $tags = is_array($filters['tags']) ? $filters['tags'] : explode(',', $filters['tags']);
            $query->where(function ($q) use ($tags) {
                foreach ($tags as $tag) {
                    $q->orWhereJsonContains('metadata->tags', trim($tag));
                }
            });
        }

        if (!empty($filters['part_of_speech'])) {
            $query->where('part_of_speech', $filters['part_of_speech']);
        }

        // Audio filter
        if (isset($filters['has_audio'])) {
            if ($filters['has_audio'] === true || $filters['has_audio'] === 'true') {
                $query->whereHas('media', function ($q) {
                    $q->where('collection_name', 'pronunciation');
                });
            } elseif ($filters['has_audio'] === false || $filters['has_audio'] === 'false') {
                $query->whereDoesntHave('media', function ($q) {
                    $q->where('collection_name', 'pronunciation');
                });
            }
        }

        // Apply sorting
        $sortBy = $sorts['sort_by'] ?? 'text';
        $sortOrder = $sorts['sort_order'] ?? 'asc';

        switch ($sortBy) {
            case 'created_at':
                $query->orderBy('created_at', $sortOrder);
                break;
            case 'translations_count':
                $query->orderBy('translations_count', $sortOrder);
                break;
            case 'language':
                $query->join('languages', 'words.language_id', '=', 'languages.id')
                    ->orderBy('languages.name', $sortOrder)
                    ->select('words.*');
                break;
            default:
                $query->orderBy('text', $sortOrder);
        }

        return $query->paginate(min($perPage, 100));
    }

    /**
     * Create a new word with translations and audio.
     */
    public function createWord(array $wordData, ?UploadedFile $audioFile = null, array $translations = []): Word
    {
        return DB::transaction(function () use ($wordData, $audioFile, $translations) {
            // Create the word
            $word = Word::create([
                'language_id' => $wordData['language_id'],
                'text' => $wordData['text'],
                'pronunciation_key' => $wordData['pronunciation_key'] ?? null,
                'part_of_speech' => $wordData['part_of_speech'] ?? null,
                'metadata' => $wordData['metadata'] ?? [],
                'tenant_id' => tenant('id')
            ]);

            // Handle pronunciation audio
            if ($audioFile) {
                $this->audioService->processWordAudio($word, $audioFile);
            }

            // Create translations
            if (!empty($translations)) {
                $this->createTranslations($word, $translations);
            }

            return $word->load(['language', 'translations.language', 'media']);
        });
    }

    /**
     * Update an existing word.
     */
    public function updateWord(Word $word, array $updateData, ?UploadedFile $audioFile = null, array $translations = []): Word
    {
        return DB::transaction(function () use ($word, $updateData, $audioFile, $translations) {
            // Update word properties
            $word->update(array_filter([
                'language_id' => $updateData['language_id'] ?? null,
                'text' => $updateData['text'] ?? null,
                'pronunciation_key' => $updateData['pronunciation_key'] ?? null,
                'part_of_speech' => $updateData['part_of_speech'] ?? null,
                'metadata' => $updateData['metadata'] ?? null,
            ], function ($value) {
                return $value !== null;
            }));

            // Handle pronunciation audio
            if ($audioFile) {
                // Clear existing audio
                $word->clearMediaCollection('pronunciation');
                $this->audioService->processWordAudio($word, $audioFile);
            }

            // Update translations if provided
            if (!empty($translations)) {
                $this->updateTranslations($word, $translations);
            }

            return $word->load(['language', 'translations.language', 'media']);
        });
    }

    /**
     * Delete a word and all associated data.
     */
    public function deleteWord(Word $word): bool
    {
        return DB::transaction(function () use ($word) {
            // Check for usage in exercises/content
            $usageCheck = $this->checkWordUsage($word);
            if (!$usageCheck['can_delete']) {
                throw new Exception('Word is in use and cannot be deleted: ' . implode(', ', $usageCheck['usage_locations']));
            }

            // Delete all translations and their media
            foreach ($word->translations as $translation) {
                $translation->clearMediaCollection('pronunciation');
                $translation->delete();
            }

            // Delete word media
            $word->clearMediaCollection('pronunciation');

            // Delete the word
            return $word->delete();
        });
    }

    /**
     * Create translations for a word.
     */
    public function createTranslations(Word $word, array $translations): Collection
    {
        $createdTranslations = collect();

        foreach ($translations as $index => $translationData) {
            $translation = $word->translations()->create([
                'language_id' => $translationData['language_id'],
                'text' => $translationData['text'],
                'pronunciation_key' => $translationData['pronunciation_key'] ?? null,
                'context_notes' => $translationData['context_notes'] ?? null,
                'usage_examples' => $translationData['usage_examples'] ?? null,
                'translation_order' => $translationData['translation_order'] ?? $index
            ]);

            // Handle translation audio
            if (isset($translationData['audio_file']) && $translationData['audio_file'] instanceof UploadedFile) {
                $this->audioService->processTranslationAudio($translation, $translationData['audio_file']);
            }

            $createdTranslations->push($translation);
        }

        return $createdTranslations;
    }

    /**
     * Update translations for a word.
     */
    public function updateTranslations(Word $word, array $translations): Collection
    {
        $updatedTranslations = collect();

        foreach ($translations as $translationData) {
            if (isset($translationData['id'])) {
                // Update existing translation
                $translation = $word->translations()->find($translationData['id']);
                if ($translation) {
                    $translation->update([
                        'language_id' => $translationData['language_id'] ?? $translation->language_id,
                        'text' => $translationData['text'] ?? $translation->text,
                        'pronunciation_key' => $translationData['pronunciation_key'] ?? $translation->pronunciation_key,
                        'context_notes' => $translationData['context_notes'] ?? $translation->context_notes,
                        'usage_examples' => $translationData['usage_examples'] ?? $translation->usage_examples,
                        'translation_order' => $translationData['translation_order'] ?? $translation->translation_order,
                    ]);

                    // Handle audio update
                    if (isset($translationData['audio_file']) && $translationData['audio_file'] instanceof UploadedFile) {
                        $translation->clearMediaCollection('pronunciation');
                        $this->audioService->processTranslationAudio($translation, $translationData['audio_file']);
                    }

                    $updatedTranslations->push($translation);
                }
            } else {
                // Create new translation
                $newTranslations = $this->createTranslations($word, [$translationData]);
                $updatedTranslations = $updatedTranslations->merge($newTranslations);
            }
        }

        return $updatedTranslations;
    }

    /**
     * Add a single translation to a word.
     */
    public function addTranslation(Word $word, array $translationData, ?UploadedFile $audioFile = null): WordTranslation
    {
        return DB::transaction(function () use ($word, $translationData, $audioFile) {
            $translation = $word->translations()->create([
                'language_id' => $translationData['language_id'],
                'text' => $translationData['text'],
                'pronunciation_key' => $translationData['pronunciation_key'] ?? null,
                'context_notes' => $translationData['context_notes'] ?? null,
                'usage_examples' => $translationData['usage_examples'] ?? null,
                'translation_order' => $translationData['translation_order'] ?? $word->translations()->count()
            ]);

            if ($audioFile) {
                $this->audioService->processTranslationAudio($translation, $audioFile);
            }

            return $translation->load(['language', 'media']);
        });
    }

    /**
     * Update a specific translation.
     */
    public function updateTranslation(WordTranslation $translation, array $updateData, ?UploadedFile $audioFile = null): WordTranslation
    {
        return DB::transaction(function () use ($translation, $updateData, $audioFile) {
            $translation->update(array_filter([
                'language_id' => $updateData['language_id'] ?? null,
                'text' => $updateData['text'] ?? null,
                'pronunciation_key' => $updateData['pronunciation_key'] ?? null,
                'context_notes' => $updateData['context_notes'] ?? null,
                'usage_examples' => $updateData['usage_examples'] ?? null,
                'translation_order' => $updateData['translation_order'] ?? null,
            ], function ($value) {
                return $value !== null;
            }));

            if ($audioFile) {
                $translation->clearMediaCollection('pronunciation');
                $this->audioService->processTranslationAudio($translation, $audioFile);
            }

            return $translation->load(['language', 'media']);
        });
    }

    /**
     * Delete a translation.
     */
    public function deleteTranslation(WordTranslation $translation): bool
    {
        return DB::transaction(function () use ($translation) {
            // Clear media
            $translation->clearMediaCollection('pronunciation');

            // Delete the translation
            return $translation->delete();
        });
    }

    /**
     * Upload audio for a word.
     */
    public function uploadWordAudio(Word $word, UploadedFile $audioFile): array
    {
        // Clear existing audio
        $word->clearMediaCollection('pronunciation');

        // Process and store new audio
        return $this->audioService->processWordAudio($word, $audioFile);
    }

    /**
     * Upload audio for a translation.
     */
    public function uploadTranslationAudio(WordTranslation $translation, UploadedFile $audioFile): array
    {
        // Clear existing audio
        $translation->clearMediaCollection('pronunciation');

        // Process and store new audio
        return $this->audioService->processTranslationAudio($translation, $audioFile);
    }

    /**
     * Perform bulk operations on words.
     */
    public function bulkOperation(string $operation, array $data): array
    {
        return DB::transaction(function () use ($operation, $data) {
            $results = [
                'success' => [],
                'errors' => [],
                'summary' => [
                    'total' => count($data['words'] ?? []),
                    'successful' => 0,
                    'failed' => 0
                ]
            ];

            switch ($operation) {
                case 'create':
                    return $this->bulkCreateWords($data['words'] ?? []);

                case 'update':
                    return $this->bulkUpdateWords($data['words'] ?? []);

                case 'delete':
                    return $this->bulkDeleteWords($data['words'] ?? []);

                default:
                    throw new Exception('Invalid bulk operation: ' . $operation);
            }
        });
    }

    /**
     * Bulk create words.
     */
    private function bulkCreateWords(array $wordsData): array
    {
        $results = [
            'success' => [],
            'errors' => [],
            'summary' => ['total' => count($wordsData), 'successful' => 0, 'failed' => 0]
        ];

        foreach ($wordsData as $index => $wordData) {
            try {
                $word = $this->createWord(
                    $wordData,
                    null, // Audio handled separately in bulk operations
                    $wordData['translations'] ?? []
                );

                $results['success'][] = [
                    'index' => $index,
                    'word' => $word->getPreviewData()
                ];
                $results['summary']['successful']++;
            } catch (Exception $e) {
                $results['errors'][] = [
                    'index' => $index,
                    'word_text' => $wordData['text'] ?? 'unknown',
                    'error' => $e->getMessage()
                ];
                $results['summary']['failed']++;
            }
        }

        return $results;
    }

    /**
     * Bulk update words.
     */
    private function bulkUpdateWords(array $wordsData): array
    {
        $results = [
            'success' => [],
            'errors' => [],
            'summary' => ['total' => count($wordsData), 'successful' => 0, 'failed' => 0]
        ];

        foreach ($wordsData as $index => $wordData) {
            try {
                if (!isset($wordData['id'])) {
                    throw new Exception('Word ID is required for updates');
                }

                $word = Word::findOrFail($wordData['id']);
                $updatedWord = $this->updateWord(
                    $word,
                    $wordData,
                    null, // Audio handled separately
                    $wordData['translations'] ?? []
                );

                $results['success'][] = [
                    'index' => $index,
                    'word' => $updatedWord->getPreviewData()
                ];
                $results['summary']['successful']++;
            } catch (Exception $e) {
                $results['errors'][] = [
                    'index' => $index,
                    'word_id' => $wordData['id'] ?? 'unknown',
                    'error' => $e->getMessage()
                ];
                $results['summary']['failed']++;
            }
        }

        return $results;
    }

    /**
     * Bulk delete words.
     */
    private function bulkDeleteWords(array $wordIds): array
    {
        $results = [
            'success' => [],
            'errors' => [],
            'summary' => ['total' => count($wordIds), 'successful' => 0, 'failed' => 0]
        ];

        foreach ($wordIds as $index => $wordId) {
            try {
                $word = Word::findOrFail($wordId);
                $this->deleteWord($word);

                $results['success'][] = [
                    'index' => $index,
                    'word_id' => $wordId
                ];
                $results['summary']['successful']++;
            } catch (Exception $e) {
                $results['errors'][] = [
                    'index' => $index,
                    'word_id' => $wordId,
                    'error' => $e->getMessage()
                ];
                $results['summary']['failed']++;
            }
        }

        return $results;
    }

    /**
     * Check if a word is used in exercises or other content.
     */
    private function checkWordUsage(Word $word): array
    {
        $usageLocations = [];
        $canDelete = true;

        // Check for usage in sentences
        if ($word->sentences()->exists()) {
            $usageLocations[] = 'sentences';
            $canDelete = false;
        }

        // Check for usage in exercises (through sentences or direct word usage)
        // This would need to be expanded based on your exercise structure

        return [
            'can_delete' => $canDelete,
            'usage_locations' => $usageLocations,
            'usage_count' => [
                'sentences' => $word->sentences()->count(),
                // Add other usage counts as needed
            ]
        ];
    }

    /**
     * Get detailed word data with all relationships.
     */
    public function getWordDetails(Word $word): array
    {
        $word->load([
            'language',
            'translations.language',
            'translations.media',
            'media',
            'usageExamples'
        ]);

        $wordData = $word->getPreviewData();

        // Add usage statistics
        $wordData['usage_stats'] = [
            'total_translations' => $word->translations->count(),
            'languages_available' => $word->translations->pluck('language.code')->unique()->count(),
            'has_audio' => $word->hasMedia('pronunciation'),
            'has_usage_examples' => $word->usageExamples->isNotEmpty(),
            'sentences_count' => $word->sentences()->count(),
        ];

        // Add detailed translation info
        $wordData['detailed_translations'] = $word->translations->map(function ($translation) {
            $data = $translation->getPreviewData();
            $data['has_audio'] = $translation->hasMedia('pronunciation');
            $data['audio_url'] = $translation->getPronunciationUrl();
            return $data;
        });

        // Add usage examples
        $wordData['usage_examples'] = $word->usageExamples->map(function ($example) {
            return [
                'id' => $example->id,
                'example' => $example->example,
                'translation' => $example->translation,
                'context' => $example->context,
                'type' => $example->type,
            ];
        });

        return $wordData;
    }

    /**
     * Get paginated words for students with read-only data.
     */
    public function getWordsForStudents(array $filters = [], int $perPage = 20, int $page = 1): array
    {
        $query = Word::with(['language', 'translations.language', 'media'])
            ->withCount('translations');

        // Apply search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('text', 'LIKE', "%{$search}%")
                    ->orWhere('pronunciation_key', 'LIKE', "%{$search}%")
                    ->orWhereHas('translations', function ($tq) use ($search) {
                        $tq->where('text', 'LIKE', "%{$search}%");
                    });
            });
        }

        // Apply filters
        if (!empty($filters['language_id'])) {
            $query->where('language_id', $filters['language_id']);
        }

        if (!empty($filters['part_of_speech'])) {
            $query->where('part_of_speech', $filters['part_of_speech']);
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'text';
        $sortDirection = $filters['sort_direction'] ?? 'asc';

        switch ($sortBy) {
            case 'created_at':
            case 'updated_at':
                $query->orderBy($sortBy, $sortDirection);
                break;
            default:
                $query->orderBy('text', $sortDirection);
        }

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        // Transform data for student consumption (read-only, safe data)
        $words = $paginated->getCollection()->map(function ($word) {
            return $this->transformWordForStudent($word);
        });

        return [
            'data' => $words,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ]
        ];
    }

    /**
     * Get detailed word information for students.
     */
    public function getWordDetailsForStudent(Word $word): array
    {
        $word->load([
            'language',
            'translations.language',
            'translations.media',
            'media'
        ]);

        $wordData = $this->transformWordForStudent($word, true);

        // Add detailed translations for students
        $wordData['translations'] = $word->translations->map(function ($translation) {
            return [
                'id' => $translation->id,
                'language' => [
                    'id' => $translation->language->id,
                    'code' => $translation->language->code,
                    'name' => $translation->language->name,
                ],
                'text' => $translation->text,
                'pronunciation_key' => $translation->pronunciation_key,
                'context_notes' => $translation->context_notes,
                'usage_examples' => $translation->usage_examples,
                'has_audio' => $translation->hasMedia('pronunciation'),
                'audio_url' => $translation->hasMedia('pronunciation')
                    ? $translation->getFirstMediaUrl('pronunciation')
                    : null,
            ];
        });

        return $wordData;
    }

    /**
     * Get translations for a specific word for students.
     */
    public function getWordTranslationsForStudent(
        Word $word,
        ?int $targetLanguageId = null,
        bool $includeAudio = true
    ): array {
        $query = $word->translations()->with(['language']);

        if ($includeAudio) {
            $query->with('media');
        }

        if ($targetLanguageId) {
            $query->where('language_id', $targetLanguageId);
        }

        $translations = $query->orderBy('translation_order')->get();

        return $translations->map(function ($translation) use ($includeAudio) {
            $data = [
                'id' => $translation->id,
                'language' => [
                    'id' => $translation->language->id,
                    'code' => $translation->language->code,
                    'name' => $translation->language->name,
                ],
                'text' => $translation->text,
                'pronunciation_key' => $translation->pronunciation_key,
                'context_notes' => $translation->context_notes,
                'usage_examples' => $translation->usage_examples,
            ];

            if ($includeAudio) {
                $data['has_audio'] = $translation->hasMedia('pronunciation');
                $data['audio_url'] = $translation->hasMedia('pronunciation')
                    ? $translation->getFirstMediaUrl('pronunciation')
                    : null;
            }

            return $data;
        })->toArray();
    }

    /**
     * Get multiple words in batch for students.
     */
    public function getBatchWordsForStudent(
        array $wordIds,
        bool $includeTranslations = true,
        bool $includeAudio = true,
        ?int $targetLanguageId = null
    ): array {
        $query = Word::with(['language']);

        if ($includeAudio) {
            $query->with('media');
        }

        if ($includeTranslations) {
            $query->with(['translations' => function ($q) use ($targetLanguageId, $includeAudio) {
                $q->with('language');
                if ($includeAudio) {
                    $q->with('media');
                }
                if ($targetLanguageId) {
                    $q->where('language_id', $targetLanguageId);
                }
                $q->orderBy('translation_order');
            }]);
        }

        $words = $query->whereIn('id', $wordIds)
            ->where('tenant_id', tenant('id'))
            ->get();

        return $words->map(function ($word) use ($includeTranslations, $includeAudio) {
            $data = $this->transformWordForStudent($word, false);

            if ($includeTranslations && $word->relationLoaded('translations')) {
                $data['translations'] = $word->translations->map(function ($translation) use ($includeAudio) {
                    $translationData = [
                        'id' => $translation->id,
                        'language' => [
                            'id' => $translation->language->id,
                            'code' => $translation->language->code,
                            'name' => $translation->language->name,
                        ],
                        'text' => $translation->text,
                        'pronunciation_key' => $translation->pronunciation_key,
                        'context_notes' => $translation->context_notes,
                    ];

                    if ($includeAudio) {
                        $translationData['has_audio'] = $translation->hasMedia('pronunciation');
                        $translationData['audio_url'] = $translation->hasMedia('pronunciation')
                            ? $translation->getFirstMediaUrl('pronunciation')
                            : null;
                    }

                    return $translationData;
                });
            }

            return $data;
        })->toArray();
    }

    /**
     * Transform word data for student consumption (safe, read-only data).
     */
    private function transformWordForStudent(Word $word, bool $includeExtendedInfo = false): array
    {
        $data = [
            'id' => $word->id,
            'text' => $word->text,
            'pronunciation_key' => $word->pronunciation_key,
            'part_of_speech' => $word->part_of_speech,
            'language' => [
                'id' => $word->language->id,
                'code' => $word->language->code,
                'name' => $word->language->name,
            ],
            'has_audio' => $word->hasMedia('pronunciation'),
            'audio_url' => $word->hasMedia('pronunciation')
                ? $word->getFirstMediaUrl('pronunciation')
                : null,
        ];

        if ($includeExtendedInfo) {
            $data['translations_count'] = $word->translations_count ?? $word->translations->count();
            $data['created_at'] = $word->created_at->toISOString();
            $data['updated_at'] = $word->updated_at->toISOString();
        }

        return $data;
    }

    /**
     * Get interactive word data for frontend clicking.
     * Returns target language audio + source language explanation.
     * This is the core method for Duolingo-style clickable words.
     */
    public function getInteractiveWordData(Word $word, int $sourceLanguageId, ?string $proficiencyLevel = 'beginner'): array
    {
        // Get explanation in appropriate language based on proficiency
        $explanationLanguageId = $this->getExplanationLanguageId($sourceLanguageId, $word->language_id, $proficiencyLevel);

        $explanation = $word->translations()
            ->where('language_id', $explanationLanguageId)
            ->with('language')
            ->first();

        return [
            'word_id' => $word->id,
            'target_word' => [
                'text' => $word->text,
                'language_id' => $word->language_id,
                'language_code' => $word->language->code,
                'pronunciation_key' => $word->pronunciation_key,
                'audio_url' => $word->hasMedia('pronunciation')
                    ? $word->getFirstMediaUrl('pronunciation')
                    : null,
                'part_of_speech' => $word->part_of_speech,
            ],
            'explanation' => $explanation ? [
                'text' => $explanation->text,
                'language_id' => $explanation->language_id,
                'language_code' => $explanation->language->code,
                'context_notes' => $explanation->context_notes,
                'usage_examples' => $explanation->usage_examples,
            ] : null,
            'proficiency_context' => $proficiencyLevel,
            'explanation_language' => $explanationLanguageId === $sourceLanguageId ? 'source' : 'target'
        ];
    }

    /**
     * Get interactive data for multiple words (for sentence highlighting).
     */
    public function getBatchInteractiveWordData(array $wordIds, int $sourceLanguageId, ?string $proficiencyLevel = 'beginner'): array
    {
        $words = Word::with(['language', 'translations.language', 'media'])
            ->whereIn('id', $wordIds)
            ->get();

        $result = [];
        foreach ($words as $word) {
            $result[$word->id] = $this->getInteractiveWordData($word, $sourceLanguageId, $proficiencyLevel);
        }

        return $result;
    }

    /**
     * Determine explanation language based on proficiency level.
     */
    private function getExplanationLanguageId(int $sourceLanguageId, int $targetLanguageId, string $proficiencyLevel): int
    {
        // Beginners get explanations in source language
        // Intermediate/Advanced can get explanations in target language
        return match ($proficiencyLevel) {
            'beginner' => $sourceLanguageId,
            'intermediate', 'advanced' => $targetLanguageId,
            default => $sourceLanguageId
        };
    }

    /**
     * Get words for building interactive sentences.
     * Only returns words that have proper translations and audio.
     */
    public function getWordsForInteractiveSentences(
        int $targetLanguageId,
        int $sourceLanguageId,
        array $filters = []
    ): Collection {
        $query = Word::where('language_id', $targetLanguageId)
            ->whereHas('translations', function ($q) use ($sourceLanguageId) {
                $q->where('language_id', $sourceLanguageId);
            })
            ->whereHas('media', function ($q) {
                $q->where('collection_name', 'pronunciation');
            })
            ->with(['language', 'translations' => function ($q) use ($sourceLanguageId) {
                $q->where('language_id', $sourceLanguageId)->with('language');
            }, 'media']);

        // Apply filters
        if (!empty($filters['part_of_speech'])) {
            $query->where('part_of_speech', $filters['part_of_speech']);
        }

        if (!empty($filters['difficulty_level'])) {
            // Assuming difficulty is stored in metadata
            $query->whereJsonContains('metadata->difficulty_level', $filters['difficulty_level']);
        }

        return $query->orderBy('text')->get();
    }
}
