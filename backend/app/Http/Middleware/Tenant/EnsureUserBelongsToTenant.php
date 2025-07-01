<?php

namespace App\Http\Middleware\Tenant;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Helpers\Tenants\TenantHelper;
use App\Services\Auth\UserTenantAssociationService;
use Illuminate\Support\Facades\Log;

/**
 * Ensure User Belongs To Tenant Middleware
 * 
 * This middleware ensures that authenticated users can only access
 * tenant resources they actually belong to. It prevents cross-tenant
 * access by validating that the authenticated user has a valid
 * association with the tenant specified in the URL.
 */
class EnsureUserBelongsToTenant
{
    protected UserTenantAssociationService $userTenantService;

    public function __construct(UserTenantAssociationService $userTenantService)
    {
        $this->userTenantService = $userTenantService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('tenant');
        
        // Skip check if user is not authenticated
        if (!$user) {
            return $next($request);
        }

        // Get current tenant from context
        $tenant = TenantHelper::current();
        
        if (!$tenant) {
            return $this->unauthorizedResponse('No tenant context available');
        }

        // Super admins can access any tenant
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Check if user belongs to this tenant
        $userInTenant = $this->userTenantService->getUserInTenant($user->email, $tenant->slug);
        
        if (!$userInTenant) {
            Log::warning('User attempted to access tenant they do not belong to', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_membership' => $user->membership,
                'attempted_tenant' => $tenant->slug,
                'user_tenant_id' => $user->tenant_id,
                'route' => $request->route()?->getName(),
                'url' => $request->url(),
                'timestamp' => now()->toISOString()
            ]);

            return $this->unauthorizedResponse('Access denied. You do not have permission to access this tenant.');
        }

        // Verify that the user's membership matches what's expected for this tenant
        $expectedMembership = $userInTenant['membership'] ?? null;
        if ($expectedMembership && $user->membership !== $expectedMembership) {
            Log::warning('User membership mismatch for tenant access', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'current_membership' => $user->membership,
                'expected_membership' => $expectedMembership,
                'tenant' => $tenant->slug,
                'route' => $request->route()?->getName(),
                'timestamp' => now()->toISOString()
            ]);

            return $this->unauthorizedResponse('Access denied. Your membership level does not match this tenant.');
        }

        return $next($request);
    }

    /**
     * Return unauthorized response
     */
    protected function unauthorizedResponse(string $message): Response
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => 'tenant_access_denied'
        ], 404); // Return 404 to hide tenant existence from unauthorized users
    }
}
