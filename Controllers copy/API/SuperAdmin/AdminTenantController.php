<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\Landlord\Tenant;
use App\Services\Landlord\TenantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Exception;

class AdminTenantController extends BaseAPIController
{
    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Display a listing of tenants (Super Admin only).
     */
    public function index(Request $request)
    {
        $this->authorize('manage-tenants');

        $query = Tenant::query();

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%")
                  ->orWhere('domain', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $tenants = $query->withCount(['users', 'learningPaths', 'languages'])
                        ->paginate($request->get('per_page', 15));

        return $this->sendResponse($tenants, 'Tenants retrieved successfully.');
    }

    /**
     * Store a newly created tenant.
     */
    public function store(Request $request)
    {
        $this->authorize('manage-tenants');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:tenants,slug',
            'domain' => 'nullable|string|max:255|unique:tenants,domain',
            'description' => 'nullable|string',
            'settings' => 'nullable|array',
            'contact_info' => 'nullable|array',
            'trial_ends_at' => 'nullable|date',
            'subscription_ends_at' => 'nullable|date',

            // Admin user creation - check central_users table since tenant DB doesn't exist yet
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|unique:central_users,email',
            'admin_password' => 'required|string|min:8',
        ]);

        try {
            // Prepare tenant data
            $tenantData = [
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'domain' => $validated['domain'],
                'description' => $validated['description'],
                'settings' => $validated['settings'] ?? [],
                'contact_info' => $validated['contact_info'] ?? [],
                'trial_ends_at' => $validated['trial_ends_at'],
                'subscription_ends_at' => $validated['subscription_ends_at'],
            ];

            // Prepare admin user data
            $adminData = [
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'password' => $validated['admin_password'],
            ];

            // Use TenantService to create tenant with proper database initialization
            $result = $this->tenantService->createTenant($tenantData, $adminData);

            return $this->sendResponse($result, 'Tenant created successfully.');

        } catch (Exception $e) {
            return $this->sendError('Failed to create tenant.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified tenant.
     */
    public function show(Tenant $tenant)
    {
        $this->authorize('view-tenant', $tenant);

        $tenant->load([
            'users.roles',
            'learningPaths' => function ($query) {
                $query->withCount(['units', 'progress']);
            },
            'languages',
            'roles'
        ]);

        $statistics = $tenant->getStatistics();

        return $this->sendResponse([
            'tenant' => $tenant,
            'statistics' => $statistics,
        ], 'Tenant retrieved successfully.');
    }

    /**
     * Update the specified tenant.
     */
    public function update(Request $request, Tenant $tenant)
    {
        $this->authorize('manage-tenant', $tenant);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('tenants')->ignore($tenant->id)],
            'domain' => ['nullable', 'string', 'max:255', Rule::unique('tenants')->ignore($tenant->id)],
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive,suspended',
            'settings' => 'nullable|array',
            'contact_info' => 'nullable|array',
            'trial_ends_at' => 'nullable|date',
            'subscription_ends_at' => 'nullable|date',
        ]);

        $tenant->update($validated);

        return $this->sendResponse($tenant, 'Tenant updated successfully.');
    }

    /**
     * Remove the specified tenant.
     */
    public function destroy(Tenant $tenant)
    {
        $this->authorize('manage-tenants');

        if ($tenant->users()->count() > 0) {
            return $this->sendError('Cannot delete tenant with existing users.');
        }

        $tenant->delete();

        return $this->sendResponse(null, 'Tenant deleted successfully.');
    }

    /**
     * Get tenant statistics.
     */
    public function statistics(Tenant $tenant)
    {
        $this->authorize('view-tenant', $tenant);

        $statistics = $tenant->getStatistics();

        // Add more detailed statistics
        $statistics['recent_activity'] = $tenant->auditLogs()
            ->latest()
            ->limit(10)
            ->get();

        $statistics['user_growth'] = $tenant->users()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $this->sendResponse($statistics, 'Tenant statistics retrieved successfully.');
    }

    /**
     * Switch tenant context (Super Admin only).
     */
    public function switchTenant(Request $request, Tenant $tenant)
    {
        $this->authorize('manage-tenants');

        if (!$tenant->isActive()) {
            return $this->sendError('Cannot switch to inactive tenant.');
        }

        session(['current_tenant_id' => $tenant->id]);

        return $this->sendResponse([
            'tenant' => $tenant,
            'message' => "Switched to tenant: {$tenant->name}"
        ], 'Tenant context switched successfully.');
    }
}
