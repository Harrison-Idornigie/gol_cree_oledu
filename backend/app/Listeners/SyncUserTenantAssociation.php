<?php

namespace App\Listeners;

use App\Services\Auth\UserTenantSyncService;
use Illuminate\Support\Facades\Log;

/**
 * Sync User Tenant Association Listener
 * 
 * Automatically syncs user tenant associations when users are
 * created, updated, or deleted in tenant databases.
 */
class SyncUserTenantAssociation
{

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
        Log::info('SyncUserTenantAssociation: User created event triggered', [
            'event_class' => get_class($event),
            'event_data' => [
                'user' => $event->user ?? null,
                'model' => $event->model ?? null,
                'event_properties' => get_object_vars($event)
            ],
            'tenant_context' => tenant()?->id,
            'should_sync' => $this->shouldSync($event)
        ]);

        if (!$this->shouldSync($event)) {
            return;
        }

        // For eloquent events, the event object itself is the model
        $user = $event;
        $tenant = $this->getCurrentTenant();

        Log::info('SyncUserTenantAssociation: Processing user creation', [
            'user_email' => $user->email ?? null,
            'user_id' => $user->id ?? null,
            'user_membership' => $user->membership ?? null,
            'user_class' => get_class($user),
            'tenant_id' => $tenant?->id,
            'tenant_slug' => $tenant?->slug
        ]);

        if ($user && $tenant && $user->email) {
            $this->syncService->syncUserToTenant($user->email, $tenant, [
                'membership' => $user->membership ?? 'admin',
                'permissions' => $user->getFixedPermissions(),
                'is_active' => true
            ]);
        } else {
            Log::warning('SyncUserTenantAssociation: Missing required data', [
                'has_user' => $user !== null,
                'has_tenant' => $tenant !== null,
                'has_email' => isset($user->email) && !empty($user->email)
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
                'permissions' => $user->getFixedPermissions(),
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


}
