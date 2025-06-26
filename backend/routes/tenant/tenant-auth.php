<?php

use App\Http\Controllers\API\Landlord\Auth\CentralLoginController;
use App\Http\Controllers\API\Landlord\Auth\CentralLogoutController;
use App\Http\Controllers\API\Landlord\Auth\CentralUserRegisterController;
use App\Http\Controllers\API\Tenant\Auth\TenantAuthController;
use Illuminate\Support\Facades\Route;

/**
 * Authentication Routes
 *
 * These routes work in both central and tenant contexts:
 * - Central: api/auth/*
 * - Tenant: api/{tenant-slug}/auth/* (when accessed through tenant middleware)
 *
 * The hybrid tenant middleware will automatically identify tenant context
 * when the URL includes a tenant slug, making these routes work seamlessly
 * in both scenarios.
 *
 * When accessed via tenant URLs (api/{tenant}/auth/*), the tenant context
 * is automatically initialized and available in controllers.
 */

Route::group([
    'prefix'     => 'auth',
    'as'         => 'auth.',
], function () {

    // Tenant authentication routes (require tenant context)
    Route::middleware([\App\Http\Middleware\Tenant\InitializeTenancyByPathOrDomain::class])->group(function () {
        // Login endpoint with rate limiting (more permissive for testing)
        Route::post('tenant-login', [TenantAuthController::class, 'login'])
            ->middleware(['throttle:100,1']) // 100 attempts per minute (testing-friendly)
            ->name('tenant.login');

        // Get user tenants endpoint with rate limiting
        Route::post('tenant-user-tenants', [TenantAuthController::class, 'getUserTenants'])
            ->middleware(['throttle:100,1']) // 100 attempts per minute (testing-friendly)
            ->name('tenant.user.tenants');

        Route::middleware('auth:tenant')->group(function () {
            Route::post('tenant-logout', [TenantAuthController::class, 'logout'])->name('tenant.logout');
        });
    });


    // Email verification routes
    Route::get('email/verify/{id}/{hash}', [TenantAuthController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    // API-friendly email verification (for frontend to POST verification data)
    Route::post('email/verify', [TenantAuthController::class, 'verify'])
        ->middleware(['throttle:6,1'])
        ->name('verification.verify.api');

    // User registration routes
    Route::post('register', [TenantAuthController::class, 'register'])
        ->name('tenant.user.register');

    // Password reset routes
    Route::post('password/email', [TenantAuthController::class, 'sendResetLinkEmail'])
        ->name('password.email');
    Route::post('password/reset', [TenantAuthController::class, 'resetPassword'])
        ->name('password.reset');

    // Google OAuth routes (tenant-specific)
    Route::post('google/url', [TenantAuthController::class, 'getGoogleAuthUrl'])
        ->name('tenant.google.url');
    Route::post('google/callback', [TenantAuthController::class, 'handleGoogleCallback'])
        ->name('tenant.google.callback');

    // Protected routes that require tenant authentication
    Route::middleware('auth:tenant')->group(function () {
        Route::post('email/verification-notification', [TenantAuthController::class, 'sendVerificationEmail'])
            ->middleware(['throttle:6,1'])
            ->name('verification.send');
        Route::get('me', [TenantAuthController::class, 'me']);

        // Admin invite routes (require admin membership)
        Route::post('admin-invite', [TenantAuthController::class, 'sendAdminInvite'])
            ->middleware(['role_permission:tenant-admin|admin'])
            ->name('tenant.admin.invite');
    });
});
