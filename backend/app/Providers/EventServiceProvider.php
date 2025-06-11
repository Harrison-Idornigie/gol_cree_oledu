<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\Landlord\TenantSetupCompleted;
use App\Events\Landlord\TenantDeleting;
use App\Events\Landlord\TenantSeedingRequested;
use App\Listeners\Landlord\CleanupTenantData;
use App\Listeners\Landlord\SeedTenantRoles;
use App\Listeners\Landlord\SeedTenantLanguages;
use App\Listeners\Landlord\SeedTenantSettings;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        // Tenant Management Events
        TenantSeedingRequested::class => [
            SeedTenantRoles::class,
            SeedTenantLanguages::class,
            SeedTenantSettings::class,
        ],

        TenantSetupCompleted::class => [
            // Add any post-setup listeners here (notifications, integrations, etc.)
        ],

        TenantDeleting::class => [
            CleanupTenantData::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}