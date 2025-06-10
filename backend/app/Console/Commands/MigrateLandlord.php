<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Migrate Landlord Command
 * 
 * Handles running migrations for the central/landlord database.
 * This includes tenant management, subscription management, and other system-wide tables.
 */
class MigrateLandlord extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'migrate:landlord 
                            {--fresh : Drop all tables and re-run all migrations}
                            {--seed : Seed the database after migrating}
                            {--force : Force the operation to run when in production}';

    /**
     * The console command description.
     */
    protected $description = 'Run landlord/central database migrations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🏢 Running Landlord (Central) Database Migrations...');
        
        try {
            $options = [
                '--path' => 'database/migrations/landlord',
                '--force' => $this->option('force'),
            ];

            if ($this->option('fresh')) {
                $this->warn('⚠️  This will drop all landlord tables!');
                if (!$this->option('no-interaction') && !$this->confirm('Are you sure you want to continue?')) {
                    $this->info('Migration cancelled.');
                    return 1;
                }

                Artisan::call('migrate:fresh', $options);
            } else {
                Artisan::call('migrate', $options);
            }

            $this->info('✅ Landlord migrations completed successfully!');
            
            // Display migration output
            $output = Artisan::output();
            if ($output) {
                $this->line($output);
            }

            if ($this->option('seed')) {
                $this->info('🌱 Seeding landlord database...');
                Artisan::call('db:seed', [
                    '--class' => 'Database\\Seeders\\Landlord\\LandlordSeeder',
                    '--force' => $this->option('force'),
                ]);
                $this->info('✅ Landlord seeding completed!');
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error running landlord migrations: ' . $e->getMessage());
            return 1;
        }
    }
}
