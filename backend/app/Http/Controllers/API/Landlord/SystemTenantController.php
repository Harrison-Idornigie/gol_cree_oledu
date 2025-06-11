<?php

namespace App\Http\Controllers\API\Landlord;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\Landlord\Tenant;
use App\Services\Landlord\TenantService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Exception;

/**
 * System Tenant Controller
 * 
 * Handles system-wide tenant management operations.
 * Access Level: Super Admin only
 * Scope: Cross-tenant (system-wide)
 * 
 * This controller manages CRUD operations for tenants across the entire system,
 * including tenant creation, updates, suspension, and statistics.
 */
class SystemTenantController extends BaseAPIController
{
    /**
     * Tenant service
     */
    protected TenantService $tenantService;

    /**
     * Constructor
     */
    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Display a listing of all tenants in the system.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $tenants = $this->tenantService->getTenants($request);
            return $this->sendResponse($tenants, 'Tenants retrieved successfully.');

        } catch (Exception $e) {
            return $this->sendError('Failed to retrieve tenants: ' . $e->getMessage(), [], 422);
        }
    }

    /**
     * Store a newly created tenant.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Tenant data
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:tenants,slug',
            'domain' => 'nullable|string|max:255|unique:tenants,domain',
            'subdomain' => 'nullable|string|max:255|unique:tenants,subdomain',
            'custom_domain' => 'nullable|string|max:255|unique:tenants,custom_domain',
            'description' => 'nullable|string|max:1000',
            'settings' => 'nullable|array',
            'contact_info' => 'nullable|array',
            'trial_ends_at' => 'nullable|date|after:today',
            'subscription_ends_at' => 'nullable|date|after:trial_ends_at',
            'status' => 'nullable|in:active,inactive,suspended',

            // Admin user data - check central_users table since tenant DB doesn't exist yet
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|max:255|unique:central_users,email',
            'admin_password' => 'required|string|min:8|confirmed',
            'admin_interface_language' => 'nullable|string|in:en,es,fr,de',
        ]);

        try {
            $tenantData = [
                'name' => $validated['name'],
                'slug' => $validated['slug'] ?? null,
                'domain' => $validated['domain'] ?? null,
                'subdomain' => $validated['subdomain'] ?? null,
                'custom_domain' => $validated['custom_domain'] ?? null,
                'description' => $validated['description'] ?? null,
                'settings' => $validated['settings'] ?? [],
                'contact_info' => $validated['contact_info'] ?? [],
                'trial_ends_at' => $validated['trial_ends_at'] ?? null,
                'subscription_ends_at' => $validated['subscription_ends_at'] ?? null,
                'status' => $validated['status'] ?? 'active',
            ];

            $adminData = [
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'password' => $validated['admin_password'],
                'interface_language' => $validated['admin_interface_language'] ?? 'en',
            ];

            $tenant = $this->tenantService->createTenant($tenantData, $adminData);

            return $this->sendCreatedResponse($tenant, 'Tenant created successfully.');

        } catch (Exception $e) {
            return $this->sendError('Failed to create tenant: ' . $e->getMessage(), [], 422);
        }
    }

    /**
     * Display the specified tenant.
     *
     * @param Tenant $tenant
     * @return JsonResponse
     */
    public function show(Tenant $tenant): JsonResponse
    {
        // Load domains (landlord relationship)
        $tenant->load(['domains']);

        // Add statistics (calculated from tenant database)
        $tenant->statistics = $tenant->getStatistics();

        return $this->sendResponse($tenant, 'Tenant retrieved successfully.');
    }

    /**
     * Update the specified tenant.
     *
     * @param Request $request
     * @param Tenant $tenant
     * @return JsonResponse
     */
    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('tenants')->ignore($tenant->id)],
            'domain' => ['nullable', 'string', 'max:255', Rule::unique('tenants')->ignore($tenant->id)],
            'subdomain' => ['nullable', 'string', 'max:255', Rule::unique('tenants')->ignore($tenant->id)],
            'custom_domain' => ['nullable', 'string', 'max:255', Rule::unique('tenants')->ignore($tenant->id)],
            'description' => 'nullable|string|max:1000',
            'settings' => 'nullable|array',
            'contact_info' => 'nullable|array',
            'trial_ends_at' => 'nullable|date',
            'subscription_ends_at' => 'nullable|date',
            'status' => 'required|in:active,inactive,suspended',
        ]);

        try {
            $updatedTenant = $this->tenantService->updateTenant($tenant, $validated);
            return $this->sendResponse($updatedTenant, 'Tenant updated successfully.');

        } catch (Exception $e) {
            return $this->sendError('Failed to update tenant: ' . $e->getMessage(), [], 422);
        }
    }

    /**
     * Remove the specified tenant from the system.
     *
     * @param Tenant $tenant
     * @return JsonResponse
     */
    public function destroy(Tenant $tenant): JsonResponse
    {
        try {
            $this->tenantService->deleteTenant($tenant);
            return $this->sendNoContentResponse();

        } catch (Exception $e) {
            return $this->sendError('Failed to delete tenant: ' . $e->getMessage(), [], 422);
        }
    }

    /**
     * Update tenant status (active, inactive, suspended).
     *
     * @param Request $request
     * @param Tenant $tenant
     * @return JsonResponse
     */
    public function updateStatus(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:active,inactive,suspended',
            'reason' => 'nullable|string|max:500'
        ]);

        try {
            $tenant->update(['status' => $validated['status']]);

            return $this->sendResponse($tenant, 'Tenant status updated successfully.');

        } catch (Exception $e) {
            return $this->sendError('Failed to update tenant status: ' . $e->getMessage(), [], 422);
        }
    }

    /**
     * Get tenant statistics and metrics.
     *
     * @param Tenant $tenant
     * @return JsonResponse
     */
    public function statistics(Tenant $tenant): JsonResponse
    {
        try {
            $statistics = $tenant->getStatistics();

            // Add additional metrics
            $statistics['created_at'] = $tenant->created_at;
            $statistics['trial_ends_at'] = $tenant->trial_ends_at;
            $statistics['subscription_ends_at'] = $tenant->subscription_ends_at;
            $statistics['status'] = $tenant->status;

            return $this->sendResponse($statistics, 'Tenant statistics retrieved successfully.');

        } catch (Exception $e) {
            return $this->sendError('Failed to retrieve tenant statistics: ' . $e->getMessage(), [], 422);
        }
    }

    /**
     * Reset tenant trial period.
     *
     * @param Request $request
     * @param Tenant $tenant
     * @return JsonResponse
     */
    public function resetTrial(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'trial_days' => 'required|integer|min:1|max:365'
        ]);

        try {
            $tenant->update([
                'trial_ends_at' => now()->addDays($validated['trial_days'])
            ]);

            return $this->sendResponse($tenant, 'Tenant trial reset successfully.');

        } catch (Exception $e) {
            return $this->sendError('Failed to reset tenant trial: ' . $e->getMessage(), [], 422);
        }
    }

    /**
     * Extend tenant subscription.
     *
     * @param Request $request
     * @param Tenant $tenant
     * @return JsonResponse
     */
    public function extendSubscription(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'extend_days' => 'required|integer|min:1|max:3650', // Up to 10 years
            'extend_months' => 'nullable|integer|min:1|max:120' // Up to 10 years
        ]);

        try {
            $currentEnd = $tenant->subscription_ends_at ?? now();

            if (isset($validated['extend_days'])) {
                $newEnd = $currentEnd->addDays($validated['extend_days']);
            } else {
                $newEnd = $currentEnd->addMonths($validated['extend_months']);
            }

            $tenant->update(['subscription_ends_at' => $newEnd]);

            return $this->sendResponse($tenant, 'Tenant subscription extended successfully.');

        } catch (Exception $e) {
            return $this->sendError('Failed to extend tenant subscription: ' . $e->getMessage(), [], 422);
        }
    }
}
