<?php

namespace App\Events;

use App\Models\Landlord\Tenant;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Tenant Created Event
 * 
 * Fired when a new tenant is successfully created.
 * This event can be used to trigger additional setup tasks,
 * notifications, or integrations.
 */
class TenantCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The newly created tenant
     */
    public Tenant $tenant;

    /**
     * The tenant admin user
     */
    public User $adminUser;

    /**
     * Create a new event instance.
     */
    public function __construct(Tenant $tenant, User $adminUser)
    {
        $this->tenant = $tenant;
        $this->adminUser = $adminUser;
    }
}
