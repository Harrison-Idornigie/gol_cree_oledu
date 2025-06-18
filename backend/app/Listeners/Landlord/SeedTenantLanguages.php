<?php

namespace App\Listeners\Landlord;

use App\Events\Landlord\TenantSeedingRequested;
use App\Models\Tenants\Language;
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
            // Switch to tenant context for all database operations
            $tenant->run(function () use ($tenant) {
                // Seed default languages
                $this->seedLanguages($tenant);
            });

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
        // Define the default languages (same as LanguageSeeder)
        $languages = [
            [
                'code' => 'en',
                'name' => 'English',
                'native_name' => 'English',
                'is_active' => true,
            ],
            [
                'code' => 'es',
                'name' => 'Spanish',
                'native_name' => 'Español',
                'is_active' => true,
            ],
            [
                'code' => 'crk',
                'name' => 'Plains Cree',
                'native_name' => 'nēhiyawēwin',
                'is_active' => true,
            ],
        ];

        foreach ($languages as $languageData) {
            $language = Language::firstOrCreate([
                'code' => $languageData['code']
            ], [
                'name' => $languageData['name'],
                'native_name' => $languageData['native_name'],
                'is_active' => $languageData['is_active'],
                'tenant_id' => $tenant->id
            ]);

            Log::info('Language seeded for tenant', [
                'tenant_id' => $tenant->id,
                'language_code' => $language->code,
                'language_name' => $language->name,
                'created' => $language->wasRecentlyCreated
            ]);
        }
    }
}
