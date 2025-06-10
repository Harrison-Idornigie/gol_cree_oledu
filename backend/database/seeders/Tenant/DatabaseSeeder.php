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
            LanguageSeeder::class,
            WordSeeder::class, // Add vocabulary words
            LearningPathSeeder::class,
            UserLanguageSeeder::class,

            // Exercise seeders
            FillInBlankExerciseSeeder::class,
            MultipleChoiceExerciseSeeder::class,
            MatchingExerciseSeeder::class,
            ConversationExerciseSeeder::class,
            ListeningExerciseSeeder::class,
            PictureExerciseSeeder::class,
        ]);
    }
}
