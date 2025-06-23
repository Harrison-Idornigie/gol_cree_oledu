<?php

namespace App\Services\Tenants\Language;

use App\Models\Tenants\Word;
use App\Models\Tenants\Language;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Service for automatically mapping sentences to existing words
 * and suggesting new words that need to be created.
 */
class SentenceWordMappingService
{
    /**
     * Analyze a sentence and map it to existing words.
     * 
     * @param string $sentenceText The sentence to analyze
     * @param int $languageId The language ID
     * @return array{mapped_words: array, missing_words: array, suggested_positions: array}
     */
    public function analyzeSentence(string $sentenceText, int $languageId): array
    {
        // Clean and tokenize the sentence
        $words = $this->tokenizeSentence($sentenceText);

        // Get existing words from database
        $existingWords = $this->findExistingWords($words, $languageId);

        // Map words to positions
        $mappedWords = [];
        $missingWords = [];
        $suggestedPositions = [];

        foreach ($words as $position => $wordText) {
            $cleanWord = $this->cleanWord($wordText);

            if (isset($existingWords[$cleanWord])) {
                $mappedWords[] = [
                    'word_id' => $existingWords[$cleanWord]->id,
                    'position' => $position,
                    'text' => $wordText,
                    'clean_text' => $cleanWord,
                    'existing_word' => $existingWords[$cleanWord]->toArray()
                ];

                $suggestedPositions[] = [
                    'position' => $position,
                    'start_time' => $this->estimateStartTime($position, count($words)),
                    'end_time' => $this->estimateEndTime($position, count($words), $wordText)
                ];
            } else {
                $missingWords[] = [
                    'text' => $wordText,
                    'clean_text' => $cleanWord,
                    'position' => $position,
                    'suggested_part_of_speech' => $this->guessPartOfSpeech($cleanWord),
                    'suggested_pronunciation' => $this->generatePronunciation($cleanWord)
                ];
            }
        }

        return [
            'mapped_words' => $mappedWords,
            'missing_words' => $missingWords,
            'suggested_positions' => $suggestedPositions,
            'total_words' => count($words),
            'mapping_percentage' => count($mappedWords) / count($words) * 100
        ];
    }

    /**
     * Create missing words and return complete mapping.
     */
    public function createMissingWordsAndMap(
        string $sentenceText,
        int $languageId,
        array $missingWordData = []
    ): array {
        $analysis = $this->analyzeSentence($sentenceText, $languageId);

        // Create missing words
        $createdWords = [];
        foreach ($analysis['missing_words'] as $missingWord) {
            $wordData = $missingWordData[$missingWord['clean_text']] ?? [];

            $word = Word::create([
                'language_id' => $languageId,
                'text' => $missingWord['clean_text'],
                'pronunciation_key' => $wordData['pronunciation_key'] ?? $missingWord['suggested_pronunciation'],
                'part_of_speech' => $wordData['part_of_speech'] ?? $missingWord['suggested_part_of_speech'],
                'metadata' => [
                    'auto_created' => true,
                    'created_from_sentence' => true,
                    'difficulty' => $wordData['difficulty'] ?? 'beginner',
                    'tags' => $wordData['tags'] ?? ['auto-generated']
                ]
            ]);

            $createdWords[] = $word;

            // Add to mapped words
            $analysis['mapped_words'][] = [
                'word_id' => $word->id,
                'position' => $missingWord['position'],
                'text' => $missingWord['text'],
                'clean_text' => $missingWord['clean_text'],
                'existing_word' => $word->toArray(),
                'auto_created' => true
            ];
        }

        // Sort mapped words by position
        usort($analysis['mapped_words'], fn($a, $b) => $a['position'] <=> $b['position']);

        return [
            'mapped_words' => $analysis['mapped_words'],
            'created_words' => $createdWords,
            'suggested_positions' => $analysis['suggested_positions'],
            'total_words' => $analysis['total_words']
        ];
    }

    /**
     * Tokenize sentence into words with position tracking.
     */
    private function tokenizeSentence(string $sentence): array
    {
        // Simple tokenization - can be enhanced with NLP libraries
        $words = [];
        $tokens = preg_split('/\s+/', trim($sentence));

        foreach ($tokens as $index => $token) {
            if (!empty($token)) {
                $words[$index] = $token;
            }
        }

        return $words;
    }

    /**
     * Find existing words in database.
     */
    private function findExistingWords(array $words, int $languageId): array
    {
        $cleanWords = array_map([$this, 'cleanWord'], $words);

        $existingWords = Word::where('language_id', $languageId)
            ->whereIn('text', $cleanWords)
            ->get()
            ->keyBy('text');

        return $existingWords->toArray();
    }

    /**
     * Clean word from punctuation for matching.
     */
    private function cleanWord(string $word): string
    {
        return strtolower(preg_replace('/[^\p{L}\p{N}]/ui', '', $word));
    }

    /**
     * Estimate audio start time based on position.
     */
    private function estimateStartTime(int $position, int $totalWords): float
    {
        // Rough estimation - 0.5 seconds per word
        return $position * 0.5;
    }

    /**
     * Estimate audio end time based on word length.
     */
    private function estimateEndTime(int $position, int $totalWords, string $word): float
    {
        $startTime = $this->estimateStartTime($position, $totalWords);
        $wordDuration = max(0.3, strlen($word) * 0.1); // Minimum 0.3s, 0.1s per character

        return $startTime + $wordDuration;
    }

    /**
     * Guess part of speech (very basic - can be enhanced with NLP).
     */
    private function guessPartOfSpeech(string $word): string
    {
        // Basic Plains Cree patterns
        if (Str::endsWith($word, ['w', 'wak', 'ak'])) {
            return 'VAI'; // Animate Intransitive Verb
        }

        if (Str::endsWith($word, ['m', 'maw', 'ew'])) {
            return 'VTA'; // Transitive Animate Verb
        }

        if (Str::startsWith($word, ['ni', 'ki', 'o'])) {
            return 'NA'; // Animate Noun (possessed)
        }

        return 'NA'; // Default to Animate Noun
    }

    /**
     * Generate basic pronunciation key.
     */
    private function generatePronunciation(string $word): string
    {
        // Basic phonetic mapping for Plains Cree
        $pronunciation = strtoupper($word);

        // Basic replacements
        $replacements = [
            'Â' => 'AH',
            'Ê' => 'AY',
            'Î' => 'EE',
            'Ô' => 'OH',
            'W' => 'W',
            'Y' => 'Y'
        ];

        foreach ($replacements as $from => $to) {
            $pronunciation = str_replace($from, $to, $pronunciation);
        }

        return $pronunciation;
    }

    /**
     * Get interactive sentence data with clickable words for frontend.
     * Enhances existing mapping with language pair context.
     */
    public function getInteractiveSentenceData(
        string $sentenceText,
        int $targetLanguageId,
        int $sourceLanguageId,
        ?string $proficiencyLevel = 'beginner'
    ): array {
        // Use existing analysis but enhance with interactive data
        $analysis = $this->analyzeSentence($sentenceText, $targetLanguageId);

        // Get interactive data for each mapped word
        $interactiveWords = [];
        foreach ($analysis['mapped_words'] as $mappedWord) {
            $word = Word::with(['translations', 'media'])->find($mappedWord['word_id']);
            if ($word) {
                $interactiveWords[] = [
                    'word_id' => $word->id,
                    'text' => $mappedWord['text'],
                    'position' => $mappedWord['position'],
                    'audio_url' => $word->hasMedia('pronunciation')
                        ? $word->getFirstMediaUrl('pronunciation')
                        : null,
                    'explanation' => $this->getWordExplanation($word, $sourceLanguageId, $proficiencyLevel),
                    'part_of_speech' => $word->part_of_speech,
                    'clickable' => true
                ];
            }
        }

        return [
            'sentence_text' => $sentenceText,
            'target_language_id' => $targetLanguageId,
            'source_language_id' => $sourceLanguageId,
            'proficiency_level' => $proficiencyLevel,
            'interactive_words' => $interactiveWords,
            'mapping_stats' => [
                'total_words' => $analysis['total_words'],
                'mapped_words' => count($analysis['mapped_words']),
                'missing_words' => count($analysis['missing_words']),
                'mapping_percentage' => $analysis['mapping_percentage']
            ],
            'missing_words' => $analysis['missing_words'] // For debugging/admin
        ];
    }

    /**
     * Get word explanation in appropriate language based on proficiency.
     */
    private function getWordExplanation(Word $word, int $sourceLanguageId, string $proficiencyLevel): ?array
    {
        // Determine explanation language based on proficiency
        $explanationLanguageId = match ($proficiencyLevel) {
            'beginner' => $sourceLanguageId,
            'intermediate', 'advanced' => $word->language_id, // Target language
            default => $sourceLanguageId
        };

        $explanation = $word->translations()
            ->where('language_id', $explanationLanguageId)
            ->first();

        return $explanation ? [
            'text' => $explanation->text,
            'language_id' => $explanation->language_id,
            'context_notes' => $explanation->context_notes,
            'usage_examples' => $explanation->usage_examples,
            'explanation_language' => $explanationLanguageId === $sourceLanguageId ? 'source' : 'target'
        ] : null;
    }

    /**
     * Validate that sentence can be made interactive (all words have translations and audio).
     */
    public function validateInteractiveSentence(string $sentenceText, int $targetLanguageId, int $sourceLanguageId): array
    {
        $analysis = $this->analyzeSentence($sentenceText, $targetLanguageId);
        $validation = [
            'can_be_interactive' => true,
            'issues' => [],
            'warnings' => []
        ];

        // Check if we have missing words
        if (!empty($analysis['missing_words'])) {
            $validation['can_be_interactive'] = false;
            $validation['issues'][] = 'Missing words: ' . implode(', ', array_column($analysis['missing_words'], 'text'));
        }

        // Check mapped words for required features
        foreach ($analysis['mapped_words'] as $mappedWord) {
            $word = Word::with(['translations', 'media'])->find($mappedWord['word_id']);

            if (!$word) continue;

            // Check for audio
            if (!$word->hasMedia('pronunciation')) {
                $validation['warnings'][] = "Word '{$mappedWord['text']}' has no audio pronunciation";
            }

            // Check for translation in source language
            $hasTranslation = $word->translations()
                ->where('language_id', $sourceLanguageId)
                ->exists();

            if (!$hasTranslation) {
                $validation['warnings'][] = "Word '{$mappedWord['text']}' has no translation in source language";
            }
        }

        return $validation;
    }
}
