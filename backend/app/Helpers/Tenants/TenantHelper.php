<?php

namespace App\Helpers\Tenants;

use App\Models\Landlord\Tenant;
use Illuminate\Support\Facades\Request;

/**
 * Tenant Helper
 * 
 * Provides utility functions for tenant operations and URL generation.
 */
class TenantHelper
{
    /**
     * Get the current tenant from the request context
     * 
     * @return Tenant|null
     */
    public static function current(): ?Tenant
    {
        return app('current_tenant') ?? Request::get('current_tenant');
    }

    /**
     * Get the current tenant ID
     * 
     * @return int|null
     */
    public static function currentId(): ?int
    {
        $tenant = self::current();
        return $tenant ? $tenant->id : null;
    }

    /**
     * Generate URL for a tenant
     * 
     * @param Tenant $tenant
     * @param string $path
     * @param bool $secure
     * @return string
     */
    public static function url(Tenant $tenant, string $path = '', bool $secure = null): string
    {
        $secure = $secure ?? Request::isSecure();
        $scheme = $secure ? 'https' : 'http';
        
        // Use custom domain if available
        if ($tenant->custom_domain) {
            $domain = $tenant->custom_domain;
        }
        // Use subdomain if available
        elseif ($tenant->subdomain) {
            $appDomain = config('app.domain');
            $domain = $tenant->subdomain . '.' . $appDomain;
        }
        // Fallback to main domain with tenant parameter
        else {
            $appDomain = config('app.domain');
            $domain = $appDomain;
            $path = ltrim($path, '/');
            $separator = strpos($path, '?') !== false ? '&' : '?';
            $path .= $separator . 'tenant_id=' . $tenant->id;
        }
        
        $url = $scheme . '://' . $domain;
        
        if ($path) {
            $url .= '/' . ltrim($path, '/');
        }
        
        return $url;
    }

    /**
     * Generate API URL for a tenant
     * 
     * @param Tenant $tenant
     * @param string $endpoint
     * @param bool $secure
     * @return string
     */
    public static function apiUrl(Tenant $tenant, string $endpoint = '', bool $secure = null): string
    {
        $path = 'api/' . ltrim($endpoint, '/');
        return self::url($tenant, $path, $secure);
    }

    /**
     * Check if current request is for a specific tenant
     * 
     * @param Tenant $tenant
     * @return bool
     */
    public static function isCurrent(Tenant $tenant): bool
    {
        $current = self::current();
        return $current && $current->id === $tenant->id;
    }

    /**
     * Get tenant from subdomain
     * 
     * @param string $subdomain
     * @return Tenant|null
     */
    public static function findBySubdomain(string $subdomain): ?Tenant
    {
        return Tenant::where('subdomain', $subdomain)
                    ->where('status', 'active')
                    ->first();
    }

    /**
     * Get tenant from custom domain
     *
     * @param string $domain
     * @return Tenant|null
     */
    public static function findByCustomDomain(string $domain): ?Tenant
    {
        return Tenant::where('custom_domain', $domain)
                    ->where('status', 'active')
                    ->first();
    }

    /**
     * Get standardized tenant data for API responses
     *
     * @param Tenant|null $tenant
     * @return array|null
     */
    public static function getApiData(?Tenant $tenant = null): ?array
    {
        $tenant = $tenant ?? self::current();

        if (!$tenant) {
            return null;
        }

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'status' => $tenant->status ?? 'active',
        ];
    }

    /**
     * Add tenant context to user data for API responses
     *
     * @param array $userData
     * @param Tenant|null $tenant
     * @return array
     */
    public static function addTenantContextToUser(array $userData, ?Tenant $tenant = null): array
    {
        $tenant = $tenant ?? self::current();

        if ($tenant) {
            $userData['tenant_id'] = $tenant->id;
            $userData['tenant'] = self::getApiData($tenant);
        }

        return $userData;
    }

    /**
     * Generate subdomain URL for tenant
     * 
     * @param Tenant $tenant
     * @param string $path
     * @param bool $secure
     * @return string|null
     */
    public static function subdomainUrl(Tenant $tenant, string $path = '', bool $secure = null): ?string
    {
        if (!$tenant->subdomain) {
            return null;
        }
        
        $secure = $secure ?? Request::isSecure();
        $scheme = $secure ? 'https' : 'http';
        $appDomain = config('app.domain');
        $domain = $tenant->subdomain . '.' . $appDomain;
        
        $url = $scheme . '://' . $domain;
        
        if ($path) {
            $url .= '/' . ltrim($path, '/');
        }
        
        return $url;
    }

    /**
     * Generate custom domain URL for tenant
     * 
     * @param Tenant $tenant
     * @param string $path
     * @param bool $secure
     * @return string|null
     */
    public static function customDomainUrl(Tenant $tenant, string $path = '', bool $secure = null): ?string
    {
        if (!$tenant->custom_domain) {
            return null;
        }
        
        $secure = $secure ?? Request::isSecure();
        $scheme = $secure ? 'https' : 'http';
        
        $url = $scheme . '://' . $tenant->custom_domain;
        
        if ($path) {
            $url .= '/' . ltrim($path, '/');
        }
        
        return $url;
    }

    /**
     * Get all available URLs for a tenant
     * 
     * @param Tenant $tenant
     * @param string $path
     * @param bool $secure
     * @return array
     */
    public static function getAllUrls(Tenant $tenant, string $path = '', bool $secure = null): array
    {
        $urls = [];
        
        // Custom domain URL
        $customUrl = self::customDomainUrl($tenant, $path, $secure);
        if ($customUrl) {
            $urls['custom_domain'] = $customUrl;
        }
        
        // Subdomain URL
        $subdomainUrl = self::subdomainUrl($tenant, $path, $secure);
        if ($subdomainUrl) {
            $urls['subdomain'] = $subdomainUrl;
        }
        
        // Main domain URL with tenant parameter
        $urls['main_domain'] = self::url($tenant, $path, $secure);
        
        return $urls;
    }

    /**
     * Check if a subdomain is available
     * 
     * @param string $subdomain
     * @param int|null $excludeTenantId
     * @return bool
     */
    public static function isSubdomainAvailable(string $subdomain, ?int $excludeTenantId = null): bool
    {
        $query = Tenant::where('subdomain', $subdomain);
        
        if ($excludeTenantId) {
            $query->where('id', '!=', $excludeTenantId);
        }
        
        return !$query->exists();
    }

    /**
     * Check if a custom domain is available
     * 
     * @param string $domain
     * @param int|null $excludeTenantId
     * @return bool
     */
    public static function isCustomDomainAvailable(string $domain, ?int $excludeTenantId = null): bool
    {
        $query = Tenant::where('custom_domain', $domain);
        
        if ($excludeTenantId) {
            $query->where('id', '!=', $excludeTenantId);
        }
        
        return !$query->exists();
    }

    /**
     * Suggest available subdomain based on tenant name
     * 
     * @param string $name
     * @param int|null $excludeTenantId
     * @return string
     */
    public static function suggestSubdomain(string $name, ?int $excludeTenantId = null): string
    {
        $baseSubdomain = \Illuminate\Support\Str::slug($name);
        $subdomain = $baseSubdomain;
        $counter = 1;
        
        while (!self::isSubdomainAvailable($subdomain, $excludeTenantId)) {
            $subdomain = $baseSubdomain . '-' . $counter;
            $counter++;
        }
        
        return $subdomain;
    }
}
