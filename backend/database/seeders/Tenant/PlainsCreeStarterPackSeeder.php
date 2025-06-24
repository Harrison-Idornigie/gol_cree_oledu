<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenants\User;
use App\Models\Tenants\Language;
use App\Models\Tenants\LanguagePair;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PlainsCreeStarterPackSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * This seeder creates a complete Plains Cree A1-C2 course starter pack
     * that serves as both usable content and templates for other languages.
     */
    public function run(): void
    {
        // Authenticate as the first user for content versioning
        $user = User::first();
        if ($user) {
            Auth::login($user);
        }

        $this->command->info('Creating Plains Cree Starter Pack for Multi-Tenant System...');

        // Ensure required languages exist
        $this->ensureLanguagesExist();

        // Ensure language pairs exist
        $this->ensureLanguagePairsExist();

        // Run vocabulary seeder first
        $this->command->info('Seeding Plains Cree vocabulary...');
        $this->call(PlainsCreeVocabularySeeder::class);

        // Run curriculum template seeder
        $this->command->info('Seeding curriculum templates...');
        $this->call(CurriculumTemplateSeeder::class);

        // Create complete A1-C2 course content with both age groups
        $this->createCompleteCourseSuite();

        // Mark as official starter pack content
        $this->markAsStarterPackContent();

        $this->command->info('Plains Cree Starter Pack created successfully!');
        $this->command->info('This tenant now has complete A1-C2 Plains Cree courses that can serve as templates for other languages.');
    }

    /**
     * Ensure required languages exist
     */
    private function ensureLanguagesExist(): void
    {
        $languages = [
            ['code' => 'crk', 'name' => 'Plains Cree', 'native_name' => 'nēhiyawēwin'],
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English'],
        ];

        foreach ($languages as $langData) {
            Language::updateOrCreate(
                ['code' => $langData['code']],
                $langData
            );
        }

        $this->command->info('Languages ensured: Plains Cree (crk), English (en)');
    }

    /**
     * Ensure language pairs exist
     */
    private function ensureLanguagePairsExist(): void
    {
        $plainsCree = Language::where('code', 'crk')->first();
        $english = Language::where('code', 'en')->first();

        if ($plainsCree && $english) {
            LanguagePair::updateOrCreate([
                'source_language_id' => $english->id,
                'target_language_id' => $plainsCree->id,
            ], [
                'name' => 'English to Plains Cree',
                'is_active' => true,
            ]);

            $this->command->info('Language pair ensured: English → Plains Cree');
        }
    }

    /**
     * Create complete course suite for A1-C2 with both age groups
     */
    private function createCompleteCourseSuite(): void
    {
        $levels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
        $ageGroups = ['kids', 'teen_adult'];

        foreach ($levels as $level) {
            foreach ($ageGroups as $ageGroup) {
                $this->command->info("Creating {$level} course content for {$ageGroup} age group...");
                $this->createLevelCourse($level, $ageGroup);
            }
        }
    }

    /**
     * Create course content for a specific level and age group
     */
    private function createLevelCourse(string $level, string $ageGroup): void
    {
        $seederClass = "Database\\Seeders\\Tenant\\PlainsCree{$level}CourseSeeder";
        
        if (class_exists($seederClass)) {
            $seeder = new $seederClass();
            
            // Set age group for age-appropriate content
            if (method_exists($seeder, 'setAgeGroup')) {
                $seeder->setAgeGroup($ageGroup);
            }
            
            // Run the seeder
            $seeder->run();
            
            $this->command->info("  ✓ {$level} course created for {$ageGroup}");
        } else {
            $this->command->warn("  ✗ Seeder class {$seederClass} not found");
        }
    }

    /**
     * Mark content as official starter pack content
     */
    private function markAsStarterPackContent(): void
    {
        // Mark all Plains Cree learning paths as starter pack content
        DB::table('learning_paths')
            ->whereHas('language', function ($query) {
                $query->where('code', 'crk');
            })
            ->update([
                'metadata' => DB::raw("JSON_SET(COALESCE(metadata, '{}'), '$.is_starter_pack', true, '$.is_official', true, '$.template_source', 'plains_cree_official')")
            ]);

        // Mark curriculum templates as official
        DB::table('curriculum_templates')
            ->whereHas('languagePair.targetLanguage', function ($query) {
                $query->where('code', 'crk');
            })
            ->update(['is_official' => true]);

        $this->command->info('Content marked as official starter pack');
    }

    /**
     * Get starter pack statistics
     */
    public function getStarterPackStats(): array
    {
        $plainsCree = Language::where('code', 'crk')->first();
        
        if (!$plainsCree) {
            return [];
        }

        return [
            'total_learning_paths' => $plainsCree->learningPaths()->count(),
            'total_units' => DB::table('units')
                ->whereIn('learning_path_id', $plainsCree->learningPaths()->pluck('id'))
                ->count(),
            'total_topics' => DB::table('topics')
                ->whereIn('unit_id', function ($query) use ($plainsCree) {
                    $query->select('id')
                        ->from('units')
                        ->whereIn('learning_path_id', $plainsCree->learningPaths()->pluck('id'));
                })
                ->count(),
            'total_lessons' => DB::table('lessons')
                ->whereIn('topic_id', function ($query) use ($plainsCree) {
                    $query->select('topics.id')
                        ->from('topics')
                        ->join('units', 'topics.unit_id', '=', 'units.id')
                        ->whereIn('units.learning_path_id', $plainsCree->learningPaths()->pluck('id'));
                })
                ->count(),
            'total_exercises' => DB::table('exercises')
                ->whereIn('lesson_id', function ($query) use ($plainsCree) {
                    $query->select('lessons.id')
                        ->from('lessons')
                        ->join('topics', 'lessons.topic_id', '=', 'topics.id')
                        ->join('units', 'topics.unit_id', '=', 'units.id')
                        ->whereIn('units.learning_path_id', $plainsCree->learningPaths()->pluck('id'));
                })
                ->count(),
            'total_vocabulary' => $plainsCree->words()->count(),
            'vocabulary_by_level' => $plainsCree->words()
                ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.proficiency_level')) as level, COUNT(*) as count")
                ->groupBy('level')
                ->pluck('count', 'level')
                ->toArray(),
        ];
    }

    /**
     * Validate starter pack completeness
     */
    public function validateStarterPack(): array
    {
        $stats = $this->getStarterPackStats();
        $issues = [];

        // Check minimum content requirements
        if ($stats['total_learning_paths'] < 6) {
            $issues[] = "Insufficient learning paths: {$stats['total_learning_paths']} (minimum: 6 for A1-C2)";
        }

        if ($stats['total_vocabulary'] < 500) {
            $issues[] = "Insufficient vocabulary: {$stats['total_vocabulary']} (minimum: 500)";
        }

        if ($stats['total_exercises'] < 100) {
            $issues[] = "Insufficient exercises: {$stats['total_exercises']} (minimum: 100)";
        }

        // Check vocabulary distribution
        $expectedLevels = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
        foreach ($expectedLevels as $level) {
            if (!isset($stats['vocabulary_by_level'][$level]) || $stats['vocabulary_by_level'][$level] < 10) {
                $issues[] = "Insufficient {$level} vocabulary: " . ($stats['vocabulary_by_level'][$level] ?? 0) . " (minimum: 10)";
            }
        }

        return [
            'is_valid' => empty($issues),
            'issues' => $issues,
            'stats' => $stats,
        ];
    }
}
