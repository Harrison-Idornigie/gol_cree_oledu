<?php

use App\Http\Controllers\API\Tenant\Admin\TenantAdminAnalyticsController;
use App\Http\Controllers\API\Tenant\Admin\TenantAdminAuditController;
use App\Http\Controllers\API\Tenant\Admin\TenantAdminDashboardController;
use App\Http\Controllers\API\Tenant\Admin\TenantAdminUserController;
use App\Http\Controllers\API\Tenant\Admin\TenantAdminMembershipController;
use App\Http\Controllers\API\Tenant\Admin\TenantAdminSettingsController;
use App\Http\Controllers\API\Tenant\Admin\TenantAdminContentController;
use Illuminate\Support\Facades\Route;

/**
 * Tenant Admin Routes
 *
 * These routes are for tenant administrators who can manage their district's
 * content, users, and settings within their tenant space.
 *
 * URL Pattern: api/{tenant-slug}/tenant-admin/*
 */

Route::prefix('tenant-admin')->middleware(['auth:tenant', 'verified'])->group(function () {

    // Dashboard & Analytics
    Route::get('dashboard', [TenantAdminDashboardController::class, 'index']);
    Route::get('analytics', [TenantAdminAnalyticsController::class, 'index']);
    Route::get('analytics/users', [TenantAdminAnalyticsController::class, 'userAnalytics']);
    Route::get('analytics/content', [TenantAdminAnalyticsController::class, 'contentAnalytics']);
    Route::get('analytics/engagement', [TenantAdminAnalyticsController::class, 'engagementAnalytics']);

    // Tenant Settings
    Route::prefix('settings')->group(function () {
        Route::get('/', [TenantAdminSettingsController::class, 'getTenantSettings']);
        Route::put('/', [TenantAdminSettingsController::class, 'updateTenantSettings']);
        Route::get('branding', [TenantAdminSettingsController::class, 'getBranding']);
        Route::put('branding', [TenantAdminSettingsController::class, 'updateBranding']);
        Route::get('features', [TenantAdminSettingsController::class, 'getFeatureSettings']);
        Route::put('features', [TenantAdminSettingsController::class, 'updateFeatureSettings']);
    });

    // User Management within Tenant
    Route::prefix('users')->group(function () {
        Route::get('/', [TenantAdminUserController::class, 'getUsers']);
        Route::get('teams', [TenantAdminUserController::class, 'getTeams']);
        Route::get('students', [TenantAdminUserController::class, 'getStudents']);
        Route::post('invite', [TenantAdminUserController::class, 'sendInvite']);
        Route::delete('invite/{invite}', [TenantAdminUserController::class, 'cancelInvite']);
        Route::post('invite/{invite}/resend', [TenantAdminUserController::class, 'resendInvite']);
        Route::patch('{user}/membership', [TenantAdminUserController::class, 'updateMembership']);
        Route::patch('{user}/status', [TenantAdminUserController::class, 'updateUserStatus']);
        Route::get('{user}/activity', [TenantAdminUserController::class, 'getUserActivity']);
    });

    // Memberships & Permissions Management
    Route::prefix('memberships')->group(function () {
        Route::get('/', [TenantAdminMembershipController::class, 'index']);
        Route::post('/', [TenantAdminMembershipController::class, 'store']);
        Route::get('{membership}', [TenantAdminMembershipController::class, 'show']);
        Route::put('{membership}', [TenantAdminMembershipController::class, 'update']);
        Route::delete('{membership}', [TenantAdminMembershipController::class, 'destroy']);
        Route::post('{membership}/permissions', [TenantAdminMembershipController::class, 'updatePermissions']);
    });

    // Audit Logs for Tenant
    Route::prefix('audit-logs')->group(function () {
        Route::get('/', [TenantAdminAuditController::class, 'index']);
        Route::get('export', [TenantAdminAuditController::class, 'export']);
        Route::get('{log}', [TenantAdminAuditController::class, 'show']);
        Route::get('summary', [TenantAdminAuditController::class, 'getSummary']);
    });

    // Content Overview (Read-only for tenant admins)
    Route::prefix('content')->group(function () {
        Route::get('overview', [TenantAdminContentController::class, 'contentOverview']);
        Route::get('learning-paths', [TenantAdminContentController::class, 'getLearningPaths']);
        Route::get('languages', [TenantAdminContentController::class, 'getLanguages']);
        Route::get('statistics', [TenantAdminContentController::class, 'getContentStatistics']);
        Route::get('health-check', [TenantAdminContentController::class, 'contentHealthCheck']);
    });

    // Reports
    Route::prefix('reports')->group(function () {
        Route::get('user-progress', [TenantAdminAnalyticsController::class, 'userProgressReport']);
        Route::get('content-usage', [TenantAdminAnalyticsController::class, 'contentUsageReport']);
        Route::get('engagement', [TenantAdminAnalyticsController::class, 'engagementReport']);
        Route::post('export', [TenantAdminAnalyticsController::class, 'exportReport']);
        Route::get('performance', [TenantAdminAnalyticsController::class, 'performanceReport']);
    });

});
