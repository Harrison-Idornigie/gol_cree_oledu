<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Carbon;
use App\Notifications\VerifyEmailNotification;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use Stancl\Tenancy\Listeners;
use Stancl\Tenancy\Middleware;
use Stancl\Tenancy\TenancyServiceProvider as BaseTenancyServiceProvider;

class TenancyServiceProvider extends BaseTenancyServiceProvider
{
    // By default, no namespace is used to support the callable array syntax.
    public static string $controllerNamespace = '';

    public function events()
    {
        return [
            // Tenant events
            Events\CreatingTenant::class => [],
            Events\TenantCreated::class => [
                JobPipeline::make([
                    Jobs\CreateDatabase::class,
                    Jobs\MigrateDatabase::class,
                    // Jobs\SeedDatabase::class,

                    // Your own jobs to prepare the tenant.
                    // Provision API keys, create S3 buckets, anything you want!

                ])->send(function (Events\TenantCreated $event) {
                    return $event->tenant;
                })->shouldBeQueued(false), // `false` by default, but you probably want to make this `true` for production.
            ],
            Events\SavingTenant::class => [],
            Events\TenantSaved::class => [],
            Events\UpdatingTenant::class => [],
            Events\TenantUpdated::class => [],
            Events\DeletingTenant::class => [],
            Events\TenantDeleted::class => [
                JobPipeline::make([
                    Jobs\DeleteDatabase::class,
                ])->send(function (Events\TenantDeleted $event) {
                    return $event->tenant;
                })->shouldBeQueued(false), // `false` by default, but you probably want to make this `true` for production.
            ],

            // Domain events
            Events\CreatingDomain::class => [],
            Events\DomainCreated::class => [],
            Events\SavingDomain::class => [],
            Events\DomainSaved::class => [],
            Events\UpdatingDomain::class => [],
            Events\DomainUpdated::class => [],
            Events\DeletingDomain::class => [],
            Events\DomainDeleted::class => [],

            // Database events
            Events\DatabaseCreated::class => [
                function (Events\DatabaseCreated $event) {
                    \Log::info('Tenant database created successfully', [
                        'tenant_id' => $event->tenant->id,
                        'database_name' => $event->tenant->database_name ?? $event->tenant->id
                    ]);
                }
            ],
            Events\DatabaseMigrated::class => [
                function (Events\DatabaseMigrated $event) {
                    \Log::info('Tenant database migrated successfully', [
                        'tenant_id' => $event->tenant->id,
                        'database_name' => $event->tenant->database_name ?? $event->tenant->id
                    ]);
                }
            ],
            Events\DatabaseSeeded::class => [],
            Events\DatabaseRolledBack::class => [],
            Events\DatabaseDeleted::class => [],

            // Tenancy events
            Events\InitializingTenancy::class => [],
            Events\TenancyInitialized::class => [
                Listeners\BootstrapTenancy::class,
                function (Events\TenancyInitialized $event) {
                    // Set up tenant-aware verification URL callback
                    VerifyEmailNotification::createUrlUsing(function ($notifiable) use ($event) {
                        return URL::temporarySignedRoute(
                            'verification.verify',
                            Carbon::now()->addMinutes(config('auth.verification.expire', 60)),
                            [
                                'id' => $notifiable->getKey(),
                                'hash' => sha1($notifiable->getEmailForVerification()),
                            ]
                        );
                    });
                },
            ],

            Events\EndingTenancy::class => [],
            Events\TenancyEnded::class => [
                Listeners\RevertToCentralContext::class,
                function (Events\TenancyEnded $event) {
                    // Reset verification URL callback to default
                    VerifyEmailNotification::createUrlUsing(null);
                },
            ],

            Events\BootstrappingTenancy::class => [],
            Events\TenancyBootstrapped::class => [],
            Events\RevertingToCentralContext::class => [],
            Events\RevertedToCentralContext::class => [],

            // Resource syncing
            Events\SyncedResourceSaved::class => [
                Listeners\UpdateSyncedResource::class,
            ],

            // Fired only when a synced resource is changed in a different DB than the origin DB (to avoid infinite loops)
            Events\SyncedResourceChangedInForeignDatabase::class => [],
        ];
    }

    public function register(): void
    {
        parent::register();

        // Ensure the UniqueIdentifierGenerator is properly bound
        $this->app->singleton(
            \Stancl\Tenancy\Contracts\UniqueIdentifierGenerator::class,
            \Stancl\Tenancy\UUIDGenerator::class
        );
    }

    public function boot(): void
    {
        parent::boot();

        $this->bootEvents();
        $this->mapRoutes();

        $this->makeTenancyMiddlewareHighestPriority();
    }

    protected function bootEvents()
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof JobPipeline) {
                    $listener = $listener->toListener();
                }

                Event::listen($event, $listener);
            }
        }
    }

    protected function mapRoutes()
    {
        // Tenant routes are now handled by RouteServiceProvider under api/{tenant} prefix
        // This method is kept for backward compatibility but routes/tenant.php
        // is no longer loaded here to prevent conflicts with the main RouteServiceProvider

        // If you need additional tenant routes that should be loaded by Stancl's
        // automatic tenant context switching, you can add them here with proper middleware
    }

    protected function makeTenancyMiddlewareHighestPriority()
    {
        $tenancyMiddleware = [
            // Even higher priority than the initialization middleware
            Middleware\PreventAccessFromCentralDomains::class,

            // Our custom hybrid middleware
            \App\Http\Middleware\Tenant\InitializeTenancyByPathOrDomain::class,

            // Keep original middleware for backward compatibility
            Middleware\InitializeTenancyByDomain::class,
            Middleware\InitializeTenancyBySubdomain::class,
            Middleware\InitializeTenancyByDomainOrSubdomain::class,
            Middleware\InitializeTenancyByPath::class,
            Middleware\InitializeTenancyByRequestData::class,
        ];

        foreach (array_reverse($tenancyMiddleware) as $middleware) {
            $this->app[\Illuminate\Contracts\Http\Kernel::class]->prependToMiddlewarePriority($middleware);
        }
    }
}
