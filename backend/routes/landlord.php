<?php

use App\Http\Controllers\API\SuperAdmin\SystemTenantController;
use App\Http\Controllers\API\SuperAdmin\SystemAnalyticsController;
use App\Http\Controllers\API\SuperAdmin\SystemConfigurationController;
use App\Http\Controllers\API\SuperAdmin\SystemUserController;
use Illuminate\Support\Facades\Route;

/**
 * Landlord Routes
 *
 * These routes are for landlords who can manage multiple tenants
 * and have access to system-wide functionality.
 */

Route::prefix('super-admin')->middleware(['auth:sanctum', 'verified', 'role:super-admin'])->group(function () {

    // Tenant Management
    Route::prefix('tenants')->group(function () {
        Route::get('/', [SystemTenantController::class, 'index']);
        Route::post('/', [SystemTenantController::class, 'store']);
        Route::get('{tenant}', [SystemTenantController::class, 'show']);
        Route::put('{tenant}', [SystemTenantController::class, 'update']);
        Route::delete('{tenant}', [SystemTenantController::class, 'destroy']);
        Route::patch('{tenant}/status', [SystemTenantController::class, 'updateStatus']);
        Route::get('{tenant}/statistics', [SystemTenantController::class, 'statistics']);
        Route::post('{tenant}/reset-trial', [SystemTenantController::class, 'resetTrial']);
        Route::post('{tenant}/extend-subscription', [SystemTenantController::class, 'extendSubscription']);
    });

    // System-wide Analytics
    Route::prefix('analytics')->group(function () {
        Route::get('overview', [SystemAnalyticsController::class, 'systemOverview']);
        Route::get('tenant-usage', [SystemAnalyticsController::class, 'tenantUsage']);
        Route::get('performance-metrics', [SystemAnalyticsController::class, 'performanceMetrics']);
        Route::get('system-health', [SystemAnalyticsController::class, 'systemHealth']);
        Route::get('usage-trends', [SystemAnalyticsController::class, 'usageTrends']);
    });

    // System Configuration
    Route::prefix('system')->group(function () {
        Route::get('settings', [SystemConfigurationController::class, 'getSystemSettings']);
        Route::put('settings', [SystemConfigurationController::class, 'updateSystemSettings']);
        Route::get('health-check', [SystemConfigurationController::class, 'healthCheck']);
        Route::get('maintenance-mode', [SystemConfigurationController::class, 'getMaintenanceMode']);
        Route::post('maintenance-mode', [SystemConfigurationController::class, 'setMaintenanceMode']);
    });

    // Global User Management (across all tenants)
    Route::prefix('users')->group(function () {
        Route::get('/', [SystemUserController::class, 'getAllUsers']);
        Route::get('search', [SystemUserController::class, 'searchUsers']);
        Route::post('{user}/impersonate', [SystemUserController::class, 'impersonateUser']);
        Route::post('stop-impersonation', [SystemUserController::class, 'stopImpersonation']);
        Route::get('{user}/audit-trail', [SystemUserController::class, 'getUserAuditTrail']);
        Route::patch('{user}/suspend', [SystemUserController::class, 'suspendUser']);
        Route::patch('{user}/activate', [SystemUserController::class, 'activateUser']);
    });

});