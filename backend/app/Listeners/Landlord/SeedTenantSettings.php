<?php

namespace App\Listeners\Landlord;

use App\Events\Landlord\TenantSeedingRequested;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Seed Tenant Settings Listener
 * 
 * Handles seeding of default settings and configurations for new tenants.
 * This listener sets up feature flags, preferences, and other tenant-specific settings.
 */
class SeedTenantSettings
{
    /**
     * Handle the event.
     */
    public function handle(TenantSeedingRequested $event): void
    {
        // Skip if settings seeding is disabled
        if (!$event->shouldSeed('settings')) {
            return;
        }

        $tenant = $event->tenant;

        try {
            // Set tenant context
            app()->instance('current_tenant', $tenant);

            // Seed default settings
            $this->seedSettings($tenant);

            Log::info('Tenant settings seeded successfully', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name
            ]);

        } catch (Exception $e) {
            Log::error('Failed to seed tenant settings', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Don't throw - let other seeders continue
        }
    }

    /**
     * Seed default settings for the tenant
     */
    protected function seedSettings($tenant): void
    {
        $defaultFeatures = config('tenant.default_features', []);
        $defaultSettings = config('tenant.default_settings', []);

        // Merge existing settings with defaults (don't override existing)
        $currentSettings = $tenant->settings ?? [];
        
        $newSettings = array_merge([
            'features' => $defaultFeatures,
            'preferences' => [
                'default_interface_language' => 'en',
                'allow_student_registration' => true,
                'require_email_verification' => true,
                'enable_progress_tracking' => true,
                'enable_leaderboards' => true,
                'enable_achievements' => true,
                'max_students_per_class' => 50,
                'session_timeout_minutes' => 120,
            ],
            'branding' => [
                'primary_color' => '#3B82F6',
                'secondary_color' => '#10B981',
                'logo_url' => null,
                'custom_css' => null,
            ],
            'notifications' => [
                'email_notifications' => true,
                'progress_reports' => true,
                'achievement_notifications' => true,
                'weekly_summaries' => true,
            ],
            'content' => [
                'default_exercise_types' => [
                    'multiple_choice',
                    'fill_in_blank',
                    'translation',
                    'listening',
                    'speaking'
                ],
                'enable_custom_exercises' => true,
                'enable_ai_generated_content' => false,
            ]
        ], $defaultSettings, $currentSettings);

        // Update tenant settings
        $tenant->update(['settings' => $newSettings]);
    }
}
