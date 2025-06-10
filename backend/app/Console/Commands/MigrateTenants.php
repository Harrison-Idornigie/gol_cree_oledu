<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Stancl\Tenancy\Database\Models\Tenant;

/**
 * Migrate Tenants Command
 * 
 * Handles running migrations for all tenant databases.
 * This is a wrapper around Stancl's tenants:migrate command with additional features.
 */
class MigrateTenants extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'migrate:tenants 
                            {tenant? : Specific tenant ID to migrate}
                            {--fresh : Drop all tables and re-run all migrations}
                            {--seed : Seed the databases after migrating}
                            {--force : Force the operation to run when in production}';

    /**
     * The console command description.
     */
    protected $description = 'Run tenant database migrations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = $this->argument('tenant');
        
        if ($tenantId) {
            return $this->migrateSingleTenant($tenantId);
        } else {
            return $this->migrateAllTenants();
        }
    }

    /**
     * Migrate a single tenant
     */
    protected function migrateSingleTenant(string $tenantId): int
    {
        $tenant = Tenant::find($tenantId);
        
        if (!$tenant) {
            $this->error("❌ Tenant with ID '{$tenantId}' not found.");
            return 1;
        }

        $this->info("🏠 Migrating tenant: {$tenant->id}");
        
        try {
            $options = [
                '--tenants' => [$tenantId],
                '--force' => $this->option('force'),
            ];

            if ($this->option('fresh')) {
                $this->warn('⚠️  This will drop all tables for this tenant!');
                if (!$this->option('no-interaction') && !$this->confirm("Are you sure you want to continue for tenant {$tenant->id}?")) {
                    $this->info('Migration cancelled.');
                    return 1;
                }

                Artisan::call('tenants:migrate-fresh', $options);
            } else {
                Artisan::call('tenants:migrate', $options);
            }

            if ($this->option('seed')) {
                $this->info("🌱 Seeding tenant {$tenant->id}...");
                Artisan::call('tenants:seed', [
                    '--tenants' => [$tenantId],
                    '--force' => $this->option('force'),
                ]);
            }

            $this->info("✅ Tenant {$tenant->id} migrated successfully!");
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error migrating tenant {$tenant->id}: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Migrate all tenants
     */
    protected function migrateAllTenants(): int
    {
        $tenantCount = Tenant::count();
        
        if ($tenantCount === 0) {
            $this->info('ℹ️  No tenants found to migrate.');
            return 0;
        }

        $this->info("🏠 Migrating {$tenantCount} tenant(s)...");
        
        try {
            $options = [
                '--force' => $this->option('force'),
            ];

            if ($this->option('fresh')) {
                $this->warn('⚠️  This will drop all tables for ALL tenants!');
                if (!$this->option('no-interaction') && !$this->confirm('Are you sure you want to continue?')) {
                    $this->info('Migration cancelled.');
                    return 1;
                }

                Artisan::call('tenants:migrate-fresh', $options);
            } else {
                Artisan::call('tenants:migrate', $options);
            }

            if ($this->option('seed')) {
                $this->info('🌱 Seeding all tenants...');
                Artisan::call('tenants:seed', [
                    '--force' => $this->option('force'),
                ]);
            }

            $this->info("✅ All {$tenantCount} tenant(s) migrated successfully!");
            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error migrating tenants: ' . $e->getMessage());
            return 1;
        }
    }
}
