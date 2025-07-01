<?php

namespace App\Services\Tenants\Course;

use App\Models\Tenants\Exercise;
use App\Models\Tenants\Lesson;
use App\Models\Tenants\Word;
use App\Models\Tenants\Sentence;
use App\Models\Tenants\ContentTemplate;
use App\Services\Tenants\Exercise\ExerciseTypeService;
use App\Services\Tenants\Language\WordManagementService;
use App\Services\Tenants\Language\SentenceManagementService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Content Scaffolding Service
 * 
 * Handles automated content generation and scaffolding operations including:
 * - Exercise generation from guidebook and sentences
 * - Lesson scaffolding from templates
 * - Content validation and quality control
 * - Bulk content generation workflows
 */
class ContentScaffoldingService
{
    protected ExerciseTypeService $exerciseTypeService;
    protected WordManagementService $wordService;
    protected SentenceManagementService $sentenceService;
    protected DifficultyAnalysisService $difficultyService;

    public function __construct(
        ExerciseTypeService $exerciseTypeService,
        WordManagementService $wordService,
        SentenceManagementService $sentenceService,
        DifficultyAnalysisService $difficultyService
    ) {
        $this->exerciseTypeService = $exerciseTypeService;
        $this->wordService = $wordService;
        $this->sentenceService = $sentenceService;
        $this->difficultyService = $difficultyService;
    }

    /**
     * Generate exercises from guidebook words.
     */
    public function generateExercisesFromGuidebook(array $wordIds, array $exerciseTypes, array $options = []): Collection
    {
        return DB::transaction(function () use ($wordIds, $exerciseTypes, $options) {
            $words = Word::with(['translations', 'language'])->whereIn('id', $wordIds)->get();

            if ($words->isEmpty()) {
                throw new Exception('No words found for exercise generation');
            }

            // Determine language context
            $sourceLanguageId = $options['source_language_id'] ?? null;
            $targetLanguageId = $options['target_language_id'] ?? $words->first()->language_id;

            // Validate language context
            if (!$sourceLanguageId) {
                throw new Exception('Source language must be specified for exercise generation');
            }

            $exercises = collect();
            $exerciseCount = $options['exercise_count'] ?? 5;
            $difficultyLevel = $options['difficulty_level'] ?? 'intermediate';

            foreach ($exerciseTypes as $exerciseType) {
                $typeExercises = $this->generateExercisesByType(
                    $words,
                    $exerciseType,
                    $exerciseCount,
                    $difficultyLevel,
                    $sourceLanguageId,
                    $targetLanguageId
                );
                $exercises = $exercises->merge($typeExercises);
            }

            Log::info('Exercises generated from guidebook', [
                'word_count' => $words->count(),
                'exercise_types' => $exerciseTypes,
                'exercises_generated' => $exercises->count(),
                'source_language_id' => $sourceLanguageId,
                'target_language_id' => $targetLanguageId,
                'tenant_id' => tenant('id')
            ]);

            return $exercises;
        });
    }

    /**
     * Generate exercises from sentences.
     */
    public function generateExercisesFromSentences(array $sentenceIds, array $exerciseTypes, array $options = []): Collection
    {
        return DB::transaction(function () use ($sentenceIds, $exerciseTypes, $options) {
            $sentences = Sentence::with(['words', 'language'])->whereIn('id', $sentenceIds)->get();

            if ($sentences->isEmpty()) {
                throw new Exception('No sentences found for exercise generation');
            }

            $exercises = collect();
            $exerciseCount = $options['exercise_count'] ?? 3;
            $difficultyLevel = $options['difficulty_level'] ?? 'intermediate';

            foreach ($exerciseTypes as $exerciseType) {
                $typeExercises = $this->generateSentenceExercisesByType($sentences, $exerciseType, $exerciseCount, $difficultyLevel);
                $exercises = $exercises->merge($typeExercises);
            }

            Log::info('Exercises generated from sentences', [
                'sentence_count' => $sentences->count(),
                'exercise_types' => $exerciseTypes,
                'exercises_generated' => $exercises->count(),
                'tenant_id' => tenant('id')
            ]);

            return $exercises;
        });
    }

    /**
     * Generate a lesson from a content template.
     */
    public function generateLessonFromTemplate(ContentTemplate $template, array $guidebook, array $options = []): Lesson
    {
        return DB::transaction(function () use ($template, $guidebook, $options) {
            // Validate template
            if ($template->template_type !== 'lesson') {
                throw new Exception('Template must be of type "lesson"');
            }

            $templateData = $template->template_data;

            // Create lesson structure
            $lesson = new Lesson([
                'title' => $options['title'] ?? $templateData['title'] ?? 'Generated Lesson',
                'description' => $options['description'] ?? $templateData['description'] ?? null,
                'status' => 'draft',
                'tenant_id' => tenant('id')
            ]);

            // Generate exercises based on template and guidebook
            $exercises = $this->generateExercisesFromTemplate($templateData, $guidebook, $options);

            // Set lesson metadata
            $lesson->generation_metadata = [
                'template_id' => $template->id,
                'guidebook_count' => count($guidebook),
                'exercises_generated' => $exercises->count(),
                'generated_at' => now()->toISOString(),
                'difficulty_level' => $options['difficulty_level'] ?? 'intermediate'
            ];

            $lesson->auto_generated = true;

            return $lesson;
        });
    }

    /**
     * Generate exercises by type from words.
     */
    private function generateExercisesByType(
        Collection $words,
        string $exerciseType,
        int $count,
        string $difficulty,
        int $sourceLanguageId,
        int $targetLanguageId
    ): Collection {
        $exercises = collect();

        switch ($exerciseType) {
            case Exercise::TYPE_MULTIPLE_CHOICE:
                $exercises = $this->generateMultipleChoiceFromWords($words, $count, $difficulty, $sourceLanguageId, $targetLanguageId);
                break;

            case Exercise::TYPE_FILL_BLANK:
                $exercises = $this->generateFillBlankFromWords($words, $count, $difficulty, $sourceLanguageId, $targetLanguageId);
                break;

            case Exercise::TYPE_MATCHING:
                $exercises = $this->generateMatchingFromWords($words, $count, $difficulty, $sourceLanguageId, $targetLanguageId);
                break;

            case Exercise::TYPE_WRITING:
                $exercises = $this->generateWritingFromWords($words, $count, $difficulty, $sourceLanguageId, $targetLanguageId);
                break;

            default:
                Log::warning('Unsupported exercise type for word generation', ['type' => $exerciseType]);
        }

        return $exercises;
    }

    /**
     * Generate multiple choice exercises from words.
     */
    private function generateMultipleChoiceFromWords(
        Collection $words,
        int $count,
        string $difficulty,
        int $sourceLanguageId,
        int $targetLanguageId
    ): Collection {
        $exercises = collect();

        for ($i = 0; $i < $count && $words->isNotEmpty(); $i++) {
            $targetWord = $words->random();

            // Get translation in the source language (user's native language)
            $correctTranslation = $targetWord->translations()
                ->where('language_id', $sourceLanguageId)
                ->first();

            if (!$correctTranslation) {
                continue;
            }

            // Get distractors (wrong answers) in the same source language
            $distractors = $this->getDistractorTranslations($targetWord, 3, $sourceLanguageId);

            $options = collect([$correctTranslation->text])
                ->merge($distractors->pluck('text'))
                ->shuffle()
                ->values();

            $correctIndex = $options->search($correctTranslation->text);

            $exercise = [
                'type' => Exercise::TYPE_MULTIPLE_CHOICE,
                'title' => "Choose the correct translation",
                'content' => [
                    'question' => "What does '{$targetWord->text}' mean?", // Question in target language
                    'options' => $options->toArray(), // Options in source language
                    'word_id' => $targetWord->id,
                    'language_context' => [
                        'question_language_id' => $targetLanguageId, // Word being asked about
                        'answer_language_id' => $sourceLanguageId,   // Translation language
                        'source_language_id' => $sourceLanguageId,
                        'target_language_id' => $targetLanguageId
                    ]
                ],
                'answers' => [
                    'correct' => $correctIndex,
                    'explanation' => "'{$targetWord->text}' means '{$correctTranslation->text}'"
                ],
                'difficulty_level' => $difficulty,
                'generated_from_template' => true,
                'generation_source' => [
                    'type' => 'guidebook',
                    'word_id' => $targetWord->id,
                    'method' => 'multiple_choice_translation'
                ],
                'auto_generated' => true,
                'tenant_id' => tenant('id')
            ];

            $exercises->push($exercise);
        }

        return $exercises;
    }

    /**
     * Generate fill-in-the-blank exercises from words.
     */
    private function generateFillBlankFromWords(Collection $words, int $count, string $difficulty): Collection
    {
        $exercises = collect();

        for ($i = 0; $i < $count && $words->isNotEmpty(); $i++) {
            $targetWord = $words->random();

            // Get sentences that contain this word
            $sentences = $targetWord->sentences()->limit(5)->get();

            if ($sentences->isEmpty()) {
                continue;
            }

            $sentence = $sentences->random();
            $sentenceText = $sentence->text;

            // Create blank by replacing the word
            $blankText = str_replace($targetWord->text, '____', $sentenceText);

            $exercise = [
                'type' => Exercise::TYPE_FILL_BLANK,
                'title' => "Fill in the blank",
                'content' => [
                    'text' => $blankText,
                    'word_id' => $targetWord->id,
                    'sentence_id' => $sentence->id,
                    'hint' => $targetWord->translations->first()?->text
                ],
                'answers' => [
                    'correct' => [$targetWord->text],
                    'alternatives' => $this->getWordAlternatives($targetWord)
                ],
                'difficulty_level' => $difficulty,
                'generated_from_template' => true,
                'generation_source' => [
                    'type' => 'guidebook',
                    'word_id' => $targetWord->id,
                    'sentence_id' => $sentence->id,
                    'method' => 'fill_blank_context'
                ],
                'auto_generated' => true,
                'tenant_id' => tenant('id')
            ];

            $exercises->push($exercise);
        }

        return $exercises;
    }

    /**
     * Generate matching exercises from words.
     */
    private function generateMatchingFromWords(Collection $words, int $count, string $difficulty): Collection
    {
        $exercises = collect();

        // Group words for matching exercises
        $wordGroups = $words->chunk(min(6, $words->count()));

        foreach ($wordGroups->take($count) as $wordGroup) {
            $pairs = [];

            foreach ($wordGroup as $word) {
                $translation = $word->translations->first();
                if ($translation) {
                    $pairs[] = [
                        'source' => $word->text,
                        'target' => $translation->text,
                        'word_id' => $word->id
                    ];
                }
            }

            if (count($pairs) >= 3) {
                $exercise = [
                    'type' => Exercise::TYPE_MATCHING,
                    'title' => "Match words with their translations",
                    'content' => [
                        'pairs' => $pairs,
                        'instructions' => 'Match each word with its correct translation'
                    ],
                    'answers' => [
                        'correct_pairs' => collect($pairs)->mapWithKeys(function ($pair) {
                            return [$pair['source'] => $pair['target']];
                        })->toArray()
                    ],
                    'difficulty_level' => $difficulty,
                    'generated_from_template' => true,
                    'generation_source' => [
                        'type' => 'guidebook',
                        'word_ids' => $wordGroup->pluck('id')->toArray(),
                        'method' => 'matching_translation'
                    ],
                    'auto_generated' => true,
                    'tenant_id' => tenant('id')
                ];

                $exercises->push($exercise);
            }
        }

        return $exercises;
    }

    /**
     * Generate writing exercises from words.
     */
    private function generateWritingFromWords(Collection $words, int $count, string $difficulty): Collection
    {
        $exercises = collect();

        for ($i = 0; $i < $count && $words->isNotEmpty(); $i++) {
            $targetWords = $words->random(min(3, $words->count()));

            $exercise = [
                'type' => Exercise::TYPE_WRITING,
                'title' => "Write a sentence using the given words",
                'content' => [
                    'prompt' => "Write a sentence using these words: " . $targetWords->pluck('text')->implode(', '),
                    'required_words' => $targetWords->pluck('text')->toArray(),
                    'word_ids' => $targetWords->pluck('id')->toArray(),
                    'min_length' => 10,
                    'max_length' => 100
                ],
                'answers' => [
                    'sample_answers' => $this->generateSampleSentences($targetWords),
                    'evaluation_criteria' => [
                        'uses_all_words' => true,
                        'grammatically_correct' => true,
                        'meaningful_content' => true
                    ]
                ],
                'difficulty_level' => $difficulty,
                'generated_from_template' => true,
                'generation_source' => [
                    'type' => 'guidebook',
                    'word_ids' => $targetWords->pluck('id')->toArray(),
                    'method' => 'writing_prompt'
                ],
                'auto_generated' => true,
                'tenant_id' => tenant('id')
            ];

            $exercises->push($exercise);
        }

        return $exercises;
    }

    /**
     * Generate exercises by type from sentences.
     */
    private function generateSentenceExercisesByType(Collection $sentences, string $exerciseType, int $count, string $difficulty): Collection
    {
        $exercises = collect();

        switch ($exerciseType) {
            case Exercise::TYPE_LISTENING:
                $exercises = $this->generateListeningFromSentences($sentences, $count, $difficulty);
                break;

            case Exercise::TYPE_FILL_BLANK:
                $exercises = $this->generateFillBlankFromSentences($sentences, $count, $difficulty);
                break;

            case Exercise::TYPE_WRITING:
                $exercises = $this->generateWritingFromSentences($sentences, $count, $difficulty);
                break;

            case Exercise::TYPE_CONVERSATION:
                $exercises = $this->generateConversationFromSentences($sentences, $count, $difficulty);
                break;

            default:
                Log::warning('Unsupported exercise type for sentence generation', ['type' => $exerciseType]);
        }

        return $exercises;
    }

    /**
     * Generate listening exercises from sentences.
     */
    private function generateListeningFromSentences(Collection $sentences, int $count, string $difficulty): Collection
    {
        $exercises = collect();

        foreach ($sentences->take($count) as $sentence) {
            // Only create listening exercises for sentences with audio
            if (!$sentence->hasMedia('audio')) {
                continue;
            }

            $exercise = [
                'type' => Exercise::TYPE_LISTENING,
                'title' => "Listen and understand",
                'content' => [
                    'audio_url' => $sentence->getFirstMediaUrl('audio'),
                    'transcript' => $sentence->text,
                    'prompt' => "Listen to the audio and answer the questions",
                    'language' => $sentence->language->code,
                    'difficulty' => $difficulty,
                    'sentence_id' => $sentence->id
                ],
                'answers' => [
                    'correct' => [$sentence->text],
                    'alternatives' => $this->generateSentenceAlternatives($sentence)
                ],
                'difficulty_level' => $difficulty,
                'generated_from_template' => true,
                'generation_source' => [
                    'type' => 'sentence',
                    'sentence_id' => $sentence->id,
                    'method' => 'listening_comprehension'
                ],
                'auto_generated' => true,
                'tenant_id' => tenant('id')
            ];

            $exercises->push($exercise);
        }

        return $exercises;
    }

    /**
     * Generate fill-in-the-blank exercises from sentences.
     */
    private function generateFillBlankFromSentences(Collection $sentences, int $count, string $difficulty): Collection
    {
        $exercises = collect();

        foreach ($sentences->take($count) as $sentence) {
            $words = $sentence->words;

            if ($words->isEmpty()) {
                continue;
            }

            // Select random words to blank out
            $wordsToBlank = $words->random(min(2, $words->count()));
            $blankText = $sentence->text;
            $correctAnswers = [];

            foreach ($wordsToBlank as $word) {
                $blankText = str_replace($word->text, '____', $blankText);
                $correctAnswers[] = $word->text;
            }

            $exercise = [
                'type' => Exercise::TYPE_FILL_BLANK,
                'title' => "Complete the sentence",
                'content' => [
                    'text' => $blankText,
                    'original_sentence' => $sentence->text,
                    'sentence_id' => $sentence->id,
                    'blanked_words' => $wordsToBlank->pluck('id')->toArray(),
                    'hints' => $wordsToBlank->map(function ($word) {
                        return $word->translations->first()?->text;
                    })->filter()->toArray()
                ],
                'answers' => [
                    'correct' => $correctAnswers,
                    'alternatives' => $this->getWordsAlternatives($wordsToBlank)
                ],
                'difficulty_level' => $difficulty,
                'generated_from_template' => true,
                'generation_source' => [
                    'type' => 'sentence',
                    'sentence_id' => $sentence->id,
                    'method' => 'fill_blank_multiple'
                ],
                'auto_generated' => true,
                'tenant_id' => tenant('id')
            ];

            $exercises->push($exercise);
        }

        return $exercises;
    }

    /**
     * Generate writing exercises from sentences.
     */
    private function generateWritingFromSentences(Collection $sentences, int $count, string $difficulty): Collection
    {
        $exercises = collect();

        foreach ($sentences->take($count) as $sentence) {
            $exercise = [
                'type' => Exercise::TYPE_WRITING,
                'title' => "Rewrite or transform the sentence",
                'content' => [
                    'prompt' => "Rewrite this sentence in your own words: " . $sentence->text,
                    'original_sentence' => $sentence->text,
                    'sentence_id' => $sentence->id,
                    'instructions' => "Keep the same meaning but use different words",
                    'min_length' => 10,
                    'max_length' => 150
                ],
                'answers' => [
                    'sample_answers' => $this->generateSentenceVariations($sentence),
                    'evaluation_criteria' => [
                        'maintains_meaning' => true,
                        'uses_different_words' => true,
                        'grammatically_correct' => true
                    ]
                ],
                'difficulty_level' => $difficulty,
                'generated_from_template' => true,
                'generation_source' => [
                    'type' => 'sentence',
                    'sentence_id' => $sentence->id,
                    'method' => 'writing_transformation'
                ],
                'auto_generated' => true,
                'tenant_id' => tenant('id')
            ];

            $exercises->push($exercise);
        }

        return $exercises;
    }

    /**
     * Generate conversation exercises from sentences.
     */
    private function generateConversationFromSentences(Collection $sentences, int $count, string $difficulty): Collection
    {
        $exercises = collect();

        // Group sentences for conversation exercises
        $sentenceGroups = $sentences->chunk(min(4, $sentences->count()));

        foreach ($sentenceGroups->take($count) as $sentenceGroup) {
            $conversationSteps = [];

            foreach ($sentenceGroup as $index => $sentence) {
                $conversationSteps[] = [
                    'type' => 'dialogue',
                    'speaker' => $index % 2 === 0 ? 'A' : 'B',
                    'content' => $sentence->text,
                    'sentence_id' => $sentence->id
                ];
            }

            $exercise = [
                'type' => Exercise::TYPE_CONVERSATION,
                'title' => "Practice conversation",
                'content' => [
                    'title' => "Conversation Practice",
                    'description' => "Practice this conversation with a partner",
                    'steps' => $conversationSteps,
                    'word_mapping' => $this->extractWordMappingFromSentences($sentenceGroup)
                ],
                'answers' => [
                    'conversation_flow' => $conversationSteps,
                    'key_phrases' => $this->extractKeyPhrases($sentenceGroup)
                ],
                'difficulty_level' => $difficulty,
                'generated_from_template' => true,
                'generation_source' => [
                    'type' => 'sentence',
                    'sentence_ids' => $sentenceGroup->pluck('id')->toArray(),
                    'method' => 'conversation_dialogue'
                ],
                'auto_generated' => true,
                'tenant_id' => tenant('id')
            ];

            $exercises->push($exercise);
        }

        return $exercises;
    }

    /**
     * Generate sentence variations for writing exercises.
     */
    private function generateSentenceVariations(Sentence $sentence): array
    {
        // This would ideally use AI to generate variations
        // For now, return simple variations
        $variations = [];
        $text = $sentence->text;

        // Simple transformations
        if (str_contains($text, ' is ')) {
            $variations[] = str_replace(' is ', ' was ', $text);
        }

        if (str_contains($text, 'The ')) {
            $variations[] = str_replace('The ', 'A ', $text);
        }

        return array_filter($variations);
    }

    /**
     * Extract word mapping from sentences for conversation exercises.
     */
    private function extractWordMappingFromSentences(Collection $sentences): array
    {
        $wordMapping = [];

        foreach ($sentences as $sentence) {
            foreach ($sentence->words as $word) {
                $translation = $word->translations->first();
                if ($translation) {
                    $wordMapping[$word->text] = $translation->text;
                }
            }
        }

        return $wordMapping;
    }

    /**
     * Extract key phrases from sentences.
     */
    private function extractKeyPhrases(Collection $sentences): array
    {
        $phrases = [];

        foreach ($sentences as $sentence) {
            // Extract common phrases (this would be more sophisticated in practice)
            $words = explode(' ', $sentence->text);
            if (count($words) >= 2) {
                for ($i = 0; $i < count($words) - 1; $i++) {
                    $phrase = $words[$i] . ' ' . $words[$i + 1];
                    $phrases[] = $phrase;
                }
            }
        }

        return array_unique($phrases);
    }

    /**
     * Get distractor translations for multiple choice.
     */
    private function getDistractorTranslations(Word $targetWord, int $count, int $languageId): Collection
    {
        return Word::whereHas('translations', function ($query) use ($languageId) {
            $query->where('language_id', $languageId);
        })
            ->where('id', '!=', $targetWord->id)
            ->where('part_of_speech', $targetWord->part_of_speech)
            ->with('translations')
            ->inRandomOrder()
            ->limit($count)
            ->get()
            ->map(function ($word) use ($languageId) {
                return $word->translations->where('language_id', $languageId)->first();
            })
            ->filter();
    }

    /**
     * Get word alternatives for fill-in-the-blank.
     */
    private function getWordAlternatives(Word $word): array
    {
        // Get synonyms or similar words
        return Word::where('language_id', $word->language_id)
            ->where('part_of_speech', $word->part_of_speech)
            ->where('id', '!=', $word->id)
            ->limit(3)
            ->pluck('text')
            ->toArray();
    }

    /**
     * Get alternatives for multiple words.
     */
    private function getWordsAlternatives(Collection $words): array
    {
        $alternatives = [];

        foreach ($words as $word) {
            $wordAlternatives = $this->getWordAlternatives($word);
            $alternatives = array_merge($alternatives, $wordAlternatives);
        }

        return array_unique($alternatives);
    }

    /**
     * Generate sample sentences using given words.
     */
    private function generateSampleSentences(Collection $words): array
    {
        // This would ideally use AI or predefined patterns
        // For now, return simple templates
        $samples = [];
        $wordTexts = $words->pluck('text')->toArray();

        if (count($wordTexts) >= 2) {
            $samples[] = "The " . $wordTexts[0] . " is " . $wordTexts[1] . ".";
            $samples[] = "I like " . $wordTexts[0] . " and " . $wordTexts[1] . ".";
        }

        return $samples;
    }

    /**
     * Generate sentence alternatives for listening exercises.
     */
    private function generateSentenceAlternatives(Sentence $sentence): array
    {
        // Get similar sentences or create variations
        return Sentence::where('language_id', $sentence->language_id)
            ->where('id', '!=', $sentence->id)
            ->limit(3)
            ->pluck('text')
            ->toArray();
    }

    /**
     * Generate exercises from template data.
     */
    private function generateExercisesFromTemplate(array $templateData, array $guidebook, array $options): Collection
    {
        $exercises = collect();

        foreach ($templateData['exercise_patterns'] ?? [] as $pattern) {
            $patternExercises = $this->generateExercisesFromPattern($pattern, $guidebook, $options);
            $exercises = $exercises->merge($patternExercises);
        }

        return $exercises;
    }

    /**
     * Generate exercises from a specific pattern.
     */
    private function generateExercisesFromPattern(array $pattern, array $guidebook, array $options): Collection
    {
        $exercises = collect();
        $exerciseType = $pattern['type'];
        $count = $pattern['count'] ?? 1;

        // Get words for this pattern
        $words = Word::whereIn('id', $guidebook)->get();

        if ($words->isNotEmpty()) {
            $patternExercises = $this->generateExercisesByType($words, $exerciseType, $count, $options['difficulty_level'] ?? 'intermediate');
            $exercises = $exercises->merge($patternExercises);
        }

        return $exercises;
    }
}
