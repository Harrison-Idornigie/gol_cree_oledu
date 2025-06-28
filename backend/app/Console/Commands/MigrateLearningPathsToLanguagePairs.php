<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenants\LearningPath;
use App\Models\Tenants\Language;
use App\Models\Tenants\LanguagePair;
use Stancl\Tenancy\Facades\Tenancy;

class MigrateLearningPathsToLanguagePairs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'learning-paths:migrate-to-language-pairs 
                            {--tenant= : Specific tenant to migrate (optional)}
                            {--dry-run : Show what would be migrated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing learning paths to use language pairs instead of single languages';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $specificTenant = $this->option('tenant');

        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
        }

        if ($specificTenant) {
            $this->migrateTenant($specificTenant, $isDryRun);
        } else {
            $this->migrateAllTenants($isDryRun);
        }

        $this->info('✅ Migration completed successfully!');
    }

    /**
     * Migrate all tenants
     */
    private function migrateAllTenants(bool $isDryRun): void
    {
        $tenants = \App\Models\Landlord\Tenant::all();
        
        $this->info("Found {$tenants->count()} tenants to migrate");

        foreach ($tenants as $tenant) {
            $this->migrateTenant($tenant->slug, $isDryRun);
        }
    }

    /**
     * Migrate a specific tenant
     */
    private function migrateTenant(string $tenantSlug, bool $isDryRun): void
    {
        $tenant = \App\Models\Landlord\Tenant::where('slug', $tenantSlug)->first();
        
        if (!$tenant) {
            $this->error("Tenant '{$tenantSlug}' not found");
            return;
        }

        $this->info("🏢 Migrating tenant: {$tenant->name} ({$tenant->slug})");

        Tenancy::initialize($tenant);

        try {
            $this->migrateLearningPathsInCurrentTenant($isDryRun);
        } catch (\Exception $e) {
            $this->error("Error migrating tenant {$tenant->slug}: " . $e->getMessage());
        } finally {
            Tenancy::end();
        }
    }

    /**
     * Migrate learning paths in the current tenant context
     */
    private function migrateLearningPathsInCurrentTenant(bool $isDryRun): void
    {
        // Get learning paths that need migration
        $learningPaths = LearningPath::whereNotNull('language_id')
            ->whereNull('language_pair_id')
            ->with('language')
            ->get();

        if ($learningPaths->isEmpty()) {
            $this->info('   ✓ No learning paths need migration');
            return;
        }

        $this->info("   📚 Found {$learningPaths->count()} learning paths to migrate");

        // Get or create English as default source language
        $english = Language::where('code', 'en')->first();
        
        if (!$english) {
            if ($isDryRun) {
                $this->warn('   ⚠️  Would create English language');
            } else {
                $english = Language::create([
                    'code' => 'en',
                    'name' => 'English',
                    'native_name' => 'English',
                    'is_active' => true,
                ]);
                $this->info('   ✓ Created English language');
            }
        }

        $migratedCount = 0;
        $createdPairs = 0;

        foreach ($learningPaths as $learningPath) {
            if (!$learningPath->language) {
                $this->warn("   ⚠️  Learning path '{$learningPath->title}' has invalid language_id");
                continue;
            }

            // Get or create language pair
            $languagePair = null;
            
            if (!$isDryRun && $english) {
                $languagePair = LanguagePair::firstOrCreate([
                    'source_language_id' => $english->id,
                    'target_language_id' => $learningPath->language_id,
                ], [
                    'is_active' => true,
                ]);

                if ($languagePair->wasRecentlyCreated) {
                    $createdPairs++;
                }

                // Update learning path
                $learningPath->update([
                    'language_pair_id' => $languagePair->id,
                ]);
            }

            if ($isDryRun) {
                $this->line("   📖 Would migrate: '{$learningPath->title}' (English → {$learningPath->language->name})");
            } else {
                $this->line("   ✓ Migrated: '{$learningPath->title}' (English → {$learningPath->language->name})");
            }

            $migratedCount++;
        }

        if (!$isDryRun) {
            $this->info("   ✅ Migrated {$migratedCount} learning paths");
            $this->info("   ✅ Created {$createdPairs} new language pairs");
        } else {
            $this->info("   🔍 Would migrate {$migratedCount} learning paths");
        }
    }
}
