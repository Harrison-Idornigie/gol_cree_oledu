<?php

use App\Http\Controllers\API\Admin\AdminAnalyticsController;
use App\Http\Controllers\API\Admin\AdminAuditController;
use App\Http\Controllers\API\Admin\AdminDashboardController;
use App\Http\Controllers\API\Admin\AdminExerciseController;
use App\Http\Controllers\API\Admin\AdminGamificationController;
use App\Http\Controllers\API\Admin\AdminGuideBookEntryController;
use App\Http\Controllers\API\Admin\AdminInviteController;
use App\Http\Controllers\API\Admin\AdminLanguageController;
use App\Http\Controllers\API\Admin\AdminLearningPathController;
use App\Http\Controllers\API\Admin\AdminLessonController;
use App\Http\Controllers\API\Admin\AdminMediaController;
use App\Http\Controllers\API\Admin\AdminProgressController;
use App\Http\Controllers\API\Admin\AdminRoleController;
use App\Http\Controllers\API\Admin\AdminSectionController;
use App\Http\Controllers\API\Admin\AdminSentenceController;
use App\Http\Controllers\API\Admin\AdminUnitController;
use App\Http\Controllers\API\Admin\AdminVocabularyController;
use App\Http\Controllers\API\Admin\AdminWordController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    // Dashboard & Analytics
    Route::get('dashboard', [AdminDashboardController::class, 'index']);
    Route::get('dashboard/engagement', [AdminDashboardController::class, 'engagement']);
    Route::get('dashboard/progress', [AdminDashboardController::class, 'progress']);
    Route::get('dashboard/achievements', [AdminDashboardController::class, 'achievements']);
    Route::get('dashboard/leaderboards', [AdminDashboardController::class, 'leaderboards']);
    Route::get('dashboard/content-health', [AdminDashboardController::class, 'contentHealth']);
    Route::get('analytics', [AdminAnalyticsController::class, 'index']);

    // Language Management
    Route::prefix('languages')->group(function () {
        Route::get('/', [AdminLanguageController::class, 'index']);
        Route::post('/', [AdminLanguageController::class, 'store']);
        Route::get('{language}', [AdminLanguageController::class, 'show']);
        Route::put('{language}', [AdminLanguageController::class, 'update']);
        Route::patch('{language}/status', [AdminLanguageController::class, 'updateStatus']);

        // Language Pairs
        Route::post('pairs', [AdminLanguageController::class, 'createPair']);
        Route::delete('pairs/{source}/{target}', [AdminLanguageController::class, 'deletePair']);
        Route::patch('pairs/{source}/{target}/status', [AdminLanguageController::class, 'updatePairStatus']);
    });

    // Word Management
    Route::prefix('words')->group(function () {
        Route::get('/', [AdminWordController::class, 'index']);
        Route::post('/', [AdminWordController::class, 'store']);
        Route::get('{word}', [AdminWordController::class, 'show']);
        Route::put('{word}', [AdminWordController::class, 'update']);
        Route::delete('{word}', [AdminWordController::class, 'destroy']);

        // Word Translations
        Route::post('{word}/translations', [AdminWordController::class, 'addTranslation']);
        Route::put('{word}/translations/{translation}', [AdminWordController::class, 'updateTranslation']);
        Route::delete('{word}/translations/{translation}', [AdminWordController::class, 'deleteTranslation']);

        // Word Audio
        Route::post('{word}/audio', [AdminWordController::class, 'uploadAudio']);
        Route::post('{word}/translations/{translation}/audio', [AdminWordController::class, 'uploadTranslationAudio']);
    });

    // Sentence Management
    Route::prefix('sentences')->group(function () {
        Route::get('/', [AdminSentenceController::class, 'index']);
        Route::post('/', [AdminSentenceController::class, 'store']);
        Route::get('{sentence}', [AdminSentenceController::class, 'show']);
        Route::put('{sentence}', [AdminSentenceController::class, 'update']);
        Route::delete('{sentence}', [AdminSentenceController::class, 'destroy']);

        // Sentence Translations
        Route::post('{sentence}/translations', [AdminSentenceController::class, 'addTranslation']);
        Route::put('{sentence}/translations/{translation}', [AdminSentenceController::class, 'updateTranslation']);
        Route::delete('{sentence}/translations/{translation}', [AdminSentenceController::class, 'deleteTranslation']);

        // Sentence Audio
        Route::post('{sentence}/audio', [AdminSentenceController::class, 'uploadAudio']);
        Route::post('{sentence}/audio-slow', [AdminSentenceController::class, 'uploadSlowAudio']);
        Route::post('{sentence}/translations/{translation}/audio', [AdminSentenceController::class, 'uploadTranslationAudio']);

        // Word Timings
        Route::put('{sentence}/word-timings', [AdminSentenceController::class, 'updateWordTimings']);
        Route::put('{sentence}/words/reorder', [AdminSentenceController::class, 'reorderWords']);
    });

    // Content Management - Full CRUD operations for admins
    Route::apiResource('learning-paths', AdminLearningPathController::class);
    Route::apiResource('units', AdminUnitController::class);
    Route::apiResource('lessons', AdminLessonController::class);
    Route::apiResource('sections', AdminSectionController::class);
    Route::apiResource('exercises', AdminExerciseController::class);

    Route::apiResource('vocabulary', AdminVocabularyController::class);
    Route::apiResource('guide-entries', AdminGuideBookEntryController::class);

    // Review Workflow for Learning Paths
    Route::prefix('learning-paths')->group(function () {
        Route::post('{learning_path}/submit-for-review', [AdminLearningPathController::class, 'submitForReview']);
        Route::post('{learning_path}/approve-review', [AdminLearningPathController::class, 'approveReview']);
        Route::post('{learning_path}/reject-review', [AdminLearningPathController::class, 'rejectReview']);
        Route::patch('{learning_path}/status', [AdminLearningPathController::class, 'updateStatus']);
        Route::post('{learning_path}/reorder-units', [AdminLearningPathController::class, 'reorderUnits']);
    });

    // Review Workflow for Units
    Route::prefix('units')->group(function () {
        Route::post('{unit}/submit-for-review', [AdminUnitController::class, 'submitForReview']);
        Route::post('{unit}/approve-review', [AdminUnitController::class, 'approveReview']);
        Route::post('{unit}/reject-review', [AdminUnitController::class, 'rejectReview']);
        Route::patch('{unit}/status', [AdminUnitController::class, 'updateStatus']);
        Route::post('{unit}/reorder-lessons', [AdminUnitController::class, 'reorderLessons']);
    });

    // Review Workflow for Lessons
    Route::prefix('lessons')->group(function () {
        Route::post('{lesson}/submit-for-review', [AdminLessonController::class, 'submitForReview']);
        Route::post('{lesson}/approve-review', [AdminLessonController::class, 'approveReview']);
        Route::post('{lesson}/reject-review', [AdminLessonController::class, 'rejectReview']);
        Route::patch('{lesson}/status', [AdminLessonController::class, 'updateStatus']);
        Route::post('{lesson}/reorder-sections', [AdminLessonController::class, 'reorderSections']);
    });

    // Review Workflow for Sections
    Route::prefix('sections')->group(function () {
        Route::post('{section}/submit-for-review', [AdminSectionController::class, 'submitForReview']);
        Route::post('{section}/approve-review', [AdminSectionController::class, 'approveReview']);
        Route::post('{section}/reject-review', [AdminSectionController::class, 'rejectReview']);
        Route::patch('{section}/status', [AdminSectionController::class, 'updateStatus']);
        Route::post('{section}/reorder-exercises', [AdminSectionController::class, 'reorderExercises']);
    });

    // Progress Management
    Route::prefix('progress')->group(function () {
        Route::get('overview', [AdminProgressController::class, 'overview']);
        Route::get('users/{user}', [AdminProgressController::class, 'userProgress']);
        Route::get('content/{type}/{id}', [AdminProgressController::class, 'contentProgress']);
    });

    // Media Management
    Route::prefix('media')->group(function () {
        Route::post('upload', [AdminMediaController::class, 'upload']);
        Route::delete('{media}', [AdminMediaController::class, 'destroy']);
    });

    // User Management
    Route::prefix('users')->group(function () {
        Route::post('invite', [AdminInviteController::class, 'send']);
        Route::delete('invite/{invite}', [AdminInviteController::class, 'cancel']);
        Route::post('invite/{invite}/resend', [AdminInviteController::class, 'resend']);
    });

    // Roles & Permissions
    Route::apiResource('roles', AdminRoleController::class);
    Route::post('roles/{role}/permissions', [AdminRoleController::class, 'updatePermissions']);

    // Audit Logs
    Route::get('audit-logs', [AdminAuditController::class, 'index']);
    Route::get('audit-logs/export', [AdminAuditController::class, 'export']);
    Route::get('audit-logs/{log}', [AdminAuditController::class, 'show']);

    // Gamification Management
    Route::prefix('gamification')->group(function () {
        Route::post('achievements', [AdminGamificationController::class, 'createAchievement']);
        Route::put('achievements/{achievement}', [AdminGamificationController::class, 'updateAchievement']);
        Route::post('xp-rules', [AdminGamificationController::class, 'configureXpRules']);
        Route::post('leagues', [AdminGamificationController::class, 'configureLeagues']);
        Route::post('streak-rules', [AdminGamificationController::class, 'configureStreakRules']);
        Route::post('daily-goals', [AdminGamificationController::class, 'configureDailyGoals']);
        Route::post('bonus-events', [AdminGamificationController::class, 'createBonusEvent']);
        Route::get('statistics', [AdminGamificationController::class, 'getStatistics']);
    });
});
