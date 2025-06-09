<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantResolutionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantMiddleware
{
    /**
     * Tenant resolution service
     */
    protected TenantResolutionService $tenantResolver;

    /**
     * Constructor
     */
    public function __construct(TenantResolutionService $tenantResolver)
    {
        $this->tenantResolver = $tenantResolver;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        // Resolve tenant using multiple methods
        $tenant = $this->tenantResolver->resolve($request, $user);

        if (!$tenant) {
            return response()->json([
                'message' => 'Unable to identify tenant. Please check your domain or contact support.',
                'code' => 'TENANT_NOT_FOUND'
            ], 404);
        }

        // Check if tenant is active
        if (!$tenant->isActive()) {
            return response()->json([
                'message' => 'This tenant is currently inactive. Please contact support.',
                'code' => 'TENANT_INACTIVE'
            ], 403);
        }

        // For authenticated users, validate tenant access
        if ($user) {
            $hasAccess = $this->validateUserTenantAccess($user, $tenant);
            if (!$hasAccess) {
                return response()->json([
                    'message' => 'You do not have access to this tenant.',
                    'code' => 'TENANT_ACCESS_DENIED'
                ], 403);
            }
        }

        // Set current tenant context
        $this->setTenantContext($request, $tenant);

        return $next($request);
    }

    /**
     * Validate if user has access to the tenant
     */
    private function validateUserTenantAccess($user, Tenant $tenant): bool
    {
        // Super admins can access any tenant
        if ($user->hasRole('super-admin')) {
            return true;
        }

        // Regular users must belong to the tenant
        return $user->tenant_id === $tenant->id;
    }

    /**
     * Set tenant context for the request
     */
    private function setTenantContext(Request $request, Tenant $tenant): void
    {
        // Set current tenant in session
        session(['current_tenant_id' => $tenant->id]);

        // Set tenant in request attributes for easy access
        $request->attributes->set('current_tenant', $tenant);

        // Set tenant in app container for global access
        app()->instance('current_tenant', $tenant);

        // Set tenant ID in config for database scoping
        config(['app.current_tenant_id' => $tenant->id]);
    }
}
