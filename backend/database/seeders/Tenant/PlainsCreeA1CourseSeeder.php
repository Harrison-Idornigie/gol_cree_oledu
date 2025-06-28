<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenants\User;
use Illuminate\Support\Facades\Auth;

class PlainsCreeA1CourseSeeder extends PlainsCreeBaseCourseSeeder
{
    protected string $level = 'A1';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Authenticate as the first user for content versioning
        $user = User::first();
        if ($user) {
            Auth::login($user);
        }

        $this->command->info('Creating Plains Cree A1 Course Content...');

        // Initialize languages and template
        $this->initializeLanguagesAndTemplate();

        // Load A1 vocabulary
        $this->loadA1Vocabulary();

        // Create the complete A1 learning path
        $learningPath = $this->createA1LearningPath();

        // Create units following the curriculum template
        $this->createUnitsFromTemplate($learningPath);

        $this->command->info('Plains Cree A1 course content created successfully!');
    }

    /**
     * Initialize languages and curriculum template
     */
    private function initializeLanguagesAndTemplate(): void
    {
        $this->plainsCree = Language::where('code', 'crk')->firstOrFail();
        $this->english = Language::where('code', 'en')->firstOrFail();

        $this->template = CurriculumTemplate::where('proficiency_level', 'A1')
            ->whereHas('languagePair', function ($query) {
                $query->where('source_language_id', $this->english->id)
                    ->where('target_language_id', $this->plainsCree->id);
            })
            ->firstOrFail();
    }

    /**
     * Load A1 level vocabulary from the database
     */
    private function loadA1Vocabulary(): void
    {
        $this->a1Vocabulary = Word::where('language_id', $this->plainsCree->id)
            ->whereJsonContains('metadata->proficiency_level', 'A1')
            ->with(['translations' => function ($query) {
                $query->where('language_id', $this->english->id);
            }])
            ->get()
            ->toArray();

        $this->command->info('Loaded ' . count($this->a1Vocabulary) . ' A1 vocabulary words');
    }

    /**
     * Create the A1 learning path
     */
    private function createA1LearningPath(): LearningPath
    {
        // Get or create language pair (English -> Plains Cree)
        $languagePairId = $this->getOrCreateLanguagePair();

        return LearningPath::updateOrCreate(
            [
                'title' => 'Plains Cree Foundations (A1) - nēhiyawēwin kiskēyihtamowin',
                'language_id' => $this->plainsCree->id, // Legacy support
            ],
            [
                'description' => 'Complete beginner Plains Cree course introducing syllabics, basic vocabulary, cultural protocols, and foundational language skills. Perfect for K-12 students and adult learners starting their Plains Cree journey.',
                'language_pair_id' => $languagePairId,
                'target_level' => 'A1',
                'status' => 'published',
                'review_status' => 'approved',
            ]
        );
    }

    /**
     * Create units from the curriculum template
     */
    private function createUnitsFromTemplate(LearningPath $learningPath): void
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
    private function createTopicsForUnit(Unit $unit, array $unitData, int $unitIndex): void
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
                    'xp_reward' => 10,
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
    private function createLessonsForTopic(Topic $topic, array $topicData, int $unitIndex, int $topicIndex): void
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
     * Create exercises for a lesson using actual Plains Cree vocabulary
     */
    private function createExercisesForLesson(Lesson $lesson, array $lessonData, int $unitIndex, int $topicIndex, int $lessonIndex): void
    {
        $exerciseCount = $lessonData['exercises'] ?? 5;
        $lessonType = $lessonData['type'] ?? 'vocabulary';

        // Get vocabulary subset for this lesson (3-5 words)
        $lessonVocabulary = $this->getVocabularyForLesson($unitIndex, $topicIndex, $lessonIndex, 5);

        if (empty($lessonVocabulary)) {
            $this->command->warn("      No vocabulary available for lesson: {$lessonData['title']}");
            return;
        }

        // Create different types of exercises based on lesson type
        for ($i = 1; $i <= $exerciseCount; $i++) {
            $exerciseType = $this->getExerciseType($lessonType, $i);
            $this->createExercise($lesson, $exerciseType, $lessonVocabulary, $i);
        }
    }

    /**
     * Get vocabulary for a specific lesson
     */
    private function getVocabularyForLesson(int $unitIndex, int $topicIndex, int $lessonIndex, int $count): array
    {
        // Calculate starting index to ensure vocabulary progression
        $startIndex = ($unitIndex * 10) + ($topicIndex * 5) + $lessonIndex;

        return array_slice($this->a1Vocabulary, $startIndex, $count);
    }

    /**
     * Determine exercise type based on lesson type and position
     */
    private function getExerciseType(string $lessonType, int $position): string
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
     * Create an exercise with actual Plains Cree content
     */
    private function createExercise(Lesson $lesson, string $type, array $vocabulary, int $order): void
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
                'difficulty_level' => 1, // A1 level
                'passing_score' => 70,
                'time_limit' => null,
                'max_attempts' => 3,
                'show_feedback' => true,
                'show_hints' => true,
                'xp_reward' => 5,
                'is_checkpoint' => false,
                'requires_previous' => false,
                'show_solutions_after' => 'attempt',
                'min_correct_required' => 1,
                'metadata' => [
                    'vocabulary_used' => array_column($vocabulary, 'id'),
                    'cultural_context' => true,
                    'syllabics_practice' => true,
                    'audio_support' => true,
                ],
            ]
        );
    }

    /**
     * Create multiple choice exercise with Plains Cree vocabulary
     */
    private function createMultipleChoiceExercise(array $vocabulary): array
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
            ],
            'answers' => [
                'correct' => $correctIndex,
                'explanation' => $targetWord['metadata']['cultural_context'] ?? '',
            ],
        ];
    }

    /**
     * Create matching exercise
     */
    private function createMatchingExercise(array $vocabulary): array
    {
        $pairs = [];
        foreach (array_slice($vocabulary, 0, 4) as $word) {
            $pairs[] = [
                'source' => $word['text'] . ' (' . ($word['metadata']['syllabics'] ?? '') . ')',
                'target' => $word['translations'][0]['text'] ?? 'unknown',
                'audio' => "audio/crk/{$word['text']}.mp3",
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
            ],
            'answers' => [
                'correct_pairs' => array_column($pairs, 'target', 'source'),
            ],
        ];
    }

    /**
     * Create fill-in-the-blank exercise
     */
    private function createFillBlankExercise(array $vocabulary): array
    {
        $targetWord = $vocabulary[0];
        $example = $targetWord['metadata']['example'] ?? "{$targetWord['text']} ôma.";
        $blankedSentence = str_replace($targetWord['text'], '____', $example);

        return [
            'title' => 'Complete the Plains Cree sentence',
            'content' => [
                'sentence' => $blankedSentence,
                'instruction' => 'Fill in the blank with the correct Plains Cree word',
                'hint' => $targetWord['translations'][0]['text'] ?? '',
                'syllabics_hint' => $targetWord['metadata']['syllabics'] ?? '',
                'audio_sentence' => "audio/crk/sentences/{$targetWord['id']}.mp3",
            ],
            'answers' => [
                'correct' => [$targetWord['text']],
                'accept_syllabics' => true,
                'case_sensitive' => false,
            ],
        ];
    }

    /**
     * Create listening exercise
     */
    private function createListeningExercise(array $vocabulary): array
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

    /**
     * Create speaking exercise
     */
    private function createSpeakingExercise(array $vocabulary): array
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

    /**
     * Create conversation exercise
     */
    private function createConversationExercise(array $vocabulary): array
    {
        $targetWord = $vocabulary[0];
        $example = $targetWord['metadata']['example'] ?? "Tânisi, {$targetWord['text']} cî?";

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

    /**
     * Create writing exercise
     */
    private function createWritingExercise(array $vocabulary): array
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

    /**
     * Get topic description based on topic data
     */
    private function getTopicDescription(array $topicData): string
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

    /**
     * Get lesson description based on lesson data
     */
    private function getLessonDescription(array $lessonData): string
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

    /**
     * Get topic icon based on title
     */
    private function getTopicIcon(string $title): string
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

    /**
     * Get topic color based on unit index
     */
    private function getTopicColor(int $unitIndex): string
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
     * Get or create language pair for Plains Cree (English -> Plains Cree)
     */
    private function getOrCreateLanguagePair(): int
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
}
