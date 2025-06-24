<?php

namespace Tests\Feature\Tenant\Student;

use Tests\TestCase;
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
use App\Models\Tenants\WordTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;

/**
 * Syllabics and Audio Support API Tests
 * 
 * Tests Plains Cree specific features including syllabics content display,
 * audio pronunciation support, and cultural context integration in API responses.
 */
class StudentSyllabicsAudioTest extends TestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $studentUser;
    protected User $teamUser;
    protected Language $plainsCreeLanguage;
    protected Language $englishLanguage;
    protected LearningPath $learningPath;
    protected array $testWords = [];
    protected array $testContent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        // Create test tenant
        $this->tenant = $this->createTestTenant();
        $this->initializeTenantContext($this->tenant);

        // Create users
        $this->studentUser = $this->createTenantStudent();
        $this->teamUser = $this->createTenantTeam();

        // Create test environment
        $this->createTestLanguages();
        $this->createSyllabicsVocabulary();
        $this->createSyllabicsContent();
    }

    protected function createTestLanguages(): void
    {
        $this->runInTenantContext($this->tenant, function () {
            $this->plainsCreeLanguage = Language::create([
                'name' => 'Plains Cree',
                'code' => 'crk',
                'native_name' => 'nēhiyawēwin',
                'is_active' => true,
                'metadata' => [
                    'writing_system' => 'syllabics',
                    'has_audio' => true,
                    'cultural_context' => 'indigenous',
                    'syllabics_support' => true,
                    'audio_features' => [
                        'pronunciation_guide' => true,
                        'slow_speech' => true,
                        'elder_voices' => true,
                        'regional_dialects' => true
                    ],
                    'cultural_features' => [
                        'traditional_knowledge' => true,
                        'ceremony_context' => true,
                        'land_based_learning' => true
                    ]
                ]
            ]);

            $this->englishLanguage = Language::create([
                'name' => 'English',
                'code' => 'en',
                'native_name' => 'English',
                'is_active' => true,
                'metadata' => [
                    'writing_system' => 'latin',
                    'has_audio' => true
                ]
            ]);
        });
    }

    protected function createSyllabicsVocabulary(): void
    {
        $this->runInTenantContext($this->tenant, function () {
            $vocabularyData = [
                [
                    'text' => 'tanisi',
                    'syllabics' => 'ᑕᓂᓯ',
                    'syllabics_breakdown' => ['ᑕ', 'ᓂ', 'ᓯ'],
                    'pronunciation_guide' => 'tah-ni-see',
                    'translations' => ['hello', 'how are you'],
                    'cultural_context' => 'Traditional greeting used in all social situations',
                    'ceremony_usage' => 'Used to open ceremonies and gatherings'
                ],
                [
                    'text' => 'atim',
                    'syllabics' => 'ᐊᑎᒼ',
                    'syllabics_breakdown' => ['ᐊ', 'ᑎᒼ'],
                    'pronunciation_guide' => 'ah-tim',
                    'translations' => ['dog'],
                    'cultural_context' => 'Dogs were important companions in traditional Plains Cree life',
                    'ceremony_usage' => 'Featured in stories about loyalty and companionship'
                ],
                [
                    'text' => 'nīpiy',
                    'syllabics' => 'ᓃᐱᕀ',
                    'syllabics_breakdown' => ['ᓃ', 'ᐱᕀ'],
                    'pronunciation_guide' => 'nee-pee-y',
                    'translations' => ['water'],
                    'cultural_context' => 'Sacred element in Plains Cree spirituality',
                    'ceremony_usage' => 'Central to water ceremonies and purification rituals'
                ],
                [
                    'text' => 'maskwa',
                    'syllabics' => 'ᒪᐢᑿ',
                    'syllabics_breakdown' => ['ᒪᐢ', 'ᑿ'],
                    'pronunciation_guide' => 'mas-kwa',
                    'translations' => ['bear'],
                    'cultural_context' => 'Powerful spirit animal in Plains Cree tradition',
                    'ceremony_usage' => 'Invoked for strength and healing in ceremonies'
                ],
                [
                    'text' => 'pīsim',
                    'syllabics' => 'ᐲᓯᒼ',
                    'syllabics_breakdown' => ['ᐲ', 'ᓯᒼ'],
                    'pronunciation_guide' => 'pee-sim',
                    'translations' => ['sun', 'moon'],
                    'cultural_context' => 'Celestial beings that guide time and seasons',
                    'ceremony_usage' => 'Honored in sunrise and sunset ceremonies'
                ]
            ];

            foreach ($vocabularyData as $wordData) {
                $word = Word::create([
                    'text' => $wordData['text'],
                    'language_id' => $this->plainsCreeLanguage->id,
                    'part_of_speech' => 'noun',
                    'status' => 'published',
                    'metadata' => [
                        'syllabics' => $wordData['syllabics'],
                        'syllabics_breakdown' => $wordData['syllabics_breakdown'],
                        'pronunciation_guide' => $wordData['pronunciation_guide'],
                        'proficiency_level' => 'A1',
                        'audio_url' => "audio/crk/{$wordData['text']}.mp3",
                        'audio_slow_url' => "audio/crk/slow/{$wordData['text']}.mp3",
                        'audio_elder_url' => "audio/crk/elder/{$wordData['text']}.mp3",
                        'cultural_context' => $wordData['cultural_context'],
                        'ceremony_usage' => $wordData['ceremony_usage'],
                        'clickable' => true,
                        'syllabics_interactive' => true
                    ],
                    'created_by' => $this->teamUser->id
                ]);

                // Create translations
                foreach ($wordData['translations'] as $index => $translation) {
                    WordTranslation::create([
                        'word_id' => $word->id,
                        'language_id' => $this->englishLanguage->id,
                        'translation' => $translation,
                        'is_primary' => $index === 0,
                        'created_by' => $this->teamUser->id
                    ]);
                }

                $this->testWords[] = $word;
            }
        });
    }

    protected function createSyllabicsContent(): void
    {
        $this->runInTenantContext($this->tenant, function () {
            $this->learningPath = LearningPath::create([
                'title' => 'Plains Cree A1 - Syllabics Mastery',
                'slug' => 'plains-cree-a1-syllabics-' . Str::random(8),
                'description' => 'Master Plains Cree syllabics writing system with audio support',
                'language_id' => $this->plainsCreeLanguage->id,
                'target_level' => 'A1',
                'status' => 'published',
                'metadata' => [
                    'syllabics_focus' => true,
                    'audio_enhanced' => true,
                    'cultural_integration' => true,
                    'elder_involvement' => true,
                    'interactive_syllabics' => true
                ],
                'created_by' => $this->teamUser->id,
            ]);

            $unit = Unit::create([
                'learning_path_id' => $this->learningPath->id,
                'title' => 'Unit 1: Syllabics Foundation',
                'description' => 'Learn the basics of Plains Cree syllabics writing',
                'order' => 1,
                'status' => 'published',
                'metadata' => [
                    'syllabics_instruction' => true,
                    'audio_pronunciation' => true,
                    'cultural_context' => 'Traditional writing system preservation'
                ],
                'created_by' => $this->teamUser->id,
            ]);

            $topic = Topic::create([
                'unit_id' => $unit->id,
                'title' => 'Basic Syllabics Characters',
                'description' => 'Learn fundamental syllabics characters with audio',
                'order' => 1,
                'status' => 'published',
                'metadata' => [
                    'syllabics_characters' => ['ᐊ', 'ᐃ', 'ᐅ', 'ᐯ', 'ᐱ', 'ᐳ'],
                    'audio_examples' => true,
                    'interactive_practice' => true
                ],
                'created_by' => $this->teamUser->id,
            ]);

            $lesson = Lesson::create([
                'topic_id' => $topic->id,
                'title' => 'Syllabics Reading Practice',
                'description' => 'Practice reading syllabics with audio support',
                'order' => 1,
                'status' => 'published',
                'metadata' => [
                    'syllabics_practice' => true,
                    'audio_integration' => true,
                    'pronunciation_focus' => true,
                    'elder_recordings' => true
                ],
                'created_by' => $this->teamUser->id,
            ]);

            $exercise = Exercise::create([
                'lesson_id' => $lesson->id,
                'title' => 'Syllabics Matching with Audio',
                'type' => 'syllabics_matching',
                'order' => 1,
                'status' => 'published',
                'content' => [
                    'instruction' => 'Match the syllabics characters with their sounds',
                    'syllabics_pairs' => [
                        ['syllabics' => 'ᑕ', 'sound' => 'ta', 'audio_url' => 'audio/syllabics/ta.mp3'],
                        ['syllabics' => 'ᓂ', 'sound' => 'ni', 'audio_url' => 'audio/syllabics/ni.mp3'],
                        ['syllabics' => 'ᓯ', 'sound' => 'si', 'audio_url' => 'audio/syllabics/si.mp3'],
                    ],
                    'audio_features' => [
                        'auto_play' => true,
                        'repeat_option' => true,
                        'slow_speech' => true,
                        'elder_voice' => true
                    ],
                    'cultural_integration' => [
                        'traditional_stories' => true,
                        'ceremony_context' => true,
                        'land_based_examples' => true
                    ]
                ],
                'metadata' => [
                    'syllabics_exercise' => true,
                    'audio_required' => true,
                    'cultural_context' => true
                ],
                'created_by' => $this->teamUser->id,
            ]);

            $this->testContent = [
                'learning_path' => $this->learningPath,
                'unit' => $unit,
                'topic' => $topic,
                'lesson' => $lesson,
                'exercise' => $exercise
            ];
        });
    }

    /** @test */
    public function student_can_access_syllabics_vocabulary_with_audio()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get Plains Cree words with syllabics
        $response = $this->getJson("/api/{$this->tenant->slug}/student/words?language_id={$this->plainsCreeLanguage->id}&include_syllabics=true");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'text',
                            'metadata' => [
                                'syllabics',
                                'syllabics_breakdown',
                                'pronunciation_guide',
                                'audio_url',
                                'audio_slow_url',
                                'audio_elder_url',
                                'cultural_context',
                                'ceremony_usage'
                            ]
                        ]
                    ]
                ]
            ]);

        $words = $response->json('data.data');
        foreach ($words as $word) {
            $this->assertNotEmpty($word['metadata']['syllabics']);
            $this->assertIsArray($word['metadata']['syllabics_breakdown']);
            $this->assertStringContainsString('audio/crk/', $word['metadata']['audio_url']);
            $this->assertStringContainsString('audio/crk/slow/', $word['metadata']['audio_slow_url']);
            $this->assertStringContainsString('audio/crk/elder/', $word['metadata']['audio_elder_url']);
            $this->assertNotEmpty($word['metadata']['cultural_context']);
        }
    }

    /** @test */
    public function student_can_access_individual_word_with_syllabics_breakdown()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $word = $this->testWords[0]; // 'tanisi'

        // API call to get specific word with syllabics breakdown
        $response = $this->getJson("/api/{$this->tenant->slug}/student/words/{$word->id}?include_syllabics_breakdown=true");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'text',
                    'metadata' => [
                        'syllabics',
                        'syllabics_breakdown',
                        'pronunciation_guide',
                        'audio_url',
                        'audio_slow_url',
                        'audio_elder_url',
                        'cultural_context',
                        'ceremony_usage',
                        'syllabics_interactive'
                    ],
                    'translations'
                ]
            ]);

        $wordData = $response->json('data');
        $this->assertEquals('ᑕᓂᓯ', $wordData['metadata']['syllabics']);
        $this->assertEquals(['ᑕ', 'ᓂ', 'ᓯ'], $wordData['metadata']['syllabics_breakdown']);
        $this->assertEquals('tah-ni-see', $wordData['metadata']['pronunciation_guide']);
        $this->assertTrue($wordData['metadata']['syllabics_interactive']);
    }

    /** @test */
    public function student_can_access_syllabics_learning_content()
    {
        // Enroll student in learning path
        $this->enrollStudentInLearningPath();

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get syllabics-focused learning path
        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths/{$this->learningPath->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'title',
                    'description',
                    'metadata' => [
                        'syllabics_focus',
                        'audio_enhanced',
                        'cultural_integration',
                        'elder_involvement',
                        'interactive_syllabics'
                    ]
                ]
            ]);

        $pathData = $response->json('data');
        $this->assertTrue($pathData['metadata']['syllabics_focus']);
        $this->assertTrue($pathData['metadata']['audio_enhanced']);
        $this->assertTrue($pathData['metadata']['cultural_integration']);
        $this->assertTrue($pathData['metadata']['elder_involvement']);
    }

    /** @test */
    public function student_can_access_syllabics_exercise_with_audio_features()
    {
        // Enroll student in learning path
        $this->enrollStudentInLearningPath();

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $exercise = $this->testContent['exercise'];

        // API call to get syllabics exercise
        $response = $this->getJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'title',
                    'type',
                    'content' => [
                        'instruction',
                        'syllabics_pairs',
                        'audio_features' => [
                            'auto_play',
                            'repeat_option',
                            'slow_speech',
                            'elder_voice'
                        ],
                        'cultural_integration' => [
                            'traditional_stories',
                            'ceremony_context',
                            'land_based_examples'
                        ]
                    ],
                    'metadata' => [
                        'syllabics_exercise',
                        'audio_required',
                        'cultural_context'
                    ]
                ]
            ]);

        $exerciseData = $response->json('data');
        $this->assertEquals('syllabics_matching', $exerciseData['type']);
        $this->assertTrue($exerciseData['metadata']['syllabics_exercise']);
        $this->assertTrue($exerciseData['metadata']['audio_required']);
        $this->assertTrue($exerciseData['content']['audio_features']['elder_voice']);
        $this->assertTrue($exerciseData['content']['cultural_integration']['ceremony_context']);

        // Verify syllabics pairs have audio URLs
        foreach ($exerciseData['content']['syllabics_pairs'] as $pair) {
            $this->assertArrayHasKey('syllabics', $pair);
            $this->assertArrayHasKey('sound', $pair);
            $this->assertArrayHasKey('audio_url', $pair);
            $this->assertStringContainsString('audio/syllabics/', $pair['audio_url']);
        }
    }

    /** @test */
    public function student_can_get_audio_pronunciation_variants()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $word = $this->testWords[2]; // 'nīpiy'

        // API call to get word with all audio variants
        $response = $this->getJson("/api/{$this->tenant->slug}/student/words/{$word->id}?include_audio_variants=true");

        $response->assertStatus(200);

        $wordData = $response->json('data');
        $this->assertArrayHasKey('audio_url', $wordData['metadata']);
        $this->assertArrayHasKey('audio_slow_url', $wordData['metadata']);
        $this->assertArrayHasKey('audio_elder_url', $wordData['metadata']);

        // Verify different audio URLs
        $this->assertStringContainsString('audio/crk/nīpiy.mp3', $wordData['metadata']['audio_url']);
        $this->assertStringContainsString('audio/crk/slow/nīpiy.mp3', $wordData['metadata']['audio_slow_url']);
        $this->assertStringContainsString('audio/crk/elder/nīpiy.mp3', $wordData['metadata']['audio_elder_url']);
    }

    /** @test */
    public function student_can_access_cultural_context_with_ceremony_usage()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $word = $this->testWords[3]; // 'maskwa'

        // API call to get word with cultural context
        $response = $this->getJson("/api/{$this->tenant->slug}/student/words/{$word->id}?include_cultural_context=true");

        $response->assertStatus(200);

        $wordData = $response->json('data');
        $this->assertEquals('Powerful spirit animal in Plains Cree tradition', $wordData['metadata']['cultural_context']);
        $this->assertEquals('Invoked for strength and healing in ceremonies', $wordData['metadata']['ceremony_usage']);
    }

    /** @test */
    public function student_can_filter_words_by_syllabics_features()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get words with interactive syllabics
        $response = $this->getJson("/api/{$this->tenant->slug}/student/words?syllabics_interactive=true&language_id={$this->plainsCreeLanguage->id}");

        $response->assertStatus(200);

        $words = $response->json('data.data');
        foreach ($words as $word) {
            $this->assertTrue($word['metadata']['syllabics_interactive']);
            $this->assertTrue($word['metadata']['clickable']);
            $this->assertNotEmpty($word['metadata']['syllabics']);
            $this->assertIsArray($word['metadata']['syllabics_breakdown']);
        }
    }

    /** @test */
    public function student_can_access_topic_with_syllabics_characters()
    {
        // Enroll student in learning path
        $this->enrollStudentInLearningPath();

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $topic = $this->testContent['topic'];

        // API call to get topic with syllabics characters
        $response = $this->getJson("/api/{$this->tenant->slug}/student/topics/{$topic->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'title',
                    'description',
                    'metadata' => [
                        'syllabics_characters',
                        'audio_examples',
                        'interactive_practice'
                    ]
                ]
            ]);

        $topicData = $response->json('data');
        $this->assertEquals(['ᐊ', 'ᐃ', 'ᐅ', 'ᐯ', 'ᐱ', 'ᐳ'], $topicData['metadata']['syllabics_characters']);
        $this->assertTrue($topicData['metadata']['audio_examples']);
        $this->assertTrue($topicData['metadata']['interactive_practice']);
    }

    /** @test */
    public function student_can_submit_syllabics_exercise_answer()
    {
        // Enroll student in learning path
        $this->enrollStudentInLearningPath();

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $exercise = $this->testContent['exercise'];

        // API call to submit syllabics matching answer
        $response = $this->postJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}/submit-answer", [
            'syllabics_answer' => 'ᑕ',
            'sound_answer' => 'ta',
            'pair_index' => 0
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'correct',
                    'feedback',
                    'points_earned',
                    'syllabics_reinforcement' => [
                        'character',
                        'sound',
                        'audio_url',
                        'pronunciation_tip',
                        'cultural_example'
                    ]
                ]
            ]);

        $answerData = $response->json('data');
        $this->assertTrue($answerData['correct']);
        $this->assertArrayHasKey('syllabics_reinforcement', $answerData);
        $this->assertEquals('ᑕ', $answerData['syllabics_reinforcement']['character']);
        $this->assertEquals('ta', $answerData['syllabics_reinforcement']['sound']);
    }

    // Helper methods
    private function enrollStudentInLearningPath(): void
    {
        $this->runInTenantContext($this->tenant, function () {
            \Illuminate\Support\Facades\DB::table('user_learning_paths')->insert([
                'id' => Str::uuid(),
                'user_id' => $this->studentUser->id,
                'learning_path_id' => $this->learningPath->id,
                'enrolled_at' => now(),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    private function createTenantStudent(): User
    {
        return $this->runInTenantContext($this->tenant, function () {
            return User::factory()->create([
                'email' => 'student@test.com',
                'membership_type' => 'student',
                'email_verified_at' => now(),
            ]);
        });
    }

    private function createTenantTeam(): User
    {
        return $this->runInTenantContext($this->tenant, function () {
            return User::factory()->create([
                'email' => 'team@test.com',
                'membership_type' => 'team',
                'email_verified_at' => now(),
            ]);
        });
    }
}
