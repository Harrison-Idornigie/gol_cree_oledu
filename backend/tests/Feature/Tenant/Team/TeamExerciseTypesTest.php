<?php

namespace Tests\Feature\Tenant\Team;

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
use App\Models\Tenants\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Comprehensive tests for Team exercise type creation and management
 */
class TeamExerciseTypesTest extends TenantTestCase
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
     * Create predefined Plains Cree words for testing
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
     * Test team can create multiple choice exercise
     */
    public function test_team_can_create_multiple_choice_exercise()
    {
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        $exerciseData = [
            'title' => 'Multiple Choice: Plains Cree Greetings',
            'description' => 'Choose the correct English translation',
            'lesson_id' => $this->lesson->id,
            'type' => 'multiple_choice',
            'content' => [
                'question' => 'What does "Tansi" mean in English?',
                'options' => ['Hello', 'Goodbye', 'Thank you', 'Please'],
                'explanation' => 'Tansi is a common greeting in Plains Cree',
                'word_references' => [
                    ['word_id' => $this->getPredefinedWord('tansi')->id, 'position' => 'question']
                ]
            ],
            'answers' => [
                'correct' => 'Hello'
            ]
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/exercises", $exerciseData);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'multiple_choice')
            ->assertJsonPath('data.content.question', 'What does "Tansi" mean in English?')
            ->assertJsonCount(4, 'data.content.options');

        $exerciseId = $response->json('data.id');

        // Verify team can view the exercise
        $response = $this->getJson("/api/{$this->tenant->slug}/team/exercises/{$exerciseId}");
        $response->assertStatus(200)
            ->assertJsonPath('data.type', 'multiple_choice')
            ->assertJsonPath('data.content.question', 'What does "Tansi" mean in English?');

        // Verify in database
        $this->runInTenantContext($this->tenant, function () {
            $this->assertDatabaseHas('exercises', [
                'title' => 'Multiple Choice: Plains Cree Greetings',
                'type' => 'multiple_choice',
                'lesson_id' => $this->lesson->id,
            ]);
        });
    }

    /**
     * Test team can create fill in the blank exercise
     */
    public function test_team_can_create_fill_blank_exercise()
    {
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        $exerciseData = [
            'title' => 'Fill in the Blank: Plains Cree Sentence',
            'description' => 'Complete the sentence with the correct word',
            'lesson_id' => $this->lesson->id,
            'type' => 'fill_blank',
            'content' => [
                'text' => '____ means hello in Plains Cree.',
                'blanks' => [
                    [
                        'position' => 0,
                        'correct_answer' => 'Tansi',
                        'alternatives' => ['tansi', 'TANSI']
                    ]
                ],
                'instructions' => 'Fill in the blank with the correct Plains Cree word'
            ],
            'answers' => [
                'correct' => ['Tansi'],
                'alternatives' => ['tansi', 'TANSI']
            ]
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/exercises", $exerciseData);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'fill_blank')
            ->assertJsonPath('data.content.text', '____ means hello in Plains Cree.');

        // Verify in database
        $this->runInTenantContext($this->tenant, function () {
            $this->assertDatabaseHas('exercises', [
                'title' => 'Fill in the Blank: Plains Cree Sentence',
                'type' => 'fill_blank',
                'lesson_id' => $this->lesson->id,
            ]);
        });
    }

    /**
     * Test team can create matching exercise
     */
    public function test_team_can_create_matching_exercise()
    {
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        $exerciseData = [
            'title' => 'Matching: Plains Cree Vocabulary',
            'description' => 'Match Plains Cree words with their English translations',
            'lesson_id' => $this->lesson->id,
            'type' => 'matching',
            'content' => [
                'instructions' => 'Match each Plains Cree word with its English translation',
                'pairs' => [
                    ['source' => 'Tansi', 'target' => 'Hello'],
                    ['source' => 'Ekosi', 'target' => 'That\'s all/Goodbye'],
                    ['source' => 'Miyo-kisikaw', 'target' => 'Good day'],
                    ['source' => 'Kitatamihin', 'target' => 'See you later']
                ]
            ],
            'answers' => [
                'correct_pairs' => [
                    ['Tansi', 'Hello'],
                    ['Ekosi', 'That\'s all/Goodbye'],
                    ['Miyo-kisikaw', 'Good day'],
                    ['Kitatamihin', 'See you later']
                ]
            ]
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/exercises", $exerciseData);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'matching')
            ->assertJsonCount(4, 'data.content.pairs');

        // Verify in database
        $this->runInTenantContext($this->tenant, function () {
            $this->assertDatabaseHas('exercises', [
                'title' => 'Matching: Plains Cree Vocabulary',
                'type' => 'matching',
                'lesson_id' => $this->lesson->id,
            ]);
        });
    }

    /**
     * Test team can create writing exercise
     */
    public function test_team_can_create_writing_exercise()
    {
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        $exerciseData = [
            'title' => 'Writing: Compose a Plains Cree Greeting',
            'description' => 'Write a sentence using Plains Cree greetings (word selection)',
            'lesson_id' => $this->lesson->id,
            'type' => 'writing',
            'content' => [
                'prompt' => 'Write a sentence in Plains Cree that includes a greeting and asks how someone is doing.',
                'min_words' => 3,
                'max_words' => 10,
                'instructions' => 'Students will select words in the correct order to form their sentence',
                'available_words' => [  // Words students can select from
                    ['id' => $this->getPredefinedWord('tansi')->id, 'text' => 'tansi'],
                    ['id' => $this->getPredefinedWord('tansiya')->id, 'text' => 'tansiya'],
                    ['id' => $this->getPredefinedWord('kiya')->id, 'text' => 'kiya']
                ],
                'word_references' => [
                    ['word_id' => $this->getPredefinedWord('tansi')->id, 'position' => 'available_words'],
                    ['word_id' => $this->getPredefinedWord('tansiya')->id, 'position' => 'available_words'],
                    ['word_id' => $this->getPredefinedWord('kiya')->id, 'position' => 'available_words']
                ]
            ],
            'answers' => [
                'correct' => ['tansi', 'tansiya', 'kiya'],  // Expected word order
                'alternative_orders' => [  // Accept multiple valid orderings
                    ['tansi', 'kiya', 'tansiya']
                ]
            ]
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/exercises", $exerciseData);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'writing')
            ->assertJsonPath('data.content.min_words', 3)
            ->assertJsonPath('data.content.max_words', 10);

        // Verify in database
        $this->runInTenantContext($this->tenant, function () {
            $this->assertDatabaseHas('exercises', [
                'title' => 'Writing: Compose a Plains Cree Greeting',
                'type' => 'writing',
                'lesson_id' => $this->lesson->id,
            ]);
        });
    }

    /**
     * Test team can create speaking exercise
     */
    public function test_team_can_create_speaking_exercise()
    {
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        $exerciseData = [
            'title' => 'Speaking: Pronounce Plains Cree Greetings',
            'description' => 'Practice pronunciation of common Plains Cree greetings',
            'lesson_id' => $this->lesson->id,
            'type' => 'speaking',
            'content' => [
                'text_to_speak' => 'Tansi, tansiya kiya?',
                'pronunciation_guide' => '/ˈtansi ˈtansija ˈkija/',
                'instructions' => 'Listen to the example and then record yourself saying the phrase',
                'example_audio_url' => '/audio/examples/tansi-tansiya-kiya.mp3'
            ],
            'answers' => [
                'target_pronunciation' => '/ˈtansi ˈtansija ˈkija/',
                'acceptable_variations' => [
                    '/ˈtansi ˈtansijə ˈkijə/',
                    '/ˈtansi ˈtansi ˈkija/'
                ]
            ]
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/exercises", $exerciseData);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'speaking')
            ->assertJsonPath('data.content.text_to_speak', 'Tansi, tansiya kiya?');

        // Verify in database
        $this->runInTenantContext($this->tenant, function () {
            $this->assertDatabaseHas('exercises', [
                'title' => 'Speaking: Pronounce Plains Cree Greetings',
                'type' => 'speaking',
                'lesson_id' => $this->lesson->id,
            ]);
        });
    }

    /**
     * Test team can create conversation exercise
     */
    public function test_team_can_create_conversation_exercise()
    {
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        $exerciseData = [
            'title' => 'Conversation: Meeting Someone New',
            'description' => 'Practice a conversation in Plains Cree when meeting someone new',
            'lesson_id' => $this->lesson->id,
            'type' => 'conversation',
            'content' => [
                'title' => 'Meeting Someone New',
                'description' => 'You are meeting someone for the first time. Practice the conversation.',
                'steps' => [
                    [
                        'type' => 'dialogue',
                        'content' => [
                            'speaker' => 'Person A',
                            'text' => 'Tansi!',
                            'translation' => 'Hello!',
                            'audio_url' => '/audio/conversation/step1.mp3'
                        ]
                    ],
                    [
                        'type' => 'choice',
                        'content' => [
                            'question' => 'How do you respond?',
                            'options' => ['Tansi!', 'Ekosi', 'Miyo-kisikaw'],
                            'correct_option' => 'Tansi!'
                        ]
                    ]
                ]
            ],
            'answers' => [
                'conversation_flow' => [
                    'step_1' => 'listen',
                    'step_2' => 'Tansi!'
                ]
            ]
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/exercises", $exerciseData);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'conversation')
            ->assertJsonCount(2, 'data.content.steps');

        // Verify in database
        $this->runInTenantContext($this->tenant, function () {
            $this->assertDatabaseHas('exercises', [
                'title' => 'Conversation: Meeting Someone New',
                'type' => 'conversation',
                'lesson_id' => $this->lesson->id,
            ]);
        });
    }

    /**
     * Test team can create listening exercise
     */
    public function test_team_can_create_listening_exercise()
    {
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        $exerciseData = [
            'title' => 'Listening: Plains Cree Pronunciation',
            'description' => 'Listen and type what you hear',
            'lesson_id' => $this->lesson->id,
            'type' => 'listening',
            'content' => [
                'audio_url' => '/audio/listening/tansi-example.mp3',
                'transcript' => 'Tansi, tansiya kiya?',
                'prompt' => 'Listen to the audio and type exactly what you hear',
                'language' => 'crk',
                'difficulty' => 'beginner'
            ],
            'answers' => [
                'correct' => ['Tansi, tansiya kiya?'],
                'alternatives' => ['tansi, tansiya kiya?', 'Tansi tansiya kiya']
            ]
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/exercises", $exerciseData);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'listening')
            ->assertJsonPath('data.content.transcript', 'Tansi, tansiya kiya?');

        // Verify in database
        $this->runInTenantContext($this->tenant, function () {
            $this->assertDatabaseHas('exercises', [
                'title' => 'Listening: Plains Cree Pronunciation',
                'type' => 'listening',
                'lesson_id' => $this->lesson->id,
            ]);
        });
    }

    /**
     * Test team can create picture exercise
     */
    public function test_team_can_create_picture_exercise()
    {
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        $exerciseData = [
            'title' => 'Picture: Identify Plains Cree Words',
            'description' => 'Look at the images and select the correct Plains Cree word',
            'lesson_id' => $this->lesson->id,
            'type' => 'picture',
            'content' => [
                'question' => 'Which image represents "miskot" (red)?',
                'mode' => 'word_to_image',
                'images' => [
                    ['url' => '/images/red_flower.jpg', 'alt' => 'Red flower'],
                    ['url' => '/images/blue_sky.jpg', 'alt' => 'Blue sky'],
                    ['url' => '/images/green_tree.jpg', 'alt' => 'Green tree'],
                    ['url' => '/images/yellow_sun.jpg', 'alt' => 'Yellow sun']
                ],
                'target_word' => 'miskot',
                'language' => 'crk'
            ],
            'answers' => [
                'correct' => '/images/red_flower.jpg'
            ]
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/exercises", $exerciseData);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'picture')
            ->assertJsonCount(4, 'data.content.images');

        // Verify in database
        $this->runInTenantContext($this->tenant, function () {
            $this->assertDatabaseHas('exercises', [
                'title' => 'Picture: Identify Plains Cree Words',
                'type' => 'picture',
                'lesson_id' => $this->lesson->id,
            ]);
        });
    }

    /**
     * Test exercise type validation
     */
    public function test_exercise_type_validation()
    {
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        // Test invalid exercise type
        $response = $this->postJson("/api/{$this->tenant->slug}/team/exercises", [
            'title' => 'Invalid Exercise',
            'lesson_id' => $this->lesson->id,
            'type' => 'invalid_type',
            'content' => ['test' => 'content']
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);

        // Test all valid exercise types
        $validTypes = [
            'multiple_choice',
            'fill_blank',
            'matching',
            'writing',
            'speaking',
            'conversation',
            'listening',
            'picture'
        ];

        foreach ($validTypes as $type) {
            $response = $this->postJson("/api/{$this->tenant->slug}/team/exercises", [
                'title' => "Test {$type} Exercise",
                'lesson_id' => $this->lesson->id,
                'type' => $type,
                'content' => $this->getMinimalValidContent($type)
            ]);
            $response->assertStatus(201);
        }
    }

    /**
     * Test team can update exercises of different types
     */
    public function test_team_can_update_exercises_of_different_types()
    {
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        // Create a multiple choice exercise
        $exerciseData = [
            'title' => 'Original Title',
            'lesson_id' => $this->lesson->id,
            'type' => 'multiple_choice',
            'content' => [
                'question' => 'Original question?',
                'options' => ['A', 'B', 'C', 'D']
            ]
        ];

        $response = $this->postJson("/api/{$this->tenant->slug}/team/exercises", $exerciseData);
        $response->assertStatus(201);
        $exerciseId = $response->json('data.id');

        // Update the exercise
        $updateData = [
            'title' => 'Updated Title',
            'content' => [
                'question' => 'Updated question?',
                'options' => ['Option 1', 'Option 2', 'Option 3', 'Option 4']
            ]
        ];

        $response = $this->putJson("/api/{$this->tenant->slug}/team/exercises/{$exerciseId}", $updateData);
        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Updated Title')
            ->assertJsonPath('data.content.question', 'Updated question?');

        // Verify in database
        $this->runInTenantContext($this->tenant, function () use ($exerciseId) {
            $this->assertDatabaseHas('exercises', [
                'id' => $exerciseId,
                'title' => 'Updated Title',
            ]);
        });
    }

    /**
     * Test team can delete exercises of all types
     */
    public function test_team_can_delete_exercises_of_all_types()
    {
        Sanctum::actingAs($this->teamMember, ['*'], 'tenant');

        $exerciseTypes = ['multiple_choice', 'fill_blank', 'writing'];

        foreach ($exerciseTypes as $type) {
            // Create exercise
            $exerciseData = [
                'title' => "Test {$type} Exercise",
                'lesson_id' => $this->lesson->id,
                'type' => $type,
                'content' => $this->getMinimalValidContent($type)
            ];

            $response = $this->postJson("/api/{$this->tenant->slug}/team/exercises", $exerciseData);
            $response->assertStatus(201);
            $exerciseId = $response->json('data.id');

            // Delete exercise
            $response = $this->deleteJson("/api/{$this->tenant->slug}/team/exercises/{$exerciseId}");
            $response->assertStatus(204);

            // Verify deletion
            $this->runInTenantContext($this->tenant, function () use ($exerciseId) {
                $this->assertDatabaseMissing('exercises', [
                    'id' => $exerciseId,
                ]);
            });
        }
    }

    /**
     * Test students cannot access team exercise endpoints
     */
    public function test_students_cannot_access_team_exercise_endpoints()
    {
        Sanctum::actingAs($this->studentUser, ['*'], 'tenant');

        // Test various endpoints
        $response = $this->getJson("/api/{$this->tenant->slug}/team/exercises");
        $response->assertStatus(403);

        $response = $this->postJson("/api/{$this->tenant->slug}/team/exercises", [
            'title' => 'Test Exercise',
            'lesson_id' => $this->lesson->id,
            'type' => 'multiple_choice',
            'content' => ['question' => 'Test question'],
        ]);
        $response->assertStatus(403);
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
}
