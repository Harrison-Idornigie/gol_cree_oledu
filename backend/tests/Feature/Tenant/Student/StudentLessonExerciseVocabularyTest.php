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
use App\Models\Tenants\UserProgress;
use App\Models\Tenants\ExerciseAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;

/**
 * Lesson and Exercise API Tests with Vocabulary Constraints
 * 
 * Tests vocabulary progression constraints (A1 words for A1 lessons, A1+A2 for A2 lessons, etc.)
 * and Duolingo-style features including clickable vocabulary, audio support, and syllabics.
 */
class StudentLessonExerciseVocabularyTest extends TestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected Tenant $tenant;
    protected User $studentUser;
    protected User $teamUser;
    protected Language $plainsCreeLanguage;
    protected Language $englishLanguage;
    protected LearningPath $learningPath;
    protected Unit $unit;
    protected Topic $topic;
    protected array $testLessons = [];
    protected array $testExercises = [];
    protected array $testWords = [];

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
        $this->createTestVocabulary();
        $this->createTestCurriculum();
        $this->createTestLessonsAndExercises();
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
                    'cultural_context' => 'indigenous'
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
                    ['text' => 'wāpamon', 'syllabics' => 'ᐚᐸᒧᐣ', 'translations' => ['mirror']],
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
                            'clickable' => true
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

    protected function createTestCurriculum(): void
    {
        $this->runInTenantContext($this->tenant, function () {
            $this->learningPath = LearningPath::create([
                'title' => 'Plains Cree A2 - Vocabulary Constraints Test',
                'slug' => 'plains-cree-a2-vocab-' . Str::random(8),
                'description' => 'Plains Cree A2 course with vocabulary constraints',
                'language_id' => $this->plainsCreeLanguage->id,
                'target_level' => 'A2',
                'status' => 'published',
                'metadata' => [
                    'proficiency_level' => 'A2',
                    'vocabulary_constraints' => ['A1', 'A2'], // A2 lessons can use A1 + A2 words
                    'duolingo_features' => [
                        'clickable_vocabulary' => true,
                        'audio_pronunciation' => true,
                        'syllabics_display' => true
                    ]
                ],
                'created_by' => $this->teamUser->id,
            ]);

            $this->unit = Unit::create([
                'learning_path_id' => $this->learningPath->id,
                'title' => 'Unit 1: Vocabulary Progression',
                'description' => 'Learn vocabulary with proper progression constraints',
                'order' => 1,
                'status' => 'published',
                'metadata' => [
                    'proficiency_level' => 'A2',
                    'vocabulary_constraints' => ['A1', 'A2']
                ],
                'created_by' => $this->teamUser->id,
            ]);

            $this->topic = Topic::create([
                'unit_id' => $this->unit->id,
                'title' => 'Topic 1: Basic Vocabulary',
                'description' => 'Learn basic Plains Cree vocabulary',
                'order' => 1,
                'status' => 'published',
                'metadata' => [
                    'proficiency_level' => 'A2',
                    'vocabulary_constraints' => ['A1', 'A2']
                ],
                'created_by' => $this->teamUser->id,
            ]);
        });
    }

    protected function createTestLessonsAndExercises(): void
    {
        $this->runInTenantContext($this->tenant, function () {
            // Create A1 lesson (should only use A1 vocabulary)
            $a1Lesson = Lesson::create([
                'topic_id' => $this->topic->id,
                'title' => 'A1 Lesson: Basic Greetings',
                'description' => 'Learn basic greetings using A1 vocabulary only',
                'order' => 1,
                'status' => 'published',
                'metadata' => [
                    'proficiency_level' => 'A1',
                    'vocabulary_constraints' => ['A1'], // Only A1 words allowed
                    'duolingo_features' => [
                        'clickable_vocabulary' => true,
                        'audio_pronunciation' => true,
                        'syllabics_display' => true
                    ]
                ],
                'created_by' => $this->teamUser->id,
            ]);

            // Create A2 lesson (can use A1 + A2 vocabulary)
            $a2Lesson = Lesson::create([
                'topic_id' => $this->topic->id,
                'title' => 'A2 Lesson: Extended Vocabulary',
                'description' => 'Learn extended vocabulary using A1 and A2 words',
                'order' => 2,
                'status' => 'published',
                'metadata' => [
                    'proficiency_level' => 'A2',
                    'vocabulary_constraints' => ['A1', 'A2'], // A1 + A2 words allowed
                    'duolingo_features' => [
                        'clickable_vocabulary' => true,
                        'audio_pronunciation' => true,
                        'syllabics_display' => true
                    ]
                ],
                'created_by' => $this->teamUser->id,
            ]);

            $this->testLessons['A1'] = $a1Lesson;
            $this->testLessons['A2'] = $a2Lesson;

            // Create exercises for each lesson
            $this->createExercisesForLesson($a1Lesson, 'A1');
            $this->createExercisesForLesson($a2Lesson, 'A2');
        });
    }

    protected function createExercisesForLesson(Lesson $lesson, string $level): void
    {
        $vocabularyConstraints = $level === 'A1' ? ['A1'] : ['A1', 'A2'];
        $availableWords = [];

        foreach ($vocabularyConstraints as $vocabLevel) {
            if (isset($this->testWords[$vocabLevel])) {
                $availableWords = array_merge($availableWords, $this->testWords[$vocabLevel]);
            }
        }

        // Create matching exercise
        $matchingExercise = Exercise::create([
            'lesson_id' => $lesson->id,
            'title' => "Match Plains Cree Words ({$level})",
            'type' => 'matching',
            'order' => 1,
            'status' => 'published',
            'content' => [
                'instruction' => 'Match each Plains Cree word with its English meaning',
                'pairs' => $this->createMatchingPairs($availableWords),
                'vocabulary_level_constraint' => $vocabularyConstraints,
                'duolingo_features' => [
                    'clickable_vocabulary' => true,
                    'audio_pronunciation' => true,
                    'syllabics_display' => true,
                    'hover_explanations' => true
                ]
            ],
            'metadata' => [
                'proficiency_level' => $level,
                'vocabulary_constraints' => $vocabularyConstraints,
                'exercise_type' => 'vocabulary_matching'
            ],
            'created_by' => $this->teamUser->id,
        ]);

        // Create multiple choice exercise
        $multipleChoiceExercise = Exercise::create([
            'lesson_id' => $lesson->id,
            'title' => "Choose the Correct Translation ({$level})",
            'type' => 'multiple_choice',
            'order' => 2,
            'status' => 'published',
            'content' => [
                'instruction' => 'Choose the correct English translation for the Plains Cree word',
                'questions' => $this->createMultipleChoiceQuestions($availableWords),
                'vocabulary_level_constraint' => $vocabularyConstraints,
                'duolingo_features' => [
                    'clickable_vocabulary' => true,
                    'audio_pronunciation' => true,
                    'syllabics_display' => true,
                    'cultural_context_popup' => true
                ]
            ],
            'metadata' => [
                'proficiency_level' => $level,
                'vocabulary_constraints' => $vocabularyConstraints,
                'exercise_type' => 'vocabulary_recognition'
            ],
            'created_by' => $this->teamUser->id,
        ]);

        $this->testExercises[$level] = [
            'matching' => $matchingExercise,
            'multiple_choice' => $multipleChoiceExercise
        ];
    }

    protected function createMatchingPairs(array $words): array
    {
        $pairs = [];
        foreach (array_slice($words, 0, 3) as $word) { // Use first 3 words
            $translation = $word->translations()->first();
            $pairs[] = [
                'source' => $word->text,
                'target' => $translation->translation,
                'syllabics' => $word->metadata['syllabics'],
                'audio_url' => $word->metadata['audio_url'],
                'proficiency_level' => $word->metadata['proficiency_level']
            ];
        }
        return $pairs;
    }

    protected function createMultipleChoiceQuestions(array $words): array
    {
        $questions = [];
        foreach (array_slice($words, 0, 2) as $word) { // Use first 2 words
            $correctTranslation = $word->translations()->first()->translation;
            $questions[] = [
                'question' => $word->text,
                'syllabics' => $word->metadata['syllabics'],
                'audio_url' => $word->metadata['audio_url'],
                'options' => [
                    $correctTranslation,
                    'incorrect option 1',
                    'incorrect option 2',
                    'incorrect option 3'
                ],
                'correct_answer' => $correctTranslation,
                'proficiency_level' => $word->metadata['proficiency_level']
            ];
        }
        return $questions;
    }

    /** @test */
    public function student_can_access_lessons_with_vocabulary_constraints()
    {
        // Enroll student in learning path
        $this->enrollStudentInLearningPath();

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        // API call to get lessons for topic
        $response = $this->getJson("/api/{$this->tenant->slug}/student/topics/{$this->topic->id}/lessons");

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
                        'metadata' => [
                            'proficiency_level',
                            'vocabulary_constraints',
                            'duolingo_features'
                        ]
                    ]
                ]
            ]);

        $lessons = $response->json('data');
        $this->assertCount(2, $lessons);

        // Verify A1 lesson has only A1 vocabulary constraints
        $a1Lesson = collect($lessons)->firstWhere('metadata.proficiency_level', 'A1');
        $this->assertEquals(['A1'], $a1Lesson['metadata']['vocabulary_constraints']);

        // Verify A2 lesson has A1 + A2 vocabulary constraints
        $a2Lesson = collect($lessons)->firstWhere('metadata.proficiency_level', 'A2');
        $this->assertEquals(['A1', 'A2'], $a2Lesson['metadata']['vocabulary_constraints']);
    }

    /** @test */
    public function student_can_access_lesson_with_duolingo_features()
    {
        // Enroll student in learning path
        $this->enrollStudentInLearningPath();

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $lesson = $this->testLessons['A2'];

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
                    'metadata' => [
                        'proficiency_level',
                        'vocabulary_constraints',
                        'duolingo_features' => [
                            'clickable_vocabulary',
                            'audio_pronunciation',
                            'syllabics_display'
                        ]
                    ],
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
        $this->assertEquals($lesson->id, $lessonData['id']);
        $this->assertEquals('A2', $lessonData['metadata']['proficiency_level']);
        $this->assertEquals(['A1', 'A2'], $lessonData['metadata']['vocabulary_constraints']);
        $this->assertTrue($lessonData['metadata']['duolingo_features']['clickable_vocabulary']);
        $this->assertTrue($lessonData['metadata']['duolingo_features']['syllabics_display']);
    }

    /** @test */
    public function student_can_access_exercises_with_vocabulary_level_constraints()
    {
        // Enroll student in learning path
        $this->enrollStudentInLearningPath();

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $exercise = $this->testExercises['A1']['matching'];

        // API call to get specific exercise
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
                        'pairs',
                        'vocabulary_level_constraint',
                        'duolingo_features'
                    ],
                    'metadata' => [
                        'proficiency_level',
                        'vocabulary_constraints'
                    ]
                ]
            ]);

        $exerciseData = $response->json('data');
        $this->assertEquals($exercise->id, $exerciseData['id']);
        $this->assertEquals('A1', $exerciseData['metadata']['proficiency_level']);
        $this->assertEquals(['A1'], $exerciseData['metadata']['vocabulary_constraints']);
        $this->assertEquals(['A1'], $exerciseData['content']['vocabulary_level_constraint']);

        // Verify all vocabulary in exercise respects A1 constraint
        foreach ($exerciseData['content']['pairs'] as $pair) {
            $this->assertEquals('A1', $pair['proficiency_level']);
        }
    }

    /** @test */
    public function student_can_access_exercise_with_syllabics_and_audio()
    {
        // Enroll student in learning path
        $this->enrollStudentInLearningPath();

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $exercise = $this->testExercises['A2']['multiple_choice'];

        // API call to get exercise with syllabics and audio
        $response = $this->getJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}");

        $response->assertStatus(200);

        $exerciseData = $response->json('data');
        $this->assertTrue($exerciseData['content']['duolingo_features']['syllabics_display']);
        $this->assertTrue($exerciseData['content']['duolingo_features']['audio_pronunciation']);

        // Verify syllabics and audio URLs are present
        foreach ($exerciseData['content']['questions'] as $question) {
            $this->assertArrayHasKey('syllabics', $question);
            $this->assertArrayHasKey('audio_url', $question);
            $this->assertStringContainsString('audio/crk/', $question['audio_url']);
            $this->assertNotEmpty($question['syllabics']);
        }
    }

    /** @test */
    public function student_can_submit_exercise_answer_and_get_feedback()
    {
        // Enroll student in learning path
        $this->enrollStudentInLearningPath();

        // Authenticate as student
        Sanctum::actingAs($this->studentUser, [], 'tenant');

        $exercise = $this->testExercises['A1']['multiple_choice'];

        // Get the exercise to find correct answer
        $exerciseResponse = $this->getJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}");
        $exerciseData = $exerciseResponse->json('data');
        $firstQuestion = $exerciseData['content']['questions'][0];
        $correctAnswer = $firstQuestion['correct_answer'];

        // API call to submit correct answer
        $response = $this->postJson("/api/{$this->tenant->slug}/student/exercises/{$exercise->id}/submit-answer", [
            'answer' => $correctAnswer,
            'question_index' => 0
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'correct',
                    'feedback',
                    'points_earned',
                    'vocabulary_reinforcement' => [
                        'word',
                        'syllabics',
                        'audio_url',
                        'cultural_context'
                    ]
                ]
            ]);

        $answerData = $response->json('data');
        $this->assertTrue($answerData['correct']);
        $this->assertGreaterThan(0, $answerData['points_earned']);
        $this->assertArrayHasKey('vocabulary_reinforcement', $answerData);
    }

    // Helper methods
    private function enrollStudentInLearningPath(): void
    {
        $this->runInTenantContext($this->tenant, function () {
            // Create enrollment record in user_learning_paths table
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
