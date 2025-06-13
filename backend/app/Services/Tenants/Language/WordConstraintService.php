<?php

namespace App\Services\Tenants\Language;

use App\Models\Tenants\Word;
use App\Models\Tenants\Language;
use App\Models\Tenants\Exercise;
use App\Models\Tenants\Lesson;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Word Constraint Service
 * 
 * Handles validation that exercises/lessons only use managed words
 * and provides methods to get available words for content builders.
 */
class WordConstraintService
{
    /**
     * Get available words for lesson/exercise builders.
     */
    public function getAvailableWords(
        ?int $sourceLanguageId = null,
        ?int $targetLanguageId = null,
        ?string $exerciseType = null,
        ?string $difficulty = null,
        ?array $tags = null,
        int $limit = 100
    ): Collection {
        $query = Word::with(['language', 'translations.language'])
            ->whereHas('language', function ($q) {
                $q->where('is_active', true);
            });

        // Filter by source language
        if ($sourceLanguageId) {
            $query->where('language_id', $sourceLanguageId);
        }

        // Filter by target language (through translations)
        if ($targetLanguageId) {
            $query->whereHas('translations', function ($q) use ($targetLanguageId) {
                $q->where('language_id', $targetLanguageId)
                  ->whereHas('language', function ($langQuery) {
                      $langQuery->where('is_active', true);
                  });
            });
        }

        // Filter by difficulty
        if ($difficulty) {
            $query->whereJsonContains('metadata->difficulty', $difficulty);
        }

        // Filter by tags
        if ($tags && is_array($tags)) {
            $query->where(function ($q) use ($tags) {
                foreach ($tags as $tag) {
                    $q->orWhereJsonContains('metadata->tags', $tag);
                }
            });
        }

        // Exercise type specific filtering
        if ($exerciseType) {
            $query = $this->applyExerciseTypeFilters($query, $exerciseType);
        }

        // Ensure words have required audio for certain exercise types
        if (in_array($exerciseType, ['listening', 'speaking', 'pronunciation'])) {
            $query->whereHas('media', function ($q) {
                $q->where('collection_name', 'pronunciation');
            });
        }

        return $query->orderBy('text')
            ->limit($limit)
            ->get();
    }

    /**
     * Apply exercise type specific filters.
     */
    private function applyExerciseTypeFilters($query, string $exerciseType)
    {
        switch ($exerciseType) {
            case 'listening':
            case 'speaking':
                // Require audio pronunciation
                $query->whereHas('media', function ($q) {
                    $q->where('collection_name', 'pronunciation');
                });
                break;

            case 'beginner_friendly':
                // Only beginner and intermediate words
                $query->where(function ($q) {
                    $q->whereJsonContains('metadata->difficulty', 'beginner')
                      ->orWhereJsonContains('metadata->difficulty', 'intermediate')
                      ->orWhereNull('metadata->difficulty');
                });
                break;

            case 'advanced':
                // Only advanced words
                $query->whereJsonContains('metadata->difficulty', 'advanced');
                break;

            case 'common_words':
                // Words tagged as common or high frequency
                $query->where(function ($q) {
                    $q->whereJsonContains('metadata->tags', 'common')
                      ->orWhereJsonContains('metadata->tags', 'high-frequency')
                      ->orWhereJsonContains('metadata->tags', 'essential');
                });
                break;
        }

        return $query;
    }

    /**
     * Validate that a lesson/exercise only uses managed words.
     */
    public function validateWordConstraints(array $wordIds, ?string $context = null): array
    {
        $errors = [];
        $warnings = [];

        // Check if all words exist and are active
        $existingWords = Word::whereIn('id', $wordIds)
            ->with(['language', 'translations'])
            ->get();

        $existingWordIds = $existingWords->pluck('id')->toArray();
        $missingWordIds = array_diff($wordIds, $existingWordIds);

        if (!empty($missingWordIds)) {
            $errors[] = [
                'type' => 'missing_words',
                'message' => 'Some words do not exist or are not accessible.',
                'word_ids' => $missingWordIds
            ];
        }

        // Check for inactive languages
        $inactiveLanguageWords = $existingWords->filter(function ($word) {
            return !$word->language || !$word->language->is_active;
        });

        if ($inactiveLanguageWords->isNotEmpty()) {
            $errors[] = [
                'type' => 'inactive_language',
                'message' => 'Some words belong to inactive languages.',
                'word_ids' => $inactiveLanguageWords->pluck('id')->toArray()
            ];
        }

        // Check for words without required audio (context-specific)
        if ($context && in_array($context, ['listening', 'speaking', 'pronunciation'])) {
            $wordsWithoutAudio = $existingWords->filter(function ($word) {
                return !$word->hasMedia('pronunciation');
            });

            if ($wordsWithoutAudio->isNotEmpty()) {
                $errors[] = [
                    'type' => 'missing_audio',
                    'message' => "Audio pronunciation is required for {$context} exercises.",
                    'word_ids' => $wordsWithoutAudio->pluck('id')->toArray()
                ];
            }
        }

        // Check for words without translations (warning)
        $wordsWithoutTranslations = $existingWords->filter(function ($word) {
            return $word->translations->isEmpty();
        });

        if ($wordsWithoutTranslations->isNotEmpty()) {
            $warnings[] = [
                'type' => 'missing_translations',
                'message' => 'Some words do not have translations. This may affect exercise quality.',
                'word_ids' => $wordsWithoutTranslations->pluck('id')->toArray()
            ];
        }

        // Check for difficulty level consistency
        $difficulties = $existingWords->pluck('metadata')
            ->map(function ($metadata) {
                return $metadata['difficulty'] ?? null;
            })
            ->filter()
            ->unique();

        if ($difficulties->count() > 2) {
            $warnings[] = [
                'type' => 'mixed_difficulty',
                'message' => 'Words have mixed difficulty levels. Consider grouping by difficulty.',
                'difficulties' => $difficulties->toArray()
            ];
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'word_count' => count($existingWordIds),
            'summary' => [
                'total_requested' => count($wordIds),
                'found' => count($existingWordIds),
                'missing' => count($missingWordIds),
                'with_audio' => $existingWords->filter(function($w) { return $w->hasMedia('pronunciation'); })->count(),
                'with_translations' => $existingWords->filter(function($w) { return $w->translations->isNotEmpty(); })->count(),
            ]
        ];
    }

    /**
     * Get word statistics for a given set of words.
     */
    public function getWordStatistics(array $wordIds): array
    {
        $words = Word::whereIn('id', $wordIds)
            ->with(['language', 'translations', 'media'])
            ->get();

        $languageStats = $words->groupBy('language_id')
            ->map(function ($group, $languageId) {
                $language = $group->first()->language;
                return [
                    'language_id' => $languageId,
                    'language_name' => $language?->name ?? 'Unknown',
                    'language_code' => $language?->code ?? 'unknown',
                    'word_count' => $group->count(),
                    'with_audio' => $group->filter(fn($w) => $w->hasMedia('pronunciation'))->count(),
                    'with_translations' => $group->filter(fn($w) => $w->translations->isNotEmpty())->count(),
                ];
            })
            ->values()
            ->toArray();

        $totalWords = $words->count();
        $difficultyStats = $words->groupBy(function ($word) {
            return $word->metadata['difficulty'] ?? 'unspecified';
        })->map(function ($group, $difficulty) use ($totalWords) {
            return [
                'difficulty' => $difficulty,
                'count' => $group->count(),
                'percentage' => round(($group->count() / $totalWords) * 100, 1)
            ];
        })->values()->toArray();

        return [
            'total_words' => $words->count(),
            'languages' => $languageStats,
            'difficulties' => $difficultyStats,
            'audio_coverage' => [
                'with_audio' => $words->filter(function($w) { return $w->hasMedia('pronunciation'); })->count(),
                'without_audio' => $words->filter(function($w) { return !$w->hasMedia('pronunciation'); })->count(),
                'percentage' => $words->count() > 0 ? round(($words->filter(function($w) { return $w->hasMedia('pronunciation'); })->count() / $words->count()) * 100, 1) : 0
            ],
            'translation_coverage' => [
                'with_translations' => $words->filter(function($w) { return $w->translations->isNotEmpty(); })->count(),
                'without_translations' => $words->filter(function($w) { return $w->translations->isEmpty(); })->count(),
                'percentage' => $words->count() > 0 ? round(($words->filter(function($w) { return $w->translations->isNotEmpty(); })->count() / $words->count()) * 100, 1) : 0
            ]
        ];
    }

    /**
     * Get recommended words for a lesson/exercise based on existing content.
     */
    public function getRecommendedWords(
        int $sourceLanguageId,
        int $targetLanguageId,
        ?string $difficulty = null,
        ?array $existingWordIds = null,
        int $limit = 20
    ): Collection {
        $query = Word::with(['language', 'translations.language'])
            ->where('language_id', $sourceLanguageId)
            ->whereHas('translations', function ($q) use ($targetLanguageId) {
                $q->where('language_id', $targetLanguageId);
            });

        // Exclude existing words
        if ($existingWordIds && !empty($existingWordIds)) {
            $query->whereNotIn('id', $existingWordIds);
        }

        // Filter by difficulty
        if ($difficulty) {
            $query->whereJsonContains('metadata->difficulty', $difficulty);
        }

        // Priority: words with audio, common words, words with multiple translations
        $query->leftJoin('media', function ($join) {
            $join->on('words.id', '=', 'media.model_id')
                 ->where('media.model_type', '=', Word::class)
                 ->where('media.collection_name', '=', 'pronunciation');
        })
        ->leftJoin('word_translations', 'words.id', '=', 'word_translations.word_id')
        ->select('words.*')
        ->selectRaw('COUNT(DISTINCT media.id) as audio_count')
        ->selectRaw('COUNT(DISTINCT word_translations.id) as translation_count')
        ->groupBy('words.id')
        ->orderByDesc('audio_count')
        ->orderByDesc('translation_count')
        ->orderBy('words.text');

        return $query->limit($limit)->get();
    }
}