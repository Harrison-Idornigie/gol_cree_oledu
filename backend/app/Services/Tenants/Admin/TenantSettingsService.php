<?php

namespace App\Services\Tenants\Admin;

use App\Models\Tenants\TenantSetting;
use App\Models\Tenants\User;
use App\Models\Tenants\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Exception;

/**
 * Tenant Settings Service
 * 
 * Handles tenant-specific settings, branding, and feature management.
 */
class TenantSettingsService
{
    public const AUDIT_AREA = 'tenant_settings';

    /**
     * Get all tenant settings.
     */
    public function getTenantSettings(): array
    {
        $cacheKey = 'tenant_settings_' . tenant('id');

        return Cache::remember($cacheKey, 3600, function () {
            // This would typically come from a TenantSettings model
            return [
                'general' => [
                    'tenant_name' => tenant('name') ?? 'Learning Platform',
                    'description' => 'Language Learning Platform',
                    'timezone' => 'UTC',
                    'locale' => 'en',
                    'date_format' => 'Y-m-d',
                    'time_format' => 'H:i:s'
                ],
                'learning' => [
                    'default_language' => 'en',
                    'sequential_learning' => true,
                    'allow_skipping' => false,
                    'auto_progress' => true,
                    'difficulty_adaptation' => true,
                    'spaced_repetition' => true
                ],
                'notifications' => [
                    'email_enabled' => true,
                    'push_enabled' => true,
                    'reminder_frequency' => 'daily',
                    'progress_reports' => true,
                    'achievement_alerts' => true
                ],
                'integrations' => [
                    'analytics_enabled' => true,
                    'third_party_apis' => [],
                    'export_enabled' => true,
                    'webhook_url' => null
                ]
            ];
        });
    }

    /**
     * Update tenant settings.
     */
    public function updateTenantSettings(array $settings, User $user): array
    {
        return DB::transaction(function () use ($settings, $user) {
            // Validate settings structure
            $this->validateSettings($settings);

            // Update each section
            $updatedSettings = [];

            if (isset($settings['general'])) {
                $updatedSettings['general'] = $this->updateGeneralSettings($settings['general'], $user);
            }

            if (isset($settings['learning'])) {
                $updatedSettings['learning'] = $this->updateLearningSettings($settings['learning'], $user);
            }

            if (isset($settings['notifications'])) {
                $updatedSettings['notifications'] = $this->updateNotificationSettings($settings['notifications'], $user);
            }

            if (isset($settings['integrations'])) {
                $updatedSettings['integrations'] = $this->updateIntegrationSettings($settings['integrations'], $user);
            }

            // Clear cache
            $this->clearSettingsCache();

            // Log the changes
            $this->createAuditLog('settings_updated', null, [
                'updated_sections' => array_keys($updatedSettings),
                'user_id' => $user->id
            ]);

            return $this->getTenantSettings();
        });
    }

    /**
     * Get tenant branding settings.
     */
    public function getBrandingSettings(): array
    {
        $cacheKey = 'tenant_branding_' . tenant('id');

        return Cache::remember($cacheKey, 3600, function () {
            return [
                'logo' => [
                    'primary_logo_url' => null,
                    'secondary_logo_url' => null,
                    'favicon_url' => null
                ],
                'colors' => [
                    'primary_color' => '#3B82F6',
                    'secondary_color' => '#10B981',
                    'accent_color' => '#F59E0B',
                    'background_color' => '#FFFFFF',
                    'text_color' => '#1F2937'
                ],
                'styling' => [
                    'font_family' => 'Inter',
                    'border_radius' => '8px',
                    'shadow_style' => 'subtle'
                ],
                'domain' => [
                    'custom_domain' => null,
                    'subdomain' => tenant('subdomain') ?? 'learning',
                    'ssl_enabled' => true
                ],
                'white_label' => [
                    'hide_powered_by' => false,
                    'custom_footer' => null,
                    'custom_support_email' => null
                ]
            ];
        });
    }

    /**
     * Update tenant branding settings.
     */
    public function updateBrandingSettings(array $branding, User $user): array
    {
        return DB::transaction(function () use ($branding, $user) {
            // Handle logo uploads if provided
            if (isset($branding['logo'])) {
                $branding['logo'] = $this->handleLogoUploads($branding['logo']);
            }

            // Validate color formats
            if (isset($branding['colors'])) {
                $this->validateColors($branding['colors']);
            }

            // Save branding settings (this would typically save to a model)
            // For now, we'll simulate this

            // Clear cache
            $this->clearBrandingCache();

            // Log the changes
            $this->createAuditLog('branding_updated', null, [
                'updated_sections' => array_keys($branding),
                'user_id' => $user->id
            ]);

            return $this->getBrandingSettings();
        });
    }

    /**
     * Get tenant feature settings.
     */
    public function getFeatureSettings(): array
    {
        $cacheKey = 'tenant_features_' . tenant('id');

        return Cache::remember($cacheKey, 3600, function () {
            return [
                'core_features' => [
                    'lessons' => true,
                    'exercises' => true,
                    'vocabulary' => true,
                    'progress_tracking' => true,
                    'gamification' => true
                ],
                'advanced_features' => [
                    'ai_recommendations' => false,
                    'speech_recognition' => false,
                    'live_sessions' => false,
                    'peer_collaboration' => false,
                    'advanced_analytics' => false
                ],
                'limits' => [
                    'max_users' => 100,
                    'max_languages' => 5,
                    'max_learning_paths' => 10,
                    'storage_mb' => 1000,
                    'api_calls_per_month' => 10000
                ],
                'integrations' => [
                    'google_classroom' => false,
                    'microsoft_teams' => false,
                    'zoom' => false,
                    'slack' => false,
                    'lms_integration' => false
                ]
            ];
        });
    }

    /**
     * Update tenant feature settings.
     */
    public function updateFeatureSettings(array $features, User $user): array
    {
        return DB::transaction(function () use ($features, $user) {
            // Validate feature toggles and limits
            $this->validateFeatureSettings($features);

            // Save feature settings (this would typically save to a model)
            // For now, we'll simulate this

            // Clear cache
            $this->clearFeatureCache();

            // Log the changes
            $this->createAuditLog('features_updated', null, [
                'updated_features' => array_keys($features),
                'user_id' => $user->id
            ]);

            return $this->getFeatureSettings();
        });
    }

    /**
     * Validate settings structure.
     */
    private function validateSettings(array $settings): void
    {
        $allowedSections = ['general', 'learning', 'notifications', 'integrations'];

        foreach (array_keys($settings) as $section) {
            if (!in_array($section, $allowedSections)) {
                throw new Exception("Invalid settings section: {$section}");
            }
        }
    }

    /**
     * Update general settings.
     */
    private function updateGeneralSettings(array $general, User $user): array
    {
        // Validate and save general settings
        return $general;
    }

    /**
     * Update learning settings.
     */
    private function updateLearningSettings(array $learning, User $user): array
    {
        // Validate and save learning settings
        return $learning;
    }

    /**
     * Update notification settings.
     */
    private function updateNotificationSettings(array $notifications, User $user): array
    {
        // Validate and save notification settings
        return $notifications;
    }

    /**
     * Update integration settings.
     */
    private function updateIntegrationSettings(array $integrations, User $user): array
    {
        // Validate and save integration settings
        return $integrations;
    }

    /**
     * Handle logo file uploads.
     */
    private function handleLogoUploads(array $logoData): array
    {
        // Handle file uploads for logos
        // This would process uploaded files and return URLs
        return $logoData;
    }

    /**
     * Validate color format.
     */
    private function validateColors(array $colors): void
    {
        foreach ($colors as $color) {
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                throw new Exception("Invalid color format: {$color}");
            }
        }
    }

    /**
     * Validate feature settings.
     */
    private function validateFeatureSettings(array $features): void
    {
        // Validate feature toggles and limits
        if (isset($features['limits']['max_users']) && $features['limits']['max_users'] < 1) {
            throw new Exception("Max users must be at least 1");
        }
    }

    /**
     * Clear settings cache.
     */
    private function clearSettingsCache(): void
    {
        Cache::forget('tenant_settings_' . tenant('id'));
    }

    /**
     * Clear branding cache.
     */
    private function clearBrandingCache(): void
    {
        Cache::forget('tenant_branding_' . tenant('id'));
    }

    /**
     * Clear feature cache.
     */
    private function clearFeatureCache(): void
    {
        Cache::forget('tenant_features_' . tenant('id'));
    }

    /**
     * Create audit log entry.
     */
    private function createAuditLog(string $action, ?int $recordId, array $metadata = []): void
    {
        AuditLog::create([
            'area' => self::AUDIT_AREA,
            'action' => $action,
            'record_id' => $recordId,
            'user_id' => Auth::id(),
            'metadata' => $metadata
        ]);
    }
}
