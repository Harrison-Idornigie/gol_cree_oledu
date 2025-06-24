<?php

namespace App\Services\Tenants\Language;

use App\Models\Tenants\Sentence;
use App\Models\Tenants\SentenceTranslation;
use App\Models\Tenants\Word;
use App\Models\Tenants\ExceptionWord;
use App\Models\Tenants\Language;
use App\Services\Tenants\Media\AudioProcessingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Exception;

/**
 * Sentence Management Service
 * 
 * Handles comprehensive sentence creation, validation, and management
 * with strict word constraint enforcement and exception handling.
 */
class SentenceManagementService
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
     * Get paginated sentences with filters and search.
     */
    public function getSentences(array $filters = [], array $sorts = [], int $perPage = 15): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = Sentence::with(['language', 'translations.language', 'words', 'media'])
            ->withCount(['words', 'translations']);

        // Apply search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('text', 'LIKE', "%{$search}%")
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

        if (!empty($filters['has_audio'])) {
            if ($filters['has_audio']) {
                $query->whereHas('media');
            } else {
                $query->whereDoesntHave('media');
            }
        }

        // Apply sorting
        foreach ($sorts as $sort) {
            $query->orderBy($sort['field'], $sort['direction']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a new sentence with word validation and relationships.
     */
    public function createSentence(array $sentenceData, array $wordData = [], ?UploadedFile $audioFile = null, ?UploadedFile $slowAudioFile = null): Sentence
    {
        return DB::transaction(function () use ($sentenceData, $wordData, $audioFile, $slowAudioFile) {
            // Validate word constraints
            $this->validateSentenceWords($sentenceData['text'], $wordData, $sentenceData['language_id']);

            // Create the sentence
            $sentence = Sentence::create([
                'language_id' => $sentenceData['language_id'],
                'text' => $sentenceData['text'],
                'pronunciation_key' => $sentenceData['pronunciation_key'] ?? null,
                'metadata' => $sentenceData['metadata'] ?? [],
                'tenant_id' => tenant('id')
            ]);

            // Attach words with positions and timing
            if (!empty($wordData)) {
                $this->attachWordsToSentence($sentence, $wordData);
            }

            // Handle audio files
            if ($audioFile) {
                $this->audioService->processSentenceAudio($sentence, $audioFile, false);
            }

            if ($slowAudioFile) {
                $this->audioService->processSentenceAudio($sentence, $slowAudioFile, true);
            }

            return $sentence->load(['language', 'words', 'media']);
        });
    }

    /**
     * Validate that all words in a sentence are either managed words or valid exceptions.
     */
    public function validateSentenceWords(string $sentenceText, array $wordData, int $languageId): array
    {
        $validation = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'suggestions' => []
        ];

        // Extract word IDs from word data
        $wordIds = collect($wordData)->pluck('word_id')->filter()->toArray();

        // Get managed words
        $managedWords = Word::whereIn('id', $wordIds)
            ->where('language_id', $languageId)
            ->get()
            ->keyBy('id');

        // Check for missing words
        $missingWordIds = array_diff($wordIds, $managedWords->keys()->toArray());
        if (!empty($missingWordIds)) {
            $validation['valid'] = false;
            $validation['errors'][] = "Words with IDs [" . implode(', ', $missingWordIds) . "] are not registered in the word database.";
        }

        // Parse sentence text and check for unregistered words
        $sentenceWords = $this->parseSentenceWords($sentenceText);
        $managedWordTexts = $managedWords->pluck('text')->map('strtolower')->toArray();

        // Get exception words for this language
        $exceptionWords = ExceptionWord::where('language_id', $languageId)
            ->active()
            ->pluck('text')
            ->map('strtolower')
            ->toArray();

        foreach ($sentenceWords as $word) {
            $wordLower = strtolower($word);

            if (!in_array($wordLower, $managedWordTexts) && !in_array($wordLower, $exceptionWords)) {
                $validation['valid'] = false;
                $validation['errors'][] = "Word '{$word}' is not registered as a managed word or exception.";

                // Suggest similar words
                $suggestions = $this->findSimilarWords($word, $languageId);
                if (!empty($suggestions)) {
                    $validation['suggestions'][] = "Did you mean: " . implode(', ', $suggestions);
                }
            }
        }

        return $validation;
    }

    /**
     * Get available words for sentence creation with autocomplete support.
     */
    public function getAvailableWordsForSentence(int $languageId, string $search = '', int $limit = 50): Collection
    {
        $query = Word::where('language_id', $languageId)
            ->with(['translations.language'])
            ->limit($limit);

        if (!empty($search)) {
            $query->where('text', 'LIKE', "%{$search}%");
        }

        $words = $query->get();

        // Include exception words as well
        $exceptionWords = ExceptionWord::where('language_id', $languageId)
            ->when(!empty($search), function ($q) use ($search) {
                $q->where('text', 'LIKE', "%{$search}%");
            })
            ->limit($limit)
            ->get()
            ->map(function ($exception) {
                return (object) [
                    'id' => $exception->id,
                    'text' => $exception->text,
                    'type' => 'exception',
                    'exception_type' => $exception->exception_type,
                    'description' => $exception->description,
                    'translations' => collect()
                ];
            });

        return $words->concat($exceptionWords);
    }

    /**
     * Parse sentence text into individual words.
     */
    protected function parseSentenceWords(string $text): array
    {
        // Remove punctuation and split by whitespace
        $cleanText = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        return array_filter(preg_split('/\s+/', trim($cleanText)));
    }

    /**
     * Find similar words for suggestions.
     */
    protected function findSimilarWords(string $word, int $languageId, int $limit = 3): array
    {
        $words = Word::where('language_id', $languageId)
            ->where('text', 'LIKE', "%{$word}%")
            ->orWhere('text', 'LIKE', "{$word}%")
            ->limit($limit)
            ->pluck('text')
            ->toArray();

        return $words;
    }

    /**
     * Attach words to sentence with position and timing data.
     */
    protected function attachWordsToSentence(Sentence $sentence, array $wordData): void
    {
        $attachData = [];

        foreach ($wordData as $data) {
            $attachData[$data['word_id']] = [
                'position' => $data['position'],
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'metadata' => $data['metadata'] ?? null
            ];
        }

        $sentence->words()->attach($attachData);
    }

    /**
     * Get detailed sentence with all relationships.
     */
    public function getDetailedSentence(int $sentenceId, string $context = 'team'): ?Sentence
    {
        $relations = [
            'language',
            'words.translations.language',
            'translations.language',
            'media'
        ];

        $sentence = Sentence::with($relations)
            ->withCount(['words', 'translations'])
            ->find($sentenceId);

        if (!$sentence) {
            return null;
        }

        // Add audio URLs
        $sentence->audio_url = $sentence->getFirstMediaUrl('audio');
        $sentence->slow_audio_url = $sentence->getFirstMediaUrl('audio_slow');

        return $sentence;
    }

    /**
     * Update an existing sentence.
     */
    public function updateSentence(Sentence $sentence, array $updateData, ?array $wordData = null): Sentence
    {
        return DB::transaction(function () use ($sentence, $updateData, $wordData) {
            // Update sentence basic data
            $sentence->update(array_filter([
                'text' => $updateData['text'] ?? null,
                'pronunciation_key' => $updateData['pronunciation_key'] ?? null,
                'metadata' => $updateData['metadata'] ?? null,
            ], function ($value) {
                return $value !== null;
            }));

            // Update word relationships if provided
            if ($wordData !== null) {
                // Detach existing words
                $sentence->words()->detach();

                // Attach new words
                $this->attachWordsToSentence($sentence, $wordData);
            }

            return $sentence->load(['language', 'words', 'translations', 'media']);
        });
    }

    /**
     * Delete a sentence.
     */
    public function deleteSentence(Sentence $sentence): bool
    {
        return DB::transaction(function () use ($sentence) {
            // Clear all media collections
            $sentence->clearMediaCollection('audio');
            $sentence->clearMediaCollection('audio_slow');

            // Delete translations (cascade will handle sentence_words)
            $sentence->translations()->delete();

            // Delete the sentence
            return $sentence->delete();
        });
    }

    /**
     * Add translation to sentence.
     */
    public function addTranslation(Sentence $sentence, array $translationData, ?UploadedFile $audioFile = null): \App\Models\Tenants\SentenceTranslation
    {
        return DB::transaction(function () use ($sentence, $translationData, $audioFile) {
            $translation = $sentence->translations()->create([
                'language_id' => $translationData['language_id'],
                'text' => $translationData['text'],
                'pronunciation_key' => $translationData['pronunciation_key'] ?? null,
                'context_notes' => $translationData['context_notes'] ?? null,
            ]);

            // Process audio if provided
            if ($audioFile) {
                $this->audioService->processTranslationAudio($translation, $audioFile);
            }

            return $translation->load(['language', 'media']);
        });
    }

    /**
     * Update sentence translation.
     */
    public function updateTranslation(\App\Models\Tenants\SentenceTranslation $translation, array $updateData, ?UploadedFile $audioFile = null): \App\Models\Tenants\SentenceTranslation
    {
        return DB::transaction(function () use ($translation, $updateData, $audioFile) {
            $translation->update(array_filter([
                'language_id' => $updateData['language_id'] ?? null,
                'text' => $updateData['text'] ?? null,
                'pronunciation_key' => $updateData['pronunciation_key'] ?? null,
                'context_notes' => $updateData['context_notes'] ?? null,
            ], function ($value) {
                return $value !== null;
            }));

            // Process audio if provided
            if ($audioFile) {
                $translation->clearMediaCollection('audio');
                $this->audioService->processTranslationAudio($translation, $audioFile);
            }

            return $translation->load(['language', 'media']);
        });
    }

    /**
     * Delete sentence translation.
     */
    public function deleteTranslation(\App\Models\Tenants\SentenceTranslation $translation): bool
    {
        return DB::transaction(function () use ($translation) {
            // Clear media
            $translation->clearMediaCollection('audio');

            // Delete the translation
            return $translation->delete();
        });
    }

    /**
     * Upload audio for sentence.
     */
    public function uploadSentenceAudio(Sentence $sentence, UploadedFile $audioFile, bool $isSlowVersion = false): array
    {
        // Clear existing audio for this type
        $collection = $isSlowVersion ? 'audio_slow' : 'audio';
        $sentence->clearMediaCollection($collection);

        // Process and store new audio
        return $this->audioService->processSentenceAudio($sentence, $audioFile, $isSlowVersion);
    }

    /**
     * Update word timings for sentence.
     */
    public function updateWordTimings(Sentence $sentence, array $timings, float $audioDuration): array
    {
        return $this->audioService->updateWordTimings($sentence, $timings, $audioDuration);
    }

    /**
     * Reorder words in sentence.
     */
    public function reorderWords(Sentence $sentence, array $wordOrder): Sentence
    {
        return DB::transaction(function () use ($sentence, $wordOrder) {
            foreach ($wordOrder as $position => $wordData) {
                $sentence->words()->updateExistingPivot($wordData['word_id'], [
                    'position' => $position + 1, // 1-based position
                    'start_time' => $wordData['start_time'] ?? null,
                    'end_time' => $wordData['end_time'] ?? null,
                    'metadata' => $wordData['metadata'] ?? null,
                ]);
            }

            return $sentence->load(['words']);
        });
    }
}
