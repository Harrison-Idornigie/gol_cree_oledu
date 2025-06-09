<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Super admins can access any tenant
        if ($user->hasRole('super-admin')) {
            $this->handleSuperAdminAccess($request);
            return $next($request);
        }

        // Regular users must belong to a tenant
        if (!$user->tenant_id) {
            return response()->json([
                'message' => 'User is not associated with any tenant.'
            ], 403);
        }

        // Check if user's tenant is active
        $tenant = $user->tenant;
        if (!$tenant || !$tenant->isActive()) {
            return response()->json([
                'message' => 'Tenant is not active.'
            ], 403);
        }

        // Set current tenant in session for this request
        session(['current_tenant_id' => $user->tenant_id]);
        $request->attributes->set('current_tenant', $tenant);

        return $next($request);
    }

    /**
     * Handle super admin access with tenant switching.
     */
    private function handleSuperAdminAccess(Request $request): void
    {
        // Allow super admins to switch tenants via header or query parameter
        $tenantId = $request->header('X-Tenant-ID') 
                   ?? $request->query('tenant_id') 
                   ?? session('current_tenant_id');

        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if ($tenant) {
                session(['current_tenant_id' => $tenantId]);
                $request->attributes->set('current_tenant', $tenant);
            }
        }
    }
}
