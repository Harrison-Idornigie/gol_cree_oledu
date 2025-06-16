<?php
namespace Database\Seeders\Tenant;

use App\Models\Tenants\Language;
use App\Models\Tenants\User;
use App\Models\Tenants\Word;
use App\Models\Tenants\WordTranslation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class WordSeeder extends Seeder
{

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Authenticate as super admin for content versioning (skip for testing)
        $superAdmin = User::where('email', 'test.superadmin@oledu.ca')->first();
        if ($superAdmin) {
            Auth::login($superAdmin);
        } else {
            $this->command->warn('Super Admin user not found. Proceeding without authentication for testing.');
        }

        // Ensure we have the required languages
        $english    = Language::where('code', 'en')->first();
        $spanish    = Language::where('code', 'es')->first();
        $plainsCree = Language::where('code', 'crk')->first();

        if (! $english || ! $spanish || ! $plainsCree) {
            $this->command->error('Required languages not found. Please run LanguageSeeder first.');
            return;
        }

        // Clear existing words if needed
        if ($this->command->confirm('Do you want to clear existing words before seeding?', true)) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            Word::truncate();
            WordTranslation::truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            $this->command->info('Cleared existing words.');
        }

        // Load word lists from JSON files
        $englishWords = $this->loadWordList('english_words.json', $english->id);
        $spanishWords = $this->loadWordList('spanish_words.json', $spanish->id);
        $creeWords    = $this->loadWordList('plains_cree_words.json', $plainsCree->id);

        // Seed English words
        $this->command->info('Seeding ' . count($englishWords) . ' English words...');
        $this->seedWords($englishWords, $english->id);

        // Seed Spanish words
        $this->command->info('Seeding ' . count($spanishWords) . ' Spanish words...');
        $this->seedWords($spanishWords, $spanish->id);

        // Seed Plains Cree words
        $this->command->info('Seeding ' . count($creeWords) . ' Plains Cree words...');
        $this->seedWords($creeWords, $plainsCree->id);

        // Create translations between languages
        $this->command->info('Creating translations between languages...');
        $this->createTranslations($english->id, $spanish->id, $plainsCree->id);

        $this->command->info('Word seeding completed successfully!');
    }

    /**
     * Load word list from JSON file or generate if not exists
     */
    private function loadWordList(string $filename, int $languageId): array
    {
        $path = database_path('seeders/data/' . $filename);

        if (File::exists($path)) {
            return json_decode(File::get($path), true);
        }

        // If file doesn't exist, generate placeholder data
        $words = [];
        $faker = \Faker\Factory::create();

        for ($i = 0; $i < 500; $i++) {
            $words[] = [
                'text'              => $faker->unique()->word(),
                'language_id'       => $languageId,
                'part_of_speech'    => $faker->randomElement(['noun', 'verb', 'adjective', 'adverb']),
                'pronunciation_key' => '/' . $faker->lexify('????') . '/',
                'metadata'          => [
                    'difficulty' => $faker->randomElement(['beginner', 'intermediate', 'advanced']),
                    'tags'       => $faker->randomElements(['common', 'academic', 'slang', 'formal'], 2),
                ],
            ];
        }

        // Ensure directory exists
        if (! File::exists(database_path('seeders/data'))) {
            File::makeDirectory(database_path('seeders/data'), 0755, true);
        }

        // Save generated data for future use
        File::put($path, json_encode($words, JSON_PRETTY_PRINT));

        return $words;
    }

    /**
     * Seed words for a specific language with improved performance
     */
    private function seedWords(array $wordList, int $languageId): void
    {
        $count     = 0;
        $total     = count($wordList);
        $batchSize = 100; // Increased for better performance
        $batches   = array_chunk($wordList, $batchSize);

        foreach ($batches as $batch) {
            $wordsToInsert = [];

            foreach ($batch as $wordData) {
                // IMPROVED: Clean metadata structure for K-12 Plains Cree learning
                $metadata = $wordData['metadata'] ?? [];
                
                // Ensure consistent metadata structure
                $standardizedMetadata = [
                    'difficulty_level' => $metadata['difficulty'] ?? 'beginner',
                    'cultural_significance' => $metadata['cultural_significance'] ?? null,
                    'syllabic_notation' => $metadata['syllabic_notation'] ?? null,
                    'semantic_domain' => $metadata['semantic_domain'] ?? 'general',
                    'learning_hints' => $metadata['learning_hints'] ?? [],
                    'frequency_score' => $metadata['frequency_score'] ?? 1,
                ];

                // IMPROVED: Handle pronunciation consistently
                $pronunciationKey = null;
                if (isset($wordData['pronunciation_key']) && preg_match('/^\/.*\/$/', $wordData['pronunciation_key'])) {
                    // Standard IPA notation
                    $pronunciationKey = $wordData['pronunciation_key'];
                } elseif (isset($wordData['pronunciation_key'])) {
                    // Non-IPA pronunciation guide goes to metadata
                    $standardizedMetadata['pronunciation_guide'] = $wordData['pronunciation_key'];
                }

                $wordsToInsert[] = [
                    'language_id'       => $languageId,
                    'text'              => $wordData['text'],
                    'pronunciation_key' => $pronunciationKey,
                    'part_of_speech'    => $wordData['part_of_speech'] ?? null,
                    'metadata'          => json_encode($standardizedMetadata),
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ];
            }

            // IMPROVED: Use batch insert for better performance
            try {
                DB::table('words')->insertOrIgnore($wordsToInsert);
            } catch (\Exception $e) {
                // Fallback to individual inserts for duplicate handling
                foreach ($wordsToInsert as $wordData) {
                    Word::updateOrCreate(
                        [
                            'language_id'    => $wordData['language_id'],
                            'text'           => $wordData['text'],
                            'part_of_speech' => $wordData['part_of_speech'],
                        ],
                        [
                            'pronunciation_key' => $wordData['pronunciation_key'],
                            'metadata'          => $wordData['metadata'],
                            'updated_at'        => $wordData['updated_at'],
                        ]
                    );
                }
            }

            $count += count($wordsToInsert);
            $this->command->info("Seeded {$count}/{$total} words");
        }
    }

    /**
     * Create translations between languages
     */
    private function createTranslations(int $englishId, int $spanishId, int $creeId): void
    {
        // Get all words
        $englishWords = Word::where('language_id', $englishId)->get();
        $spanishWords = Word::where('language_id', $spanishId)->get();
        $creeWords    = Word::where('language_id', $creeId)->get();

        // Create translations in batches
        $this->command->info('Creating English to Spanish translations...');
        $this->createTranslationBatch($englishWords, $spanishWords, $spanishId);

        $this->command->info('Creating English to Plains Cree translations...');
        $this->createTranslationBatch($englishWords, $creeWords, $creeId);

        $this->command->info('Creating Spanish to English translations...');
        $this->createTranslationBatch($spanishWords, $englishWords, $englishId);

        $this->command->info('Creating Spanish to Plains Cree translations...');
        $this->createTranslationBatch($spanishWords, $creeWords, $creeId);

        $this->command->info('Creating Plains Cree to English translations...');
        $this->createTranslationBatch($creeWords, $englishWords, $englishId);

        $this->command->info('Creating Plains Cree to Spanish translations...');
        $this->createTranslationBatch($creeWords, $spanishWords, $spanishId);
    }

    /**
     * Create translations between two sets of words
     */
    private function createTranslationBatch($sourceWords, $targetWords, $targetLanguageId): void
    {
        $translations = [];
        $count        = 0;
        $total        = count($sourceWords);
        $batchSize    = 100;

        foreach ($sourceWords as $index => $sourceWord) {
            // Use corresponding target word or random if index out of bounds
            $targetWord = $targetWords[$index % count($targetWords)];

            // IMPROVED: Clean separation of concerns
            // Metadata should only contain cultural/pedagogical information
            $targetMetadata = json_decode($targetWord->metadata, true) ?? [];
            
            // IMPROVED: Use dedicated pronunciation_key field for IPA/standard pronunciation
            // Only use metadata for cultural pronunciation guides (e.g., syllabics)
            $pronunciationKey = $targetWord->pronunciation_key;
            
            // IMPROVED: Handle cultural pronunciation guides in metadata separately
            $culturalPronunciation = $targetMetadata['cultural_pronunciation'] ?? null;
            $usageExamples = $targetMetadata['usage_examples'] ?? [];
            
            $translations[] = [
                'word_id'           => $sourceWord->id,
                'language_id'       => $targetLanguageId,
                'text'              => $targetWord->text,
                'pronunciation_key' => $pronunciationKey, // Standard IPA pronunciation
                'context_notes'     => $this->generateContextNotes($sourceWord, $targetWord),
                'usage_examples'    => json_encode($usageExamples), // Structured examples
                'translation_order' => 1,
                'created_at'        => now(),
                'updated_at'        => now(),
            ];

            $count++;

            if (count($translations) >= $batchSize || $count >= $total) {
                // IMPROVED: Better conflict resolution
                foreach ($translations as $translation) {
                    WordTranslation::updateOrCreate(
                        [
                            'word_id'           => $translation['word_id'],
                            'language_id'       => $translation['language_id'],
                            'translation_order' => $translation['translation_order'],
                        ],
                        [
                            'text'              => $translation['text'],
                            'pronunciation_key' => $translation['pronunciation_key'],
                            'context_notes'     => $translation['context_notes'],
                            'usage_examples'    => $translation['usage_examples'],
                            'updated_at'        => $translation['updated_at'],
                        ]
                    );
                }

                $this->command->info("Created {$count}/{$total} translations");
                $translations = [];
            }
        }
    }

    /**
     * Generate contextually appropriate translation notes
     */
    private function generateContextNotes($sourceWord, $targetWord): string
    {
        $sourceMetadata = json_decode($sourceWord->metadata, true) ?? [];
        $targetMetadata = json_decode($targetWord->metadata, true) ?? [];
        
        $notes = [];
        
        // Add cultural context if available
        if (isset($sourceMetadata['cultural_significance'])) {
            $notes[] = "Cultural note: " . $sourceMetadata['cultural_significance'];
        }
        
        // Add grammatical context
        if ($sourceWord->part_of_speech !== $targetWord->part_of_speech) {
            $notes[] = "Part of speech differs: {$sourceWord->part_of_speech} → {$targetWord->part_of_speech}";
        }
        
        // Add difficulty context for K-12 learning
        if (isset($targetMetadata['difficulty_level'])) {
            $level = $targetMetadata['difficulty_level'];
            $notes[] = "Difficulty: {$level}";
        }
        
        return implode('; ', $notes) ?: "Translation of '{$sourceWord->text}'";
    }
}