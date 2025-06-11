<?php

use App\Http\Controllers\API\Landlord\Auth\CentralLoginController;
use App\Http\Controllers\API\Landlord\Auth\CentralLogoutController;
use App\Http\Controllers\API\Landlord\Auth\CentralUserRegisterController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix'     => 'auth',
    'as'         => 'auth.',
], function () {
    // Central authentication routes (for super admins)
    Route::post('central-login', [CentralLoginController::class, 'login']);
    Route::middleware('auth:central')->group(function () {
        Route::post('central-logout', [CentralLogoutController::class, 'logout']);
        Route::get('central-me', [CentralLoginController::class, 'me']);
    });

    // Central user registration (for super admins)
    Route::post('register', [CentralUserRegisterController::class, 'register'])->name('central.user.register');
});
//
// Email verification routes
// Route::get('email/verify/{id}/{hash}', [TenantVerificationController::class, 'verify'])
//     ->middleware(['signed', 'throttle:6,1'])
//     ->name('verification.verify');
//
// Password reset routes
// Route::post('password/email', [TenantForgotPasswordController::class, 'sendResetLinkEmail'])
//     ->name('password.email');
// Route::post('password/reset', [TenantResetPasswordController::class, 'reset'])
//     ->name('password.reset');