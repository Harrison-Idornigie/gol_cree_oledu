<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Migrate All Command
 * 
 * Handles running both landlord and tenant migrations in the correct order.
 * This is the main command for setting up the entire multi-tenant application.
 */
class MigrateAll extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'migrate:all 
                            {--fresh : Drop all tables and re-run all migrations}
                            {--seed : Seed the databases after migrating}
                            {--force : Force the operation to run when in production}
                            {--landlord-only : Only migrate landlord database}
                            {--tenants-only : Only migrate tenant databases}';

    /**
     * The console command description.
     */
    protected $description = 'Run all migrations (landlord and tenants) in the correct order';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Starting Multi-Tenant Application Migration...');
        
        $landlordOnly = $this->option('landlord-only');
        $tenantsOnly = $this->option('tenants-only');
        
        // Step 1: Migrate Landlord (unless tenants-only)
        if (!$tenantsOnly) {
            $this->newLine();
            $this->info('📋 Step 1: Migrating Landlord Database...');
            
            $result = Artisan::call('migrate:landlord', [
                '--fresh' => $this->option('fresh'),
                '--seed' => $this->option('seed'),
                '--force' => $this->option('force'),
            ]);
            
            if ($result !== 0) {
                $this->error('❌ Landlord migration failed!');
                return 1;
            }
        }
        
        // Step 2: Migrate Tenants (unless landlord-only)
        if (!$landlordOnly) {
            $this->newLine();
            $this->info('📋 Step 2: Migrating Tenant Databases...');
            
            $result = Artisan::call('migrate:tenants', [
                '--fresh' => $this->option('fresh'),
                '--seed' => $this->option('seed'),
                '--force' => $this->option('force'),
            ]);
            
            if ($result !== 0) {
                $this->error('❌ Tenant migration failed!');
                return 1;
            }
        }
        
        $this->newLine();
        $this->info('🎉 Multi-Tenant Application Migration Completed Successfully!');
        
        // Display summary
        $this->displaySummary($landlordOnly, $tenantsOnly);
        
        return 0;
    }
    
    /**
     * Display migration summary
     */
    protected function displaySummary(bool $landlordOnly, bool $tenantsOnly): void
    {
        $this->newLine();
        $this->info('📊 Migration Summary:');
        
        if (!$tenantsOnly) {
            $this->line('  ✅ Landlord database migrated');
            $this->line('     - Tenants table');
            $this->line('     - Domains table');
            $this->line('     - User impersonation tokens');
            $this->line('     - Future: Subscription management tables');
        }
        
        if (!$landlordOnly) {
            $this->line('  ✅ Tenant databases migrated');
            $this->line('     - User management');
            $this->line('     - Language learning content');
            $this->line('     - Progress tracking');
            $this->line('     - Gamification features');
        }
        
        $this->newLine();
        $this->info('🔧 Next Steps:');
        $this->line('  1. Create your first tenant: php artisan tenants:create');
        $this->line('  2. Add domains: php artisan tenants:domain');
        $this->line('  3. Test tenant access via subdomain or custom domain');
    }
}
