<?php

use Illuminate\Support\Facades\Route;

/**
 * Web Routes
 *
 * These routes handle web-based requests and should return HTML responses.
 * For API endpoints, use routes/api.php instead.
 */

Route::get('/', function () {
    // Check if request expects JSON (API client)
    if (request()->expectsJson() || request()->is('api/*')) {
        return response()->json([
            'message' => 'Welcome to the GOL 2025 API!',
            'version' => config('app.version', '1.0.0'),
            'status' => 'operational',
            'endpoints' => [
                'api' => url('/api'),
                'health' => url('/api/public/health'),
                'docs' => url('/docs'), // If you have API documentation
            ]
        ]);
    }

    // Return HTML landing page for browser requests
    return view('welcome');
})->name('home');

// Health check endpoint for web context
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'version' => config('app.version', '1.0.0'),
        'environment' => app()->environment(),
    ]);
})->name('health');

// Fallback route for undefined web routes
Route::fallback(function () {
    if (request()->expectsJson()) {
        return response()->json([
            'error' => 'Endpoint not found',
            'message' => 'The requested endpoint does not exist.'
        ], 404);
    }

    // For web requests, you might want to redirect to frontend or show a 404 page
    return response()->view('errors.404', [], 404);
});