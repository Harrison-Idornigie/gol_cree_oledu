<?php

use App\Http\Controllers\API\Landlord\Auth\CentralLoginController;
use App\Http\Controllers\API\Landlord\Auth\CentralLogoutController;
use App\Http\Controllers\API\Landlord\Auth\CentralUserRegisterController;
use App\Http\Controllers\API\Tenant\Auth\TenantForgotPasswordController;
use App\Http\Controllers\API\Tenant\Auth\TenantLoginController;
use App\Http\Controllers\API\Tenant\Auth\TenantLogoutController;
use App\Http\Controllers\API\Tenant\Auth\TenantResetPasswordController;
use App\Http\Controllers\API\Tenant\Auth\TenantVerificationController;
use App\Http\Controllers\API\Tenant\TenantUserController;
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
        Route::post('tenant-login', [TenantLoginController::class, 'login'])->name('tenant.login');
        Route::post('tenant-user-tenants', [TenantLoginController::class, 'getUserTenants'])->name('tenant.user.tenants');
        Route::middleware('auth:tenant')->group(function () {
            Route::post('tenant-logout', [TenantLogoutController::class, 'logout'])->name('tenant.logout');
        });
    });

   
    Route::get('email/verify/{id}/{hash}', [TenantVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    // Password reset routes
    Route::post('password/email', [TenantForgotPasswordController::class, 'sendResetLinkEmail'])
        ->name('password.email');
    Route::post('password/reset', [TenantResetPasswordController::class, 'reset'])
        ->name('password.reset');

    // Protected routes that require tenant authentication
    Route::middleware('auth:tenant')->group(function () {
        Route::post('email/verification-notification', [TenantVerificationController::class, 'sendVerificationEmail'])
            ->middleware(['throttle:6,1'])
            ->name('verification.send');
        Route::get('me', [TenantUserController::class, 'me']);
    });

  

  
});
