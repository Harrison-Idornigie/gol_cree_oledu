<?php

use App\Http\Controllers\API\Auth\GoogleController;
use App\Http\Controllers\API\Auth\UserContextController;
use App\Http\Controllers\API\Landlord\Auth\NewTenantRegistrationController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix'     => 'auth',
   'as'         => 'auth.',
], function () {
    // Public tenant registration (self-service)
    Route::post('register-tenant-admin', [NewTenantRegistrationController::class, 'registerTenantAdmin'])
        ->middleware(['throttle:5,1'])
        ->name('register.tenant.admin');

    Route::get('validate-tenant-slug/{slug}', [NewTenantRegistrationController::class, 'validateSlug'])
        ->middleware(['throttle:20,1'])
        ->name('validate.tenant.slug');

    Route::get('tenant-creation-progress/{progressId}', [NewTenantRegistrationController::class, 'checkProgress'])
        ->middleware(['throttle:30,1'])
        ->name('tenant.creation.progress');

    // User context detection for unified login
    Route::post('detect-user-context', [UserContextController::class, 'detectUserContext'])
        ->middleware(['throttle:10,1'])
        ->name('detect.user.context');

    Route::post('validate-redirect-url', [UserContextController::class, 'validateRedirectUrl'])
        ->middleware(['throttle:20,1'])
        ->name('validate.redirect.url');

     // Google OAuth routes
    Route::get('google', [GoogleController::class, 'redirectToGoogle'])->name('google.redirect');
    Route::get('google/callback', [GoogleController::class, 'handleGoogleCallback'])->name('google.callback');
    Route::get('google/url', [GoogleController::class, 'getAuthUrl']);
    Route::post('google/callback', [GoogleController::class, 'handleGoogleCallback'])->name('google.callback.post');
    Route::post('google/tenant-callback', [GoogleController::class, 'handleTenantGoogleCallback'])->name('google.tenant.callback');

    // Token exchange endpoint for mobile apps and other clients
    Route::post('google/token', [GoogleController::class, 'exchangeToken'])->name('google.token.exchange');
});
 