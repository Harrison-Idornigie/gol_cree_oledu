<?php

use App\Http\Middleware\Tenant\InitializeTenancyByPathOrDomain;
use App\Http\Middleware\Tenant\EnsureEmailIsVerified;
use App\Http\Middleware\Tenant\CheckSequentialAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        api: __DIR__ . '/../routes/api.php',

        // Custom multi-tenant route registration
        then: function () {
            // Central Authentication Routes (SSO-like, no tenant context)
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/auth.php'));

            // Super Admin Routes (system-wide management)
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/landlord.php'));

            // Tenant-scoped Routes (with tenant context)
            Route::middleware(['api', InitializeTenancyByPathOrDomain::class])
                ->prefix('api/{tenant}')
                ->where(['tenant' => '^(?!auth|public|super-admin|health|info)[a-zA-Z0-9][a-zA-Z0-9\-_]*[a-zA-Z0-9]$'])
                ->group(base_path('routes/tenant.php'));
        }
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Only add middleware if the classes exist
        if (class_exists(EnsureEmailIsVerified::class)) {
            $middleware->alias(['verified' => EnsureEmailIsVerified::class]);
        }

        if (class_exists(CheckSequentialAccess::class)) {
            $middleware->alias(['sequential-learning' => CheckSequentialAccess::class]);
        }
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();