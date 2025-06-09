<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Tenant Resolution Service
 * 
 * Handles tenant identification through multiple methods with priority hierarchy:
 * 1. Custom Domain (e.g., learn.district1.edu)
 * 2. Subdomain (e.g., district1.app.com)
 * 3. Header-based (X-Tenant-ID)
 * 4. User Association (user.tenant_id)
 */
class TenantResolutionService
{
    /**
     * Cache key prefix for tenant resolution
     */
    private const CACHE_PREFIX = 'tenant_resolution:';

    /**
     * Cache TTL in seconds (1 hour)
     */
    private const CACHE_TTL = 3600;

    /**
     * Resolve tenant from request using multiple methods
     * 
     * @param Request $request
     * @param \App\Models\User|null $user
     * @return Tenant|null
     */
    public function resolve(Request $request, $user = null): ?Tenant
    {
        // Try each resolution method in priority order
        $tenant = $this->resolveByCustomDomain($request)
                ?? $this->resolveBySubdomain($request)
                ?? $this->resolveByHeader($request)
                ?? $this->resolveByUser($user);

        if ($tenant) {
            Log::debug('Tenant resolved', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'method' => $this->getResolutionMethod($request, $user, $tenant)
            ]);
        }

        return $tenant;
    }

    /**
     * Resolve tenant by custom domain
     * 
     * @param Request $request
     * @return Tenant|null
     */
    public function resolveByCustomDomain(Request $request): ?Tenant
    {
        $host = $request->getHost();
        
        if (!$host) {
            return null;
        }

        $cacheKey = self::CACHE_PREFIX . 'custom_domain:' . $host;
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($host) {
            return Tenant::where('custom_domain', $host)
                        ->where('status', 'active')
                        ->first();
        });
    }

    /**
     * Resolve tenant by subdomain
     * 
     * @param Request $request
     * @return Tenant|null
     */
    public function resolveBySubdomain(Request $request): ?Tenant
    {
        $host = $request->getHost();
        $subdomain = $this->extractSubdomain($host);
        
        if (!$subdomain || $this->isReservedSubdomain($subdomain)) {
            return null;
        }

        $cacheKey = self::CACHE_PREFIX . 'subdomain:' . $subdomain;
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($subdomain) {
            return Tenant::where('subdomain', $subdomain)
                        ->where('status', 'active')
                        ->first();
        });
    }

    /**
     * Resolve tenant by header (X-Tenant-ID)
     * 
     * @param Request $request
     * @return Tenant|null
     */
    public function resolveByHeader(Request $request): ?Tenant
    {
        $tenantId = $request->header('X-Tenant-ID') ?? $request->query('tenant_id');
        
        if (!$tenantId) {
            return null;
        }

        $cacheKey = self::CACHE_PREFIX . 'header:' . $tenantId;
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($tenantId) {
            return Tenant::where('id', $tenantId)
                        ->where('status', 'active')
                        ->first();
        });
    }

    /**
     * Resolve tenant by user association
     * 
     * @param \App\Models\User|null $user
     * @return Tenant|null
     */
    public function resolveByUser($user): ?Tenant
    {
        if (!$user || !$user->tenant_id) {
            return null;
        }

        $cacheKey = self::CACHE_PREFIX . 'user:' . $user->tenant_id;
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user) {
            return Tenant::where('id', $user->tenant_id)
                        ->where('status', 'active')
                        ->first();
        });
    }

    /**
     * Extract subdomain from host
     * 
     * @param string $host
     * @return string|null
     */
    private function extractSubdomain(string $host): ?string
    {
        $appDomain = config('app.domain', 'localhost');
        
        // Remove port if present
        $host = explode(':', $host)[0];
        
        // Check if host ends with app domain
        if (!str_ends_with($host, $appDomain)) {
            return null;
        }
        
        // Extract subdomain
        $subdomain = str_replace('.' . $appDomain, '', $host);
        
        // Return null if no subdomain or if it's the main domain
        return ($subdomain === $host || $subdomain === '') ? null : $subdomain;
    }

    /**
     * Check if subdomain is reserved
     * 
     * @param string $subdomain
     * @return bool
     */
    private function isReservedSubdomain(string $subdomain): bool
    {
        $reserved = [
            'www', 'api', 'admin', 'app', 'mail', 'ftp', 'blog', 
            'support', 'help', 'docs', 'status', 'cdn', 'assets',
            'static', 'media', 'files', 'images', 'js', 'css',
            'test', 'staging', 'dev', 'demo', 'sandbox'
        ];
        
        return in_array(strtolower($subdomain), $reserved);
    }

    /**
     * Get the resolution method used for debugging
     * 
     * @param Request $request
     * @param \App\Models\User|null $user
     * @param Tenant $tenant
     * @return string
     */
    private function getResolutionMethod(Request $request, $user, Tenant $tenant): string
    {
        if ($tenant->custom_domain === $request->getHost()) {
            return 'custom_domain';
        }
        
        $subdomain = $this->extractSubdomain($request->getHost());
        if ($subdomain && $tenant->subdomain === $subdomain) {
            return 'subdomain';
        }
        
        $headerTenantId = $request->header('X-Tenant-ID') ?? $request->query('tenant_id');
        if ($headerTenantId && $tenant->id == $headerTenantId) {
            return 'header';
        }
        
        if ($user && $user->tenant_id == $tenant->id) {
            return 'user_association';
        }
        
        return 'unknown';
    }

    /**
     * Clear tenant resolution cache
     * 
     * @param Tenant|null $tenant
     * @return void
     */
    public function clearCache(?Tenant $tenant = null): void
    {
        if ($tenant) {
            // Clear specific tenant cache
            Cache::forget(self::CACHE_PREFIX . 'custom_domain:' . $tenant->custom_domain);
            Cache::forget(self::CACHE_PREFIX . 'subdomain:' . $tenant->subdomain);
            Cache::forget(self::CACHE_PREFIX . 'header:' . $tenant->id);
            Cache::forget(self::CACHE_PREFIX . 'user:' . $tenant->id);
        } else {
            // Clear all tenant resolution cache
            Cache::flush(); // In production, use more specific cache clearing
        }
    }

    /**
     * Validate tenant domain configuration
     * 
     * @param Tenant $tenant
     * @return array
     */
    public function validateTenantDomains(Tenant $tenant): array
    {
        $issues = [];
        
        // Check custom domain
        if ($tenant->custom_domain) {
            if (!$this->isValidDomain($tenant->custom_domain)) {
                $issues[] = 'Invalid custom domain format';
            }
            
            if ($this->isDomainInUse($tenant->custom_domain, $tenant->id)) {
                $issues[] = 'Custom domain is already in use';
            }
        }
        
        // Check subdomain
        if ($tenant->subdomain) {
            if (!$this->isValidSubdomain($tenant->subdomain)) {
                $issues[] = 'Invalid subdomain format';
            }
            
            if ($this->isReservedSubdomain($tenant->subdomain)) {
                $issues[] = 'Subdomain is reserved';
            }
            
            if ($this->isSubdomainInUse($tenant->subdomain, $tenant->id)) {
                $issues[] = 'Subdomain is already in use';
            }
        }
        
        return $issues;
    }

    /**
     * Check if domain is valid
     * 
     * @param string $domain
     * @return bool
     */
    private function isValidDomain(string $domain): bool
    {
        return filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }

    /**
     * Check if subdomain is valid
     * 
     * @param string $subdomain
     * @return bool
     */
    private function isValidSubdomain(string $subdomain): bool
    {
        return preg_match('/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?$/i', $subdomain);
    }

    /**
     * Check if domain is already in use
     * 
     * @param string $domain
     * @param int|null $excludeTenantId
     * @return bool
     */
    private function isDomainInUse(string $domain, ?int $excludeTenantId = null): bool
    {
        $query = Tenant::where('custom_domain', $domain);
        
        if ($excludeTenantId) {
            $query->where('id', '!=', $excludeTenantId);
        }
        
        return $query->exists();
    }

    /**
     * Check if subdomain is already in use
     * 
     * @param string $subdomain
     * @param int|null $excludeTenantId
     * @return bool
     */
    private function isSubdomainInUse(string $subdomain, ?int $excludeTenantId = null): bool
    {
        $query = Tenant::where('subdomain', $subdomain);
        
        if ($excludeTenantId) {
            $query->where('id', '!=', $excludeTenantId);
        }
        
        return $query->exists();
    }
}
