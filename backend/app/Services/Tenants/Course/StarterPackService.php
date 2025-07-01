<?php

namespace App\Services\Tenants\Course;

use App\Models\Tenants\Language;
use App\Models\Tenants\LearningPath;
use App\Models\Tenants\CurriculumTemplate;
use Database\Seeders\Tenant\PlainsCreeStarterPackSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class StarterPackService
{
    /**
     * Initialize starter pack for a new tenant
     */
    public function initializeStarterPack(): array
    {
        try {
            DB::beginTransaction();

            // Run the Plains Cree starter pack seeder
            $seeder = new PlainsCreeStarterPackSeeder();
            $seeder->run();

            // Validate the starter pack
            $validation = $seeder->validateStarterPack();

            if (!$validation['is_valid']) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Starter pack validation failed',
                    'issues' => $validation['issues'],
                ];
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Starter pack initialized successfully',
                'stats' => $validation['stats'],
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => 'Failed to initialize starter pack: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get available starter pack templates
     */
    public function getAvailableTemplates(): array
    {
        return [
            'plains_cree' => [
                'name' => 'Plains Cree Complete Course (A1-C2)',
                'description' => 'Complete Plains Cree language learning course with cultural context, syllabics instruction, and age-appropriate learning paths.',
                'levels' => ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'],
                'age_groups' => ['kids', 'teen_adult'],
                'features' => [
                    'Duolingo-style word tracking',
                    'Audio pronunciation support',
                    'Syllabics writing system',
                    'Cultural context integration',
                    'Elder teachings and traditional knowledge',
                    'Age-appropriate learning paths',
                    'Word progression through guidebooks',
                ],
                'total_words' => 500,
                'estimated_lessons' => 200,
                'is_official' => true,
            ],
        ];
    }

    /**
     * Clone Plains Cree course structure for another language
     */
    public function cloneCourseStructureForLanguage(string $targetLanguageCode, array $options = []): array
    {
        try {
            DB::beginTransaction();

            $targetLanguage = Language::where('code', $targetLanguageCode)->first();
            if (!$targetLanguage) {
                throw new \Exception("Target language '{$targetLanguageCode}' not found");
            }

            $plainsCree = Language::where('code', 'crk')->first();
            if (!$plainsCree) {
                throw new \Exception("Plains Cree template language not found");
            }

            // Clone curriculum templates
            $clonedTemplates = $this->cloneCurriculumTemplates($plainsCree, $targetLanguage, $options);

            // Clone learning path structures (without content)
            $clonedPaths = $this->cloneLearningPathStructures($plainsCree, $targetLanguage, $options);

            DB::commit();

            return [
                'success' => true,
                'message' => "Course structure cloned for {$targetLanguage->name}",
                'cloned_templates' => count($clonedTemplates),
                'cloned_paths' => count($clonedPaths),
                'target_language' => $targetLanguage->name,
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => 'Failed to clone course structure: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Clone curriculum templates for target language
     */
    private function cloneCurriculumTemplates(Language $sourceLanguage, Language $targetLanguage, array $options): array
    {
        $sourceTemplates = CurriculumTemplate::whereHas('languagePair', function ($query) use ($sourceLanguage) {
            $query->where('target_language_id', $sourceLanguage->id);
        })->get();

        $clonedTemplates = [];

        foreach ($sourceTemplates as $template) {
            $cloned = CurriculumTemplate::create([
                'name' => str_replace('Plains Cree', $targetLanguage->name, $template->name),
                'description' => str_replace('Plains Cree', $targetLanguage->name, $template->description),
                'language_pair_id' => $this->getOrCreateLanguagePair($targetLanguage),
                'proficiency_level' => $template->proficiency_level,
                'estimated_hours' => $template->estimated_hours,
                'prerequisites' => $template->prerequisites,
                'template_data' => $this->adaptTemplateDataForLanguage($template->template_data, $targetLanguage, $options),
                'is_official' => false, // Cloned templates are not official
                'created_by' => auth()->id(),
                'usage_count' => 0,
                'effectiveness_score' => null,
            ]);

            $clonedTemplates[] = $cloned;
        }

        return $clonedTemplates;
    }

    /**
     * Clone learning path structures for target language
     */
    private function cloneLearningPathStructures(Language $sourceLanguage, Language $targetLanguage, array $options): array
    {
        $sourcePaths = LearningPath::where('language_id', $sourceLanguage->id)
            ->whereJsonContains('metadata->is_starter_pack', true)
            ->get();

        $clonedPaths = [];

        foreach ($sourcePaths as $path) {
            // Get or create language pair for this target language
            $languagePairId = $this->getOrCreateLanguagePair($targetLanguage);

            $cloned = LearningPath::create([
                'title' => str_replace('Plains Cree', $targetLanguage->name, $path->title),
                'description' => str_replace('Plains Cree', $targetLanguage->name, $path->description),
                'language_id' => $targetLanguage->id, // Legacy support
                'language_pair_id' => $languagePairId,
                'target_level' => $path->target_level,
                'status' => 'draft', // Cloned paths start as drafts
                'review_status' => 'pending',
                'metadata' => [
                    'cloned_from' => 'plains_cree_starter_pack',
                    'source_path_id' => $path->id,
                    'is_template_clone' => true,
                    'target_language' => $targetLanguage->code,
                ],
            ]);

            // Note: We don't clone the actual content (units, topics, lessons, exercises)
            // as that would require language-specific words and translations
            // Teachers will use the scaffolding system to populate content

            $clonedPaths[] = $cloned;
        }

        return $clonedPaths;
    }

    /**
     * Adapt template data for target language
     */
    private function adaptTemplateDataForLanguage(array $templateData, Language $targetLanguage, array $options): array
    {
        // Remove Plains Cree specific elements
        unset($templateData['syllabics_instruction']);
        unset($templateData['elder_involvement']);
        unset($templateData['land_based_learning']);

        // Add generic language learning elements
        $templateData['pronunciation_focus'] = true;
        $templateData['cultural_components'] = $options['include_cultural_components'] ?? true;
        $templateData['audio_support'] = true;
        $templateData['word_progression'] = true;

        // Adapt unit titles and descriptions
        if (isset($templateData['units'])) {
            foreach ($templateData['units'] as &$unit) {
                $unit['title'] = $this->adaptTitleForLanguage($unit['title'], $targetLanguage);

                if (isset($unit['topics'])) {
                    foreach ($unit['topics'] as &$topic) {
                        $topic['title'] = $this->adaptTitleForLanguage($topic['title'], $targetLanguage);

                        if (isset($topic['lessons'])) {
                            foreach ($topic['lessons'] as &$lesson) {
                                $lesson['title'] = $this->adaptTitleForLanguage($lesson['title'], $targetLanguage);
                            }
                        }
                    }
                }
            }
        }

        return $templateData;
    }

    /**
     * Adapt titles for target language
     */
    private function adaptTitleForLanguage(string $title, Language $targetLanguage): string
    {
        // Remove Plains Cree specific terms and make generic
        $adaptations = [
            'Plains Cree' => $targetLanguage->name,
            'nēhiyawēwin' => $targetLanguage->native_name ?? $targetLanguage->name,
            'Syllabic Writing System' => 'Writing System',
            'Elder Teachings' => 'Cultural Teachings',
            'Traditional Stories' => 'Cultural Stories',
            'Ceremonial Language' => 'Formal Language',
        ];

        return str_replace(array_keys($adaptations), array_values($adaptations), $title);
    }

    /**
     * Get or create language pair for target language
     */
    private function getOrCreateLanguagePair(Language $targetLanguage): int
    {
        $english = Language::where('code', 'en')->first();

        $pair = \App\Models\Tenants\LanguagePair::firstOrCreate([
            'source_language_id' => $english->id,
            'target_language_id' => $targetLanguage->id,
        ], [
            'name' => "English to {$targetLanguage->name}",
            'is_active' => true,
        ]);

        return $pair->id;
    }

    /**
     * Get starter pack status for current tenant
     */
    public function getStarterPackStatus(): array
    {
        $plainsCree = Language::where('code', 'crk')->first();

        if (!$plainsCree) {
            return [
                'initialized' => false,
                'message' => 'Starter pack not initialized',
            ];
        }

        $seeder = new PlainsCreeStarterPackSeeder();
        $validation = $seeder->validateStarterPack();

        return [
            'initialized' => true,
            'is_valid' => $validation['is_valid'],
            'stats' => $validation['stats'],
            'issues' => $validation['issues'] ?? [],
        ];
    }
}
