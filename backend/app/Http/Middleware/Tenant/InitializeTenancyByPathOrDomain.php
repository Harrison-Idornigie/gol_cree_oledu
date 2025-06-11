<?php

namespace App\Http\Middleware\Tenant;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;
use Stancl\Tenancy\Tenancy;
use App\Models\Landlord\Tenant;

/**
 * Hybrid Tenant Identification Middleware
 * 
 * This middleware implements a hybrid tenant identification system that:
 * 1. First attempts to identify tenant from URL path (api/{tenant-slug}/...)
 * 2. Falls back to domain-based identification if path-based fails
 * 3. Maintains backward compatibility with existing domain-based routes
 */
class InitializeTenancyByPathOrDomain
{
    protected $tenancy;
    protected $domainResolver;

    public function __construct(Tenancy $tenancy, DomainTenantResolver $domainResolver)
    {
        $this->tenancy = $tenancy;
        $this->domainResolver = $domainResolver;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip tenant identification for central/landlord routes
        if ($this->isCentralRoute($request)) {
            return $next($request);
        }

        // Attempt path-based tenant identification first
        $tenant = $this->identifyTenantFromPath($request);
        
        if ($tenant) {
            // Initialize tenancy with path-identified tenant
            $this->tenancy->initialize($tenant);
            
            // Store tenant slug in request for controllers to access
            $request->merge(['tenant_slug' => $tenant->slug]);
            
            return $next($request);
        }

        // Fall back to domain-based identification
        try {
            $tenant = $this->identifyTenantFromDomain($request);
            if ($tenant) {
                $this->tenancy->initialize($tenant);
                return $next($request);
            }
        } catch (\Exception) {
            // Log domain identification error but continue to error handling
        }

        // If both methods fail, return appropriate error
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant could not be identified from path or domain.',
                'error' => 'tenant_not_found'
            ], 404);
        }

        return response()->json([
            'success' => false,
            'message' => 'Tenant could not be identified from path or domain.',
            'error' => 'tenant_not_found'
        ], 404);
    }

    /**
     * Identify tenant from domain
     */
    protected function identifyTenantFromDomain(Request $request): ?Tenant
    {
        try {
            $resolvedTenant = $this->domainResolver->resolve($request);
            if ($resolvedTenant instanceof Tenant) {
                return $resolvedTenant;
            }
            // If it's a different tenant model, find our model by ID
            return Tenant::find($resolvedTenant->id);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Identify tenant from URL path
     */
    protected function identifyTenantFromPath(Request $request): ?Tenant
    {
        $path = $request->path();

        // Check if path matches pattern: api/{tenant-slug}/...
        // This covers both api/{tenant-slug}/auth/* and api/{tenant-slug}/student/* etc.
        if (preg_match('/^api\/([^\/]+)\//', $path, $matches)) {
            $tenantSlug = $matches[1];

            // Skip if this looks like a non-tenant API route
            if ($this->isNonTenantApiRoute($tenantSlug)) {
                return null;
            }

            // Use caching for better performance
            $cacheKey = "tenant_slug_{$tenantSlug}";
            $tenant = cache()->remember($cacheKey, 300, function () use ($tenantSlug) {
                return Tenant::where('slug', $tenantSlug)
                            ->where('status', 'active')
                            ->first();
            });

            if ($tenant) {
                // Add tenant slug to route parameters for controllers
                if ($request->route()) {
                    $request->route()->setParameter('tenant', $tenantSlug);
                }
                return $tenant;
            }
        }

        return null;
    }

    /**
     * Check if this is a central/landlord route that shouldn't have tenant context
     */
    protected function isCentralRoute(Request $request): bool
    {
        $path = $request->path();

        // Define central route patterns that should never have tenant context
        $centralRoutes = [
            'api/public/',
            'api/super-admin/',
            'api/health',
            'api/info',
        ];

        // Check exact central route patterns
        foreach ($centralRoutes as $centralRoute) {
            if (str_starts_with($path, $centralRoute)) {
                return true;
            }
        }

        // Check for central auth routes (without tenant slug)
        // Pattern: api/auth/* (central) vs api/{tenant-slug}/auth/* (tenant-scoped)
        if (str_starts_with($path, 'api/auth/')) {
            // This is a central auth route (no tenant slug)
            return true;
        }

        // Check if this is a tenant-scoped auth route
        // Pattern: api/{tenant-slug}/auth/*
        if (preg_match('/^api\/([^\/]+)\/auth\//', $path, $matches)) {
            $potentialTenantSlug = $matches[1];

            // If the "tenant slug" is actually a reserved central route, treat as central
            if ($this->isNonTenantApiRoute($potentialTenantSlug)) {
                return true;
            }

            // This is a tenant-scoped auth route, not central
            return false;
        }

        return false;
    }

    /**
     * Check if the slug looks like a non-tenant API route
     */
    protected function isNonTenantApiRoute(string $slug): bool
    {
        $nonTenantRoutes = [
            'public',
            'super-admin',
            'auth',
            'health',
            'info'
        ];
        
        return in_array($slug, $nonTenantRoutes);
    }
}
