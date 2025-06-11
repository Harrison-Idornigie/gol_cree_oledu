<?php

use App\Http\Controllers\API\Auth\GoogleController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix'     => 'auth',
   'as'         => 'auth.',
], function () {
     // Google OAuth routes
    Route::get('google', [GoogleController::class, 'redirectToGoogle'])->name('google.redirect');
    Route::get('google/callback', [GoogleController::class, 'handleGoogleCallback'])->name('google.callback');
    Route::get('google/url', [GoogleController::class, 'getAuthUrl']);
    Route::post('google/callback', [GoogleController::class, 'handleGoogleCallback'])->name('google.callback.post');
    Route::post('google/tenant-callback', [GoogleController::class, 'handleTenantGoogleCallback'])->name('google.tenant.callback');

    // Token exchange endpoint for mobile apps and other clients
    Route::post('google/token', [GoogleController::class, 'exchangeToken'])->name('google.token.exchange');
});
 