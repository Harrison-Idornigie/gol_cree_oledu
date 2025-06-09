<?php

namespace App\Events\Landlord;

use App\Models\Landlord\Tenant;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Tenant Deleting Event
 * 
 * Fired before a tenant is deleted.
 * This event allows listeners to perform cleanup operations
 * before the tenant is removed from the system.
 */
class TenantDeleting
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The tenant being deleted
     */
    public Tenant $tenant;

    /**
     * Create a new event instance.
     */
    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }
}
