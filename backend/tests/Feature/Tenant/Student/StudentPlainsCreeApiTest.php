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
use App\Models\Tenants\Word;
use App\Models\Tenants\WordTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;

/**
 * Comprehensive Plains Cree Student API Tests
 * 
 * Tests the complete Plains Cree language learning system including:
 * - A1-C2 proficiency level filtering
 * - Vocabulary progression constraints
 * - Age-appropriate content delivery
 * - Syllabics content and audio support
 * - Multi-tenant isolation
 * - Duolingo-style features
 */
class StudentPlainsCreeApiTest extends TenantTestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected Tenant $otherTenant;
    protected User $studentUser;
    protected User $teamUser;
    protected Language $plainsCreeLanguage;
    protected Language $englishLanguage;
    protected array $testLearningPaths = [];
    protected array $testWords = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenancy();

        // Create test tenants
        $this->tenant = $this->createTestTenant();
        $this->otherTenant = $this->createTestTenant('other-tenant');
        $this->initializeTenantContext($this->tenant);

        // Create users with different roles in tenant context
        $this->studentUser = $this->createTenantStudent();
        $this->teamUser = $this->createTenantTeam();

        // Create test environment
        $this->createTestLanguages();
        $this->createTestVocabulary();
        $this->createTestLearningPaths();
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
                    'starter_pack' => true
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

    protected function createTestVocabulary(): void
    {
        $this->runInTenantContext($this->tenant, function () {
            // Create vocabulary for different proficiency levels
            $vocabularyData = [
                'A1' => [
                    ['text' => 'tanisi', 'syllabics' => 'ᑕᓂᓯ', 'translations' => ['hello', 'how are you']],
                    ['text' => 'atim', 'syllabics' => 'ᐊᑎᒼ', 'translations' => ['dog']],
                    ['text' => 'nīpiy', 'syllabics' => 'ᓃᐱᕀ', 'translations' => ['water']],
                ],
                'A2' => [
                    ['text' => 'kīsikāw', 'syllabics' => 'ᑮᓯᑳᐤ', 'translations' => ['day', 'sun']],
                    ['text' => 'maskwa', 'syllabics' => 'ᒪᐢᑿ', 'translations' => ['bear']],
                ],
                'B1' => [
                    ['text' => 'pīsim', 'syllabics' => 'ᐲᓯᒼ', 'translations' => ['sun', 'moon']],
                    ['text' => 'wāpamon', 'syllabics' => 'ᐚᐸᒧᐣ', 'translations' => ['mirror', 'reflection']],
                ],
            ];

            foreach ($vocabularyData as $level => $words) {
                foreach ($words as $wordData) {
                    $word = Word::create([
                        'text' => $wordData['text'],
                        'language_id' => $this->plainsCreeLanguage->id,
                        'part_of_speech' => 'noun',
                        'status' => 'published',
                        'metadata' => [
                            'syllabics' => $wordData['syllabics'],
                            'proficiency_level' => $level,
                            'audio_url' => "audio/crk/{$wordData['text']}.mp3",
                            'cultural_notes' => 'Traditional Plains Cree word',
                            'age_appropriate' => ['kids', 'teen_adult']
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

                    $this->testWords[$level][] = $word;
                }
            }
        });
    }

    protected function createTestLearningPaths(): void
    {
        $this->runInTenantContext($this->tenant, function () {
            $levels = ['A1', 'A2', 'B1'];
            $ageGroups = ['kids', 'teen_adult'];

            foreach ($levels as $level) {
                foreach ($ageGroups as $ageGroup) {
                    $learningPath = LearningPath::create([
                        'title' => "Plains Cree {$level} - {$ageGroup}",
                        'slug' => "plains-cree-{$level}-{$ageGroup}-" . Str::random(8),
                        'description' => "Plains Cree {$level} level course for {$ageGroup}",
                        'language_id' => $this->plainsCreeLanguage->id,
                        'target_level' => $level,
                        'status' => 'published',
                        'metadata' => [
                            'age_group' => $ageGroup,
                            'is_starter_pack' => true,
                            'is_official' => true,
                            'proficiency_level' => $level,
                            'vocabulary_constraints' => $this->getVocabularyConstraints($level),
                            'duolingo_features' => [
                                'clickable_vocabulary' => true,
                                'audio_pronunciation' => true,
                                'syllabics_display' => true,
                                'cultural_context' => true
                            ]
                        ],
                        'created_by' => $this->teamUser->id,
                    ]);

                    $this->testLearningPaths["{$level}_{$ageGroup}"] = $learningPath;

                    // Create a unit for this learning path
                    $this->createTestUnit($learningPath, $level, $ageGroup);
                }
            }
        });
    }

    protected function createTestUnit(LearningPath $learningPath, string $level, string $ageGroup): void
    {
        $unit = Unit::create([
            'learning_path_id' => $learningPath->id,
            'title' => "Unit 1: Basic Greetings ({$level})",
            'description' => "Learn basic Plains Cree greetings and introductions",
            'order' => 1,
            'status' => 'published',
            'metadata' => [
                'age_group' => $ageGroup,
                'proficiency_level' => $level,
                'syllabics_focus' => true,
                'cultural_context' => 'Traditional greetings and social protocols'
            ],
            'created_by' => $this->teamUser->id,
        ]);

        // Create a topic for this unit
        $this->createTestTopic($unit, $level, $ageGroup);
    }

    protected function createTestTopic(Unit $unit, string $level, string $ageGroup): void
    {
        $topic = Topic::create([
            'unit_id' => $unit->id,
            'title' => "Greetings and Introductions",
            'description' => "Learn how to greet people in Plains Cree",
            'order' => 1,
            'status' => 'published',
            'metadata' => [
                'age_group' => $ageGroup,
                'proficiency_level' => $level,
                'cultural_notes' => 'Traditional Plains Cree greeting protocols'
            ],
            'created_by' => $this->teamUser->id,
        ]);

        // Create a lesson for this topic
        $this->createTestLesson($topic, $level, $ageGroup);
    }

    protected function createTestLesson(Topic $topic, string $level, string $ageGroup): void
    {
        $lesson = Lesson::create([
            'topic_id' => $topic->id,
            'title' => "Saying Hello - tanisi",
            'description' => "Learn the basic Plains Cree greeting 'tanisi'",
            'order' => 1,
            'status' => 'published',
            'metadata' => [
                'age_group' => $ageGroup,
                'proficiency_level' => $level,
                'vocabulary_constraints' => $this->getVocabularyConstraints($level),
                'lesson_length' => $ageGroup === 'kids' ? 'short' : 'standard',
                'syllabics_instruction' => true
            ],
            'created_by' => $this->teamUser->id,
        ]);

        // Create an exercise for this lesson
        $this->createTestExercise($lesson, $level, $ageGroup);
    }

    protected function createTestExercise(Lesson $lesson, string $level, string $ageGroup): void
    {
        Exercise::create([
            'lesson_id' => $lesson->id,
            'title' => "Match Plains Cree Greetings",
            'type' => 'matching',
            'order' => 1,
            'status' => 'published',
            'content' => [
                'instruction' => 'Match each Plains Cree word with its English meaning',
                'pairs' => [
                    ['source' => 'tanisi', 'target' => 'hello', 'syllabics' => 'ᑕᓂᓯ'],
                    ['source' => 'atim', 'target' => 'dog', 'syllabics' => 'ᐊᑎᒼ'],
                ],
                'duolingo_features' => [
                    'clickable_vocabulary' => true,
                    'audio_pronunciation' => true,
                    'syllabics_display' => true,
                    'cultural_context_popup' => true
                ]
            ],
            'metadata' => [
                'age_group' => $ageGroup,
                'proficiency_level' => $level,
                'vocabulary_level_constraint' => $this->getVocabularyConstraints($level),
                'gamification' => $ageGroup === 'kids' ? 'high' : 'moderate'
            ],
            'created_by' => $this->teamUser->id,
        ]);
    }

    protected function getVocabularyConstraints(string $level): array
    {
        $constraints = [
            'A1' => ['A1'],
            'A2' => ['A1', 'A2'],
            'B1' => ['A1', 'A2', 'B1'],
            'B2' => ['A1', 'A2', 'B1', 'B2'],
            'C1' => ['A1', 'A2', 'B1', 'B2', 'C1'],
            'C2' => ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'],
        ];

        return $constraints[$level] ?? ['A1'];
    }



    /** @test */
    public function student_can_access_plains_cree_languages_with_starter_pack_metadata()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get languages
        $response = $this->getJson("/api/{$this->tenant->slug}/student/languages");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'code',
                            'native_name',
                            'metadata'
                        ]
                    ]
                ]
            ]);

        // Find Plains Cree language in response
        $languages = $response->json('data.data');
        $plainsCree = collect($languages)->firstWhere('code', 'crk');

        $this->assertNotNull($plainsCree);
        $this->assertEquals('Plains Cree', $plainsCree['name']);
        $this->assertEquals('nēhiyawēwin', $plainsCree['native_name']);
        $this->assertTrue($plainsCree['metadata']['starter_pack']);
        $this->assertEquals('syllabics', $plainsCree['metadata']['writing_system']);
    }

    /** @test */
    public function student_can_get_learning_paths_filtered_by_proficiency_level()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get A1 level learning paths
        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths/by-level/A1");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'title',
                            'target_level',
                            'language_id',
                            'metadata'
                        ]
                    ]
                ]
            ]);

        $learningPaths = $response->json('data.data');
        foreach ($learningPaths as $path) {
            $this->assertEquals('A1', $path['target_level']);
            $this->assertEquals('A1', $path['metadata']['proficiency_level']);
        }
    }

    /** @test */
    public function student_can_access_age_appropriate_learning_paths()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get learning paths with age group filter
        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths?age_group=kids");

        $response->assertStatus(200);

        $learningPaths = $response->json('data.data');
        foreach ($learningPaths as $path) {
            $this->assertEquals('kids', $path['metadata']['age_group']);
        }
    }

    /** @test */
    public function student_can_get_learning_path_with_vocabulary_constraints()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $learningPath = $this->testLearningPaths['A2_teen_adult'];

        // API call to get specific learning path
        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths/{$learningPath->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'title',
                    'target_level',
                    'metadata' => [
                        'vocabulary_constraints',
                        'duolingo_features'
                    ]
                ]
            ]);

        $pathData = $response->json('data');
        $this->assertEquals(['A1', 'A2'], $pathData['metadata']['vocabulary_constraints']);
        $this->assertTrue($pathData['metadata']['duolingo_features']['clickable_vocabulary']);
        $this->assertTrue($pathData['metadata']['duolingo_features']['syllabics_display']);
    }

    /** @test */
    public function student_can_enroll_in_plains_cree_learning_path()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $learningPath = $this->testLearningPaths['A1_kids'];

        // API call to enroll in learning path
        $response = $this->postJson("/api/{$this->tenant->slug}/student/learning-paths/{$learningPath->id}/enroll");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'enrollment_id',
                    'learning_path_id',
                    'user_id',
                    'enrolled_at'
                ]
            ]);

        // Verify enrollment was created
        $this->runInTenantContext($this->tenant, function () use ($learningPath) {
            $this->assertDatabaseHas('user_learning_paths', [
                'user_id' => $this->studentUser->id,
                'learning_path_id' => $learningPath->id,
            ]);
        });
    }

    /** @test */
    public function student_cannot_access_learning_paths_from_other_tenants()
    {
        // Create learning path in other tenant
        $otherLearningPath = $this->runInTenantContext($this->otherTenant, function () {
            $language = Language::create([
                'name' => 'Other Plains Cree',
                'code' => 'crk',
                'native_name' => 'nēhiyawēwin',
                'is_active' => true
            ]);

            return LearningPath::create([
                'title' => 'Other Tenant Plains Cree A1',
                'slug' => 'other-plains-cree-a1',
                'description' => 'Plains Cree A1 in other tenant',
                'language_id' => $language->id,
                'target_level' => 'A1',
                'status' => 'published',
                'created_by' => 1,
            ]);
        });

        // Authenticate as student in original tenant
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // Try to access learning path from other tenant
        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths/{$otherLearningPath->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function student_can_access_units_with_syllabics_content()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $learningPath = $this->testLearningPaths['A1_kids'];

        // API call to get units for learning path
        $response = $this->getJson("/api/{$this->tenant->slug}/student/learning-paths/{$learningPath->id}/units");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'order',
                        'metadata'
                    ]
                ]
            ]);

        $units = $response->json('data');
        foreach ($units as $unit) {
            $this->assertEquals('kids', $unit['metadata']['age_group']);
            $this->assertEquals('A1', $unit['metadata']['proficiency_level']);
            $this->assertTrue($unit['metadata']['syllabics_focus']);
        }
    }

    /** @test */
    public function student_can_access_topics_with_cultural_context()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $learningPath = $this->testLearningPaths['A1_teen_adult'];
        $unit = $learningPath->units()->first();

        // API call to get topics for unit
        $response = $this->getJson("/api/{$this->tenant->slug}/student/units/{$unit->id}/topics");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'metadata'
                    ]
                ]
            ]);

        $topics = $response->json('data');
        foreach ($topics as $topic) {
            $this->assertEquals('teen_adult', $topic['metadata']['age_group']);
            $this->assertEquals('A1', $topic['metadata']['proficiency_level']);
            $this->assertArrayHasKey('cultural_notes', $topic['metadata']);
        }
    }

    /** @test */
    public function student_can_access_lessons_with_vocabulary_constraints()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $learningPath = $this->testLearningPaths['A2_teen_adult'];
        $unit = $learningPath->units()->first();
        $topic = $unit->topics()->first();

        // API call to get lessons for topic
        $response = $this->getJson("/api/{$this->tenant->slug}/student/topics/{$topic->id}/lessons");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'metadata'
                    ]
                ]
            ]);

        $lessons = $response->json('data');
        foreach ($lessons as $lesson) {
            $this->assertEquals('teen_adult', $lesson['metadata']['age_group']);
            $this->assertEquals('A2', $lesson['metadata']['proficiency_level']);
            $this->assertEquals(['A1', 'A2'], $lesson['metadata']['vocabulary_constraints']);
            $this->assertTrue($lesson['metadata']['syllabics_instruction']);
        }
    }

    /** @test */
    public function student_can_access_exercises_with_duolingo_features()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $learningPath = $this->testLearningPaths['A1_kids'];
        $unit = $learningPath->units()->first();
        $topic = $unit->topics()->first();
        $lesson = $topic->lessons()->first();

        // API call to get specific lesson
        $response = $this->getJson("/api/{$this->tenant->slug}/student/lessons/{$lesson->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'title',
                    'description',
                    'exercises' => [
                        '*' => [
                            'id',
                            'title',
                            'type',
                            'content',
                            'metadata'
                        ]
                    ]
                ]
            ]);

        $lessonData = $response->json('data');
        foreach ($lessonData['exercises'] as $exercise) {
            $this->assertEquals('kids', $exercise['metadata']['age_group']);
            $this->assertEquals('A1', $exercise['metadata']['proficiency_level']);
            $this->assertTrue($exercise['content']['duolingo_features']['clickable_vocabulary']);
            $this->assertTrue($exercise['content']['duolingo_features']['syllabics_display']);
            $this->assertTrue($exercise['content']['duolingo_features']['audio_pronunciation']);
        }
    }

    /** @test */
    public function student_can_get_vocabulary_with_progression_constraints()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get vocabulary for A2 level (should include A1 + A2)
        $response = $this->getJson("/api/{$this->tenant->slug}/student/words?max_proficiency_level=A2&language_id={$this->plainsCreeLanguage->id}");

        $response->assertStatus(200);

        $words = $response->json('data.data');
        $proficiencyLevels = array_column(array_column($words, 'metadata'), 'proficiency_level');

        // Should only contain A1 and A2 words
        foreach ($proficiencyLevels as $level) {
            $this->assertContains($level, ['A1', 'A2'], 'A2 lessons should only access A1 and A2 vocabulary');
        }

        // Should not contain B1 or higher
        $this->assertNotContains('B1', $proficiencyLevels);
    }

    /** @test */
    public function student_can_get_words_with_syllabics_and_audio()
    {
        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get Plains Cree words with audio
        $response = $this->getJson("/api/{$this->tenant->slug}/student/words?language_id={$this->plainsCreeLanguage->id}&include_audio=true");

        $response->assertStatus(200);

        $words = $response->json('data.data');
        foreach ($words as $word) {
            $this->assertArrayHasKey('syllabics', $word['metadata']);
            $this->assertArrayHasKey('audio_url', $word['metadata']);
            $this->assertStringContainsString('audio/crk/', $word['metadata']['audio_url']);
            $this->assertNotEmpty($word['metadata']['syllabics']);
        }
    }
}
