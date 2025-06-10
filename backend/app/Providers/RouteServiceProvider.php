<?php
namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            // Central API Routes (no tenant context)
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // Central Authentication Routes (no tenant context)
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/auth.php'));

            // Landlord Routes (no tenant context)
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/landlord.php'));

            // Tenant-scoped API Routes with hybrid tenant identification
            Route::middleware(['api', \App\Http\Middleware\Tenant\InitializeTenancyByPathOrDomain::class])
                ->prefix('api/{tenant}')
                ->group(function () {
                    // Tenant-scoped Authentication Routes
                    // These routes work with tenant context when accessed via api/{tenant}/auth/*
                    require base_path('routes/auth.php');

                    // Tenant Admin Routes
                    require base_path('routes/tenant-admin.php');

                    // Team Routes
                    require base_path('routes/tenant-team.php');

                    // Student Routes
                    require base_path('routes/tenant-students.php');
                });

            // Web Routes
            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
