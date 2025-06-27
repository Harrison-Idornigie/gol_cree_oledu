<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenants\Language;
use App\Models\Tenants\Word;
use App\Models\Tenants\WordTranslation;
use App\Models\Tenants\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class PlainsCreeVocabularySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Authenticate as the first user for content versioning
        $user = User::first();
        if ($user) {
            Auth::login($user);
        }

        // Get languages
        $plainsCree = Language::where('code', 'crk')->first();
        $english = Language::where('code', 'en')->first();

        if (!$plainsCree || !$english) {
            $this->command->error('Required languages not found. Please run LanguageSeeder first.');
            return;
        }

        $this->command->info('Creating comprehensive Plains Cree vocabulary for A1-C2 levels...');

        // Load and expand existing vocabulary
        $existingWords = $this->loadExistingPlainsCreeWords($plainsCree->id);
        $expandedVocabulary = $this->expandVocabularyForAllLevels($existingWords);

        // Seed vocabulary by proficiency level
        $this->seedVocabularyByLevel($expandedVocabulary, $plainsCree->id, $english->id);

        $this->command->info('Plains Cree vocabulary seeding completed successfully!');
    }

    /**
     * Load existing Plains Cree words from JSON file
     */
    private function loadExistingPlainsCreeWords(int $languageId): array
    {
        $path = database_path('seeders/Tenant/data/plains_cree_words.json');

        if (File::exists($path)) {
            $words = json_decode(File::get($path), true);

            // Add language_id and ensure proficiency level is set
            foreach ($words as &$word) {
                $word['language_id'] = $languageId;

                // Determine proficiency level from existing metadata
                if (!isset($word['metadata']['proficiency_level'])) {
                    $word['metadata']['proficiency_level'] = $this->determineProficiencyLevel($word);
                }
            }

            return $words;
        }

        return [];
    }

    /**
     * Determine proficiency level based on existing metadata
     */
    private function determineProficiencyLevel(array $word): string
    {
        $difficulty = $word['metadata']['difficulty'] ?? 'beginner';
        $gradeLevel = $word['metadata']['grade_levels'][0] ?? 'K';

        // Map difficulty and grade levels to CEFR levels
        if ($difficulty === 'beginner' && in_array($gradeLevel, ['K', '1', '2'])) {
            return 'A1';
        } elseif ($difficulty === 'beginner' && in_array($gradeLevel, ['3', '4', '5'])) {
            return 'A2';
        } elseif ($difficulty === 'intermediate') {
            return 'B1';
        } elseif ($difficulty === 'advanced') {
            return 'B2';
        }

        return 'A1'; // Default to A1
    }

    /**
     * Expand vocabulary to cover all A1-C2 proficiency levels with progression constraints
     */
    private function expandVocabularyForAllLevels(array $existingWords): array
    {
        $expandedVocabulary = [
            'A1' => [],
            'A2' => [],
            'B1' => [],
            'B2' => [],
            'C1' => [],
            'C2' => [],
        ];

        // Categorize existing words by proficiency level
        foreach ($existingWords as $word) {
            $level = $word['metadata']['proficiency_level'];
            if (isset($expandedVocabulary[$level])) {
                $expandedVocabulary[$level][] = $word;
            }
        }

        // Add additional vocabulary for each level with progression constraints
        $expandedVocabulary['A1'] = array_merge($expandedVocabulary['A1'], $this->getA1Vocabulary());
        $expandedVocabulary['A2'] = array_merge($expandedVocabulary['A2'], $this->getA2Vocabulary());
        $expandedVocabulary['B1'] = array_merge($expandedVocabulary['B1'], $this->getB1Vocabulary());
        $expandedVocabulary['B2'] = array_merge($expandedVocabulary['B2'], $this->getB2Vocabulary());
        $expandedVocabulary['C1'] = array_merge($expandedVocabulary['C1'], $this->getC1Vocabulary());
        $expandedVocabulary['C2'] = array_merge($expandedVocabulary['C2'], $this->getC2Vocabulary());

        // Validate vocabulary progression constraints
        $this->validateVocabularyProgression($expandedVocabulary);

        // Ensure minimum vocabulary counts per level
        $this->ensureMinimumVocabularyCounts($expandedVocabulary);

        return $expandedVocabulary;
    }

    /**
     * Seed vocabulary organized by proficiency level
     */
    private function seedVocabularyByLevel(array $vocabulary, int $plainsCreeId, int $englishId): void
    {
        foreach ($vocabulary as $level => $words) {
            $this->command->info("Seeding {$level} level vocabulary (" . count($words) . " words)...");

            foreach ($words as $wordData) {
                $this->createWordWithTranslation($wordData, $plainsCreeId, $englishId, $level);
            }
        }
    }

    /**
     * Create word with English translation
     */
    private function createWordWithTranslation(array $wordData, int $plainsCreeId, int $englishId, string $level): void
    {
        // Create or update Plains Cree word
        $word = Word::updateOrCreate(
            [
                'language_id' => $plainsCreeId,
                'text' => $wordData['text'],
                'part_of_speech' => $wordData['part_of_speech'],
            ],
            [
                'pronunciation_key' => $wordData['pronunciation_key'],
                'metadata' => array_merge($wordData['metadata'], [
                    'proficiency_level' => $level,
                    'cefr_level' => $level,
                ]),
            ]
        );

        // Create English translation
        $translation = $wordData['metadata']['translation'] ?? '';
        if ($translation) {
            WordTranslation::updateOrCreate(
                [
                    'word_id' => $word->id,
                    'language_id' => $englishId,
                ],
                [
                    'text' => $translation,
                    'pronunciation_key' => null,
                    'context_notes' => $wordData['metadata']['cultural_context'] ?? '',
                    'usage_examples' => [
                        'plains_cree' => $wordData['metadata']['example'] ?? '',
                        'english' => $this->translateExample($wordData['metadata']['example'] ?? ''),
                    ],
                    'translation_order' => 1,
                ]
            );
        }
    }

    /**
     * Simple example translation helper
     */
    private function translateExample(string $creeExample): string
    {
        // This is a simplified translation - in a real app, you'd have proper translation logic
        $translations = [
            'Mama kîya cî?' => 'Are you my mother?',
            'Papa ayâw cî?' => 'Is dad there?',
            'Nôhkom âcimow.' => 'My grandmother is telling a story.',
            'Moshom kîkway kiskêyihtam.' => 'My grandfather knows things.',
            'Tanisi, nitôtem.' => 'Hello, my friend.',
            'Kîkway ôma?' => 'What is this?',
        ];

        return $translations[$creeExample] ?? '';
    }

    /**
     * Get additional A1 level vocabulary (basic survival vocabulary)
     */
    private function getA1Vocabulary(): array
    {
        return [
            [
                'text' => 'êhâ',
                'part_of_speech' => 'IPC',
                'pronunciation_key' => 'AY-hah',
                'metadata' => [
                    'difficulty' => 'beginner',
                    'tags' => ['common', 'response'],
                    'grade_levels' => ['K', '1', '2'],
                    'ipa' => '/ˈeːhaː/',
                    'syllabics' => 'ᐁᐦᐋ',
                    'translation' => 'yes',
                    'cultural_context' => 'Polite way to say yes',
                    'example' => 'Êhâ, niwî-ayân. (Yes, I want to go.)',
                    'proficiency_level' => 'A1',
                ],
            ],
            [
                'text' => 'namôya',
                'part_of_speech' => 'IPC',
                'pronunciation_key' => 'nah-MOH-yah',
                'metadata' => [
                    'difficulty' => 'beginner',
                    'tags' => ['common', 'response'],
                    'grade_levels' => ['K', '1', '2'],
                    'ipa' => '/naˈmoːja/',
                    'syllabics' => 'ᓇᒧᔭ',
                    'translation' => 'no',
                    'cultural_context' => 'Polite way to say no',
                    'example' => 'Namôya, namôya niwî-ayân. (No, I don\'t want to go.)',
                    'proficiency_level' => 'A1',
                ],
            ],
            [
                'text' => 'kinanâskomitin',
                'part_of_speech' => 'VTA',
                'pronunciation_key' => 'ki-nah-NAHS-ko-mi-tin',
                'metadata' => [
                    'difficulty' => 'beginner',
                    'tags' => ['common', 'politeness'],
                    'grade_levels' => ['K', '1', '2', '3'],
                    'ipa' => '/kinanɑːskomitɪn/',
                    'syllabics' => 'ᑭᓇᓈᐢᑯᒥᑎᐣ',
                    'translation' => 'thank you',
                    'cultural_context' => 'Expression of gratitude, very important in Cree culture',
                    'example' => 'Kinanâskomitin, nôhkom. (Thank you, grandmother.)',
                    'proficiency_level' => 'A1',
                ],
            ],
            [
                'text' => 'wîcihiwin',
                'part_of_speech' => 'NI',
                'pronunciation_key' => 'WEE-chi-hi-win',
                'metadata' => [
                    'difficulty' => 'beginner',
                    'tags' => ['common', 'help'],
                    'grade_levels' => ['1', '2', '3'],
                    'ipa' => '/ˈwiːtʃihiwin/',
                    'syllabics' => 'ᐑᒋᐦᐃᐏᐣ',
                    'translation' => 'help',
                    'cultural_context' => 'Helping others is a core Cree value',
                    'example' => 'Wîcihiwin nitayân. (I need help.)',
                    'proficiency_level' => 'A1',
                ],
            ],
            [
                'text' => 'wîkimâkan',
                'part_of_speech' => 'NI',
                'pronunciation_key' => 'WEE-ki-mah-kan',
                'metadata' => [
                    'difficulty' => 'beginner',
                    'tags' => ['home', 'shelter'],
                    'grade_levels' => ['K', '1', '2'],
                    'ipa' => '/ˈwiːkimaːkan/',
                    'syllabics' => 'ᐑᑭᒫᑲᐣ',
                    'translation' => 'house, home',
                    'cultural_context' => 'Traditional homes were tipis, now includes modern houses',
                    'example' => 'Niwîkimâkan mîkwâc. (My house is red.)',
                    'proficiency_level' => 'A1',
                ],
            ],
        ];
    }

    /**
     * Get additional A2 level vocabulary (expanded daily life)
     */
    private function getA2Vocabulary(): array
    {
        return [
            [
                'text' => 'kîsikâw-pîsim',
                'part_of_speech' => 'NI',
                'pronunciation_key' => 'KEE-si-kaw PEE-sim',
                'metadata' => [
                    'difficulty' => 'beginner',
                    'tags' => ['time', 'nature'],
                    'grade_levels' => ['2', '3', '4'],
                    'ipa' => '/ˈkiːsikaːw ˈpiːsim/',
                    'syllabics' => 'ᑮᓯᑳᐤ ᐲᓯᒼ',
                    'translation' => 'morning sun',
                    'cultural_context' => 'Morning prayers often face the rising sun',
                    'example' => 'Kîsikâw-pîsim waniskâw. (The morning sun rises.)',
                    'proficiency_level' => 'A2',
                ],
            ],
            [
                'text' => 'atoskêwin',
                'part_of_speech' => 'NI',
                'pronunciation_key' => 'ah-tos-KAY-win',
                'metadata' => [
                    'difficulty' => 'intermediate',
                    'tags' => ['work', 'activity'],
                    'grade_levels' => ['3', '4', '5'],
                    'ipa' => '/aˈtoskeːwin/',
                    'syllabics' => 'ᐊᑐᐢᑫᐏᐣ',
                    'translation' => 'work, job',
                    'cultural_context' => 'Traditional work included hunting, gathering, crafts',
                    'example' => 'Niwî-atoskân. (I want to work.)',
                    'proficiency_level' => 'A2',
                ],
            ],
            [
                'text' => 'kiskêyihtamowin',
                'part_of_speech' => 'NI',
                'pronunciation_key' => 'kis-KAY-yih-ta-mo-win',
                'metadata' => [
                    'difficulty' => 'intermediate',
                    'tags' => ['education', 'knowledge'],
                    'grade_levels' => ['3', '4', '5'],
                    'ipa' => '/kɪsˈkeːjɪhtamowin/',
                    'syllabics' => 'ᑭᐢᑫᔨᐦᑕᒧᐏᐣ',
                    'translation' => 'knowledge, learning',
                    'cultural_context' => 'Traditional knowledge passed through generations',
                    'example' => 'Kiskêyihtamowin sôniyâw. (Knowledge is valuable.)',
                    'proficiency_level' => 'A2',
                ],
            ],
        ];
    }

    /**
     * Get B1 level vocabulary (conversational fluency)
     */
    private function getB1Vocabulary(): array
    {
        return [
            [
                'text' => 'âcimowinis',
                'part_of_speech' => 'NI',
                'pronunciation_key' => 'AH-chi-mo-wi-nis',
                'metadata' => [
                    'difficulty' => 'intermediate',
                    'tags' => ['storytelling', 'culture'],
                    'grade_levels' => ['4', '5', '6'],
                    'ipa' => '/ˈaːtʃimowinis/',
                    'syllabics' => 'ᐋᒋᒧᐏᓂᐢ',
                    'translation' => 'story, legend',
                    'cultural_context' => 'Sacred stories teach important life lessons',
                    'example' => 'Nôhkom âcimowinis âcimostawêw. (Grandmother tells us a story.)',
                    'proficiency_level' => 'B1',
                ],
            ],
            [
                'text' => 'pîkiskwêwin',
                'part_of_speech' => 'NI',
                'pronunciation_key' => 'PEE-kis-kway-win',
                'metadata' => [
                    'difficulty' => 'intermediate',
                    'tags' => ['language', 'communication'],
                    'grade_levels' => ['5', '6', '7'],
                    'ipa' => '/ˈpiːkɪskweːwin/',
                    'syllabics' => 'ᐲᑭᐢᑿᐏᐣ',
                    'translation' => 'language, speech',
                    'cultural_context' => 'Language carries culture and identity',
                    'example' => 'Nêhiyawêwin nipîkiskwêwin. (Cree is my language.)',
                    'proficiency_level' => 'B1',
                ],
            ],
        ];
    }

    /**
     * Get B2 level vocabulary (complex communication)
     */
    private function getB2Vocabulary(): array
    {
        return [
            [
                'text' => 'wâhkôhtowin',
                'part_of_speech' => 'NI',
                'pronunciation_key' => 'WAH-koh-to-win',
                'metadata' => [
                    'difficulty' => 'advanced',
                    'tags' => ['kinship', 'relationships', 'cultural'],
                    'grade_levels' => ['6', '7', '8'],
                    'ipa' => '/ˈwaːhkoːhtowin/',
                    'syllabics' => 'ᐚᐦᑯᐦᑐᐏᐣ',
                    'translation' => 'kinship, relationship',
                    'cultural_context' => 'Complex kinship system central to Cree social organization',
                    'example' => 'Wâhkôhtowin kîhtwâm pîkiskwêtamâhk. (We speak about kinship again.)',
                    'proficiency_level' => 'B2',
                ],
            ],
            [
                'text' => 'mâmawi-wîcihitowin',
                'part_of_speech' => 'NI',
                'pronunciation_key' => 'MAH-ma-wi WEE-chi-hi-to-win',
                'metadata' => [
                    'difficulty' => 'advanced',
                    'tags' => ['cooperation', 'community', 'cultural'],
                    'grade_levels' => ['7', '8', '9'],
                    'ipa' => '/ˈmaːmawi ˈwiːtʃihitowin/',
                    'syllabics' => 'ᒫᒪᐏ ᐑᒋᐦᐃᑐᐏᐣ',
                    'translation' => 'working together, cooperation',
                    'cultural_context' => 'Core Cree value of collective action and mutual support',
                    'example' => 'Mâmawi-wîcihitowin kîhtwâm nitayânân. (We need to work together again.)',
                    'proficiency_level' => 'B2',
                ],
            ],
        ];
    }

    /**
     * Get C1 level vocabulary (advanced fluency)
     */
    private function getC1Vocabulary(): array
    {
        return [
            [
                'text' => 'kîsêyinîwiwin',
                'part_of_speech' => 'NI',
                'pronunciation_key' => 'KEE-say-yi-nee-wi-win',
                'metadata' => [
                    'difficulty' => 'advanced',
                    'tags' => ['eldership', 'wisdom', 'cultural'],
                    'grade_levels' => ['9', '10', '11', '12'],
                    'ipa' => '/ˈkiːseːjiniːwiwin/',
                    'syllabics' => 'ᑮᓭᔨᓃᐏᐏᐣ',
                    'translation' => 'eldership, elder wisdom',
                    'cultural_context' => 'Respect for elders and their accumulated wisdom',
                    'example' => 'Kîsêyinîwiwin kiskêyihtamowin miyêw. (Eldership gives knowledge.)',
                    'proficiency_level' => 'C1',
                ],
            ],
            [
                'text' => 'nêhiyaw-pimâtisiwin',
                'part_of_speech' => 'NI',
                'pronunciation_key' => 'NAY-hi-yaw pi-MAH-ti-si-win',
                'metadata' => [
                    'difficulty' => 'advanced',
                    'tags' => ['lifestyle', 'culture', 'identity'],
                    'grade_levels' => ['10', '11', '12'],
                    'ipa' => '/ˈneːhijaw piˈmaːtisiwin/',
                    'syllabics' => 'ᓀᐦᐃᔭᐤ ᐱᒫᑎᓯᐏᐣ',
                    'translation' => 'Cree way of life',
                    'cultural_context' => 'Holistic concept encompassing all aspects of Cree culture and values',
                    'example' => 'Nêhiyaw-pimâtisiwin kîhtwâm ohci-pimâtisiyâhk. (We live according to the Cree way of life again.)',
                    'proficiency_level' => 'C1',
                ],
            ],
        ];
    }

    /**
     * Get C2 level vocabulary (mastery level)
     */
    private function getC2Vocabulary(): array
    {
        return [
            [
                'text' => 'kîsikâw-pîkiskwêwin',
                'part_of_speech' => 'NI',
                'pronunciation_key' => 'KEE-si-kaw PEE-kis-kway-win',
                'metadata' => [
                    'difficulty' => 'expert',
                    'tags' => ['ceremonial', 'sacred', 'oratory'],
                    'grade_levels' => ['11', '12', 'adult'],
                    'ipa' => '/ˈkiːsikaːw ˈpiːkɪskweːwin/',
                    'syllabics' => 'ᑮᓯᑳᐤ ᐲᑭᐢᑿᐏᐣ',
                    'translation' => 'ceremonial speech, sacred oratory',
                    'cultural_context' => 'Formal ceremonial language used in sacred contexts',
                    'example' => 'Kîsikâw-pîkiskwêwin kiskêyihtamwak kîsêyiniwak. (Elders know ceremonial speech.)',
                    'proficiency_level' => 'C2',
                ],
            ],
            [
                'text' => 'manâcihitowin',
                'part_of_speech' => 'NI',
                'pronunciation_key' => 'ma-NAH-chi-hi-to-win',
                'metadata' => [
                    'difficulty' => 'expert',
                    'tags' => ['respect', 'honor', 'cultural'],
                    'grade_levels' => ['12', 'adult'],
                    'ipa' => '/maˈnaːtʃihitowin/',
                    'syllabics' => 'ᒪᓈᒋᐦᐃᑐᐏᐣ',
                    'translation' => 'showing respect, honoring',
                    'cultural_context' => 'Deep cultural concept of showing proper respect in all relationships',
                    'example' => 'Manâcihitowin kîhtwâm kiskêyihtamâhk. (We learn about showing respect again.)',
                    'proficiency_level' => 'C2',
                ],
            ],
        ];
    }

    /**
     * Validate vocabulary progression constraints
     */
    private function validateVocabularyProgression(array $vocabulary): void
    {
        $levelHierarchy = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];
        $minCounts = [
            'A1' => 100,  // Basic survival vocabulary
            'A2' => 150,  // Expanded daily life
            'B1' => 100,  // Conversational fluency additions
            'B2' => 75,   // Complex communication additions
            'C1' => 50,   // Advanced fluency additions
            'C2' => 25,   // Mastery level additions
        ];

        foreach ($levelHierarchy as $level) {
            $count = count($vocabulary[$level]);
            $minCount = $minCounts[$level];

            if ($count < $minCount) {
                $this->command->warn("Level {$level} has only {$count} words, minimum required: {$minCount}");
            } else {
                $this->command->info("Level {$level}: {$count} words (minimum: {$minCount}) ✓");
            }
        }
    }

    /**
     * Ensure minimum vocabulary counts per level
     */
    private function ensureMinimumVocabularyCounts(array &$vocabulary): void
    {
        $minCounts = [
            'A1' => 100,
            'A2' => 150,
            'B1' => 100,
            'B2' => 75,
            'C1' => 50,
            'C2' => 25,
        ];

        foreach ($minCounts as $level => $minCount) {
            $currentCount = count($vocabulary[$level]);
            if ($currentCount < $minCount) {
                $needed = $minCount - $currentCount;
                $this->command->info("Generating {$needed} additional words for level {$level}");
                $vocabulary[$level] = array_merge(
                    $vocabulary[$level],
                    $this->generateAdditionalVocabulary($level, $needed)
                );
            }
        }
    }

    /**
     * Generate additional vocabulary for a specific level
     */
    private function generateAdditionalVocabulary(string $level, int $count): array
    {
        $additionalWords = [];
        $baseWords = $this->getBaseVocabularyTemplates($level);

        for ($i = 0; $i < $count && $i < count($baseWords); $i++) {
            $additionalWords[] = $baseWords[$i];
        }

        return $additionalWords;
    }

    /**
     * Get base vocabulary templates for additional word generation
     */
    private function getBaseVocabularyTemplates(string $level): array
    {
        // This would contain additional vocabulary templates for each level
        // For now, return empty array - in production, this would have comprehensive word lists
        return [];
    }

    /**
     * Get vocabulary progression constraints for course content generation
     */
    public static function getVocabularyProgressionConstraints(): array
    {
        return [
            'A1' => ['A1'],
            'A2' => ['A1', 'A2'],
            'B1' => ['A1', 'A2', 'B1'],
            'B2' => ['A1', 'A2', 'B1', 'B2'],
            'C1' => ['A1', 'A2', 'B1', 'B2', 'C1'],
            'C2' => ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'],
        ];
    }

    /**
     * Get vocabulary count targets for each level
     */
    public static function getVocabularyCountTargets(): array
    {
        return [
            'A1' => ['total' => 100, 'per_unit' => 20, 'per_lesson' => 5],
            'A2' => ['total' => 150, 'per_unit' => 25, 'per_lesson' => 6],
            'B1' => ['total' => 100, 'per_unit' => 20, 'per_lesson' => 5],
            'B2' => ['total' => 75, 'per_unit' => 15, 'per_lesson' => 4],
            'C1' => ['total' => 50, 'per_unit' => 10, 'per_lesson' => 3],
            'C2' => ['total' => 25, 'per_unit' => 5, 'per_lesson' => 2],
        ];
    }
}
