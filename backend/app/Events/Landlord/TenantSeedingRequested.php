<?php

namespace App\Events\Landlord;

use App\Models\Landlord\Tenant;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Tenant Seeding Requested Event
 * 
 * Fired after a tenant is created and database is initialized,
 * requesting that the tenant be seeded with default data.
 * 
 * This event allows multiple listeners to seed different aspects
 * of the tenant independently and in a modular fashion.
 */
class TenantSeedingRequested
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
     * Seeding context/options
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

    /**
     * Get seeding context value
     */
    public function getContext(string $key, $default = null)
    {
        return $this->context[$key] ?? $default;
    }

    /**
     * Check if seeding should include specific feature
     */
    public function shouldSeed(string $feature): bool
    {
        return $this->getContext("seed_{$feature}", true);
    }
}
