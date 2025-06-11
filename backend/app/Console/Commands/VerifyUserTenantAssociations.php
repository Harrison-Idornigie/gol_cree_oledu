<?php

namespace App\Console\Commands;

use App\Models\Landlord\Tenant;
use App\Models\Landlord\UserTenantAssociation;
use App\Services\Auth\UserTenantSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyUserTenantAssociations extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenant:verify-user-associations 
                            {--email= : Verify specific user by email}
                            {--tenant= : Verify specific tenant by slug}
                            {--fix : Automatically fix found inconsistencies}
                            {--sample=100 : Number of random users to verify}';

    /**
     * The console command description.
     */
    protected $description = 'Verify integrity of user_tenant_associations against actual tenant data';

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
        $this->info('🔍 Starting User Tenant Association Verification');
        $this->newLine();

        $specificEmail = $this->option('email');
        $specificTenant = $this->option('tenant');
        $autoFix = $this->option('fix');
        $sampleSize = (int) $this->option('sample');

        if ($specificEmail) {
            return $this->verifySpecificUser($specificEmail, $autoFix);
        }

        if ($specificTenant) {
            return $this->verifySpecificTenant($specificTenant, $autoFix);
        }

        return $this->verifyRandomSample($sampleSize, $autoFix);
    }

    protected function verifySpecificUser(string $email, bool $autoFix): int
    {
        $this->info("Verifying user: {$email}");
        
        $result = $this->syncService->verifySyncIntegrity($email);
        $this->displayUserVerification($result, $autoFix);
        
        return $result['mismatches'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function verifySpecificTenant(string $tenantSlug, bool $autoFix): int
    {
        $tenant = Tenant::where('slug', $tenantSlug)->where('status', 'active')->first();
        
        if (!$tenant) {
            $this->error("Tenant '{$tenantSlug}' not found or inactive");
            return self::FAILURE;
        }

        $this->info("Verifying tenant: {$tenantSlug}");
        
        $associations = UserTenantAssociation::forTenant($tenantSlug)->active()->get();
        $stats = ['verified' => 0, 'mismatches' => 0, 'missing' => 0, 'fixed' => 0];

        $progressBar = $this->output->createProgressBar($associations->count());

        foreach ($associations as $association) {
            $this->verifyAssociation($association, $autoFix, $stats);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->displayTenantResults($tenantSlug, $stats, $autoFix);
        
        return $stats['mismatches'] > 0 || $stats['missing'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function verifyRandomSample(int $sampleSize, bool $autoFix): int
    {
        $this->info("Verifying random sample of {$sampleSize} associations");
        
        $associations = UserTenantAssociation::active()
            ->inRandomOrder()
            ->limit($sampleSize)
            ->get();

        if ($associations->isEmpty()) {
            $this->warn('No associations found to verify');
            return self::SUCCESS;
        }

        $stats = ['verified' => 0, 'mismatches' => 0, 'missing' => 0, 'fixed' => 0];
        $progressBar = $this->output->createProgressBar($associations->count());

        foreach ($associations as $association) {
            $this->verifyAssociation($association, $autoFix, $stats);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->displaySampleResults($stats, $autoFix);
        
        return $stats['mismatches'] > 0 || $stats['missing'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function verifyAssociation(UserTenantAssociation $association, bool $autoFix, array &$stats): void
    {
        try {
            $tenant = $association->tenant;
            $actualUser = null;

            $tenant->run(function () use ($association, &$actualUser) {
                $actualUser = DB::table('users')
                    ->where('email', $association->email)
                    ->first();
            });

            if (!$actualUser) {
                $stats['missing']++;
                
                if ($autoFix) {
                    $association->delete();
                    $stats['fixed']++;
                    $this->line("🔧 Removed orphaned association: {$association->email} → {$association->tenant_slug}");
                } else {
                    $this->warn("❌ Missing user in tenant: {$association->email} → {$association->tenant_slug}");
                }
                return;
            }

            if ($association->membership !== $actualUser->membership) {
                $stats['mismatches']++;
                
                if ($autoFix) {
                    $association->update(['membership' => $actualUser->membership]);
                    $stats['fixed']++;
                    $this->line("🔧 Fixed membership mismatch: {$association->email} → {$association->tenant_slug} ({$association->membership} → {$actualUsermembership})");
                } else {
                    $this->warn("❌ Membership mismatch: {$association->email} → {$association->tenant_slug} (Central: {$association->membership}, Actual: {$actualUsermembership})");
                }
                return;
            }

            $stats['verified']++;

        } catch (\Exception $e) {
            $this->error("Failed to verify {$association->email} → {$association->tenant_slug}: {$e->getMessage()}");
        }
    }

    protected function displayUserVerification(array $result, bool $autoFix): void
    {
        $this->table(
            ['Metric', 'Value'],
            [
                ['Email', $result['email']],
                ['Central Associations', $result['central_associations']],
                ['Verified Tenants', $result['verified_tenants']],
                ['Mismatches', $result['mismatches']],
            ]
        );

        if ($result['mismatches'] > 0) {
            $this->newLine();
            $this->warn('Mismatches found:');
            foreach ($result['details'] as $detail) {
                if (!$detail['matches']) {
                    $this->line("  • {$detail['tenant_slug']}: Central={$detail['central_membership']}, Actual={$detail['actual_membership']}");
                }
            }
        }
    }

    protected function displayTenantResults(string $tenantSlug, array $stats, bool $autoFix): void
    {
        $this->info("📊 Verification Results for {$tenantSlug}:");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Verified (Correct)', $stats['verified']],
                ['Membership Mismatches', $stats['mismatches']],
                ['Missing Users', $stats['missing']],
                $autoFix ? ['Fixed', $stats['fixed']] : null,
            ]
        );
    }

    protected function displaySampleResults(array $stats, bool $autoFix): void
    {
        $this->info('📊 Sample Verification Results:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Verified (Correct)', $stats['verified']],
                ['Membership Mismatches', $stats['mismatches']],
                ['Missing Users', $stats['missing']],
                $autoFix ? ['Fixed', $stats['fixed']] : null,
            ]
        );

        $total = $stats['verified'] + $stats['mismatches'] + $stats['missing'];
        if ($total > 0) {
            $accuracy = round(($stats['verified'] / $total) * 100, 2);
            $this->info("🎯 Data Integrity: {$accuracy}%");
        }

        if (!$autoFix && ($stats['mismatches'] > 0 || $stats['missing'] > 0)) {
            $this->newLine();
            $this->info('💡 Run with --fix to automatically resolve issues');
        }
    }
}
