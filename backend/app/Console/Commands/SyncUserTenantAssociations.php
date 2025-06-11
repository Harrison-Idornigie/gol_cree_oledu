<?php

namespace App\Console\Commands;

use App\Models\Landlord\Tenant;
use App\Services\Auth\UserTenantSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncUserTenantAssociations extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenant:sync-user-associations 
                            {--tenant= : Sync specific tenant by slug}
                            {--dry-run : Show what would be synced without making changes}
                            {--force : Force sync even if associations already exist}
                            {--chunk=100 : Number of users to process at once}';

    /**
     * The console command description.
     */
    protected $description = 'Migrate existing tenant users to central user_tenant_associations table';

    protected UserTenantSyncService $syncService;

    public function __construct(UserTenantSyncService $syncService)
    {
        parent::__construct();
        $this->syncService = $syncService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Starting User Tenant Association Sync');
        $this->newLine();

        $isDryRun = $this->option('dry-run');
        $specificTenant = $this->option('tenant');
        $force = $this->option('force');
        $chunkSize = (int) $this->option('chunk');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Get tenants to process
        $tenants = $this->getTenantsToProcess($specificTenant);
        
        if ($tenants->isEmpty()) {
            $this->error('No active tenants found to process');
            return self::FAILURE;
        }

        $this->info("Processing {$tenants->count()} tenant(s)");
        $this->newLine();

        $overallStats = [
            'tenants_processed' => 0,
            'total_users_found' => 0,
            'total_synced' => 0,
            'total_skipped' => 0,
            'total_errors' => 0
        ];

        $progressBar = $this->output->createProgressBar($tenants->count());
        $progressBar->setFormat('verbose');

        foreach ($tenants as $tenant) {
            $this->processTenant($tenant, $isDryRun, $force, $chunkSize, $overallStats);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->displayResults($overallStats, $isDryRun);

        return self::SUCCESS;
    }

    protected function getTenantsToProcess(?string $specificTenant)
    {
        $query = Tenant::where('status', 'active');
        
        if ($specificTenant) {
            $query->where('slug', $specificTenant);
        }
        
        return $query->orderBy('slug')->get();
    }

    protected function processTenant(Tenant $tenant, bool $isDryRun, bool $force, int $chunkSize, array &$overallStats): void
    {
        try {
            $stats = ['users_found' => 0, 'synced' => 0, 'skipped' => 0, 'errors' => 0];

            $tenant->run(function () use ($tenant, $isDryRun, $force, $chunkSize, &$stats) {
                $totalUsers = DB::table('users')->count();
                $stats['users_found'] = $totalUsers;

                if ($totalUsers === 0) {
                    return;
                }

                // Process users in chunks to avoid memory issues
                DB::table('users')->orderBy('id')->chunk($chunkSize, function ($users) use ($tenant, $isDryRun, $force, &$stats) {
                    foreach ($users as $user) {
                        $this->processUser($user, $tenant, $isDryRun, $force, $stats);
                    }
                });
            });

            $overallStats['tenants_processed']++;
            $overallStats['total_users_found'] += $stats['users_found'];
            $overallStats['total_synced'] += $stats['synced'];
            $overallStats['total_skipped'] += $stats['skipped'];
            $overallStats['total_errors'] += $stats['errors'];

            if (!$isDryRun) {
                $this->line("✅ {$tenant->slug}: {$stats['synced']} synced, {$stats['skipped']} skipped, {$stats['errors']} errors");
            }

        } catch (\Exception $e) {
            $overallStats['total_errors']++;
            $this->error("❌ Failed to process tenant {$tenant->slug}: {$e->getMessage()}");
        }
    }

    protected function processUser($user, Tenant $tenant, bool $isDryRun, bool $force, array &$stats): void
    {
        try {
            // Check if association already exists
            if (!$force) {
                $existingAssociation = DB::connection('landlord')
                    ->table('user_tenant_associations')
                    ->where('email', $user->email)
                    ->where('tenant_id', $tenant->id)
                    ->exists();

                if ($existingAssociation) {
                    $stats['skipped']++;
                    return;
                }
            }

            if ($isDryRun) {
                $this->line("Would sync: {$user->email} → {$tenant->slug} ({$user->membership})");
                $stats['synced']++;
                return;
            }

            // Create/update association
            DB::connection('landlord')->table('user_tenant_associations')->updateOrInsert(
                [
                    'email' => $user->email,
                    'tenant_id' => $tenant->id,
                ],
                [
                    'tenant_slug' => $tenant->slug,
                    'membership' => $user->membership,
                    'permissions' => json_encode($user->permissions ?? []),
                    'is_active' => $user->is_active ?? true,
                    'last_accessed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $stats['synced']++;

        } catch (\Exception $e) {
            $stats['errors']++;
            $this->error("Failed to sync user {$user->email} in {$tenant->slug}: {$e->getMessage()}");
        }
    }

    protected function displayResults(array $stats, bool $isDryRun): void
    {
        $this->info('📊 Migration Results:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Tenants Processed', $stats['tenants_processed']],
                ['Total Users Found', $stats['total_users_found']],
                [$isDryRun ? 'Would Sync' : 'Synced', $stats['total_synced']],
                ['Skipped (Already Exist)', $stats['total_skipped']],
                ['Errors', $stats['total_errors']],
            ]
        );

        if ($isDryRun) {
            $this->newLine();
            $this->info('💡 Run without --dry-run to perform actual migration');
        } else {
            $this->newLine();
            $this->info('✅ Migration completed successfully!');
            
            if ($stats['total_errors'] > 0) {
                $this->warn("⚠️  {$stats['total_errors']} errors occurred. Check logs for details.");
            }
        }
    }
}
