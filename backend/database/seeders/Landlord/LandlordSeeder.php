<?php

namespace Database\Seeders\Landlord;

use Illuminate\Database\Seeder;

/**
 * Landlord Seeder
 * 
 * Seeds the central/landlord database with system-wide data.
 * This includes super admin users, system memberships, and other
 * central configuration data.
 */
class LandlordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🌱 Seeding landlord database...');

        // Seed super admin user
        $this->call([
            SuperAdminSeeder::class,
        ]);

        $this->command->info('✅ Landlord database seeded successfully!');
    }
}
