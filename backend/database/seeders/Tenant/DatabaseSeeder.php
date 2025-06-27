<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenants\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create a test user
        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name'              => 'Test User',
                'password'          => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        // Run other seeders
        $this->call([
            // Core language setup
            LanguageSeeder::class,
            LanguagePairSeeder::class, // Create language pairs for templates
            WordSeeder::class, // Add vocabulary words
            LearningPathSeeder::class,
            UserLanguageSeeder::class,

            // Template seeders (must come after language setup)
            CurriculumTemplateSeeder::class,
            ContentTemplateSeeder::class,

            // Plains Cree Starter Pack (complete A1-C2 courses with real content)
            PlainsCreeStarterPackSeeder::class,

            // Exercise seeders (for additional demo content)
            FillInBlankExerciseSeeder::class,
            MultipleChoiceExerciseSeeder::class,
            MatchingExerciseSeeder::class,
            ConversationExerciseSeeder::class,
            ListeningExerciseSeeder::class,
            PictureExerciseSeeder::class,

            // Role seeders
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class
        ]);
    }
}
