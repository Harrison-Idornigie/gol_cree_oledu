<?php

namespace App\Events\Landlord;

use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Tenant Setup Completed Event
 * 
 * Fired when a tenant has been fully created and configured:
 * - Tenant record created in landlord database
 * - Tenant database created and migrated
 * - Admin user created in tenant database
 * - Ready for post-setup operations (notifications, integrations, etc.)
 */
class TenantSetupCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The tenant that was created
     */
    public Tenant $tenant;

    /**
     * The admin user created for the tenant
     */
    public User $adminUser;

    /**
     * Additional context data
     */
    public array $context;

    /**
     * Create a new event instance.
     */
    public function __construct(Tenant $tenant, User $adminUser, array $context = [])
    {
        $this->tenant = $tenant;
        $this->adminUser = $adminUser;
        $this->context = $context;
    }
}
