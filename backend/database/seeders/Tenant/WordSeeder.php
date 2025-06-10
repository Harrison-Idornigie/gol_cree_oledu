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
        // Authenticate as super admin for content versioning
        $superAdmin = User::where('email', 'test.superadmin@oledu.ca')->first();
        if (! $superAdmin) {
            $this->command->error('Super Admin user not found. Please run SuperAdminSeeder first.');
            return;
        }
        Auth::login($superAdmin);

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
        $this->command->info('Seeding 500 English words...');
        $this->seedWords($englishWords, $english->id);

        // Seed Spanish words
        $this->command->info('Seeding 500 Spanish words...');
        $this->seedWords($spanishWords, $spanish->id);

        // Seed Plains Cree words
        $this->command->info('Seeding 500 Plains Cree words...');
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
     * Seed words for a specific language
     */
    private function seedWords(array $wordList, int $languageId): void
    {
        $count     = 0;
        $total     = count($wordList);
        $batchSize = 50;
        $batches   = array_chunk($wordList, $batchSize);

        foreach ($batches as $batch) {
            $wordsToInsert = [];

            foreach ($batch as $wordData) {
                // Update metadata to include pronunciation guide and notes if using new format
                $metadata = $wordData['metadata'] ?? [];

                // If we have a pronunciation_key in the new format (like "TAH-nee-see" instead of IPA)
                // move it to metadata.pronunciation_guide
                if (isset($wordData['pronunciation_key']) && ! preg_match('/^\/.*\/$/', $wordData['pronunciation_key'])) {
                    $metadata['pronunciation_guide'] = $wordData['pronunciation_key'];
                    $pronunciationKey                = null; // Don't use the pronunciation_key field for the new format
                } else {
                    $pronunciationKey = $wordData['pronunciation_key'] ?? null;
                }

                $wordsToInsert[] = [
                    'language_id'       => $languageId,
                    'text'              => $wordData['text'],
                    'pronunciation_key' => $pronunciationKey,
                    'part_of_speech'    => $wordData['part_of_speech'] ?? null,
                    'metadata'          => json_encode($metadata),
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ];
            }

            // Use updateOrCreate instead of insert to handle duplicates
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
                        'created_at'        => $wordData['created_at'],
                        'updated_at'        => $wordData['updated_at'],
                    ]
                );
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
        $englishWords = Word::where('language_id', $englishId)->take(500)->get();
        $spanishWords = Word::where('language_id', $spanishId)->take(500)->get();
        $creeWords    = Word::where('language_id', $creeId)->take(500)->get();

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

            // Get pronunciation guide from target word's metadata
            $targetMetadata     = json_decode($targetWord->metadata, true) ?? [];
            $pronunciationGuide = $targetMetadata['pronunciation_guide'] ?? null;
            $pronunciationKey   = $targetWord->pronunciation_key;

            // If we have a pronunciation_guide in metadata, use that instead of pronunciation_key
            if ($pronunciationGuide) {
                $pronunciationKey = null;
            }

            $translations[] = [
                'word_id'           => $sourceWord->id,
                'language_id'       => $targetLanguageId,
                'text'              => $targetWord->text,
                'pronunciation_key' => $pronunciationKey,
                'context_notes'     => "Translation of '{$sourceWord->text}'",
                'usage_examples'    => $targetMetadata['example'] ?? null,
                'translation_order' => 1,
                'created_at'        => now(),
                'updated_at'        => now(),
            ];

            $count++;

            if (count($translations) >= $batchSize || $count >= $total) {
                // Use updateOrCreate instead of insert to handle duplicates
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
                            'created_at'        => $translation['created_at'],
                            'updated_at'        => $translation['updated_at'],
                        ]
                    );
                }

                $this->command->info("Created {$count}/{$total} translations");
                $translations = [];
            }
        }
    }
}