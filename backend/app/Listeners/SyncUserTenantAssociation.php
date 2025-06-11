<?php

namespace App\Listeners;

use App\Services\Auth\UserTenantSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Sync User Tenant Association Listener
 * 
 * Automatically syncs user tenant associations when users are
 * created, updated, or deleted in tenant databases.
 */
class SyncUserTenantAssociation implements ShouldQueue
{
    use InteractsWithQueue;

    protected UserTenantSyncService $syncService;

    public function __construct(UserTenantSyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    /**
     * Handle user created event
     */
    public function handleUserCreated($event): void
    {
        if (!$this->shouldSync($event)) {
            return;
        }

        $user = $event->user ?? $event->model;
        $tenant = $this->getCurrentTenant();

        if ($user && $tenant) {
            $this->syncService->syncUserToTenant($user->email, $tenant, [
                'membership' => $user->membership,
                'permissions' => $user->permissions ?? [],
                'is_active' => true
            ]);
        }
    }

    /**
     * Handle user updated event
     */
    public function handleUserUpdated($event): void
    {
        if (!$this->shouldSync($event)) {
            return;
        }

        $user = $event->user ?? $event->model;
        $tenant = $this->getCurrentTenant();

        if ($user && $tenant) {
            $this->syncService->syncUserToTenant($user->email, $tenant, [
                'membership' => $user->membership,
                'permissions' => $user->permissions ?? [],
                'is_active' => $user->is_active ?? true
            ]);
        }
    }

    /**
     * Handle user deleted event
     */
    public function handleUserDeleted($event): void
    {
        if (!$this->shouldSync($event)) {
            return;
        }

        $user = $event->user ?? $event->model;
        $tenant = $this->getCurrentTenant();

        if ($user && $tenant) {
            $this->syncService->removeUserFromTenant($user->email, $tenant->slug);
        }
    }

    /**
     * Check if we should sync this event
     */
    protected function shouldSync($event): bool
    {
        // Only sync if we're in a tenant context
        return tenant() !== null;
    }

    /**
     * Get the current tenant
     */
    protected function getCurrentTenant(): ?\App\Models\Landlord\Tenant
    {
        $currentTenant = tenant();
        
        if (!$currentTenant) {
            return null;
        }

        // Get the full tenant model from landlord database
        return \App\Models\Landlord\Tenant::find($currentTenant->id);
    }

    /**
     * Handle failed job
     */
    public function failed($event, $exception): void
    {
        Log::error('Failed to sync user tenant association', [
            'event' => class_basename($event),
            'exception' => $exception->getMessage(),
            'tenant_id' => tenant()?->id
        ]);
    }
}
