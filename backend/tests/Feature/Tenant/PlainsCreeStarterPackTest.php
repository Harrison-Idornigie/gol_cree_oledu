<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenants\Language;
use App\Models\Tenants\LearningPath;
use App\Models\Tenants\Word;
use App\Models\Tenants\Exercise;
use App\Models\Tenants\CurriculumTemplate;
use App\Services\Tenant\StarterPackService;
use Database\Seeders\Tenant\PlainsCreeStarterPackSeeder;
use Database\Seeders\Tenant\PlainsCreeVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\InteractsWithTenancy;

class PlainsCreeStarterPackTest extends TestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected StarterPackService $starterPackService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->starterPackService = new StarterPackService();
    }

    /** @test */
    public function it_can_initialize_plains_cree_starter_pack()
    {
        // Act
        $result = $this->starterPackService->initializeStarterPack();

        // Assert
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('stats', $result);

        // Verify Plains Cree language exists
        $plainsCree = Language::where('code', 'crk')->first();
        $this->assertNotNull($plainsCree);
        $this->assertEquals('Plains Cree', $plainsCree->name);
        $this->assertEquals('nēhiyawēwin', $plainsCree->native_name);
    }

    /** @test */
    public function it_creates_complete_a1_to_c2_learning_paths()
    {
        // Arrange
        $this->starterPackService->initializeStarterPack();

        // Act
        $plainsCree = Language::where('code', 'crk')->first();
        $learningPaths = LearningPath::where('language_id', $plainsCree->id)->get();

        // Assert
        $this->assertGreaterThanOrEqual(6, $learningPaths->count()); // At least A1-C2

        $levels = $learningPaths->pluck('target_level')->unique()->toArray();
        $expectedLevels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];

        foreach ($expectedLevels as $level) {
            $this->assertContains($level, $levels, "Missing {$level} learning path");
        }
    }

    /** @test */
    public function it_validates_vocabulary_progression_constraints()
    {
        // Arrange
        $this->starterPackService->initializeStarterPack();
        $progressionConstraints = PlainsCreeVocabularySeeder::getVocabularyProgressionConstraints();

        // Act & Assert
        foreach ($progressionConstraints as $level => $allowedLevels) {
            $vocabulary = Word::whereHas('language', function ($query) {
                $query->where('code', 'crk');
            })
                ->where(function ($query) use ($allowedLevels) {
                    foreach ($allowedLevels as $allowedLevel) {
                        $query->orWhereJsonContains('metadata->proficiency_level', $allowedLevel);
                    }
                })
                ->get();

            $this->assertGreaterThan(0, $vocabulary->count(), "No vocabulary found for {$level} level");

            // Verify vocabulary only contains allowed levels
            foreach ($vocabulary as $word) {
                $wordLevel = $word->metadata['proficiency_level'] ?? 'A1';
                $this->assertContains(
                    $wordLevel,
                    $allowedLevels,
                    "Word '{$word->text}' has level {$wordLevel} which is not allowed for {$level} course"
                );
            }
        }
    }

    /** @test */
    public function it_ensures_all_exercises_use_predefined_vocabulary()
    {
        // Arrange
        $this->starterPackService->initializeStarterPack();

        // Act
        $exercises = Exercise::whereHas('lesson.topic.unit.learningPath', function ($query) {
            $query->whereHas('language', function ($subQuery) {
                $subQuery->where('code', 'crk');
            });
        })->get();

        // Assert
        $this->assertGreaterThan(0, $exercises->count(), 'No exercises found');

        foreach ($exercises as $exercise) {
            $vocabularyUsed = $exercise->metadata['vocabulary_used'] ?? [];

            if (!empty($vocabularyUsed)) {
                foreach ($vocabularyUsed as $wordId) {
                    $word = Word::find($wordId);
                    $this->assertNotNull($word, "Exercise {$exercise->id} references non-existent word ID {$wordId}");
                    $this->assertEquals('crk', $word->language->code, "Exercise uses word from wrong language");
                }
            }
        }
    }

    /** @test */
    public function it_validates_duolingo_style_features_in_exercises()
    {
        // Arrange
        $this->starterPackService->initializeStarterPack();

        // Act
        $exercises = Exercise::whereHas('lesson.topic.unit.learningPath', function ($query) {
            $query->whereHas('language', function ($subQuery) {
                $subQuery->where('code', 'crk');
            });
        })->get();

        // Assert
        foreach ($exercises as $exercise) {
            $metadata = $exercise->metadata ?? [];
            $content = $exercise->content ?? [];

            // Check for Duolingo-style features
            $this->assertArrayHasKey(
                'duolingo_style',
                $metadata,
                "Exercise {$exercise->id} missing Duolingo-style metadata"
            );

            $duolingoFeatures = $metadata['duolingo_style'];
            $this->assertTrue(
                $duolingoFeatures['clickable_vocabulary'] ?? false,
                "Exercise {$exercise->id} missing clickable vocabulary"
            );
            $this->assertTrue(
                $duolingoFeatures['audio_pronunciation'] ?? false,
                "Exercise {$exercise->id} missing audio pronunciation"
            );
            $this->assertTrue(
                $duolingoFeatures['syllabics_display'] ?? false,
                "Exercise {$exercise->id} missing syllabics display"
            );

            // Check for clickable vocabulary in content
            if (isset($content['clickable_vocabulary'])) {
                foreach ($content['clickable_vocabulary'] as $vocabItem) {
                    $this->assertArrayHasKey('word_id', $vocabItem);
                    $this->assertArrayHasKey('text', $vocabItem);
                    $this->assertArrayHasKey('syllabics', $vocabItem);
                    $this->assertArrayHasKey('translation', $vocabItem);
                    $this->assertArrayHasKey('audio_url', $vocabItem);
                }
            }
        }
    }

    /** @test */
    public function it_validates_age_appropriate_learning_paths()
    {
        // Arrange
        $this->starterPackService->initializeStarterPack();

        // Act
        $learningPaths = LearningPath::whereHas('language', function ($query) {
            $query->where('code', 'crk');
        })->get();

        // Assert
        foreach ($learningPaths as $path) {
            $lessons = $path->units()->with('topics.lessons')->get()
                ->flatMap->topics
                ->flatMap->lessons;

            foreach ($lessons as $lesson) {
                $metadata = $lesson->metadata ?? [];

                if (isset($metadata['age_group'])) {
                    $ageGroup = $metadata['age_group'];
                    $this->assertContains(
                        $ageGroup,
                        ['kids', 'teen_adult'],
                        "Invalid age group '{$ageGroup}' in lesson {$lesson->id}"
                    );

                    if (isset($metadata['duration_settings'])) {
                        $duration = $metadata['duration_settings'];

                        if ($ageGroup === 'kids') {
                            $this->assertLessThanOrEqual(
                                10,
                                $duration['target_duration_minutes'] ?? 0,
                                "Kids lesson {$lesson->id} duration too long"
                            );
                        } else {
                            $this->assertLessThanOrEqual(
                                15,
                                $duration['target_duration_minutes'] ?? 0,
                                "Teen/adult lesson {$lesson->id} duration too long"
                            );
                        }
                    }
                }
            }
        }
    }

    /** @test */
    public function it_can_clone_course_structure_for_other_languages()
    {
        // Arrange
        $this->starterPackService->initializeStarterPack();

        // Create a target language
        $spanish = Language::create([
            'code' => 'es',
            'name' => 'Spanish',
            'native_name' => 'Español'
        ]);

        // Act
        $result = $this->starterPackService->cloneCourseStructureForLanguage('es');

        // Assert
        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['cloned_templates']);
        $this->assertGreaterThan(0, $result['cloned_paths']);

        // Verify cloned templates exist
        $clonedTemplates = CurriculumTemplate::whereHas('languagePair', function ($query) use ($spanish) {
            $query->where('target_language_id', $spanish->id);
        })->get();

        $this->assertGreaterThan(0, $clonedTemplates->count());

        // Verify cloned learning paths exist
        $clonedPaths = LearningPath::where('language_id', $spanish->id)->get();
        $this->assertGreaterThan(0, $clonedPaths->count());

        foreach ($clonedPaths as $path) {
            $this->assertStringContains('Spanish', $path->title);
            $this->assertEquals('draft', $path->status);
            $this->assertArrayHasKey('cloned_from', $path->metadata ?? []);
        }
    }

    /** @test */
    public function it_validates_starter_pack_completeness()
    {
        // Arrange
        $this->starterPackService->initializeStarterPack();

        // Act
        $seeder = new PlainsCreeStarterPackSeeder();
        $validation = $seeder->validateStarterPack();

        // Assert
        $this->assertTrue(
            $validation['is_valid'],
            'Starter pack validation failed: ' . implode(', ', $validation['issues'] ?? [])
        );

        $stats = $validation['stats'];
        $this->assertGreaterThanOrEqual(6, $stats['total_learning_paths']);
        $this->assertGreaterThanOrEqual(500, $stats['total_vocabulary']);
        $this->assertGreaterThanOrEqual(100, $stats['total_exercises']);

        // Validate vocabulary distribution
        $expectedLevels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
        foreach ($expectedLevels as $level) {
            $this->assertArrayHasKey(
                $level,
                $stats['vocabulary_by_level'],
                "Missing vocabulary for level {$level}"
            );
            $this->assertGreaterThan(
                0,
                $stats['vocabulary_by_level'][$level],
                "No vocabulary found for level {$level}"
            );
        }
    }

    /** @test */
    public function it_ensures_all_vocabulary_has_translations_and_audio_references()
    {
        // Arrange
        $this->starterPackService->initializeStarterPack();

        // Act
        $plainsCree = Language::where('code', 'crk')->first();
        $vocabulary = Word::where('language_id', $plainsCree->id)
            ->with('translations')
            ->get();

        // Assert
        $this->assertGreaterThan(0, $vocabulary->count());

        foreach ($vocabulary as $word) {
            // Check for English translation
            $englishTranslation = $word->translations->where('language.code', 'en')->first();
            $this->assertNotNull(
                $englishTranslation,
                "Word '{$word->text}' missing English translation"
            );

            // Check for syllabics
            $this->assertArrayHasKey(
                'syllabics',
                $word->metadata ?? [],
                "Word '{$word->text}' missing syllabics"
            );

            // Check for audio reference
            $this->assertNotEmpty(
                $word->metadata['syllabics'] ?? '',
                "Word '{$word->text}' has empty syllabics"
            );

            // Check for cultural context
            $this->assertArrayHasKey(
                'cultural_context',
                $word->metadata ?? [],
                "Word '{$word->text}' missing cultural context"
            );

            // Check for proficiency level
            $this->assertArrayHasKey(
                'proficiency_level',
                $word->metadata ?? [],
                "Word '{$word->text}' missing proficiency level"
            );
        }
    }
}
