<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenants\Language;
use App\Models\Tenants\LearningPath;
use App\Models\Tenants\Unit;
use App\Models\Tenants\Topic;
use App\Models\Tenants\Lesson;
use App\Models\Tenants\Exercise;
use App\Models\Tenants\Word;
use App\Models\Tenants\User;
use App\Models\Tenants\CurriculumTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

abstract class PlainsCreeBaseCourseSeeder extends Seeder
{
    protected Language $plainsCree;
    protected Language $english;
    protected CurriculumTemplate $template;
    protected array $vocabulary = [];
    protected array $vocabularyByLevel = [];
    protected string $level;
    protected string $ageGroup = 'teen_adult'; // Default age group

    /**
     * Initialize languages and curriculum template for the specific level
     */
    protected function initializeLanguagesAndTemplate(): void
    {
        $this->plainsCree = Language::where('code', 'crk')->firstOrFail();
        $this->english = Language::where('code', 'en')->firstOrFail();

        $this->template = CurriculumTemplate::where('proficiency_level', $this->level)
            ->whereHas('languagePair', function ($query) {
                $query->where('source_language_id', $this->english->id)
                    ->where('target_language_id', $this->plainsCree->id);
            })
            ->firstOrFail();
    }

    /**
     * Load vocabulary for the specific level and all previous levels with progression constraints
     */
    protected function loadVocabulary(): void
    {
        // Get vocabulary progression constraints from the vocabulary seeder
        $progressionConstraints = PlainsCreeVocabularySeeder::getVocabularyProgressionConstraints();
        $allowedLevels = $progressionConstraints[$this->level] ?? ['A1'];

        $this->vocabulary = Word::where('language_id', $this->plainsCree->id)
            ->where(function ($query) use ($allowedLevels) {
                foreach ($allowedLevels as $level) {
                    $query->orWhereJsonContains('metadata->proficiency_level', $level);
                }
            })
            ->with(['translations' => function ($query) {
                $query->where('language_id', $this->english->id);
            }])
            ->orderBy('id') // Ensure consistent ordering for vocabulary progression
            ->get()
            ->toArray();

        // Organize vocabulary by level for better progression control
        $this->vocabularyByLevel = [];
        foreach ($this->vocabulary as $word) {
            $wordLevel = $word['metadata']['proficiency_level'] ?? 'A1';
            $this->vocabularyByLevel[$wordLevel][] = $word;
        }

        $this->command->info('Loaded ' . count($this->vocabulary) . " vocabulary words for {$this->level} level (from levels: " . implode(', ', $allowedLevels) . ")");

        // Show vocabulary distribution by level
        foreach ($allowedLevels as $level) {
            $count = count($this->vocabularyByLevel[$level] ?? []);
            $this->command->info("  - {$level}: {$count} words");
        }
    }

    /**
     * Create learning path for the specific level
     */
    protected function createLearningPath(string $title, string $description): LearningPath
    {
        // Get or create language pair (English -> Plains Cree)
        $languagePairId = $this->getOrCreateLanguagePair();

        return LearningPath::updateOrCreate(
            [
                'title' => $title,
                'language_id' => $this->plainsCree->id, // Legacy support
            ],
            [
                'description' => $description,
                'language_pair_id' => $languagePairId,
                'target_level' => $this->level,
                'status' => 'published',
                'review_status' => 'approved',
            ]
        );
    }

    /**
     * Get or create language pair for Plains Cree (English -> Plains Cree)
     */
    protected function getOrCreateLanguagePair(): int
    {
        $english = Language::where('code', 'en')->first();

        if (!$english) {
            throw new \Exception('English language not found. Please run LanguageSeeder first.');
        }

        $pair = \App\Models\Tenants\LanguagePair::firstOrCreate([
            'source_language_id' => $english->id,
            'target_language_id' => $this->plainsCree->id,
        ], [
            'is_active' => true,
        ]);

        return $pair->id;
    }

    /**
     * Create units from the curriculum template
     */
    protected function createUnitsFromTemplate(LearningPath $learningPath): void
    {
        $templateData = $this->template->template_data;
        $units = $templateData['units'] ?? [];

        foreach ($units as $unitIndex => $unitData) {
            $this->command->info("Creating Unit: {$unitData['title']}");

            $unit = Unit::updateOrCreate(
                [
                    'learning_path_id' => $learningPath->id,
                    'title' => $unitData['title'],
                ],
                [
                    'description' => $unitData['cultural_focus'] ?? 'Cultural learning unit',
                    'order' => $unitData['order'],
                    'status' => 'published',
                    'review_status' => 'approved',
                ]
            );

            // Create topics for this unit
            $this->createTopicsForUnit($unit, $unitData, $unitIndex);
        }
    }

    /**
     * Create topics for a unit
     */
    protected function createTopicsForUnit(Unit $unit, array $unitData, int $unitIndex): void
    {
        $topics = $unitData['topics'] ?? [];

        foreach ($topics as $topicIndex => $topicData) {
            $this->command->info("  Creating Topic: {$topicData['title']}");

            $topic = Topic::updateOrCreate(
                [
                    'unit_id' => $unit->id,
                    'title' => $topicData['title'],
                ],
                [
                    'description' => $this->getTopicDescription($topicData),
                    'order' => $topicIndex + 1,
                    'status' => 'published',
                    'xp_reward' => $this->getXpReward(),
                    'max_level' => 5,
                    'is_bonus' => false,
                    'icon' => $this->getTopicIcon($topicData['title']),
                    'color' => $this->getTopicColor($unitIndex),
                ]
            );

            // Create lessons for this topic
            $this->createLessonsForTopic($topic, $topicData, $unitIndex, $topicIndex);
        }
    }

    /**
     * Create lessons for a topic
     */
    protected function createLessonsForTopic(Topic $topic, array $topicData, int $unitIndex, int $topicIndex): void
    {
        $lessons = $topicData['lessons'] ?? [];

        foreach ($lessons as $lessonIndex => $lessonData) {
            $this->command->info("    Creating Lesson: {$lessonData['title']}");

            $lesson = Lesson::updateOrCreate(
                [
                    'topic_id' => $topic->id,
                    'title' => $lessonData['title'],
                ],
                [
                    'description' => $this->getLessonDescription($lessonData),
                    'order' => $lessonIndex + 1,
                    'status' => 'published',
                    'review_status' => 'approved',
                ]
            );

            // Create exercises for this lesson
            $this->createExercisesForLesson($lesson, $lessonData, $unitIndex, $topicIndex, $lessonIndex);
        }
    }

    /**
     * Create exercises for a lesson using actual Plains Cree vocabulary with age-appropriate settings
     */
    protected function createExercisesForLesson(Lesson $lesson, array $lessonData, int $unitIndex, int $topicIndex, int $lessonIndex): void
    {
        // Use age-appropriate exercise count
        $exerciseCount = $lessonData['exercises'] ?? $this->getAgeAppropriateExerciseCount();
        $lessonType = $lessonData['type'] ?? 'vocabulary';

        // Get vocabulary subset for this lesson
        $lessonVocabulary = $this->getVocabularyForLesson($unitIndex, $topicIndex, $lessonIndex, $this->getVocabularyPerLesson());

        if (empty($lessonVocabulary)) {
            $this->command->warn("      No vocabulary available for lesson: {$lessonData['title']}");
            return;
        }

        // Add age-appropriate lesson metadata
        $lessonDuration = $this->getAgeAppropriateLessonDuration();
        $lesson->update([
            'metadata' => array_merge($lesson->metadata ?? [], [
                'age_group' => $this->ageGroup,
                'duration_settings' => $lessonDuration,
                'level' => $this->level,
            ])
        ]);

        // Create different types of exercises based on lesson type
        for ($i = 1; $i <= $exerciseCount; $i++) {
            $exerciseType = $this->getExerciseType($lessonType, $i);
            $this->createExercise($lesson, $exerciseType, $lessonVocabulary, $i);
        }
    }

    /**
     * Get vocabulary for a specific lesson with level-appropriate progression
     */
    protected function getVocabularyForLesson(int $unitIndex, int $topicIndex, int $lessonIndex, int $count): array
    {
        // Get vocabulary count targets for this level
        $countTargets = PlainsCreeVocabularySeeder::getVocabularyCountTargets();
        $levelTargets = $countTargets[$this->level] ?? ['per_lesson' => 5];

        // Adjust count based on level targets
        $adjustedCount = min($count, $levelTargets['per_lesson']);

        // Calculate starting index to ensure vocabulary progression within level constraints
        $startIndex = ($unitIndex * 10) + ($topicIndex * 5) + $lessonIndex;

        // Prioritize current level vocabulary, then fall back to previous levels
        $lessonVocabulary = [];

        // First, try to get vocabulary from the current level
        $currentLevelVocab = $this->vocabularyByLevel[$this->level] ?? [];
        if (count($currentLevelVocab) > 0) {
            $currentLevelStart = $startIndex % count($currentLevelVocab);
            $fromCurrentLevel = array_slice($currentLevelVocab, $currentLevelStart, $adjustedCount);
            $lessonVocabulary = array_merge($lessonVocabulary, $fromCurrentLevel);
        }

        // If we need more words, supplement from previous levels
        $remaining = $adjustedCount - count($lessonVocabulary);
        if ($remaining > 0) {
            $remainingVocab = array_slice($this->vocabulary, $startIndex, $remaining);
            $lessonVocabulary = array_merge($lessonVocabulary, $remainingVocab);
        }

        return array_slice($lessonVocabulary, 0, $adjustedCount);
    }

    /**
     * Determine exercise type based on lesson type and position
     */
    protected function getExerciseType(string $lessonType, int $position): string
    {
        $exerciseTypes = [
            'vocabulary' => ['multiple_choice', 'matching', 'fill_blank'],
            'conversation' => ['multiple_choice', 'listening', 'conversation'],
            'pronunciation' => ['listening', 'speaking', 'multiple_choice'],
            'grammar' => ['fill_blank', 'multiple_choice', 'writing'],
            'cultural' => ['multiple_choice', 'listening', 'conversation'],
        ];

        $types = $exerciseTypes[$lessonType] ?? $exerciseTypes['vocabulary'];
        return $types[($position - 1) % count($types)];
    }

    /**
     * Create an exercise with actual Plains Cree content and age-appropriate settings
     */
    protected function createExercise(Lesson $lesson, string $type, array $vocabulary, int $order): void
    {
        if (empty($vocabulary)) {
            return;
        }

        $exerciseData = match ($type) {
            'multiple_choice' => $this->createMultipleChoiceExercise($vocabulary),
            'matching' => $this->createMatchingExercise($vocabulary),
            'fill_blank' => $this->createFillBlankExercise($vocabulary),
            'listening' => $this->createListeningExercise($vocabulary),
            'speaking' => $this->createSpeakingExercise($vocabulary),
            'conversation' => $this->createConversationExercise($vocabulary),
            'writing' => $this->createWritingExercise($vocabulary),
            default => $this->createMultipleChoiceExercise($vocabulary),
        };

        // Get age-appropriate rewards
        $rewards = $this->getAgeAppropriateRewards();

        Exercise::updateOrCreate(
            [
                'lesson_id' => $lesson->id,
                'title' => $exerciseData['title'],
                'order' => $order,
            ],
            [
                'type' => $type,
                'content' => $exerciseData['content'],
                'answers' => $exerciseData['answers'],
                'status' => 'published',
                'review_status' => 'approved',
                'purpose' => Exercise::PURPOSE_PRACTICE,
                'difficulty_level' => $this->getDifficultyLevel(),
                'passing_score' => $this->getPassingScore(),
                'time_limit' => null,
                'max_attempts' => 3,
                'show_feedback' => true,
                'show_hints' => true,
                'xp_reward' => $rewards['exercise_xp'],
                'is_checkpoint' => false,
                'requires_previous' => false,
                'show_solutions_after' => 'attempt',
                'min_correct_required' => 1,
                'metadata' => [
                    'vocabulary_used' => array_column($vocabulary, 'id'),
                    'cultural_context' => true,
                    'syllabics_practice' => true,
                    'audio_support' => true,
                    'level' => $this->level,
                    'age_group' => $this->ageGroup,
                    'rewards' => $rewards,
                    'duolingo_style' => [
                        'clickable_vocabulary' => true,
                        'popup_explanations' => true,
                        'audio_pronunciation' => true,
                        'syllabics_display' => true,
                        'cultural_context_popup' => true,
                    ],
                ],
            ]
        );
    }

    // Abstract methods that must be implemented by each level
    abstract protected function getDefaultExerciseCount(): int;
    abstract protected function getVocabularyPerLesson(): int;
    abstract protected function getDifficultyLevel(): int;
    abstract protected function getPassingScore(): int;
    abstract protected function getXpReward(): int;
    abstract protected function getExerciseXpReward(): int;

    /**
     * Set age group for age-appropriate learning paths
     */
    public function setAgeGroup(string $ageGroup): void
    {
        $this->ageGroup = $ageGroup;
    }

    /**
     * Get age-appropriate exercise count
     */
    protected function getAgeAppropriateExerciseCount(): int
    {
        $baseCount = $this->getDefaultExerciseCount();

        return match ($this->ageGroup) {
            'kids' => max(3, $baseCount - 2), // Fewer exercises for kids
            'teen_adult' => $baseCount,
            default => $baseCount,
        };
    }

    /**
     * Get age-appropriate lesson duration metadata
     */
    protected function getAgeAppropriateLessonDuration(): array
    {
        return match ($this->ageGroup) {
            'kids' => [
                'target_duration_minutes' => 8,
                'max_duration_minutes' => 10,
                'break_frequency' => 'every_3_exercises',
                'gamification_level' => 'high',
            ],
            'teen_adult' => [
                'target_duration_minutes' => 12,
                'max_duration_minutes' => 15,
                'break_frequency' => 'every_5_exercises',
                'gamification_level' => 'medium',
            ],
            default => [
                'target_duration_minutes' => 12,
                'max_duration_minutes' => 15,
                'break_frequency' => 'every_5_exercises',
                'gamification_level' => 'medium',
            ],
        };
    }

    /**
     * Get age-appropriate XP and reward settings
     */
    protected function getAgeAppropriateRewards(): array
    {
        $baseXp = $this->getXpReward();
        $baseExerciseXp = $this->getExerciseXpReward();

        return match ($this->ageGroup) {
            'kids' => [
                'topic_xp' => $baseXp + 5, // More XP for kids
                'exercise_xp' => $baseExerciseXp + 2,
                'celebration_frequency' => 'high',
                'visual_rewards' => true,
                'sound_effects' => true,
            ],
            'teen_adult' => [
                'topic_xp' => $baseXp,
                'exercise_xp' => $baseExerciseXp,
                'celebration_frequency' => 'medium',
                'visual_rewards' => false,
                'sound_effects' => false,
            ],
            default => [
                'topic_xp' => $baseXp,
                'exercise_xp' => $baseExerciseXp,
                'celebration_frequency' => 'medium',
                'visual_rewards' => false,
                'sound_effects' => false,
            ],
        };
    }

    // Exercise creation methods (shared across all levels) with Duolingo-style features
    protected function createMultipleChoiceExercise(array $vocabulary): array
    {
        $targetWord = $vocabulary[0];
        $translation = $targetWord['translations'][0]['text'] ?? 'unknown';
        $syllabics = $targetWord['metadata']['syllabics'] ?? '';

        // Create distractors from other vocabulary
        $distractors = array_slice($vocabulary, 1, 3);
        $options = [$translation];

        foreach ($distractors as $distractor) {
            $options[] = $distractor['translations'][0]['text'] ?? 'option';
        }

        // Shuffle options
        shuffle($options);
        $correctIndex = array_search($translation, $options);

        return [
            'title' => "What does '{$targetWord['text']}' mean?",
            'content' => [
                'question' => "What does '{$targetWord['text']}' ({$syllabics}) mean?",
                'options' => $options,
                'language' => 'crk',
                'target_word' => $targetWord['text'],
                'syllabics' => $syllabics,
                'audio_file' => "audio/crk/{$targetWord['text']}.mp3",
                'cultural_context' => $targetWord['metadata']['cultural_context'] ?? '',
                'clickable_vocabulary' => [
                    [
                        'word_id' => $targetWord['id'],
                        'text' => $targetWord['text'],
                        'syllabics' => $syllabics,
                        'translation' => $translation,
                        'pronunciation_key' => $targetWord['pronunciation_key'] ?? '',
                        'audio_url' => "audio/crk/{$targetWord['text']}.mp3",
                        'cultural_context' => $targetWord['metadata']['cultural_context'] ?? '',
                        'part_of_speech' => $targetWord['part_of_speech'] ?? '',
                        'example' => $targetWord['metadata']['example'] ?? '',
                    ]
                ],
                'duolingo_features' => [
                    'hover_translations' => true,
                    'click_for_audio' => true,
                    'syllabics_toggle' => true,
                    'cultural_context_popup' => true,
                ],
            ],
            'answers' => [
                'correct' => $correctIndex,
                'explanation' => $targetWord['metadata']['cultural_context'] ?? '',
                'detailed_feedback' => [
                    'correct_response' => "Excellent! '{$targetWord['text']}' means '{$translation}'. " . ($targetWord['metadata']['cultural_context'] ?? ''),
                    'incorrect_response' => "Not quite. '{$targetWord['text']}' actually means '{$translation}'. Try listening to the pronunciation again.",
                ],
            ],
        ];
    }

    protected function createMatchingExercise(array $vocabulary): array
    {
        $pairs = [];
        $clickableVocabulary = [];

        foreach (array_slice($vocabulary, 0, 4) as $word) {
            $translation = $word['translations'][0]['text'] ?? 'unknown';
            $syllabics = $word['metadata']['syllabics'] ?? '';

            $pairs[] = [
                'source' => $word['text'] . ' (' . $syllabics . ')',
                'target' => $translation,
                'audio' => "audio/crk/{$word['text']}.mp3",
                'word_id' => $word['id'],
            ];

            $clickableVocabulary[] = [
                'word_id' => $word['id'],
                'text' => $word['text'],
                'syllabics' => $syllabics,
                'translation' => $translation,
                'pronunciation_key' => $word['pronunciation_key'] ?? '',
                'audio_url' => "audio/crk/{$word['text']}.mp3",
                'cultural_context' => $word['metadata']['cultural_context'] ?? '',
                'part_of_speech' => $word['part_of_speech'] ?? '',
            ];
        }

        return [
            'title' => 'Match Plains Cree words with their English meanings',
            'content' => [
                'instruction' => 'Match each Plains Cree word with its English translation',
                'pairs' => $pairs,
                'language' => 'crk',
                'show_syllabics' => true,
                'audio_support' => true,
                'clickable_vocabulary' => $clickableVocabulary,
                'duolingo_features' => [
                    'drag_and_drop' => true,
                    'audio_on_match' => true,
                    'syllabics_display' => true,
                    'hover_pronunciation' => true,
                ],
            ],
            'answers' => [
                'correct_pairs' => array_column($pairs, 'target', 'source'),
                'feedback_on_match' => true,
            ],
        ];
    }

    protected function createFillBlankExercise(array $vocabulary): array
    {
        $targetWord = $vocabulary[0];
        $example = $targetWord['metadata']['example'] ?? "{$targetWord['text']} ôma.";
        $blankedSentence = str_replace($targetWord['text'], '____', $example);
        $translation = $targetWord['translations'][0]['text'] ?? '';
        $syllabics = $targetWord['metadata']['syllabics'] ?? '';

        // Create clickable vocabulary for other words in the sentence
        $clickableVocabulary = [
            [
                'word_id' => $targetWord['id'],
                'text' => $targetWord['text'],
                'syllabics' => $syllabics,
                'translation' => $translation,
                'pronunciation_key' => $targetWord['pronunciation_key'] ?? '',
                'audio_url' => "audio/crk/{$targetWord['text']}.mp3",
                'cultural_context' => $targetWord['metadata']['cultural_context'] ?? '',
                'part_of_speech' => $targetWord['part_of_speech'] ?? '',
                'is_target' => true,
            ]
        ];

        return [
            'title' => 'Complete the Plains Cree sentence',
            'content' => [
                'sentence' => $blankedSentence,
                'instruction' => 'Fill in the blank with the correct Plains Cree word',
                'hint' => $translation,
                'syllabics_hint' => $syllabics,
                'audio_sentence' => "audio/crk/sentences/{$targetWord['id']}.mp3",
                'clickable_vocabulary' => $clickableVocabulary,
                'duolingo_features' => [
                    'word_bank' => false, // Type the answer
                    'syllabics_input' => true,
                    'audio_replay' => true,
                    'hint_system' => true,
                    'progressive_hints' => [
                        "The word means '{$translation}'",
                        "In syllabics: {$syllabics}",
                        "Pronunciation: {$targetWord['pronunciation_key'] ?? ''}",
                    ],
                ],
                'sentence_context' => [
                    'full_sentence' => $example,
                    'english_translation' => $this->translateSentence($example),
                    'cultural_notes' => $targetWord['metadata']['cultural_context'] ?? '',
                ],
            ],
            'answers' => [
                'correct' => [$targetWord['text'], $syllabics],
                'accept_syllabics' => true,
                'case_sensitive' => false,
                'partial_credit' => true,
                'feedback' => [
                    'correct' => "Perfect! '{$targetWord['text']}' ({$syllabics}) means '{$translation}'.",
                    'incorrect' => "Try again. Listen to the audio and think about the meaning: '{$translation}'.",
                    'hint_used' => "Good job using the hints! '{$targetWord['text']}' is the correct answer.",
                ],
            ],
        ];
    }

    protected function createListeningExercise(array $vocabulary): array
    {
        $targetWord = $vocabulary[0];
        $options = array_slice(array_column($vocabulary, 'text'), 0, 4);
        shuffle($options);
        $correctIndex = array_search($targetWord['text'], $options);

        return [
            'title' => 'Listen and choose the correct word',
            'content' => [
                'instruction' => 'Listen to the Plains Cree word and choose the correct spelling',
                'audio_file' => "audio/crk/{$targetWord['text']}.mp3",
                'options' => $options,
                'show_syllabics' => true,
                'repeat_allowed' => true,
            ],
            'answers' => [
                'correct' => $correctIndex,
                'audio_word' => $targetWord['text'],
            ],
        ];
    }

    protected function createSpeakingExercise(array $vocabulary): array
    {
        $targetWord = $vocabulary[0];

        return [
            'title' => 'Practice saying the Plains Cree word',
            'content' => [
                'instruction' => 'Listen to the word and practice saying it',
                'target_word' => $targetWord['text'],
                'syllabics' => $targetWord['metadata']['syllabics'] ?? '',
                'pronunciation_guide' => $targetWord['pronunciation_key'] ?? '',
                'audio_model' => "audio/crk/{$targetWord['text']}.mp3",
                'cultural_context' => $targetWord['metadata']['cultural_context'] ?? '',
                'recording_required' => true,
            ],
            'answers' => [
                'target_pronunciation' => $targetWord['pronunciation_key'] ?? '',
                'accept_variations' => true,
            ],
        ];
    }

    protected function createConversationExercise(array $vocabulary): array
    {
        $targetWord = $vocabulary[0];

        return [
            'title' => 'Use the word in conversation',
            'content' => [
                'instruction' => 'Complete this conversation using the Plains Cree word',
                'dialogue' => [
                    ['speaker' => 'A', 'text' => 'Tânisi! (Hello!)'],
                    ['speaker' => 'B', 'text' => 'Tânisi! _____ cî? (Hello! Are you _____?)'],
                ],
                'target_word' => $targetWord['text'],
                'syllabics' => $targetWord['metadata']['syllabics'] ?? '',
                'context' => 'Greeting conversation',
                'cultural_notes' => 'Greetings are important in Plains Cree culture',
            ],
            'answers' => [
                'correct' => [$targetWord['text']],
                'context_appropriate' => true,
            ],
        ];
    }

    protected function createWritingExercise(array $vocabulary): array
    {
        $targetWord = $vocabulary[0];

        return [
            'title' => 'Write the Plains Cree word in syllabics',
            'content' => [
                'instruction' => 'Write this word using Plains Cree syllabics',
                'target_word' => $targetWord['text'],
                'english_meaning' => $targetWord['translations'][0]['text'] ?? '',
                'pronunciation_guide' => $targetWord['pronunciation_key'] ?? '',
                'audio_support' => "audio/crk/{$targetWord['text']}.mp3",
                'syllabics_keyboard' => true,
            ],
            'answers' => [
                'correct' => [$targetWord['metadata']['syllabics'] ?? ''],
                'accept_roman' => true,
                'case_sensitive' => false,
            ],
        ];
    }

    // Helper methods for UI elements
    protected function getTopicDescription(array $topicData): string
    {
        $title = $topicData['title'];

        $descriptions = [
            'Syllabic Writing System' => 'Learn the Plains Cree syllabic writing system and basic sound recognition',
            'Basic Greetings' => 'Master essential Plains Cree greetings and polite expressions',
            'Family Members' => 'Learn kinship terms and family relationships in Plains Cree culture',
            'Numbers 1-20' => 'Count from 1 to 20 in Plains Cree with cultural context',
            'Colors and Shapes' => 'Describe the world around you with Plains Cree color and shape words',
            'Daily Activities' => 'Express common daily activities and routines in Plains Cree',
        ];

        return $descriptions[$title] ?? "Learn about {$title} in Plains Cree";
    }

    protected function getLessonDescription(array $lessonData): string
    {
        $type = $lessonData['type'] ?? 'vocabulary';
        $title = $lessonData['title'];

        $typeDescriptions = [
            'vocabulary' => "Build your Plains Cree vocabulary with {$title}",
            'conversation' => "Practice conversational Plains Cree with {$title}",
            'pronunciation' => "Master Plains Cree pronunciation with {$title}",
            'grammar' => "Learn Plains Cree grammar through {$title}",
            'cultural' => "Explore Plains Cree culture through {$title}",
        ];

        return $typeDescriptions[$type] ?? "Learn Plains Cree through {$title}";
    }

    protected function getTopicIcon(string $title): string
    {
        $icons = [
            'Syllabic Writing System' => '📝',
            'Basic Greetings' => '👋',
            'Family Members' => '👨‍👩‍👧‍👦',
            'Numbers 1-20' => '🔢',
            'Colors and Shapes' => '🎨',
            'Daily Activities' => '🌅',
        ];

        return $icons[$title] ?? '📚';
    }

    protected function getTopicColor(int $unitIndex): string
    {
        $colors = [
            '#FF6B6B', // Red
            '#4ECDC4', // Teal
            '#45B7D1', // Blue
            '#96CEB4', // Green
            '#FFEAA7', // Yellow
            '#DDA0DD', // Plum
            '#98D8C8', // Mint
        ];

        return $colors[$unitIndex % count($colors)];
    }

    /**
     * Translate Plains Cree sentences to English (simplified)
     */
    protected function translateSentence(string $creeSentence): string
    {
        // This is a simplified translation helper
        // In a real application, this would use proper translation services
        $translations = [
            'Mama kîya cî?' => 'Are you my mother?',
            'Papa ayâw cî?' => 'Is dad there?',
            'Nôhkom âcimow.' => 'My grandmother is telling a story.',
            'Moshom kîkway kiskêyihtam.' => 'My grandfather knows things.',
            'Tanisi, nitôtem.' => 'Hello, my friend.',
            'Kîkway ôma?' => 'What is this?',
            'Êhâ, niwî-ayân.' => 'Yes, I want to go.',
            'Namôya, namôya niwî-ayân.' => 'No, I don\'t want to go.',
            'Kinanâskomitin, nôhkom.' => 'Thank you, grandmother.',
            'Wîcihiwin nitayân.' => 'I need help.',
            'Niwîkimâkan mîkwâc.' => 'My house is red.',
        ];

        return $translations[$creeSentence] ?? 'Translation not available';
    }

    /**
     * Get comprehensive Duolingo-style metadata for exercises
     */
    protected function getDuolingoStyleMetadata(): array
    {
        return [
            'clickable_vocabulary' => true,
            'hover_translations' => true,
            'audio_pronunciation' => true,
            'syllabics_display' => true,
            'cultural_context_popup' => true,
            'progressive_hints' => true,
            'visual_feedback' => true,
            'celebration_animations' => $this->ageGroup === 'kids',
            'streak_tracking' => true,
            'xp_animations' => true,
            'mistake_tracking' => true,
            'spaced_repetition' => true,
        ];
    }
}
