<?php

namespace Tests\Feature\Tenant\Student;

use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use App\Models\Tenants\Language;
use App\Models\Tenants\LearningPath;
use App\Models\Tenants\Unit;
use App\Models\Tenants\Topic;
use App\Models\Tenants\Lesson;
use App\Models\Tenants\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Comprehensive tests for Student exercise type completion and interaction
 */
class StudentExerciseTypesTest extends TenantTestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $teamMember;
    protected User $studentUser;
    protected Language $language;
    protected LearningPath $learningPath;
    protected Unit $unit;
    protected Topic $topic;
    protected Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        // Create test tenant
        $this->tenant = $this->createTestTenant();

        // Create test users in tenant context
        $this->teamMember = $this->createTenantTeam();
        $this->studentUser = $this->createTenantStudent();

        // Create the learning structure
        $this->createLearningStructure();

        // Create predefined words for testing
        $this->createPredefinedWords();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    protected function createLearningStructure(): void
    {
        $this->runInTenantContext($this->tenant, function () {
            $this->language = Language::create([
                'name' => 'Plains Cree',
                'code' => 'crk',
                'native_name' => 'nêhiyawêwin',
                'direction' => 'ltr',
                'status' => 'active',
            ]);

            $this->learningPath = LearningPath::create([
                'title' => 'Plains Cree for Beginners',
                'description' => 'Learn Plains Cree from scratch',
                'language_id' => $this->language->id,
                'target_level' => 'beginner',
                'status' => 'published',
                'created_by' => $this->teamMember->id,
            ]);

            $this->unit = Unit::create([
                'title' => 'Unit 1: Basics',
                'description' => 'Basic Plains Cree vocabulary and phrases',
                'learning_path_id' => $this->learningPath->id,
                'order' => 1,
                'status' => 'published',
            ]);

            $this->topic = Topic::create([
                'title' => 'Greetings',
                'slug' => 'greetings',
                'description' => 'Basic Plains Cree greetings',
                'unit_id' => $this->unit->id,
                'order' => 1,
                'status' => 'published',
            ]);

            $this->lesson = Lesson::create([
                'title' => 'Lesson 1: Basic Greetings',
                'description' => 'Learn basic Plains Cree greetings',
                'topic_id' => $this->topic->id,
                'order' => 1,
                'status' => 'published',
                'created_by' => $this->teamMember->id,
            ]);
        });
    }

    /**
     * Create predefined words for testing
     */
    protected function createPredefinedWords(): void
    {
        $this->runInTenantContext($this->tenant, function () {
            // Create English language for source
            $english = Language::create([
                'name' => 'English',
                'code' => 'en',
                'native_name' => 'English',
                'is_active' => true,
            ]);

            // Create predefined Plains Cree words
            $creeWords = [
                [
                    'text' => 'tansi',
                    'pronunciation_key' => 'TAN-see',
                    'part_of_speech' => 'interjection',
                    'metadata' => [
                        'difficulty' => 'beginner',
                        'tags' => ['greeting', 'common'],
                        'syllabics' => 'ᑕᓯ',
                        'translation' => 'hello',
                        'cultural_context' => 'Common Plains Cree greeting'
                    ]
                ],
                [
                    'text' => 'ekosi',
                    'pronunciation_key' => 'eh-KOH-see',
                    'part_of_speech' => 'particle',
                    'metadata' => [
                        'difficulty' => 'beginner',
                        'tags' => ['common', 'conclusion'],
                        'syllabics' => 'ᐁᑯᓯ',
                        'translation' => 'that\'s all, goodbye',
                        'cultural_context' => 'Used to end conversations or indicate completion'
                    ]
                ],
                [
                    'text' => 'miyo-kisikaw',
                    'pronunciation_key' => 'MEE-yoh KEE-see-kaw',
                    'part_of_speech' => 'noun',
                    'metadata' => [
                        'difficulty' => 'beginner',
                        'tags' => ['greeting', 'time'],
                        'syllabics' => 'ᒥᔪ ᑭᓯᑲᐤ',
                        'translation' => 'good day',
                        'cultural_context' => 'Formal daytime greeting'
                    ]
                ],
                [
                    'text' => 'kitatamihin',
                    'pronunciation_key' => 'kee-TAH-tah-mee-hin',
                    'part_of_speech' => 'verb',
                    'metadata' => [
                        'difficulty' => 'intermediate',
                        'tags' => ['parting', 'future'],
                        'syllabics' => 'ᑭᑕᑕᒥᐦᐃᐣ',
                        'translation' => 'see you later',
                        'cultural_context' => 'Common parting phrase'
                    ]
                ],
                [
                    'text' => 'miskot',
                    'pronunciation_key' => 'MIS-kot',
                    'part_of_speech' => 'adjective',
                    'metadata' => [
                        'difficulty' => 'beginner',
                        'tags' => ['color', 'descriptive'],
                        'syllabics' => 'ᒥᐢᑯᐟ',
                        'translation' => 'red',
                        'cultural_context' => 'Basic color term'
                    ]
                ],
                [
                    'text' => 'tansiya',
                    'pronunciation_key' => 'tan-SEE-yah',
                    'part_of_speech' => 'particle',
                    'metadata' => [
                        'difficulty' => 'beginner',
                        'tags' => ['question', 'greeting'],
                        'syllabics' => 'ᑕᓯᔭ',
                        'translation' => 'how are you',
                        'cultural_context' => 'Common greeting question'
                    ]
                ],
                [
                    'text' => 'kiya',
                    'pronunciation_key' => 'KEE-yah',
                    'part_of_speech' => 'pronoun',
                    'metadata' => [
                        'difficulty' => 'beginner',
                        'tags' => ['pronoun', 'common'],
                        'syllabics' => 'ᑭᔭ',
                        'translation' => 'you',
                        'cultural_context' => 'Second person pronoun'
                    ]
                ]
            ];

            foreach ($creeWords as $wordData) {
                \App\Models\Tenants\Word::create([
                    'language_id' => $this->language->id,
                    'text' => $wordData['text'],
                    'pronunciation_key' => $wordData['pronunciation_key'],
                    'part_of_speech' => $wordData['part_of_speech'],
                    'metadata' => $wordData['metadata'],
                ]);
            }

            // Create corresponding English words for translations
            $englishWords = [
                [
                    'text' => 'hello',
                    'pronunciation_key' => 'heh-LOH',
                    'part_of_speech' => 'interjection',
                    'metadata' => ['difficulty' => 'beginner', 'tags' => ['greeting']]
                ],
                [
                    'text' => 'goodbye',
                    'pronunciation_key' => 'good-BYE',
                    'part_of_speech' => 'interjection',
                    'metadata' => ['difficulty' => 'beginner', 'tags' => ['parting']]
                ],
                [
                    'text' => 'good day',
                    'pronunciation_key' => 'good DAY',
                    'part_of_speech' => 'noun phrase',
                    'metadata' => ['difficulty' => 'beginner', 'tags' => ['greeting']]
                ],
                [
                    'text' => 'see you later',
                    'pronunciation_key' => 'see YOO LAY-ter',
                    'part_of_speech' => 'phrase',
                    'metadata' => ['difficulty' => 'beginner', 'tags' => ['parting']]
                ],
                [
                    'text' => 'red',
                    'pronunciation_key' => 'red',
                    'part_of_speech' => 'adjective',
                    'metadata' => ['difficulty' => 'beginner', 'tags' => ['color']]
                ],
                [
                    'text' => 'how are you',
                    'pronunciation_key' => 'how ar YOO',
                    'part_of_speech' => 'phrase',
                    'metadata' => ['difficulty' => 'beginner', 'tags' => ['question']]
                ],
                [
                    'text' => 'you',
                    'pronunciation_key' => 'YOO',
                    'part_of_speech' => 'pronoun',
                    'metadata' => ['difficulty' => 'beginner', 'tags' => ['pronoun']]
                ]
            ];

            foreach ($englishWords as $wordData) {
                \App\Models\Tenants\Word::create([
                    'language_id' => $english->id,
                    'text' => $wordData['text'],
                    'pronunciation_key' => $wordData['pronunciation_key'],
                    'part_of_speech' => $wordData['part_of_speech'],
                    'metadata' => $wordData['metadata'],
                ]);
            }
        });
    }

    /**
     * Get a predefined word by text
     */
    protected function getPredefinedWord(string $text): \App\Models\Tenants\Word
    {
        return $this->runInTenantContext($this->tenant, function () use ($text) {
            return \App\Models\Tenants\Word::where('text', $text)->first();
        });
    }

    /**
     * Create a test exercise using predefined words
     */
    protected function createTestExercise(string $type, array $content, array $answers = []): Exercise
    {
        return $this->runInTenantContext($this->tenant, function () use ($type, $content, $answers) {
            return Exercise::create([
                'title' => "Test {$type} Exercise",
                'slug' => 'test-' . $type . '-exercise-' . uniqid(),
                'description' => "A test exercise of type {$type}",
                'lesson_id' => $this->lesson->id,
                'type' => $type,
                'status' => 'published',
                'content' => $content,
                'answers' => $answers,
                'created_by' => $this->teamMember->id,
            ]);
        });
    }

    /**
     * Test student can complete multiple choice exercise
     */
    public function test_student_can_complete_multiple_choice_exercise()
    {
        $exercise = $this->createTestExercise('multiple_choice', [
            'question' => 'What does "Tansi" mean in English?',
            'options' => ['Hello', 'Goodbye', 'Thank you', 'Please'],
            'word_references' => [
                ['word_id' => $this->getPredefinedWord('tansi')->id, 'position' => 'question']
            ]
        ], [
            'correct' => 'Hello'
        ]);

        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        // Student views the exercise
        $response = $this->getJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}");
        $response->assertStatus(200)
            ->assertJsonPath('data.type', 'multiple_choice')
            ->assertJsonPath('data.content.question', 'What does "Tansi" mean in English?')
            ->assertJsonCount(4, 'data.content.options');

        // Student submits correct answer
        $response = $this->postJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}/check", [
            'answer' => 'Hello'
        ]);
        $response->assertStatus(200)
            ->assertJsonPath('data.is_correct', true)
            ->assertJsonPath('data.passed', true);

        // Student submits incorrect answer
        $response = $this->postJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}/check", [
            'answer' => 'Goodbye'
        ]);
        $response->assertStatus(200)
            ->assertJsonPath('data.is_correct', false)
            ->assertJsonPath('data.passed', false);
    }

    /**
     * Test student can complete fill in the blank exercise
     */
    public function test_student_can_complete_fill_blank_exercise()
    {
        $exercise = $this->createTestExercise('fill_blank', [
            'text' => '____ means hello in Plains Cree.',
            'blanks' => [
                [
                    'position' => 0,
                    'correct_answer' => 'tansi',
                    'alternatives' => ['Tansi', 'TANSI']
                ]
            ],
            'word_references' => [
                ['word_id' => $this->getPredefinedWord('tansi')->id, 'position' => 'text']
            ]
        ], [
            'correct' => ['tansi'],
            'alternatives' => ['Tansi', 'TANSI']
        ]);

        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        // Student submits correct answer
        $response = $this->postJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}/check", [
            'answer' => ['tansi']  // FillBlankHandler expects array
        ]);
        $response->assertStatus(200)
            ->assertJsonPath('data.is_correct', true);

        // Student submits alternative correct answer
        $response = $this->postJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}/check", [
            'answer' => ['Tansi']  // FillBlankHandler expects array
        ]);
        $response->assertStatus(200)
            ->assertJsonPath('data.is_correct', true);

        // Student submits incorrect answer
        $response = $this->postJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}/check", [
            'answer' => ['Hello']  // FillBlankHandler expects array
        ]);
        $response->assertStatus(200)
            ->assertJsonPath('data.is_correct', false);
    }

    /**
     * Test student can complete matching exercise
     */
    public function test_student_can_complete_matching_exercise()
    {
        $exercise = $this->createTestExercise('matching', [
            'instructions' => 'Match each Plains Cree word with its English translation',
            'pairs' => [
                ['source' => 'tansi', 'target' => 'hello'],
                ['source' => 'ekosi', 'target' => 'that\'s all/goodbye'],
                ['source' => 'miyo-kisikaw', 'target' => 'good day'],
                ['source' => 'kitatamihin', 'target' => 'see you later']
            ],
            'word_references' => [
                ['word_id' => $this->getPredefinedWord('tansi')->id, 'position' => 'source'],
                ['word_id' => $this->getPredefinedWord('ekosi')->id, 'position' => 'source'],
                ['word_id' => $this->getPredefinedWord('miyo-kisikaw')->id, 'position' => 'source'],
                ['word_id' => $this->getPredefinedWord('kitatamihin')->id, 'position' => 'source']
            ]
        ], [
            'correct' => [0 => 'hello', 1 => 'that\'s all/goodbye', 2 => 'good day', 3 => 'see you later']  // MatchingHandler format
        ]);

        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        // Student submits correct matching
        $response = $this->postJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}/check", [
            'answer' => [0 => 'hello', 1 => 'that\'s all/goodbye', 2 => 'good day', 3 => 'see you later']  // MatchingHandler format
        ]);
        $response->assertStatus(200)
            ->assertJsonPath('data.is_correct', true);

        // Student submits incorrect matching
        $response = $this->postJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}/check", [
            'answer' => [
                ['tansi', 'goodbye'],
                ['ekosi', 'hello'],
                ['miyo-kisikaw', 'good day'],
                ['kitatamihin', 'see you later']
            ]
        ]);
        $response->assertStatus(200)
            ->assertJsonPath('data.is_correct', false);
    }

    /**
     * Test student can submit writing exercise (word selection approach)
     * Students select words from a predefined list to construct sentences,
     * ensuring proper Plains Cree spelling and focusing on sentence structure.
     */
    public function test_student_can_submit_writing_exercise()
    {
        $exercise = $this->createTestExercise('writing', [
            'prompt' => 'Write a sentence in Plains Cree that includes a greeting and asks how someone is doing.',
            'min_words' => 3,
            'max_words' => 10,
            'instructions' => 'Select the words in the correct order to form your sentence',
            'available_words' => [  // Words student can select from
                ['id' => $this->getPredefinedWord('tansi')->id, 'text' => 'tansi'],
                ['id' => $this->getPredefinedWord('tansiya')->id, 'text' => 'tansiya'],
                ['id' => $this->getPredefinedWord('kiya')->id, 'text' => 'kiya']
            ],
            'word_references' => [
                ['word_id' => $this->getPredefinedWord('tansi')->id, 'position' => 'available_words'],
                ['word_id' => $this->getPredefinedWord('tansiya')->id, 'position' => 'available_words'],
                ['word_id' => $this->getPredefinedWord('kiya')->id, 'position' => 'available_words']
            ]
        ], [
            'correct' => ['tansi', 'tansiya', 'kiya'],  // Expected word order
            'alternative_orders' => [  // Accept multiple valid orderings
                ['tansi', 'kiya', 'tansiya']
            ]
        ]);

        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        // Student submits writing response by selecting words in order
        $response = $this->postJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}/check", [
            'answer' => ['tansi', 'tansiya', 'kiya']  // Student's selected word order
        ]);
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'is_correct',
                    'score',
                    'passed',
                    'feedback',
                    'attempt_number',
                    'exercise_completed'
                ]
            ]);
    }

    /**
     * Test student can submit speaking exercise
     */
    public function test_student_can_submit_speaking_exercise()
    {
        $exercise = $this->createTestExercise('speaking', [
            'text_to_speak' => 'tansi, tansiya kiya?',
            'pronunciation_guide' => '/ˈtansi ˈtansija ˈkija/',
            'instructions' => 'Listen to the example and then record yourself saying the phrase',
            'example_audio_url' => '/audio/examples/tansi-tansiya-kiya.mp3',
            'word_references' => [
                ['word_id' => $this->getPredefinedWord('tansi')->id, 'position' => 'text_to_speak'],
                ['word_id' => $this->getPredefinedWord('tansiya')->id, 'position' => 'text_to_speak'],
                ['word_id' => $this->getPredefinedWord('kiya')->id, 'position' => 'text_to_speak']
            ]
        ], [
            'target_pronunciation' => '/ˈtansi ˈtansija ˈkija/',
            'acceptable_variations' => [
                '/ˈtansi ˈtansijə ˈkijə/',
                '/ˈtansi ˈtansi ˈkija/'
            ]
        ]);

        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        // Student submits audio recording (simulated)
        $response = $this->postJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}/check", [
            'answer' => json_encode(['score' => 0.8, 'pronunciation_quality' => 'good'])  // SpeakingHandler expects JSON with score
        ]);
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'is_correct',
                    'score',
                    'passed',
                    'feedback',
                    'attempt_number',
                    'exercise_completed'
                ]
            ]);
    }

    /**
     * Test student can complete conversation exercise
     */
    public function test_student_can_complete_conversation_exercise()
    {
        $exercise = $this->createTestExercise('conversation', [
            'title' => 'Meeting Someone New',
            'description' => 'You are meeting someone for the first time. Practice the conversation.',
            'steps' => [
                [
                    'type' => 'dialogue',
                    'content' => [
                        'speaker' => 'Person A',
                        'text' => 'tansi!',
                        'translation' => 'Hello!',
                        'audio_url' => '/audio/conversation/step1.mp3'
                    ]
                ],
                [
                    'type' => 'choice',
                    'content' => [
                        'question' => 'How do you respond?',
                        'options' => ['tansi!', 'ekosi', 'miyo-kisikaw'],
                        'correct_option' => 'tansi!'
                    ]
                ]
            ],
            'word_references' => [
                ['word_id' => $this->getPredefinedWord('tansi')->id, 'position' => 'dialogue'],
                ['word_id' => $this->getPredefinedWord('ekosi')->id, 'position' => 'options'],
                ['word_id' => $this->getPredefinedWord('miyo-kisikaw')->id, 'position' => 'options']
            ]
        ], [
            'steps' => [
                1 => ['correct' => 'tansi!']
            ]
        ]);

        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        // Student progresses through conversation
        $response = $this->postJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}/check", [
            'answer' => [
                'step_index' => 1,
                'answer' => 'tansi!'
            ]
        ]);
        $response->assertStatus(200)
            ->assertJsonPath('data.is_correct', true);
    }

    /**
     * Test student can complete listening exercise
     */
    public function test_student_can_complete_listening_exercise()
    {
        $exercise = $this->createTestExercise('listening', [
            'audio_url' => '/audio/listening/tansi-example.mp3',
            'transcript' => 'tansi, tansiya kiya?',
            'prompt' => 'Listen to the audio and type exactly what you hear',
            'language' => 'crk',
            'difficulty' => 'beginner',
            'word_references' => [
                ['word_id' => $this->getPredefinedWord('tansi')->id, 'position' => 'transcript'],
                ['word_id' => $this->getPredefinedWord('tansiya')->id, 'position' => 'transcript'],
                ['word_id' => $this->getPredefinedWord('kiya')->id, 'position' => 'transcript']
            ]
        ], [
            'correct' => ['tansi, tansiya kiya?'],
            'alternatives' => ['Tansi, tansiya kiya?', 'tansi tansiya kiya']
        ]);

        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        // Student submits correct transcription
        $response = $this->postJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}/check", [
            'answer' => 'tansi, tansiya kiya?'
        ]);
        $response->assertStatus(200)
            ->assertJsonPath('data.is_correct', true);

        // Student submits alternative correct answer
        $response = $this->postJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}/check", [
            'answer' => 'Tansi, tansiya kiya?'
        ]);
        $response->assertStatus(200)
            ->assertJsonPath('data.is_correct', true);

        // Student submits incorrect transcription
        $response = $this->postJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}/check", [
            'answer' => 'Hello, how are you?'
        ]);
        $response->assertStatus(200)
            ->assertJsonPath('data.is_correct', false);
    }

    /**
     * Test student can complete picture exercise
     */
    public function test_student_can_complete_picture_exercise()
    {
        $exercise = $this->createTestExercise('picture', [
            'question' => 'Which image represents "miskot" (red)?',
            'mode' => 'word_to_image',
            'images' => [
                ['url' => '/images/red_flower.jpg', 'alt' => 'Red flower'],
                ['url' => '/images/blue_sky.jpg', 'alt' => 'Blue sky'],
                ['url' => '/images/green_tree.jpg', 'alt' => 'Green tree'],
                ['url' => '/images/yellow_sun.jpg', 'alt' => 'Yellow sun']
            ],
            'target_word' => 'miskot',
            'language' => 'crk',
            'word_references' => [
                ['word_id' => $this->getPredefinedWord('miskot')->id, 'position' => 'question']
            ]
        ], [
            'correct' => 0  // PictureHandler expects numeric index
        ]);

        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        // Student submits correct image selection
        $response = $this->postJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}/check", [
            'answer' => 0  // PictureHandler expects numeric index
        ]);
        $response->assertStatus(200)
            ->assertJsonPath('data.is_correct', true);

        // Student submits incorrect image selection
        $response = $this->postJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}/check", [
            'answer' => 1  // PictureHandler expects numeric index
        ]);
        $response->assertStatus(200)
            ->assertJsonPath('data.is_correct', false);
    }

    /**
     * Test student progress tracking across exercise types
     */
    public function test_student_progress_tracking_across_exercise_types()
    {
        // Create exercises of different types
        $exerciseTypes = ['multiple_choice', 'fill_blank', 'matching'];
        $exercises = [];

        foreach ($exerciseTypes as $type) {
            $content = $this->getMinimalValidContent($type);
            $answers = $this->getMinimalValidAnswers($type);
            $exercises[$type] = $this->createTestExercise($type, $content, $answers);
        }

        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        // Student completes exercises
        foreach ($exercises as $type => $exercise) {
            $answer = $this->getValidAnswerForType($type);
            $response = $this->postJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}/check", [
                'answer' => $answer
            ]);
            $response->assertStatus(200);
        }

        // Verify progress tracking
        $response = $this->getJson("/api/{$this->tenant->slug}/student/exercises");
        $response->assertStatus(200);
    }

    /**
     * Test student statistics for different exercise types
     */
    public function test_student_statistics_for_different_exercise_types()
    {
        $exercise = $this->createTestExercise('multiple_choice', [
            'question' => 'Test question?',
            'options' => ['Correct', 'Wrong1', 'Wrong2', 'Wrong3']
        ], [
            'correct' => 'Correct'
        ]);

        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        // Student attempts exercise multiple times
        // Correct attempt
        $this->postJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}/check", [
            'answer' => 'Correct'
        ]);

        // Incorrect attempt
        $this->postJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}/check", [
            'answer' => 'Wrong1'
        ]);

        // Get statistics
        $response = $this->getJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}/statistics");
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'exercise',
                    'statistics' => [
                        'total_attempts',
                        'best_score',
                        'average_score',
                        'completed',
                        'total_time_spent',
                        'improvement_trend',
                        'last_attempt_at'
                    ]
                ]
            ]);
    }

    /**
     * Test student cannot access unpublished exercises
     */
    public function test_student_cannot_access_unpublished_exercises()
    {
        $draftExercise = $this->runInTenantContext($this->tenant, function () {
            return Exercise::create([
                'title' => 'Draft Exercise',
                'slug' => 'draft-exercise-' . uniqid(),
                'description' => 'This is a draft exercise',
                'lesson_id' => $this->lesson->id,
                'type' => 'multiple_choice',
                'status' => 'draft', // Unpublished
                'content' => [
                    'question' => 'Draft question?',
                    'options' => ['A', 'B', 'C', 'D']
                ],
                'created_by' => $this->teamMember->id,
            ]);
        });

        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        $response = $this->getJson("/api/{$this->tenant->slug}/student/exercises/{$draftExercise->id}");
        $response->assertStatus(400);
    }

    /**
     * Test unauthenticated access is blocked
     */
    public function test_unauthenticated_access_blocked()
    {
        $exercise = $this->createTestExercise('multiple_choice', [
            'question' => 'Test?',
            'options' => ['A', 'B', 'C', 'D']
        ]);

        $response = $this->getJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}");
        $response->assertStatus(401);

        $response = $this->postJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}/check", [
            'answer' => 'A'
        ]);
        $response->assertStatus(401);
    }

    /**
     * Test exercise answer validation for different types
     */
    public function test_exercise_answer_validation_for_different_types()
    {
        $exercise = $this->createTestExercise('multiple_choice', [
            'question' => 'Test question?',
            'options' => ['A', 'B', 'C', 'D']
        ], [
            'correct' => 'A'  // Add required correct answer
        ]);

        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        // Test missing answer
        $response = $this->postJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}/check", []);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['answer']);

        // Test invalid answer format for multiple choice (should return false but with 200 status)
        $response = $this->postJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}/check", [
            'answer' => ['invalid', 'array']
        ]);
        $response->assertStatus(200)
            ->assertJsonPath('data.is_correct', false);  // Handler rejects invalid format gracefully
    }

    /**
     * Get minimal valid content for exercise type testing
     */
    private function getMinimalValidContent(string $type): array
    {
        return match ($type) {
            'multiple_choice' => [
                'question' => 'Test question?',
                'options' => ['Option 1', 'Option 2', 'Option 3', 'Option 4']
            ],
            'fill_blank' => [
                'text' => 'This is a ____ sentence.',
                'blanks' => [['position' => 0, 'correct_answer' => 'test']]
            ],
            'matching' => [
                'pairs' => [
                    ['source' => 'A', 'target' => '1'],
                    ['source' => 'B', 'target' => '2']
                ]
            ],
            'writing' => [
                'prompt' => 'Write a test sentence.'
            ],
            'speaking' => [
                'text_to_speak' => 'Test phrase'
            ],
            'conversation' => [
                'steps' => [
                    [
                        'type' => 'dialogue',
                        'content' => ['speaker' => 'A', 'text' => 'Hello']
                    ]
                ]
            ],
            'listening' => [
                'audio_url' => '/test.mp3',
                'transcript' => 'Test audio'
            ],
            'picture' => [
                'question' => 'Select the correct image',
                'images' => [['url' => '/test.jpg', 'alt' => 'Test']],
                'mode' => 'word_to_image'
            ],
            default => ['test' => 'content']
        };
    }

    /**
     * Get minimal valid answers for exercise type testing
     */
    private function getMinimalValidAnswers(string $type): array
    {
        return match ($type) {
            'multiple_choice' => ['correct' => 'Option 1'],
            'fill_blank' => ['correct' => ['test']],
            'matching' => ['correct_pairs' => [['A', '1'], ['B', '2']]],
            'writing' => ['sample_answers' => ['This is a test sentence.']],
            'speaking' => ['target_pronunciation' => '/test/'],
            'conversation' => ['conversation_flow' => ['step_1' => 'Hello']],
            'listening' => ['correct' => ['Test audio']],
            'picture' => ['correct' => '/test.jpg'],
            default => ['correct' => 'test answer']
        };
    }

    /**
     * Get valid answer for exercise type testing
     */
    private function getValidAnswerForType(string $type): mixed
    {
        return match ($type) {
            'multiple_choice' => 'Option 1',
            'fill_blank' => 'test',
            'matching' => [['A', '1'], ['B', '2']],
            'writing' => 'This is a test sentence.',
            'speaking' => ['audio_url' => '/test_recording.mp3'],
            'conversation' => ['responses' => ['step_1' => 'Hello']],
            'listening' => 'Test audio',
            'picture' => '/test.jpg',
            default => 'test answer'
        };
    }
}
