<?php

use Illuminate\Support\Facades\Route;

/**
 * General API Routes
 *
 * These routes are for general API functionality that doesn't require
 * specific role-based access or tenant isolation.
 */

// Public API routes (no authentication required)
Route::prefix('public')->group(function () {
    // Health check
    Route::get('health', function () {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0')
        ]);
    });

    // System information (limited)
    Route::get('info', function () {
        return response()->json([
            'app_name' => config('app.name'),
            'version' => config('app.version', '1.0.0'),
            'environment' => app()->environment(),
        ]);
    });
});

// Authenticated routes (no specific role required)
// Note: These routes will work with both central and tenant guards
Route::middleware(['auth:sanctum'])->group(function () {
    // User profile routes that work across all roles
    Route::get('profile', function () {
        return response()->json(auth()->user());
    });

    // Basic user settings
    Route::get('user/basic-info', function () {
        $user = auth()->user();
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'tenant_id' => $user->tenant_id,
            'email_verified_at' => $user->email_verified_at,
        ]);
    });
});