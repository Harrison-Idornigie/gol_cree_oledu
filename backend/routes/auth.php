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

Route::group([
    'prefix'     => 'auth',
    'as'         => 'auth.',
    'middleware' => 'api',
], function () {
    // Authentication routes
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
});
