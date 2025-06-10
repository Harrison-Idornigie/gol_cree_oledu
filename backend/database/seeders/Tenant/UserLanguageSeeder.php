<?php

namespace Database\Seeders;

use App\Models\Language;
use App\Models\User;
use App\Models\UserLanguage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserLanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the languages
        $english = Language::where('code', 'en')->first();
        $spanish = Language::where('code', 'es')->first();
        $plainsCree = Language::where('code', 'crk')->first();
        
        if (!$english || !$spanish || !$plainsCree) {
            $this->command->error('Required languages not found. Make sure LanguageSeeder has been run.');
            return;
        }
        
        // Get all users (or you could target specific users)
        $users = User::all();
        
        foreach ($users as $user) {
            // Check if user already has languages to avoid duplicates
            $existingLanguages = $user->userLanguages()->pluck('language_id')->toArray();
            
            // Add English as primary language if not already added
            if (!in_array($english->id, $existingLanguages)) {
                UserLanguage::create([
                    'user_id' => $user->id,
                    'language_id' => $english->id,
                    'is_primary' => true,
                    'proficiency_level' => 'intermediate',
                ]);
                
                $this->command->info("Added English as primary language for user: {$user->name}");
            }
            
            // Add Spanish if not already added
            if (!in_array($spanish->id, $existingLanguages)) {
                UserLanguage::create([
                    'user_id' => $user->id,
                    'language_id' => $spanish->id,
                    'is_primary' => false,
                    'proficiency_level' => 'beginner',
                ]);
                
                $this->command->info("Added Spanish as a language for user: {$user->name}");
            }
            
            // Add Plains Cree if not already added
            if (!in_array($plainsCree->id, $existingLanguages)) {
                UserLanguage::create([
                    'user_id' => $user->id,
                    'language_id' => $plainsCree->id,
                    'is_primary' => false,
                    'proficiency_level' => 'beginner',
                ]);
                
                $this->command->info("Added Plains Cree as a language for user: {$user->name}");
            }
        }
    }
}
