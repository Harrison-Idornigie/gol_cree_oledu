<?php
namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
            LearningPathSeeder::class,
            SuperAdminSeeder::class,
            UserLanguageSeeder::class,
        ]);
    }
}
