<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenants\CurriculumTemplate;
use App\Models\Tenants\Language;
use App\Models\Tenants\LanguagePair;
use App\Models\Tenants\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class CurriculumTemplateSeeder extends Seeder
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
        $english = Language::where('code', 'en')->first();
        $spanish = Language::where('code', 'es')->first();
        $plainsCree = Language::where('code', 'crk')->first();
        $french = Language::where('code', 'fr')->first();
        $german = Language::where('code', 'de')->first();

        if (!$english || !$spanish || !$plainsCree) {
            $this->command->error('Required languages not found. Please run LanguageSeeder and LanguagePairSeeder first.');
            return;
        }

        // Create comprehensive Plains Cree curriculum templates following 3-tier framework
        $this->createEnglishToPlainsCreeTemplates($english, $plainsCree);

        // Note: Other language pairs can be added following the same comprehensive framework
        // $this->createEnglishToSpanishTemplates($english, $spanish);
        // $this->createEnglishToFrenchTemplates($english, $french);
        // $this->createESLTemplates($spanish, $english);
        // $this->createPlainsCreeRevitalizationTemplates($plainsCree, $english);

        $this->command->info('Curriculum templates created successfully!');
    }

    /**
     * Create English to Spanish curriculum templates
     */
    private function createEnglishToSpanishTemplates(Language $english, Language $spanish): void
    {
        $languagePair = LanguagePair::where('source_language_id', $english->id)
            ->where('target_language_id', $spanish->id)
            ->first();

        if (!$languagePair) {
            $this->command->error('English-Spanish language pair not found.');
            return;
        }

        $levels = [
            CurriculumTemplate::LEVEL_A1 => [
                'name' => 'Spanish for Beginners (A1)',
                'description' => 'Complete beginner Spanish curriculum focusing on basic vocabulary, greetings, and simple phrases for K-12 students.',
                'estimated_hours' => 60,
                'prerequisites' => [],
            ],
            CurriculumTemplate::LEVEL_A2 => [
                'name' => 'Elementary Spanish (A2)',
                'description' => 'Elementary Spanish curriculum building on A1 foundations with expanded vocabulary and basic grammar structures.',
                'estimated_hours' => 80,
                'prerequisites' => ['A1 Spanish completion or equivalent'],
            ],
            CurriculumTemplate::LEVEL_B1 => [
                'name' => 'Intermediate Spanish (B1)',
                'description' => 'Intermediate Spanish curriculum focusing on conversational skills, cultural contexts, and practical communication.',
                'estimated_hours' => 100,
                'prerequisites' => ['A2 Spanish completion or equivalent'],
            ],
            CurriculumTemplate::LEVEL_B2 => [
                'name' => 'Upper Intermediate Spanish (B2)',
                'description' => 'Upper intermediate Spanish curriculum with complex grammar, literature introduction, and advanced communication.',
                'estimated_hours' => 120,
                'prerequisites' => ['B1 Spanish completion or equivalent'],
            ],
            CurriculumTemplate::LEVEL_C1 => [
                'name' => 'Advanced Spanish (C1)',
                'description' => 'Advanced Spanish curriculum focusing on fluency, academic writing, and cultural analysis.',
                'estimated_hours' => 140,
                'prerequisites' => ['B2 Spanish completion or equivalent'],
            ],
            CurriculumTemplate::LEVEL_C2 => [
                'name' => 'Proficient Spanish (C2)',
                'description' => 'Proficient Spanish curriculum for near-native competency with literature, professional communication, and cultural mastery.',
                'estimated_hours' => 160,
                'prerequisites' => ['C1 Spanish completion or equivalent'],
            ],
        ];

        foreach ($levels as $level => $data) {
            $templateData = $this->generateSpanishTemplateData($level);

            CurriculumTemplate::updateOrCreate(
                [
                    'name' => $data['name'],
                    'language_pair_id' => $languagePair->id,
                    'proficiency_level' => $level,
                ],
                [
                    'description' => $data['description'],
                    'estimated_hours' => $data['estimated_hours'],
                    'prerequisites' => $data['prerequisites'],
                    'template_data' => $templateData,
                    'is_official' => true,
                    'created_by' => auth()->id(),
                    'usage_count' => 0,
                    'effectiveness_score' => null,
                ]
            );
        }
    }

    /**
     * Create English to French curriculum templates
     */
    private function createEnglishToFrenchTemplates(Language $english, ?Language $french): void
    {
        if (!$french) {
            $this->command->warn('French language not found, skipping French templates.');
            return;
        }

        $languagePair = LanguagePair::where('source_language_id', $english->id)
            ->where('target_language_id', $french->id)
            ->first();

        if (!$languagePair) {
            $this->command->error('English-French language pair not found.');
            return;
        }

        $levels = [
            CurriculumTemplate::LEVEL_A1 => [
                'name' => 'French for Beginners (A1)',
                'description' => 'Complete beginner French curriculum focusing on pronunciation, basic vocabulary, and simple expressions for K-12 students.',
                'estimated_hours' => 65,
                'prerequisites' => [],
            ],
            CurriculumTemplate::LEVEL_A2 => [
                'name' => 'Elementary French (A2)',
                'description' => 'Elementary French curriculum with expanded vocabulary, basic grammar, and cultural introduction.',
                'estimated_hours' => 85,
                'prerequisites' => ['A1 French completion or equivalent'],
            ],
            CurriculumTemplate::LEVEL_B1 => [
                'name' => 'Intermediate French (B1)',
                'description' => 'Intermediate French curriculum focusing on conversational fluency and cultural understanding.',
                'estimated_hours' => 105,
                'prerequisites' => ['A2 French completion or equivalent'],
            ],
        ];

        foreach ($levels as $level => $data) {
            $templateData = $this->generateFrenchTemplateData($level);

            CurriculumTemplate::updateOrCreate(
                [
                    'name' => $data['name'],
                    'language_pair_id' => $languagePair->id,
                    'proficiency_level' => $level,
                ],
                [
                    'description' => $data['description'],
                    'estimated_hours' => $data['estimated_hours'],
                    'prerequisites' => $data['prerequisites'],
                    'template_data' => $templateData,
                    'is_official' => true,
                    'created_by' => auth()->id(),
                    'usage_count' => 0,
                    'effectiveness_score' => null,
                ]
            );
        }
    }

    /**
     * Create English to Plains Cree curriculum templates following comprehensive 3-tier framework
     */
    private function createEnglishToPlainsCreeTemplates(Language $english, Language $plainsCree): void
    {
        $languagePair = LanguagePair::where('source_language_id', $english->id)
            ->where('target_language_id', $plainsCree->id)
            ->first();

        if (!$languagePair) {
            $this->command->error('English-Plains Cree language pair not found.');
            return;
        }

        // Comprehensive CEFR-aligned Plains Cree curriculum templates
        $levels = [
            CurriculumTemplate::LEVEL_A1 => [
                'name' => 'Plains Cree Foundations (A1) - nēhiyawēwin kiskēyihtamowin',
                'description' => 'Complete beginner Plains Cree curriculum introducing syllabics, basic vocabulary, cultural protocols, and foundational language skills for K-12 students.',
                'estimated_hours' => 135,
                'units_count' => 7,
                'lessons_count' => 135,
                'prerequisites' => [],
            ],
            CurriculumTemplate::LEVEL_A2 => [
                'name' => 'Plains Cree Elementary (A2) - nēhiyawēwin kiskēyihtamowin',
                'description' => 'Elementary Plains Cree curriculum building on A1 foundations with expanded vocabulary, basic grammar structures, and deeper cultural understanding.',
                'estimated_hours' => 200,
                'units_count' => 9,
                'lessons_count' => 200,
                'prerequisites' => ['A1 Plains Cree completion or equivalent'],
            ],
            CurriculumTemplate::LEVEL_B1 => [
                'name' => 'Plains Cree Intermediate (B1) - nēhiyawēwin kiskēyihtamowin',
                'description' => 'Intermediate Plains Cree focusing on conversational fluency, storytelling traditions, and active participation in cultural practices.',
                'estimated_hours' => 275,
                'units_count' => 11,
                'lessons_count' => 275,
                'prerequisites' => ['A2 Plains Cree completion or equivalent'],
            ],
            CurriculumTemplate::LEVEL_B2 => [
                'name' => 'Plains Cree Upper Intermediate (B2) - nēhiyawēwin kiskēyihtamowin',
                'description' => 'Upper intermediate Plains Cree with complex grammar, traditional literature, ceremonial language, and community leadership skills.',
                'estimated_hours' => 375,
                'units_count' => 13,
                'lessons_count' => 375,
                'prerequisites' => ['B1 Plains Cree completion or equivalent'],
            ],
            CurriculumTemplate::LEVEL_C1 => [
                'name' => 'Plains Cree Advanced (C1) - nēhiyawēwin kiskēyihtamowin',
                'description' => 'Advanced Plains Cree curriculum focusing on fluency, traditional knowledge systems, ceremonial protocols, and language teaching skills.',
                'estimated_hours' => 475,
                'units_count' => 16,
                'lessons_count' => 475,
                'prerequisites' => ['B2 Plains Cree completion or equivalent'],
            ],
            CurriculumTemplate::LEVEL_C2 => [
                'name' => 'Plains Cree Mastery (C2) - nēhiyawēwin kiskēyihtamowin',
                'description' => 'Mastery-level Plains Cree curriculum for near-native competency with traditional oratory, ceremonial leadership, and language revitalization skills.',
                'estimated_hours' => 550,
                'units_count' => 19,
                'lessons_count' => 550,
                'prerequisites' => ['C1 Plains Cree completion or equivalent'],
            ],
        ];

        foreach ($levels as $level => $data) {
            $templateData = $this->generatePlainsCreeTemplateData($level, $data);

            CurriculumTemplate::updateOrCreate(
                [
                    'name' => $data['name'],
                    'language_pair_id' => $languagePair->id,
                    'proficiency_level' => $level,
                ],
                [
                    'description' => $data['description'],
                    'estimated_hours' => $data['estimated_hours'],
                    'prerequisites' => $data['prerequisites'],
                    'template_data' => $templateData,
                    'is_official' => true,
                    'created_by' => auth()->id(),
                    'usage_count' => 0,
                    'effectiveness_score' => null,
                ]
            );
        }
    }

    /**
     * Create ESL (English as Second Language) templates
     */
    private function createESLTemplates(Language $spanish, Language $english): void
    {
        $languagePair = LanguagePair::where('source_language_id', $spanish->id)
            ->where('target_language_id', $english->id)
            ->first();

        if (!$languagePair) {
            $this->command->error('Spanish-English language pair not found.');
            return;
        }

        $levels = [
            CurriculumTemplate::LEVEL_A1 => [
                'name' => 'English for Spanish Speakers (A1)',
                'description' => 'ESL curriculum for Spanish-speaking K-12 students learning English basics.',
                'estimated_hours' => 75,
                'prerequisites' => [],
            ],
            CurriculumTemplate::LEVEL_A2 => [
                'name' => 'Elementary English for Spanish Speakers (A2)',
                'description' => 'Elementary ESL curriculum building English skills for Spanish speakers.',
                'estimated_hours' => 95,
                'prerequisites' => ['A1 English completion or equivalent'],
            ],
        ];

        foreach ($levels as $level => $data) {
            $templateData = $this->generateESLTemplateData($level);

            CurriculumTemplate::updateOrCreate(
                [
                    'name' => $data['name'],
                    'language_pair_id' => $languagePair->id,
                    'proficiency_level' => $level,
                ],
                [
                    'description' => $data['description'],
                    'estimated_hours' => $data['estimated_hours'],
                    'prerequisites' => $data['prerequisites'],
                    'template_data' => $templateData,
                    'is_official' => true,
                    'created_by' => auth()->id(),
                    'usage_count' => 0,
                    'effectiveness_score' => null,
                ]
            );
        }
    }

    /**
     * Create Plains Cree revitalization templates
     */
    private function createPlainsCreeRevitalizationTemplates(Language $plainsCree, Language $english): void
    {
        $languagePair = LanguagePair::where('source_language_id', $plainsCree->id)
            ->where('target_language_id', $english->id)
            ->first();

        if (!$languagePair) {
            $this->command->error('Plains Cree-English language pair not found.');
            return;
        }

        $levels = [
            CurriculumTemplate::LEVEL_A1 => [
                'name' => 'Plains Cree Language Revitalization (A1)',
                'description' => 'Community-focused Plains Cree curriculum for language revitalization with cultural immersion.',
                'estimated_hours' => 80,
                'prerequisites' => [],
            ],
            CurriculumTemplate::LEVEL_A2 => [
                'name' => 'Plains Cree Cultural Immersion (A2)',
                'description' => 'Advanced Plains Cree revitalization curriculum with storytelling and ceremonial language.',
                'estimated_hours' => 100,
                'prerequisites' => ['A1 Plains Cree Revitalization completion'],
            ],
        ];

        foreach ($levels as $level => $data) {
            $templateData = $this->generatePlainsCreeRevitalizationTemplateData($level);

            CurriculumTemplate::updateOrCreate(
                [
                    'name' => $data['name'],
                    'language_pair_id' => $languagePair->id,
                    'proficiency_level' => $level,
                ],
                [
                    'description' => $data['description'],
                    'estimated_hours' => $data['estimated_hours'],
                    'prerequisites' => $data['prerequisites'],
                    'template_data' => $templateData,
                    'is_official' => true,
                    'created_by' => auth()->id(),
                    'usage_count' => 0,
                    'effectiveness_score' => null,
                ]
            );
        }
    }

    /**
     * Generate Spanish template data based on proficiency level
     */
    private function generateSpanishTemplateData(string $level): array
    {
        $baseStructure = [
            'units' => [],
            'assessment_strategy' => 'continuous_assessment',
            'cultural_components' => true,
            'technology_integration' => true,
        ];

        switch ($level) {
            case CurriculumTemplate::LEVEL_A1:
                $baseStructure['units'] = [
                    [
                        'title' => 'Greetings and Introductions',
                        'order' => 1,
                        'estimated_hours' => 10,
                        'topics' => [
                            [
                                'title' => 'Basic Greetings',
                                'lessons' => [
                                    ['title' => 'Hola, Buenos días', 'type' => 'vocabulary', 'exercises' => 5],
                                    ['title' => 'Introductions', 'type' => 'conversation', 'exercises' => 4],
                                ]
                            ],
                            [
                                'title' => 'Numbers 1-20',
                                'lessons' => [
                                    ['title' => 'Counting Basics', 'type' => 'vocabulary', 'exercises' => 6],
                                    ['title' => 'Age and Phone Numbers', 'type' => 'practical', 'exercises' => 4],
                                ]
                            ]
                        ]
                    ],
                    [
                        'title' => 'Family and Home',
                        'order' => 2,
                        'estimated_hours' => 12,
                        'topics' => [
                            [
                                'title' => 'Family Members',
                                'lessons' => [
                                    ['title' => 'Mi Familia', 'type' => 'vocabulary', 'exercises' => 5],
                                    ['title' => 'Family Descriptions', 'type' => 'grammar', 'exercises' => 4],
                                ]
                            ]
                        ]
                    ]
                ];
                break;

            case CurriculumTemplate::LEVEL_A2:
                $baseStructure['units'] = [
                    [
                        'title' => 'Daily Routines',
                        'order' => 1,
                        'estimated_hours' => 15,
                        'topics' => [
                            [
                                'title' => 'Time and Schedule',
                                'lessons' => [
                                    ['title' => 'Telling Time', 'type' => 'vocabulary', 'exercises' => 6],
                                    ['title' => 'Daily Activities', 'type' => 'grammar', 'exercises' => 5],
                                ]
                            ]
                        ]
                    ],
                    [
                        'title' => 'Food and Restaurants',
                        'order' => 2,
                        'estimated_hours' => 18,
                        'topics' => [
                            [
                                'title' => 'Food Vocabulary',
                                'lessons' => [
                                    ['title' => 'Fruits and Vegetables', 'type' => 'vocabulary', 'exercises' => 7],
                                    ['title' => 'Ordering Food', 'type' => 'conversation', 'exercises' => 6],
                                ]
                            ]
                        ]
                    ]
                ];
                break;

            default:
                $baseStructure['units'] = [
                    [
                        'title' => 'Advanced Communication',
                        'order' => 1,
                        'estimated_hours' => 20,
                        'topics' => [
                            [
                                'title' => 'Complex Grammar',
                                'lessons' => [
                                    ['title' => 'Subjunctive Mood', 'type' => 'grammar', 'exercises' => 8],
                                    ['title' => 'Advanced Conversations', 'type' => 'conversation', 'exercises' => 6],
                                ]
                            ]
                        ]
                    ]
                ];
        }

        return $baseStructure;
    }

    /**
     * Generate French template data based on proficiency level
     */
    private function generateFrenchTemplateData(string $level): array
    {
        $baseStructure = [
            'units' => [],
            'assessment_strategy' => 'continuous_assessment',
            'cultural_components' => true,
            'pronunciation_focus' => true,
        ];

        switch ($level) {
            case CurriculumTemplate::LEVEL_A1:
                $baseStructure['units'] = [
                    [
                        'title' => 'French Sounds and Greetings',
                        'order' => 1,
                        'estimated_hours' => 12,
                        'topics' => [
                            [
                                'title' => 'French Pronunciation',
                                'lessons' => [
                                    ['title' => 'Vowel Sounds', 'type' => 'pronunciation', 'exercises' => 6],
                                    ['title' => 'Basic Greetings', 'type' => 'vocabulary', 'exercises' => 5],
                                ]
                            ]
                        ]
                    ]
                ];
                break;

            default:
                $baseStructure['units'] = [
                    [
                        'title' => 'French Communication',
                        'order' => 1,
                        'estimated_hours' => 15,
                        'topics' => [
                            [
                                'title' => 'Conversation Skills',
                                'lessons' => [
                                    ['title' => 'Daily Conversations', 'type' => 'conversation', 'exercises' => 7],
                                ]
                            ]
                        ]
                    ]
                ];
        }

        return $baseStructure;
    }

    /**
     * Generate comprehensive Plains Cree template data following 3-tier framework
     */
    private function generatePlainsCreeTemplateData(string $level, array $levelData): array
    {
        $baseStructure = [
            'framework_tier_1' => [
                'proficiency_level' => $level,
                'units_count' => $levelData['units_count'],
                'lessons_count' => $levelData['lessons_count'],
                'estimated_hours' => $levelData['estimated_hours'],
            ],
            'framework_tier_2' => $this->getUniversalThematicUnits($level),
            'framework_tier_3' => $this->getSkillBasedLessonTypes($level),
            'units' => [],
            'assessment_strategy' => 'cultural_community_assessment',
            'cultural_components' => true,
            'syllabics_instruction' => true,
            'elder_involvement' => true,
            'land_based_learning' => true,
            'seasonal_activities' => true,
            'community_protocols' => true,
            'intergenerational_learning' => true,
        ];

        // Generate units based on proficiency level
        $baseStructure['units'] = $this->generatePlainsCreeUnits($level, $levelData);

        return $baseStructure;
    }

    /**
     * Get universal thematic units adapted for Plains Cree cultural context
     */
    private function getUniversalThematicUnits(string $level): array
    {
        $themes = [
            'personal_identity' => [
                'english_title' => 'Personal Identity',
                'cree_title' => 'nīya kēkwāy - Who I Am',
                'description' => 'Greetings, introductions, clan/family connections, traditional names',
                'cultural_focus' => 'Traditional naming ceremonies, clan systems, kinship protocols',
            ],
            'family_relationships' => [
                'english_title' => 'Family & Relationships',
                'cree_title' => 'niwāhkōmākanak - My Relations',
                'description' => 'Kinship terms, traditional family structures, relationship protocols',
                'cultural_focus' => 'Extended family systems, adoption traditions, respect protocols',
            ],
            'daily_life' => [
                'english_title' => 'Daily Life',
                'cree_title' => 'kīsikāw pimātisiwin - Daily Living',
                'description' => 'Daily routines, seasonal cycles, traditional and modern activities',
                'cultural_focus' => 'Seasonal rounds, traditional daily practices, modern adaptations',
            ],
            'food_dining' => [
                'english_title' => 'Food & Dining',
                'cree_title' => 'mīcim - Food Ways',
                'description' => 'Traditional foods, hunting, gathering, modern food practices',
                'cultural_focus' => 'Traditional harvesting, food preparation, sharing protocols',
            ],
            'travel_transportation' => [
                'english_title' => 'Travel & Transportation',
                'cree_title' => 'pimohtēwin - Movement',
                'description' => 'Traditional travel methods, modern transportation, directions',
                'cultural_focus' => 'Traditional trails, seasonal travel, visiting protocols',
            ],
            'work_education' => [
                'english_title' => 'Work & Education',
                'cree_title' => 'atoskēwin ēkwa kiskēyihtamowin - Work and Learning',
                'description' => 'Traditional roles, modern careers, education systems',
                'cultural_focus' => 'Traditional knowledge systems, apprenticeship, modern education',
            ],
            'health_body' => [
                'english_title' => 'Health & Body',
                'cree_title' => 'miyowīcēhtowin - Wellness',
                'description' => 'Body parts, traditional medicine, wellness practices',
                'cultural_focus' => 'Traditional healing, plant medicines, holistic wellness',
            ],
            'entertainment_hobbies' => [
                'english_title' => 'Entertainment & Hobbies',
                'cree_title' => 'mētawēwin - Play and Recreation',
                'description' => 'Traditional games, storytelling, modern recreational activities',
                'cultural_focus' => 'Traditional games, powwow, storytelling traditions',
            ],
            'technology_communication' => [
                'english_title' => 'Technology & Communication',
                'cree_title' => 'kiyokēwin - Communication',
                'description' => 'Modern technology in Plains Cree context, digital communication',
                'cultural_focus' => 'Language technology, digital preservation, modern communication',
            ],
            'culture_society' => [
                'english_title' => 'Culture & Society',
                'cree_title' => 'nēhiyaw-pimātisiwin - Cree Way of Life',
                'description' => 'Ceremonies, traditions, community governance, cultural protocols',
                'cultural_focus' => 'Sacred ceremonies, governance systems, cultural protocols',
            ],
        ];

        // Filter themes based on proficiency level
        return $this->filterThemesByLevel($themes, $level);
    }

    /**
     * Get skill-based lesson types with Plains Cree-specific adaptations
     */
    private function getSkillBasedLessonTypes(string $level): array
    {
        return [
            'vocabulary_introduction' => [
                'english_title' => 'Vocabulary Introduction',
                'cree_title' => 'ayamihēwina kiskēyihtamowin - Learning Words',
                'description' => '10-15 new Plains Cree words with syllabics instruction',
                'structure' => [
                    'syllabics_writing' => true,
                    'audio_pronunciation' => true,
                    'cultural_context' => true,
                    'visual_supports' => true,
                ],
            ],
            'grammar_focus' => [
                'english_title' => 'Grammar Focus',
                'cree_title' => 'ayamihēwin nahiyawēwin - Language Structure',
                'description' => 'Plains Cree grammar structures, verb conjugations, sentence patterns',
                'structure' => [
                    'animate_inanimate' => true,
                    'verb_conjugations' => true,
                    'sentence_patterns' => true,
                    'cultural_examples' => true,
                ],
            ],
            'conversation_practice' => [
                'english_title' => 'Conversation Practice',
                'cree_title' => 'kiyokātowin - Speaking Together',
                'description' => 'Culturally appropriate dialogues and conversation practice',
                'structure' => [
                    'cultural_protocols' => true,
                    'respectful_communication' => true,
                    'situational_dialogues' => true,
                    'peer_practice' => true,
                ],
            ],
            'listening_comprehension' => [
                'english_title' => 'Listening Comprehension',
                'cree_title' => 'pētamāsowin - Listening',
                'description' => 'Elder recordings, traditional stories, authentic audio materials',
                'structure' => [
                    'elder_recordings' => true,
                    'traditional_stories' => true,
                    'natural_speech' => true,
                    'cultural_content' => true,
                ],
            ],
            'reading_comprehension' => [
                'english_title' => 'Reading Comprehension',
                'cree_title' => 'ayamihāsowin - Reading',
                'description' => 'Syllabics reading, traditional texts, modern Plains Cree writing',
                'structure' => [
                    'syllabics_fluency' => true,
                    'traditional_texts' => true,
                    'modern_writing' => true,
                    'comprehension_strategies' => true,
                ],
            ],
            'writing_practice' => [
                'english_title' => 'Writing Practice',
                'cree_title' => 'masinihikēwin - Writing',
                'description' => 'Syllabics writing practice, modern Plains Cree composition',
                'structure' => [
                    'syllabics_practice' => true,
                    'composition_skills' => true,
                    'cultural_writing' => true,
                    'digital_literacy' => true,
                ],
            ],
            'pronunciation_focus' => [
                'english_title' => 'Pronunciation Focus',
                'cree_title' => 'tānisi ē-isitēk - How to Say It',
                'description' => 'Plains Cree phonetics, sound system, pronunciation practice',
                'structure' => [
                    'sound_system' => true,
                    'phonetic_patterns' => true,
                    'accent_training' => true,
                    'elder_modeling' => true,
                ],
            ],
            'cultural_context' => [
                'english_title' => 'Cultural Context',
                'cree_title' => 'nēhiyaw-itāpisinīkēwin - Cree Ways of Being',
                'description' => 'Ceremonies, protocols, traditional knowledge integration',
                'structure' => [
                    'ceremonial_language' => true,
                    'cultural_protocols' => true,
                    'traditional_knowledge' => true,
                    'community_connections' => true,
                ],
            ],
        ];
    }

    /**
     * Filter thematic units based on proficiency level
     */
    private function filterThemesByLevel(array $themes, string $level): array
    {
        $levelThemes = [];

        switch ($level) {
            case CurriculumTemplate::LEVEL_A1:
                // A1: Focus on basic personal and family themes
                $levelThemes = array_intersect_key($themes, array_flip([
                    'personal_identity',
                    'family_relationships',
                    'daily_life',
                    'food_dining'
                ]));
                break;

            case CurriculumTemplate::LEVEL_A2:
                // A2: Add community and basic social themes
                $levelThemes = array_intersect_key($themes, array_flip([
                    'personal_identity',
                    'family_relationships',
                    'daily_life',
                    'food_dining',
                    'travel_transportation',
                    'work_education',
                    'health_body'
                ]));
                break;

            case CurriculumTemplate::LEVEL_B1:
                // B1: Add entertainment and technology
                $levelThemes = array_intersect_key($themes, array_flip([
                    'personal_identity',
                    'family_relationships',
                    'daily_life',
                    'food_dining',
                    'travel_transportation',
                    'work_education',
                    'health_body',
                    'entertainment_hobbies',
                    'technology_communication'
                ]));
                break;

            case CurriculumTemplate::LEVEL_B2:
            case CurriculumTemplate::LEVEL_C1:
            case CurriculumTemplate::LEVEL_C2:
                // B2+: All themes including complex cultural and societal topics
                $levelThemes = $themes;
                break;

            default:
                $levelThemes = $themes;
        }

        return $levelThemes;
    }

    /**
     * Generate Plains Cree units based on proficiency level and thematic framework
     */
    private function generatePlainsCreeUnits(string $level, array $levelData): array
    {
        $themes = $this->getUniversalThematicUnits($level);
        $units = [];
        $unitsCount = $levelData['units_count'];
        $lessonsPerUnit = intval($levelData['lessons_count'] / $unitsCount);

        $themeKeys = array_keys($themes);

        for ($unitOrder = 1; $unitOrder <= $unitsCount; $unitOrder++) {
            // Cycle through themes, allowing repetition for higher levels
            $themeIndex = ($unitOrder - 1) % count($themeKeys);
            $themeKey = $themeKeys[$themeIndex];
            $theme = $themes[$themeKey];

            $unit = [
                'title' => $theme['cree_title'] . ' (' . $theme['english_title'] . ')',
                'order' => $unitOrder,
                'estimated_hours' => intval($levelData['estimated_hours'] / $unitsCount),
                'theme_focus' => $themeKey,
                'cultural_focus' => $theme['cultural_focus'],
                'topics' => $this->generateTopicsForUnit($level, $theme, $lessonsPerUnit),
                'assessment' => $this->getUnitAssessment($level, $themeKey),
                'cultural_activities' => $this->getCulturalActivities($level, $themeKey),
                'elder_involvement' => $this->getElderInvolvement($level, $themeKey),
                'land_based_components' => $this->getLandBasedComponents($level, $themeKey),
            ];

            $units[] = $unit;
        }

        return $units;
    }

    /**
     * Generate topics for a unit based on theme and lesson count
     */
    private function generateTopicsForUnit(string $level, array $theme, int $lessonsPerUnit): array
    {
        $skillTypes = $this->getSkillBasedLessonTypes($level);
        $topics = [];
        $topicsPerUnit = max(2, intval($lessonsPerUnit / 8)); // Roughly 8 lessons per topic

        for ($topicOrder = 1; $topicOrder <= $topicsPerUnit; $topicOrder++) {
            $topic = [
                'title' => $theme['english_title'] . ' - Part ' . $topicOrder,
                'cree_title' => $theme['cree_title'] . ' - ' . $topicOrder,
                'order' => $topicOrder,
                'estimated_hours' => intval($lessonsPerUnit / $topicsPerUnit * 0.8), // 80% for lessons, 20% for assessment
                'lessons' => $this->generateLessonsForTopic($level, $skillTypes, intval($lessonsPerUnit / $topicsPerUnit)),
                'cultural_integration' => $this->getCulturalIntegration($level, $theme),
                'vocabulary_focus' => $this->getVocabularyFocus($level, $theme),
                'grammar_focus' => $this->getGrammarFocus($level),
            ];

            $topics[] = $topic;
        }

        return $topics;
    }

    /**
     * Generate lessons for a topic using skill-based lesson types
     */
    private function generateLessonsForTopic(string $level, array $skillTypes, int $lessonsPerTopic): array
    {
        $lessons = [];
        $skillTypeKeys = array_keys($skillTypes);

        for ($lessonOrder = 1; $lessonOrder <= $lessonsPerTopic; $lessonOrder++) {
            // Cycle through skill types
            $skillIndex = ($lessonOrder - 1) % count($skillTypeKeys);
            $skillKey = $skillTypeKeys[$skillIndex];
            $skillType = $skillTypes[$skillKey];

            $lesson = [
                'title' => $skillType['english_title'] . ' - Lesson ' . $lessonOrder,
                'cree_title' => $skillType['cree_title'] . ' - ' . $lessonOrder,
                'type' => $skillKey,
                'order' => $lessonOrder,
                'estimated_duration' => 45, // 45 minutes per lesson
                'skill_focus' => $skillKey,
                'exercises_count' => $this->getExerciseCount($level, $skillKey),
                'cultural_components' => $skillType['structure'],
                'assessment_type' => $this->getAssessmentType($level, $skillKey),
            ];

            $lessons[] = $lesson;
        }

        return $lessons;
    }

    /**
     * Get exercise count based on level and skill type
     */
    private function getExerciseCount(string $level, string $skillType): int
    {
        $baseCounts = [
            'vocabulary_introduction' => 6,
            'grammar_focus' => 8,
            'conversation_practice' => 4,
            'listening_comprehension' => 5,
            'reading_comprehension' => 6,
            'writing_practice' => 4,
            'pronunciation_focus' => 7,
            'cultural_context' => 5,
        ];

        $multiplier = match ($level) {
            CurriculumTemplate::LEVEL_A1 => 0.8,
            CurriculumTemplate::LEVEL_A2 => 1.0,
            CurriculumTemplate::LEVEL_B1 => 1.2,
            CurriculumTemplate::LEVEL_B2 => 1.4,
            CurriculumTemplate::LEVEL_C1 => 1.6,
            CurriculumTemplate::LEVEL_C2 => 1.8,
            default => 1.0,
        };

        return intval(($baseCounts[$skillType] ?? 5) * $multiplier);
    }

    /**
     * Get unit assessment structure
     */
    private function getUnitAssessment(string $level, string $themeKey): array
    {
        return [
            'formative_assessment' => [
                'ongoing_observation' => true,
                'portfolio_development' => true,
                'peer_assessment' => true,
                'self_reflection' => true,
            ],
            'summative_assessment' => [
                'cultural_presentation' => true,
                'language_demonstration' => true,
                'community_connection' => true,
                'elder_evaluation' => $level !== CurriculumTemplate::LEVEL_A1,
            ],
            'assessment_criteria' => [
                'syllabics_proficiency' => true,
                'oral_communication' => true,
                'cultural_understanding' => true,
                'community_engagement' => true,
            ],
        ];
    }

    /**
     * Get cultural activities for unit
     */
    private function getCulturalActivities(string $level, string $themeKey): array
    {
        $activities = [
            'personal_identity' => ['naming_ceremony_simulation', 'family_tree_creation', 'clan_system_exploration'],
            'family_relationships' => ['kinship_mapping', 'family_story_sharing', 'adoption_ceremony_learning'],
            'daily_life' => ['seasonal_calendar_creation', 'traditional_daily_routine', 'modern_adaptation_discussion'],
            'food_dining' => ['traditional_food_preparation', 'harvesting_simulation', 'sharing_circle_practice'],
            'travel_transportation' => ['traditional_trail_mapping', 'seasonal_movement_patterns', 'visiting_protocol_practice'],
            'work_education' => ['traditional_role_exploration', 'apprenticeship_simulation', 'knowledge_keeper_interviews'],
            'health_body' => ['traditional_medicine_garden', 'wellness_ceremony_participation', 'healing_story_sharing'],
            'entertainment_hobbies' => ['traditional_games_tournament', 'storytelling_circle', 'powwow_preparation'],
            'technology_communication' => ['digital_storytelling', 'language_app_creation', 'social_media_in_cree'],
            'culture_society' => ['ceremony_observation', 'governance_council_simulation', 'protocol_practice'],
        ];

        return $activities[$themeKey] ?? ['cultural_exploration', 'community_connection', 'traditional_practice'];
    }

    /**
     * Get elder involvement activities
     */
    private function getElderInvolvement(string $level, string $themeKey): array
    {
        return [
            'guest_teaching' => [
                'frequency' => 'weekly',
                'focus' => 'traditional_knowledge_sharing',
                'format' => 'storytelling_and_demonstration',
            ],
            'language_modeling' => [
                'audio_recordings' => true,
                'pronunciation_guidance' => true,
                'cultural_context_explanation' => true,
            ],
            'assessment_participation' => [
                'oral_evaluation' => $level !== CurriculumTemplate::LEVEL_A1,
                'cultural_knowledge_assessment' => true,
                'community_presentation_feedback' => true,
            ],
            'curriculum_guidance' => [
                'content_review' => true,
                'cultural_appropriateness_check' => true,
                'traditional_knowledge_validation' => true,
            ],
        ];
    }

    /**
     * Get land-based learning components
     */
    private function getLandBasedComponents(string $level, string $themeKey): array
    {
        $components = [
            'personal_identity' => ['sacred_site_visits', 'land_connection_ceremonies', 'traditional_territory_exploration'],
            'family_relationships' => ['family_gathering_places', 'ancestral_site_visits', 'community_land_connections'],
            'daily_life' => ['seasonal_activity_participation', 'traditional_camping', 'land_based_routines'],
            'food_dining' => ['traditional_harvesting', 'plant_identification_walks', 'seasonal_food_gathering'],
            'travel_transportation' => ['traditional_trail_walking', 'navigation_skills', 'seasonal_travel_patterns'],
            'work_education' => ['traditional_skill_practice', 'land_based_apprenticeships', 'seasonal_work_cycles'],
            'health_body' => ['medicinal_plant_gathering', 'wellness_ceremonies_outdoors', 'traditional_healing_practices'],
            'entertainment_hobbies' => ['outdoor_traditional_games', 'land_based_storytelling', 'seasonal_celebrations'],
            'technology_communication' => ['digital_land_mapping', 'GPS_with_traditional_knowledge', 'land_based_documentation'],
            'culture_society' => ['ceremonial_site_visits', 'traditional_governance_outdoors', 'community_land_projects'],
        ];

        return $components[$themeKey] ?? ['land_connection_activities', 'outdoor_learning', 'traditional_practices'];
    }

    /**
     * Get cultural integration strategies
     */
    private function getCulturalIntegration(string $level, array $theme): array
    {
        return [
            'protocols' => [
                'opening_ceremonies' => true,
                'closing_ceremonies' => true,
                'respect_protocols' => true,
                'sharing_circles' => true,
            ],
            'knowledge_systems' => [
                'traditional_teachings' => true,
                'oral_tradition_integration' => true,
                'ceremonial_knowledge' => $level !== CurriculumTemplate::LEVEL_A1,
                'sacred_knowledge_boundaries' => true,
            ],
            'community_connections' => [
                'family_involvement' => true,
                'community_events' => true,
                'intergenerational_learning' => true,
                'knowledge_keeper_partnerships' => true,
            ],
        ];
    }

    /**
     * Get vocabulary focus for theme
     */
    private function getVocabularyFocus(string $level, array $theme): array
    {
        return [
            'word_count_target' => match ($level) {
                CurriculumTemplate::LEVEL_A1 => 10,
                CurriculumTemplate::LEVEL_A2 => 15,
                CurriculumTemplate::LEVEL_B1 => 20,
                CurriculumTemplate::LEVEL_B2 => 25,
                CurriculumTemplate::LEVEL_C1 => 30,
                CurriculumTemplate::LEVEL_C2 => 35,
                default => 15,
            },
            'syllabics_instruction' => true,
            'cultural_context' => true,
            'audio_pronunciation' => true,
            'visual_supports' => true,
            'traditional_usage' => true,
            'modern_adaptations' => $level !== CurriculumTemplate::LEVEL_A1,
        ];
    }

    /**
     * Get grammar focus for level
     */
    private function getGrammarFocus(string $level): array
    {
        $grammarFoci = [
            CurriculumTemplate::LEVEL_A1 => [
                'animate_inanimate_distinction',
                'basic_verb_forms',
                'simple_sentence_structure',
                'personal_pronouns',
            ],
            CurriculumTemplate::LEVEL_A2 => [
                'verb_conjugation_patterns',
                'possessive_forms',
                'question_formation',
                'basic_tense_usage',
            ],
            CurriculumTemplate::LEVEL_B1 => [
                'complex_verb_forms',
                'conditional_structures',
                'narrative_tenses',
                'relative_clauses',
            ],
            CurriculumTemplate::LEVEL_B2 => [
                'advanced_verb_aspects',
                'complex_sentence_structures',
                'discourse_markers',
                'stylistic_variations',
            ],
            CurriculumTemplate::LEVEL_C1 => [
                'ceremonial_language_structures',
                'formal_speech_patterns',
                'traditional_oratory_forms',
                'complex_grammatical_relationships',
            ],
            CurriculumTemplate::LEVEL_C2 => [
                'mastery_level_structures',
                'dialectical_variations',
                'historical_language_forms',
                'expert_level_usage',
            ],
        ];

        return $grammarFoci[$level] ?? $grammarFoci[CurriculumTemplate::LEVEL_A1];
    }

    /**
     * Get assessment type for skill
     */
    private function getAssessmentType(string $level, string $skillType): string
    {
        $assessmentTypes = [
            'vocabulary_introduction' => 'formative_vocabulary_check',
            'grammar_focus' => 'grammar_application_task',
            'conversation_practice' => 'peer_conversation_assessment',
            'listening_comprehension' => 'listening_comprehension_quiz',
            'reading_comprehension' => 'reading_analysis_task',
            'writing_practice' => 'writing_portfolio_entry',
            'pronunciation_focus' => 'pronunciation_recording',
            'cultural_context' => 'cultural_reflection_journal',
        ];

        return $assessmentTypes[$skillType] ?? 'formative_assessment';
    }

    /**
     * Generate ESL template data based on proficiency level
     */
    private function generateESLTemplateData(string $level): array
    {
        $baseStructure = [
            'units' => [],
            'assessment_strategy' => 'scaffolded_assessment',
            'cultural_components' => true,
            'spanish_cognates' => true,
            'differentiated_instruction' => true,
        ];

        switch ($level) {
            case CurriculumTemplate::LEVEL_A1:
                $baseStructure['units'] = [
                    [
                        'title' => 'English Basics for Spanish Speakers',
                        'order' => 1,
                        'estimated_hours' => 18,
                        'topics' => [
                            [
                                'title' => 'English Sounds',
                                'lessons' => [
                                    ['title' => 'Difficult English Sounds', 'type' => 'pronunciation', 'exercises' => 8],
                                    ['title' => 'Cognates and False Friends', 'type' => 'vocabulary', 'exercises' => 6],
                                ]
                            ]
                        ]
                    ]
                ];
                break;

            default:
                $baseStructure['units'] = [
                    [
                        'title' => 'Advanced English Skills',
                        'order' => 1,
                        'estimated_hours' => 20,
                        'topics' => [
                            [
                                'title' => 'Academic English',
                                'lessons' => [
                                    ['title' => 'Academic Vocabulary', 'type' => 'vocabulary', 'exercises' => 7],
                                    ['title' => 'Writing Skills', 'type' => 'writing', 'exercises' => 6],
                                ]
                            ]
                        ]
                    ]
                ];
        }

        return $baseStructure;
    }

    /**
     * Generate Plains Cree revitalization template data
     */
    private function generatePlainsCreeRevitalizationTemplateData(string $level): array
    {
        $baseStructure = [
            'units' => [],
            'assessment_strategy' => 'community_assessment',
            'cultural_components' => true,
            'community_involvement' => true,
            'intergenerational_learning' => true,
            'land_based_learning' => true,
        ];

        switch ($level) {
            case CurriculumTemplate::LEVEL_A1:
                $baseStructure['units'] = [
                    [
                        'title' => 'Language Awakening',
                        'order' => 1,
                        'estimated_hours' => 20,
                        'topics' => [
                            [
                                'title' => 'Connection to Language',
                                'lessons' => [
                                    ['title' => 'Why Our Language Matters', 'type' => 'cultural', 'exercises' => 5],
                                    ['title' => 'Family Language Stories', 'type' => 'storytelling', 'exercises' => 4],
                                ]
                            ]
                        ]
                    ]
                ];
                break;

            default:
                $baseStructure['units'] = [
                    [
                        'title' => 'Language Leadership',
                        'order' => 1,
                        'estimated_hours' => 25,
                        'topics' => [
                            [
                                'title' => 'Teaching Others',
                                'lessons' => [
                                    ['title' => 'Sharing Knowledge', 'type' => 'teaching', 'exercises' => 6],
                                    ['title' => 'Community Events', 'type' => 'cultural', 'exercises' => 5],
                                ]
                            ]
                        ]
                    ]
                ];
        }

        return $baseStructure;
    }
}
