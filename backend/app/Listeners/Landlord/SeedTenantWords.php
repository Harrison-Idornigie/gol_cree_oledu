<?php

namespace App\Listeners\Landlord;

use App\Events\Landlord\TenantSeedingRequested;
use App\Models\Tenants\Language;
use App\Models\Tenants\User;
use App\Models\Tenants\Word;
use App\Models\Tenants\WordTranslation;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Seed Tenant Words Listener
 * 
 * Handles seeding of guidebook words for new tenants.
 * This listener automatically seeds Plains Cree, English, and Spanish guidebook
 * with cross-language translations during tenant provisioning.
 */
class SeedTenantWords
{
    /**
     * Handle the event.
     */
    public function handle(TenantSeedingRequested $event): void
    {
        // Skip if words seeding is disabled
        if (!$event->shouldSeed('words')) {
            return;
        }

        $tenant = $event->tenant;

        try {
            // Switch to tenant context for all database operations
            $tenant->run(function () use ($tenant, $event) {
                // Seed guidebook words
                $this->seedWords($tenant, $event->adminUser);
            });

            Log::info('Tenant words seeded successfully', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name
            ]);

        } catch (Exception $e) {
            Log::error('Failed to seed tenant words', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Don't throw - let other seeders continue
        }
    }

    /**
     * Seed guidebook words for the tenant
     */
    private function seedWords($tenant, User $adminUser): void
    {
        // Authenticate as admin user for content versioning
        Auth::login($adminUser);

        // Ensure we have the required languages (they should be seeded by SeedTenantLanguages)
        $english = Language::where('code', 'en')->first();
        $spanish = Language::where('code', 'es')->first();
        $plainsCree = Language::where('code', 'crk')->first();

        if (!$english || !$spanish || !$plainsCree) {
            Log::warning('Required languages not found for word seeding', [
                'tenant_id' => $tenant->id,
                'english_exists' => !!$english,
                'spanish_exists' => !!$spanish,
                'plains_cree_exists' => !!$plainsCree
            ]);
            return;
        }

        // Check if words already exist (avoid duplicate seeding)
        $existingWordsCount = Word::count();
        if ($existingWordsCount > 0) {
            Log::info('Words already exist, skipping seeding', [
                'tenant_id' => $tenant->id,
                'existing_words_count' => $existingWordsCount
            ]);
            return;
        }

        Log::info('Starting guidebook seeding', [
            'tenant_id' => $tenant->id,
            'languages' => [
                'english_id' => $english->id,
                'spanish_id' => $spanish->id,
                'plains_cree_id' => $plainsCree->id
            ]
        ]);

        // Load word lists from JSON files
        $englishWords = $this->loadWordList('english_words.json', $english->id);
        $spanishWords = $this->loadWordList('spanish_words.json', $spanish->id);
        $creeWords = $this->loadWordList('plains_cree_words.json', $plainsCree->id);

        // Seed words for each language
        $this->seedWordsForLanguage($englishWords, $english->id, 'English');
        $this->seedWordsForLanguage($spanishWords, $spanish->id, 'Spanish');
        $this->seedWordsForLanguage($creeWords, $plainsCree->id, 'Plains Cree');

        // Create translations between languages
        Log::info('Creating cross-language translations');
        $this->createTranslations($english->id, $spanish->id, $plainsCree->id);

        Log::info('Guidebook seeding completed successfully', [
            'tenant_id' => $tenant->id,
            'english_words' => count($englishWords),
            'spanish_words' => count($spanishWords),
            'cree_words' => count($creeWords)
        ]);
    }

    /**
     * Load word list from JSON file or generate if not exists
     */
    private function loadWordList(string $filename, int $languageId): array
    {
        $path = database_path("seeders/Tenant/data/{$filename}");

        if (File::exists($path)) {
            $content = File::get($path);
            $words = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to parse JSON file', [
                    'filename' => $filename,
                    'error' => json_last_error_msg()
                ]);
                return [];
            }
            
            return $words;
        }

        Log::warning('Word list file not found, generating placeholder data', [
            'filename' => $filename,
            'path' => $path
        ]);

        // If file doesn't exist, generate minimal placeholder data
        $words = [];
        $faker = \Faker\Factory::create();

        for ($i = 0; $i < 50; $i++) { // Reduced count for automated seeding
            $words[] = [
                'text' => $faker->unique()->word(),
                'language_id' => $languageId,
                'part_of_speech' => $faker->randomElement(['noun', 'verb', 'adjective', 'adverb']),
                'pronunciation_key' => '/' . $faker->lexify('????') . '/',
                'metadata' => [
                    'difficulty' => $faker->randomElement(['beginner', 'intermediate', 'advanced']),
                    'tags' => $faker->randomElements(['common', 'academic', 'slang', 'formal'], 2),
                ],
            ];
        }

        return $words;
    }

    /**
     * Seed words for a specific language with improved performance
     */
    private function seedWordsForLanguage(array $wordList, int $languageId, string $languageName): void
    {
        if (empty($wordList)) {
            Log::warning("No words to seed for {$languageName}");
            return;
        }

        Log::info("Seeding {$languageName} words", ['count' => count($wordList)]);

        $count = 0;
        $total = count($wordList);
        $batchSize = 100;
        $batches = array_chunk($wordList, $batchSize);

        foreach ($batches as $batch) {
            $wordsToInsert = [];

            foreach ($batch as $wordData) {
                // Clean metadata structure for K-12 Plains Cree learning
                $metadata = $wordData['metadata'] ?? [];
                
                // Ensure consistent metadata structure
                $standardizedMetadata = [
                    'difficulty_level' => $metadata['difficulty'] ?? 'beginner',
                    'cultural_significance' => $metadata['cultural_context'] ?? $metadata['cultural_significance'] ?? null,
                    'syllabic_notation' => $metadata['syllabics'] ?? null,
                    'semantic_domain' => $metadata['semantic_domain'] ?? 'general',
                    'learning_hints' => $metadata['learning_hints'] ?? [],
                    'frequency_score' => $metadata['frequency_score'] ?? 1,
                    'grade_levels' => $metadata['grade_levels'] ?? ['K'],
                    'ipa' => $metadata['ipa'] ?? null,
                    'pronunciation_notes' => $metadata['pronunciation_notes'] ?? null,
                    'translation' => $metadata['translation'] ?? null,
                    'example' => $metadata['example'] ?? null,
                ];

                // Handle pronunciation consistently
                $pronunciationKey = null;
                if (isset($wordData['pronunciation_key']) && preg_match('/^\/.*\/$/', $wordData['pronunciation_key'])) {
                    // Standard IPA notation
                    $pronunciationKey = $wordData['pronunciation_key'];
                } elseif (isset($wordData['pronunciation_key'])) {
                    // Non-IPA pronunciation guide goes to metadata
                    $standardizedMetadata['pronunciation_guide'] = $wordData['pronunciation_key'];
                }

                $wordsToInsert[] = [
                    'language_id' => $languageId,
                    'text' => $wordData['text'],
                    'pronunciation_key' => $pronunciationKey,
                    'part_of_speech' => $wordData['part_of_speech'] ?? null,
                    'metadata' => json_encode($standardizedMetadata),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Use batch insert for better performance
            try {
                DB::table('words')->insertOrIgnore($wordsToInsert);
            } catch (Exception $e) {
                Log::warning('Batch insert failed, falling back to individual inserts', [
                    'language' => $languageName,
                    'error' => $e->getMessage()
                ]);
                
                // Fallback to individual inserts for duplicate handling
                foreach ($wordsToInsert as $wordData) {
                    Word::updateOrCreate(
                        [
                            'language_id' => $wordData['language_id'],
                            'text' => $wordData['text'],
                            'part_of_speech' => $wordData['part_of_speech'],
                        ],
                        [
                            'pronunciation_key' => $wordData['pronunciation_key'],
                            'metadata' => $wordData['metadata'],
                            'updated_at' => $wordData['updated_at'],
                        ]
                    );
                }
            }

            $count += count($wordsToInsert);
        }

        Log::info("Completed seeding {$languageName} words", [
            'total_seeded' => $count,
            'total_expected' => $total
        ]);
    }

    /**
     * Create translations between languages
     */
    private function createTranslations(int $englishId, int $spanishId, int $creeId): void
    {
        // Get all words
        $englishWords = Word::where('language_id', $englishId)->get();
        $spanishWords = Word::where('language_id', $spanishId)->get();
        $creeWords = Word::where('language_id', $creeId)->get();

        if ($englishWords->isEmpty() || $spanishWords->isEmpty() || $creeWords->isEmpty()) {
            Log::warning('Cannot create translations - missing words for one or more languages', [
                'english_count' => $englishWords->count(),
                'spanish_count' => $spanishWords->count(),
                'cree_count' => $creeWords->count()
            ]);
            return;
        }

        // Create translations in batches (simplified for automated seeding)
        $this->createTranslationBatch($englishWords, $spanishWords, $spanishId, 'English to Spanish');
        $this->createTranslationBatch($englishWords, $creeWords, $creeId, 'English to Plains Cree');
        $this->createTranslationBatch($creeWords, $englishWords, $englishId, 'Plains Cree to English');
    }

    /**
     * Create translations between two sets of words
     */
    private function createTranslationBatch($sourceWords, $targetWords, $targetLanguageId, $description): void
    {
        Log::info("Creating translations: {$description}");
        
        $translations = [];
        $count = 0;
        $total = min(count($sourceWords), count($targetWords), 100); // Limit for automated seeding
        $batchSize = 50;

        foreach ($sourceWords->take($total) as $index => $sourceWord) {
            // Use corresponding target word or random if index out of bounds
            $targetWord = $targetWords[$index % count($targetWords)];

            $targetMetadata = json_decode($targetWord->metadata, true) ?? [];
            $pronunciationKey = $targetWord->pronunciation_key;
            $usageExamples = $targetMetadata['usage_examples'] ?? [];
            
            $translations[] = [
                'word_id' => $sourceWord->id,
                'language_id' => $targetLanguageId,
                'text' => $targetWord->text,
                'pronunciation_key' => $pronunciationKey,
                'context_notes' => $this->generateContextNotes($sourceWord, $targetWord),
                'usage_examples' => json_encode($usageExamples),
                'translation_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $count++;

            if (count($translations) >= $batchSize || $count >= $total) {
                // Insert translations
                foreach ($translations as $translation) {
                    WordTranslation::updateOrCreate(
                        [
                            'word_id' => $translation['word_id'],
                            'language_id' => $translation['language_id'],
                            'translation_order' => $translation['translation_order'],
                        ],
                        [
                            'text' => $translation['text'],
                            'pronunciation_key' => $translation['pronunciation_key'],
                            'context_notes' => $translation['context_notes'],
                            'usage_examples' => $translation['usage_examples'],
                            'updated_at' => $translation['updated_at'],
                        ]
                    );
                }

                $translations = [];
            }
        }

        Log::info("Completed translations: {$description}", ['count' => $count]);
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
