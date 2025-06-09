<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant Migration Command
 * 
 * Handles running migrations for specific tenants or all tenants.
 * Separates landlord (system-wide) from tenant-specific migrations.
 */
class TenantMigrate extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenant:migrate 
                            {tenant? : The tenant ID to migrate}
                            {--all : Run migrations for all tenants}
                            {--fresh : Drop all tables and re-run all migrations}
                            {--seed : Seed the database after migrating}
                            {--force : Force the operation to run when in production}';

    /**
     * The console command description.
     */
    protected $description = 'Run tenant-specific migrations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->migrateAllTenants();
        }

        $tenantId = $this->argument('tenant');
        if (!$tenantId) {
            $this->error('Please specify a tenant ID or use --all flag');
            return 1;
        }

        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            $this->error("Tenant with ID {$tenantId} not found");
            return 1;
        }

        return $this->migrateTenant($tenant);
    }

    /**
     * Migrate all tenants
     */
    protected function migrateAllTenants(): int
    {
        $tenants = Tenant::all();
        
        if ($tenants->isEmpty()) {
            $this->info('No tenants found');
            return 0;
        }

        $this->info("Migrating {$tenants->count()} tenants...");

        foreach ($tenants as $tenant) {
            $this->info("Migrating tenant: {$tenant->name} (ID: {$tenant->id})");
            
            if ($this->migrateTenant($tenant) !== 0) {
                $this->error("Failed to migrate tenant: {$tenant->name}");
                return 1;
            }
        }

        $this->info('All tenants migrated successfully');
        return 0;
    }

    /**
     * Migrate a specific tenant
     */
    protected function migrateTenant(Tenant $tenant): int
    {
        try {
            // Set tenant context
            app()->instance('current_tenant', $tenant);
            
            // Run tenant-specific migrations
            $options = [
                '--path' => 'database/migrations/tenant',
                '--force' => $this->option('force'),
            ];

            if ($this->option('fresh')) {
                // Drop tenant-specific tables only
                $this->dropTenantTables($tenant);
                $options['--step'] = 0;
            }

            Artisan::call('migrate', $options);

            if ($this->option('seed')) {
                Artisan::call('db:seed', [
                    '--class' => 'TenantSeeder',
                    '--force' => $this->option('force'),
                ]);
            }

            $this->info("Tenant {$tenant->name} migrated successfully");
            return 0;

        } catch (\Exception $e) {
            $this->error("Error migrating tenant {$tenant->name}: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Drop tenant-specific tables
     */
    protected function dropTenantTables(Tenant $tenant): void
    {
        $tenantTables = [
            'users', 'roles', 'permissions', 'role_user', 'permission_role',
            'languages', 'learning_paths', 'units', 'topics', 'lessons', 'exercises',
            'words', 'word_translations', 'sentences', 'sentence_translations',
            'user_progress', 'user_languages', 'vocabulary', 'guide_book_entries',
            'admin_invites', 'exercise_attempts', 'user_achievements',
            'media', 'audit_logs'
        ];

        foreach ($tenantTables as $table) {
            if (Schema::hasTable($table)) {
                Schema::drop($table);
            }
        }
    }
}
