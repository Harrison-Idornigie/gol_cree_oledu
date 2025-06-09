<?php

namespace App\Listeners\Landlord;

use App\Events\Landlord\TenantSeedingRequested;
use App\Models\Language;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Seed Tenant Languages Listener
 * 
 * Handles seeding of default languages for new tenants.
 * This listener is modular and can be easily extended or replaced.
 */
class SeedTenantLanguages
{
    /**
     * Handle the event.
     */
    public function handle(TenantSeedingRequested $event): void
    {
        // Skip if languages seeding is disabled
        if (!$event->shouldSeed('languages')) {
            return;
        }

        $tenant = $event->tenant;

        try {
            // Set tenant context
            app()->instance('current_tenant', $tenant);

            // Seed default languages
            $this->seedLanguages($tenant);

            Log::info('Tenant languages seeded successfully', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name
            ]);

        } catch (Exception $e) {
            Log::error('Failed to seed tenant languages', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Don't throw - let other seeders continue
        }
    }

    /**
     * Seed languages for the tenant
     */
    protected function seedLanguages($tenant): void
    {
        $languages = config('tenant.default_languages', []);

        foreach ($languages as $languageData) {
            Language::firstOrCreate([
                'code' => $languageData['code'],
                'tenant_id' => $tenant->id
            ], [
                'name' => $languageData['name'],
                'flag' => $languageData['flag'],
                'is_active' => $languageData['is_active'],
            ]);
        }
    }
}
