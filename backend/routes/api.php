<?php

use App\Http\Controllers\API\LearningPathController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::get('learning-paths', [LearningPathController::class, 'index']);
Route::get('learning-paths/{learningPath}', [LearningPathController::class, 'show']);
Route::get('learning-paths/level/{level}', [LearningPathController::class, 'byLevel']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Learning paths management
    Route::middleware('can:manage-learning-paths')->group(function () {
        Route::post('learning-paths', [LearningPathController::class, 'store']);
        Route::put('learning-paths/{learningPath}', [LearningPathController::class, 'update']);
        Route::delete('learning-paths/{learningPath}', [LearningPathController::class, 'destroy']);
        Route::patch('learning-paths/{learningPath}/status', [LearningPathController::class, 'updateStatus']);
    });

    // User progress
    Route::get('learning-paths/{learningPath}/progress', [LearningPathController::class, 'progress']);
});

// Health check
Route::get('health', function () {
    return response()->json(['status' => 'healthy']);
});