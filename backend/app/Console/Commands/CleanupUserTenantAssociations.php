<?php

namespace App\Console\Commands;

use App\Models\Landlord\Tenant;
use App\Models\Landlord\UserTenantAssociation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupUserTenantAssociations extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenant:cleanup-associations 
                            {--dry-run : Show what would be cleaned without making changes}
                            {--orphaned : Clean associations for deleted tenants}
                            {--inactive : Clean associations for inactive tenants}
                            {--duplicates : Clean duplicate associations}
                            {--all : Clean all types of issues}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up orphaned, duplicate, or invalid user tenant associations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🧹 Starting User Tenant Association Cleanup');
        $this->newLine();

        $isDryRun = $this->option('dry-run');
        $cleanOrphaned = $this->option('orphaned') || $this->option('all');
        $cleanInactive = $this->option('inactive') || $this->option('all');
        $cleanDuplicates = $this->option('duplicates') || $this->option('all');

        if (!$cleanOrphaned && !$cleanInactive && !$cleanDuplicates) {
            $this->error('Please specify what to clean: --orphaned, --inactive, --duplicates, or --all');
            return self::FAILURE;
        }

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        $totalCleaned = 0;

        if ($cleanOrphaned) {
            $totalCleaned += $this->cleanOrphanedAssociations($isDryRun);
        }

        if ($cleanInactive) {
            $totalCleaned += $this->cleanInactiveAssociations($isDryRun);
        }

        if ($cleanDuplicates) {
            $totalCleaned += $this->cleanDuplicateAssociations($isDryRun);
        }

        $this->newLine();
        $this->info("📊 Total associations " . ($isDryRun ? 'that would be' : '') . " cleaned: {$totalCleaned}");

        if ($isDryRun && $totalCleaned > 0) {
            $this->newLine();
            $this->info('💡 Run without --dry-run to perform actual cleanup');
        }

        return self::SUCCESS;
    }

    protected function cleanOrphanedAssociations(bool $isDryRun): int
    {
        $this->info('🔍 Checking for orphaned associations (deleted tenants)...');

        // Get IDs of active tenants
        $activeTenantIds = Tenant::pluck('id')->toArray();

        // Find associations for non-existent tenants
        $orphanedQuery = UserTenantAssociation::whereNotIn('tenant_id', $activeTenantIds);
        $orphanedCount = $orphanedQuery->count();

        if ($orphanedCount === 0) {
            $this->line('✅ No orphaned associations found');
            return 0;
        }

        if ($isDryRun) {
            $this->warn("Would clean {$orphanedCount} orphaned associations");
            
            // Show sample of what would be cleaned
            $samples = $orphanedQuery->limit(5)->get();
            foreach ($samples as $sample) {
                $this->line("  • {$sample->email} → {$sample->tenant_slug} (tenant_id: {$sample->tenant_id})");
            }
            if ($orphanedCount > 5) {
                $this->line("  • ... and " . ($orphanedCount - 5) . " more");
            }
        } else {
            $orphanedQuery->delete();
            $this->info("🗑️  Cleaned {$orphanedCount} orphaned associations");
        }

        return $orphanedCount;
    }

    protected function cleanInactiveAssociations(bool $isDryRun): int
    {
        $this->info('🔍 Checking for associations with inactive tenants...');

        // Find associations for inactive tenants
        $inactiveQuery = UserTenantAssociation::whereHas('tenant', function ($query) {
            $query->where('status', '!=', 'active');
        });
        
        $inactiveCount = $inactiveQuery->count();

        if ($inactiveCount === 0) {
            $this->line('✅ No associations with inactive tenants found');
            return 0;
        }

        if ($isDryRun) {
            $this->warn("Would clean {$inactiveCount} associations with inactive tenants");
            
            // Show sample of what would be cleaned
            $samples = $inactiveQuery->with('tenant')->limit(5)->get();
            foreach ($samples as $sample) {
                $this->line("  • {$sample->email} → {$sample->tenant_slug} (status: {$sample->tenant->status})");
            }
            if ($inactiveCount > 5) {
                $this->line("  • ... and " . ($inactiveCount - 5) . " more");
            }
        } else {
            $inactiveQuery->delete();
            $this->info("🗑️  Cleaned {$inactiveCount} associations with inactive tenants");
        }

        return $inactiveCount;
    }

    protected function cleanDuplicateAssociations(bool $isDryRun): int
    {
        $this->info('🔍 Checking for duplicate associations...');

        // Find duplicate associations (same email + tenant_id)
        $duplicates = DB::table('user_tenant_associations')
            ->select('email', 'tenant_id', DB::raw('COUNT(*) as count'), DB::raw('MIN(id) as keep_id'))
            ->groupBy('email', 'tenant_id')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->isEmpty()) {
            $this->line('✅ No duplicate associations found');
            return 0;
        }

        $totalDuplicates = 0;

        foreach ($duplicates as $duplicate) {
            $duplicateCount = $duplicate->count - 1; // Keep one, remove the rest
            $totalDuplicates += $duplicateCount;

            if ($isDryRun) {
                $this->warn("Would remove {$duplicateCount} duplicate(s) for {$duplicate->email} in tenant {$duplicate->tenant_id}");
            } else {
                // Delete all except the oldest one (lowest ID)
                UserTenantAssociation::where('email', $duplicate->email)
                    ->where('tenant_id', $duplicate->tenant_id)
                    ->where('id', '!=', $duplicate->keep_id)
                    ->delete();
            }
        }

        if (!$isDryRun && $totalDuplicates > 0) {
            $this->info("🗑️  Cleaned {$totalDuplicates} duplicate associations");
        }

        return $totalDuplicates;
    }
}
