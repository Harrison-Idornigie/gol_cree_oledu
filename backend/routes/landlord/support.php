<?php

use App\Http\Controllers\API\Landlord\SystemUserController;
use App\Http\Controllers\API\Landlord\Support\SupportAccessController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Support Access Routes
|--------------------------------------------------------------------------
|
| These routes handle support access functionality for central staff
| to access tenant contexts for customer support purposes.
|
*/

// Central Support Management Routes (Super Admin only)
Route::group([
    'prefix' => 'super-admin/support',
    'middleware' => ['auth:central', 'verified'],
], function () {
    
    // Generate support access token
    Route::post('access/generate', [SystemUserController::class, 'generateSupportAccess'])
        ->name('support.access.generate');
    
    // Get support access audit log
    Route::get('access/audit', [SystemUserController::class, 'getSupportAccessAudit'])
        ->name('support.access.audit');
    
    // Revoke support access token
    Route::delete('access/{token}', [SystemUserController::class, 'revokeSupportAccess'])
        ->name('support.access.revoke');
});

// Support Access Authentication Routes (Public)
Route::group([
    'prefix' => 'support',
], function () {
    
    // Use support access token to authenticate
    Route::post('access/{token}', [SupportAccessController::class, 'useSupportAccess'])
        ->name('support.access.use');
    
    // Validate support access token
    Route::get('access/{token}/validate', [SupportAccessController::class, 'validateSupportToken'])
        ->name('support.access.validate');
});

// Support Session Management Routes (Tenant Context)
Route::group([
    'prefix' => 'support',
    'middleware' => ['auth:tenant'],
], function () {
    
    // Get current support context
    Route::get('context', [SupportAccessController::class, 'getSupportContext'])
        ->name('support.context');
    
    // End support access session
    Route::post('end-session', [SupportAccessController::class, 'endSupportAccess'])
        ->name('support.session.end');
});
