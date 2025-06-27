<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenants\Language;
use App\Models\Tenants\LanguagePair;
use App\Models\Tenants\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class LanguagePairSeeder extends Seeder
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

        if (!$english || !$spanish || !$plainsCree) {
            $this->command->error('Required languages not found. Please run LanguageSeeder first.');
            return;
        }

        // Add French for more language pair options
        $french = Language::updateOrCreate(
            ['code' => 'fr'],
            [
                'name' => 'French',
                'native_name' => 'Français',
                'is_active' => true,
            ]
        );

        // Add German for additional options
        $german = Language::updateOrCreate(
            ['code' => 'de'],
            [
                'name' => 'German',
                'native_name' => 'Deutsch',
                'is_active' => true,
            ]
        );

        // Create language pairs for curriculum templates
        $languagePairs = [
            // English as source language (learning other languages from English)
            [$english->id, $spanish->id],
            [$english->id, $french->id],
            [$english->id, $german->id],
            [$english->id, $plainsCree->id],
            
            // Other languages to English (ESL scenarios)
            [$spanish->id, $english->id],
            [$french->id, $english->id],
            [$german->id, $english->id],
            [$plainsCree->id, $english->id],
            
            // Indigenous language preservation pairs
            [$plainsCree->id, $spanish->id],
            [$plainsCree->id, $french->id],
        ];

        foreach ($languagePairs as [$sourceId, $targetId]) {
            LanguagePair::updateOrCreate(
                [
                    'source_language_id' => $sourceId,
                    'target_language_id' => $targetId,
                ],
                [
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Language pairs created successfully!');
    }
}
