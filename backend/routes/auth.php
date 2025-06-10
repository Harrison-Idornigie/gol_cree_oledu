<?php

use App\Http\Controllers\API\Auth\ForgotPasswordController;
use App\Http\Controllers\API\Auth\GoogleController;
use App\Http\Controllers\API\Auth\LoginController;
use App\Http\Controllers\API\Auth\LogoutController;
use App\Http\Controllers\API\Auth\RegisterController;
use App\Http\Controllers\API\Auth\ResetPasswordController;
use App\Http\Controllers\API\Auth\TenantRegistrationController;
use App\Http\Controllers\API\Auth\VerificationController;
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
    'middleware' => ['api', \App\Http\Middleware\Tenant\InitializeTenancyByPathOrDomain::class],
], function () {
    // Authentication routes (work in both central and tenant contexts)
    Route::post('login', [LoginController::class, 'login']);
    Route::post('register', [RegisterController::class, 'register']);

    // Email verification routes
    Route::get('email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    // Password reset routes
    Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])
        ->name('password.email');
    Route::post('password/reset', [ResetPasswordController::class, 'reset'])
        ->name('password.reset');

    // Protected routes that require authentication
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [LogoutController::class, 'logout']);
        Route::post('email/verification-notification', [VerificationController::class, 'sendVerificationEmail'])
            ->middleware(['throttle:6,1'])
            ->name('verification.send');
        Route::get('me', [\App\Http\Controllers\API\Auth\UserController::class, 'me']);
    });

    // Google OAuth routes
    Route::get('google', [GoogleController::class, 'redirectToGoogle'])->name('google.redirect');
    Route::get('google/callback', [GoogleController::class, 'handleGoogleCallback'])->name('google.callback');
    Route::get('google/url', [GoogleController::class, 'getAuthUrl']);
    Route::post('google/callback', [GoogleController::class, 'handleGoogleCallback'])->name('google.callback.post');
    Route::post('google/tenant-callback', [GoogleController::class, 'handleTenantGoogleCallback'])->name('google.tenant.callback');

    // Token exchange endpoint for mobile apps and other clients
    Route::post('google/token', [GoogleController::class, 'exchangeToken'])->name('google.token.exchange');

    // Tenant registration routes
    Route::post('register-tenant-admin', [TenantRegistrationController::class, 'registerTenantAdmin']);
    Route::get('validate-tenant-slug/{slug}', [TenantRegistrationController::class, 'validateSlug']);
    Route::get('tenant-creation-progress/{progressId}', [TenantRegistrationController::class, 'checkProgress']);
});
