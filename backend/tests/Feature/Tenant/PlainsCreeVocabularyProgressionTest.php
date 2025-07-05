<?php

namespace Tests\Feature\Tenant;

use App\Models\Tenants\Language;
use App\Models\Tenants\Word;
use App\Models\Tenants\LearningPath;
use App\Models\Tenants\Exercise;
use Database\Seeders\Tenant\PlainsCreeVocabularySeeder;
use Database\Seeders\Tenant\PlainsCreeA1CourseSeeder;
use Database\Seeders\Tenant\PlainsCreeA2CourseSeeder;
use Database\Seeders\Tenant\PlainsCreeB1CourseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;
use Tests\Traits\InteractsWithTenancy;

class PlainsCreeVocabularyProgressionTest extends TenantTestCase
{
    use RefreshDatabase, InteractsWithTenancy;

    protected function setUp(): void
    {
        parent::setUp();

        // Create required languages
        Language::create(['code' => 'crk', 'name' => 'Plains Cree', 'native_name' => 'nēhiyawēwin']);
        Language::create(['code' => 'en', 'name' => 'English', 'native_name' => 'English']);
    }

    /**  */
    public function it_enforces_vocabulary_progression_constraints()
    {
        // Arrange
        $this->seed(PlainsCreeVocabularySeeder::class);
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

            // Verify vocabulary exists for this level
            $this->assertGreaterThan(
                0,
                $vocabulary->count(),
                "No vocabulary found for {$level} level with constraints: " . implode(', ', $allowedLevels)
            );

            // Verify no vocabulary from higher levels is included
            $allLevels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
            $disallowedLevels = array_diff($allLevels, $allowedLevels);

            foreach ($vocabulary as $word) {
                $wordLevel = $word->metadata['proficiency_level'] ?? 'A1';
                $this->assertNotContains(
                    $wordLevel,
                    $disallowedLevels,
                    "Word '{$word->text}' has level {$wordLevel} which should not be available for {$level} course"
                );
            }
        }
    }

    /**  */
    public function it_validates_vocabulary_count_targets()
    {
        // Arrange
        $this->seed(PlainsCreeVocabularySeeder::class);
        $countTargets = PlainsCreeVocabularySeeder::getVocabularyCountTargets();

        // Act & Assert
        foreach ($countTargets as $level => $targets) {
            $vocabulary = Word::whereHas('language', function ($query) {
                $query->where('code', 'crk');
            })
                ->whereJsonContains('metadata->proficiency_level', $level)
                ->get();

            $actualCount = $vocabulary->count();
            $targetCount = $targets['total'];

            $this->assertGreaterThanOrEqual(
                $targetCount * 0.8,
                $actualCount,
                "Level {$level} has {$actualCount} words, but target is {$targetCount} (allowing 20% tolerance)"
            );

            // Verify per-lesson targets are reasonable
            $this->assertGreaterThan(
                0,
                $targets['per_lesson'],
                "Level {$level} per-lesson target must be greater than 0"
            );
            $this->assertLessThanOrEqual(
                10,
                $targets['per_lesson'],
                "Level {$level} per-lesson target should not exceed 10 words"
            );
        }
    }

    /**  */
    public function it_ensures_a1_vocabulary_is_foundational()
    {
        // Arrange
        $this->seed(PlainsCreeVocabularySeeder::class);

        // Act
        $a1Vocabulary = Word::whereHas('language', function ($query) {
            $query->where('code', 'crk');
        })
            ->whereJsonContains('metadata->proficiency_level', 'A1')
            ->get();

        // Assert
        $this->assertGreaterThanOrEqual(
            100,
            $a1Vocabulary->count(),
            'A1 level should have at least 100 foundational words'
        );

        // Check for essential word categories
        $essentialCategories = ['family', 'common', 'greeting', 'number', 'colors'];
        $foundCategories = [];

        foreach ($a1Vocabulary as $word) {
            $tags = $word->metadata['tags'] ?? [];
            foreach ($essentialCategories as $category) {
                if (in_array($category, $tags)) {
                    $foundCategories[] = $category;
                }
            }
        }

        $uniqueFoundCategories = array_unique($foundCategories);
        $this->assertGreaterThanOrEqual(
            3,
            count($uniqueFoundCategories),
            'A1 vocabulary should cover at least 3 essential categories'
        );
    }

    /**  */
    public function it_validates_vocabulary_progression_in_course_content()
    {
        // Arrange
        $this->seed(PlainsCreeVocabularySeeder::class);

        // Create curriculum templates first
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\Tenant\\CurriculumTemplateSeeder']);

        // Act - Create A1 and A2 courses
        $a1Seeder = new PlainsCreeA1CourseSeeder();
        $a1Seeder->run();

        $a2Seeder = new PlainsCreeA2CourseSeeder();
        $a2Seeder->run();

        // Assert - A1 course should only use A1 vocabulary
        $a1Path = LearningPath::where('target_level', 'A1')
            ->whereHas('language', function ($query) {
                $query->where('code', 'crk');
            })
            ->first();

        $this->assertNotNull($a1Path, 'A1 learning path should exist');

        $a1Exercises = Exercise::whereHas('lesson.topic.unit.learningPath', function ($query) use ($a1Path) {
            $query->where('id', $a1Path->id);
        })->get();

        foreach ($a1Exercises as $exercise) {
            $vocabularyUsed = $exercise->metadata['vocabulary_used'] ?? [];

            foreach ($vocabularyUsed as $wordId) {
                $word = Word::find($wordId);
                $wordLevel = $word->metadata['proficiency_level'] ?? 'A1';
                $this->assertEquals(
                    'A1',
                    $wordLevel,
                    "A1 exercise {$exercise->id} uses word '{$word->text}' from level {$wordLevel}"
                );
            }
        }

        // Assert - A2 course should use A1 + A2 vocabulary
        $a2Path = LearningPath::where('target_level', 'A2')
            ->whereHas('language', function ($query) {
                $query->where('code', 'crk');
            })
            ->first();

        $this->assertNotNull($a2Path, 'A2 learning path should exist');

        $a2Exercises = Exercise::whereHas('lesson.topic.unit.learningPath', function ($query) use ($a2Path) {
            $query->where('id', $a2Path->id);
        })->get();

        foreach ($a2Exercises as $exercise) {
            $vocabularyUsed = $exercise->metadata['vocabulary_used'] ?? [];

            foreach ($vocabularyUsed as $wordId) {
                $word = Word::find($wordId);
                $wordLevel = $word->metadata['proficiency_level'] ?? 'A1';
                $this->assertContains(
                    $wordLevel,
                    ['A1', 'A2'],
                    "A2 exercise {$exercise->id} uses word '{$word->text}' from level {$wordLevel}"
                );
            }
        }
    }

    /**  */
    public function it_ensures_vocabulary_has_required_metadata()
    {
        // Arrange
        $this->seed(PlainsCreeVocabularySeeder::class);

        // Act
        $vocabulary = Word::whereHas('language', function ($query) {
            $query->where('code', 'crk');
        })->get();

        // Assert
        $this->assertGreaterThan(0, $vocabulary->count());

        foreach ($vocabulary as $word) {
            $metadata = $word->metadata ?? [];

            // Required fields for Plains Cree vocabulary
            $this->assertArrayHasKey(
                'proficiency_level',
                $metadata,
                "Word '{$word->text}' missing proficiency_level"
            );
            $this->assertArrayHasKey(
                'syllabics',
                $metadata,
                "Word '{$word->text}' missing syllabics"
            );
            $this->assertArrayHasKey(
                'translation',
                $metadata,
                "Word '{$word->text}' missing translation"
            );
            $this->assertArrayHasKey(
                'cultural_context',
                $metadata,
                "Word '{$word->text}' missing cultural_context"
            );

            // Validate proficiency level
            $level = $metadata['proficiency_level'];
            $this->assertContains(
                $level,
                ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'],
                "Word '{$word->text}' has invalid proficiency level: {$level}"
            );

            // Validate syllabics is not empty
            $this->assertNotEmpty(
                $metadata['syllabics'],
                "Word '{$word->text}' has empty syllabics"
            );

            // Validate translation is not empty
            $this->assertNotEmpty(
                $metadata['translation'],
                "Word '{$word->text}' has empty translation"
            );
        }
    }

    /**  */
    public function it_validates_vocabulary_distribution_across_levels()
    {
        // Arrange
        $this->seed(PlainsCreeVocabularySeeder::class);

        // Act
        $distribution = Word::whereHas('language', function ($query) {
            $query->where('code', 'crk');
        })
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.proficiency_level')) as level, COUNT(*) as count")
            ->groupBy('level')
            ->pluck('count', 'level')
            ->toArray();

        // Assert
        $expectedLevels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];

        foreach ($expectedLevels as $level) {
            $this->assertArrayHasKey(
                $level,
                $distribution,
                "No vocabulary found for level {$level}"
            );
            $this->assertGreaterThan(
                0,
                $distribution[$level],
                "Level {$level} has no vocabulary words"
            );
        }

        // A1 should have the most vocabulary (foundational)
        $this->assertGreaterThanOrEqual(
            $distribution['A2'],
            $distribution['A1'],
            'A1 should have at least as much vocabulary as A2'
        );

        // Higher levels should generally have less vocabulary
        $this->assertLessThanOrEqual(
            $distribution['B1'],
            $distribution['B2'],
            'B2 should have less or equal vocabulary than B1'
        );
        $this->assertLessThanOrEqual(
            $distribution['C1'],
            $distribution['C2'],
            'C2 should have less or equal vocabulary than C1'
        );
    }

    /**  */
    public function it_ensures_vocabulary_progression_supports_spaced_repetition()
    {
        // Arrange
        $this->seed(PlainsCreeVocabularySeeder::class);

        // Act
        $progressionConstraints = PlainsCreeVocabularySeeder::getVocabularyProgressionConstraints();

        // Assert - Each level should include all previous levels for spaced repetition
        $expectedProgression = [
            'A1' => ['A1'],
            'A2' => ['A1', 'A2'],
            'B1' => ['A1', 'A2', 'B1'],
            'B2' => ['A1', 'A2', 'B1', 'B2'],
            'C1' => ['A1', 'A2', 'B1', 'B2', 'C1'],
            'C2' => ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'],
        ];

        foreach ($expectedProgression as $level => $expectedLevels) {
            $actualLevels = $progressionConstraints[$level] ?? [];
            $this->assertEquals(
                $expectedLevels,
                $actualLevels,
                "Level {$level} progression constraints don't match expected pattern for spaced repetition"
            );
        }
    }
}
