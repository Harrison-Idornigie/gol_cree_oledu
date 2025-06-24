<?php

namespace App\Http\Controllers\API\Tenant\Admin;

use App\Http\Controllers\API\BaseAPIController;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Services\Tenants\Admin\TenantSettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

/**
 * Tenant Admin Settings Controller
 * 
 * Handles tenant-specific settings and configuration management.
 * Access Level: Tenant Admin
 * Scope: Tenant-specific
 * 
 * This controller manages tenant settings, branding, and feature
 * configurations within the tenant scope.
 */
class TenantAdminSettingsController extends BaseAPIController
{
    use BelongsToTenant;

    protected TenantSettingsService $tenantSettingsService;

    /**
     * Constructor - Apply tenant admin middleware
     */
    public function __construct(TenantSettingsService $tenantSettingsService)
    {
        $this->tenantSettingsService = $tenantSettingsService;
        $this->middleware(['auth:api', 'role:tenant-admin']);
    }

    /**
     * Get tenant settings.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getTenantSettings(Request $request): JsonResponse
    {
        try {
            $settings = $this->tenantSettingsService->getTenantSettings();

            return $this->sendResponse($settings, 'Tenant settings retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve tenant settings', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update tenant settings.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function updateTenantSettings(Request $request): JsonResponse
    {
        $request->validate([
            'general' => 'nullable|array',
            'general.tenant_name' => 'nullable|string|max:255',
            'general.description' => 'nullable|string|max:1000',
            'general.timezone' => 'nullable|string|max:50',
            'general.locale' => 'nullable|string|size:2',
            'learning' => 'nullable|array',
            'learning.default_language' => 'nullable|string|size:2',
            'learning.sequential_learning' => 'nullable|boolean',
            'learning.allow_skipping' => 'nullable|boolean',
            'notifications' => 'nullable|array',
            'notifications.email_enabled' => 'nullable|boolean',
            'notifications.push_enabled' => 'nullable|boolean',
            'integrations' => 'nullable|array'
        ]);

        try {
            $user = $request->user();
            $settings = $this->tenantSettingsService->updateTenantSettings($request->all(), $user);

            return $this->sendResponse($settings, 'Tenant settings updated successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to update tenant settings', ['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Get tenant branding settings.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getBranding(Request $request): JsonResponse
    {
        try {
            $branding = $this->tenantSettingsService->getBrandingSettings();

            return $this->sendResponse($branding, 'Branding settings retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve branding settings', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update tenant branding settings.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function updateBranding(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => 'nullable|array',
            'logo.primary_logo' => 'nullable|file|image|max:2048',
            'logo.secondary_logo' => 'nullable|file|image|max:2048',
            'logo.favicon' => 'nullable|file|image|max:512',
            'colors' => 'nullable|array',
            'colors.primary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'colors.secondary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'styling' => 'nullable|array',
            'domain' => 'nullable|array',
            'domain.custom_domain' => 'nullable|string|max:255',
            'white_label' => 'nullable|array'
        ]);

        try {
            $user = $request->user();
            $branding = $this->tenantSettingsService->updateBrandingSettings($request->all(), $user);

            return $this->sendResponse($branding, 'Branding settings updated successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to update branding settings', ['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Get feature settings for tenant.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getFeatureSettings(Request $request): JsonResponse
    {
        try {
            $features = $this->tenantSettingsService->getFeatureSettings();

            return $this->sendResponse($features, 'Feature settings retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve feature settings', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update feature settings for tenant.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function updateFeatureSettings(Request $request): JsonResponse
    {
        $request->validate([
            'core_features' => 'nullable|array',
            'advanced_features' => 'nullable|array',
            'limits' => 'nullable|array',
            'limits.max_users' => 'nullable|integer|min:1',
            'limits.max_languages' => 'nullable|integer|min:1',
            'limits.storage_mb' => 'nullable|integer|min:100',
            'integrations' => 'nullable|array'
        ]);

        try {
            $user = $request->user();
            $features = $this->tenantSettingsService->updateFeatureSettings($request->all(), $user);

            return $this->sendResponse($features, 'Feature settings updated successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to update feature settings', ['error' => $e->getMessage()], 422);
        }
    }
}
