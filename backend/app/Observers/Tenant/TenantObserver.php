<?php

namespace App\Observers;

use App\Models\Tenant;
use App\Services\TenantResolutionService;

/**
 * Tenant Observer
 * 
 * Handles tenant model events to maintain cache consistency
 * and perform related operations when tenant data changes.
 */
class TenantObserver
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
     * Handle the Tenant "created" event.
     */
    public function created(Tenant $tenant): void
    {
        // Clear cache to ensure new tenant is discoverable
        $this->tenantResolver->clearCache();
        
        // Log tenant creation
        \Log::info('Tenant created', [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'subdomain' => $tenant->subdomain,
            'custom_domain' => $tenant->custom_domain,
        ]);
    }

    /**
     * Handle the Tenant "updated" event.
     */
    public function updated(Tenant $tenant): void
    {
        // Clear cache for this specific tenant
        $this->tenantResolver->clearCache($tenant);
        
        // If domain-related fields changed, clear all cache
        if ($tenant->wasChanged(['subdomain', 'custom_domain', 'domain', 'status'])) {
            $this->tenantResolver->clearCache();
            
            \Log::info('Tenant domain configuration updated', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'changes' => $tenant->getChanges(),
            ]);
        }
    }

    /**
     * Handle the Tenant "deleted" event.
     */
    public function deleted(Tenant $tenant): void
    {
        // Clear all cache when tenant is deleted
        $this->tenantResolver->clearCache();
        
        \Log::info('Tenant deleted', [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
        ]);
    }

    /**
     * Handle the Tenant "restored" event.
     */
    public function restored(Tenant $tenant): void
    {
        // Clear cache when tenant is restored
        $this->tenantResolver->clearCache();
        
        \Log::info('Tenant restored', [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
        ]);
    }

    /**
     * Handle the Tenant "force deleted" event.
     */
    public function forceDeleted(Tenant $tenant): void
    {
        // Clear all cache when tenant is force deleted
        $this->tenantResolver->clearCache();
        
        \Log::info('Tenant force deleted', [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
        ]);
    }
}
