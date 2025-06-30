<?php

use App\Http\Controllers\API\Tenant\Student\StudentExerciseController;
use App\Http\Controllers\API\Tenant\Student\StudentGuideController;
use App\Http\Controllers\API\Tenant\Student\StudentLanguageController;
use App\Http\Controllers\API\Tenant\Student\StudentLearningPathController;
use App\Http\Controllers\API\Tenant\Student\StudentLessonController;
use App\Http\Controllers\API\Tenant\Student\StudentTopicController;
use App\Http\Controllers\API\Tenant\Student\StudentUnitController;
use App\Http\Controllers\API\Tenant\Student\StudentUserLanguageController;
use App\Http\Controllers\API\Tenant\Student\StudentUserProgressController;
use App\Http\Controllers\API\Tenant\Student\StudentUserSettingsController;
use App\Http\Controllers\API\Tenant\Student\StudentVocabularyController;
use App\Http\Controllers\API\Tenant\Student\StudentWordController;

use Illuminate\Support\Facades\Route;

/**
 * Student Routes
 *
 * These routes are for students who can access learning content
 * within their tenant space.
 *
 * URL Pattern: api/{tenant-slug}/student/*
 */

// Routes that require authentication but not email verification
Route::prefix('student')->middleware(['auth:tenant'])->group(function () {
    // User Progress Routes - Allow users to track their own progress even without verification
    Route::prefix('progress')->group(function () {
        Route::get('/', [StudentUserProgressController::class, 'index']);
        Route::post('/{type}/{id}', [StudentUserProgressController::class, 'store']);
        Route::get('/{type}/{id}', [StudentUserProgressController::class, 'show']);
        Route::put('/{type}/{id}', [StudentUserProgressController::class, 'update']);
    });
});

// Routes that require both authentication and email verification
Route::prefix('student')->middleware(['auth:tenant', \App\Http\Middleware\Tenant\EnsureEmailIsVerifiedWithGracePeriod::class])->group(function () {
    // Enforce integer IDs for lessons and topics
    Route::pattern('lesson', '[0-9]+');
    Route::pattern('topic', '[0-9]+');
    // Learning Content Routes - Read-only access for regular users
    // These routes should only provide access to published content

    // Languages
    Route::prefix('languages')->group(function () {
        Route::get('/', [StudentLanguageController::class, 'index']);
        Route::get('/with-learning-paths', [StudentLanguageController::class, 'withLearningPaths']);
        Route::get('/{language}', [StudentLanguageController::class, 'show']);
        Route::get('/{language}/learning-paths', [StudentLanguageController::class, 'learningPaths']);
        Route::get('/{language}/proficiency-levels', [StudentLanguageController::class, 'proficiencyLevels']);
        Route::get('/{language}/progress', [StudentLanguageController::class, 'userProgress']);
        Route::get('/{language}/dashboard', [StudentLanguageController::class, 'dashboard']);
    });

    // User Selected Languages
    Route::prefix('user/selected-languages')->group(function () {
        Route::get('/', [StudentUserLanguageController::class, 'index']);
        Route::post('/', [StudentUserLanguageController::class, 'store']);
        Route::delete('/{languageId}', [StudentUserLanguageController::class, 'destroy']);
        Route::patch('/{languageId}/set-primary', [StudentUserLanguageController::class, 'setPrimary']);
    });

    // User Settings Routes
    Route::prefix('user/settings')->group(function () {
        Route::get('/', [StudentUserSettingsController::class, 'getSettings']);
        Route::get('/languages', [StudentUserSettingsController::class, 'getAvailableLanguages']);
        Route::patch('/interface-language', [StudentUserSettingsController::class, 'updateInterfaceLanguage']);
    });

    // Learning Paths
    Route::get('learning-paths', [StudentLearningPathController::class, 'index']);
    Route::get('learning-paths/by-level/{level}', [StudentLearningPathController::class, 'byLevel']);
    Route::get('learning-paths/{learningPath}', [StudentLearningPathController::class, 'show']);
    Route::get('learning-paths/{learningPath}/progress', [StudentLearningPathController::class, 'progress']);
    Route::post('learning-paths/{learningPath}/enroll', [StudentLearningPathController::class, 'enroll']);

    // Units - Disable automatic model binding for these routes
    Route::get('learning-paths/{learningPathId}/units', [StudentUnitController::class, 'index'])->withoutMiddleware('Illuminate\Routing\Middleware\SubstituteBindings');
    Route::get('learning-paths/{learningPathId}/next-unit', [StudentUnitController::class, 'nextUnit'])->withoutMiddleware('Illuminate\Routing\Middleware\SubstituteBindings');
    Route::get('units/recommendations', [StudentUnitController::class, 'recommendations']);
    Route::get('units/{unitId}', [StudentUnitController::class, 'show'])->middleware('sequential-learning')->withoutMiddleware('Illuminate\Routing\Middleware\SubstituteBindings');
    Route::get('units/{unitId}/progress', [StudentUnitController::class, 'progress'])->withoutMiddleware('Illuminate\Routing\Middleware\SubstituteBindings');
    Route::get('units/{unitId}/topics', [StudentUnitController::class, 'topics'])->withoutMiddleware('Illuminate\Routing\Middleware\SubstituteBindings');
    Route::get('units/{unitId}/contents', [StudentUnitController::class, 'contents'])->withoutMiddleware('Illuminate\Routing\Middleware\SubstituteBindings');
    Route::post('units/{unitId}/start', [StudentUnitController::class, 'start'])->withoutMiddleware('Illuminate\Routing\Middleware\SubstituteBindings');
    Route::put('units/{unitId}/progress', [StudentUnitController::class, 'updateProgress'])->withoutMiddleware('Illuminate\Routing\Middleware\SubstituteBindings');
    Route::put('units/{unitId}/complete', [StudentUnitController::class, 'complete'])->withoutMiddleware('Illuminate\Routing\Middleware\SubstituteBindings');

    // Topics
    Route::get('units/{unit}/topics', [StudentTopicController::class, 'index']);
    Route::get('topics/{topic}', [StudentTopicController::class, 'show'])->middleware('sequential-learning');
    Route::get('topics/{topic}/progress', [StudentTopicController::class, 'progress']);

    // Lessons
    Route::get('topics/{topic}/lessons', [StudentLessonController::class, 'index']);
    Route::get('lessons/{lesson}', [StudentLessonController::class, 'show'])->middleware('sequential-learning');
    Route::get('lessons/{lesson}/progress', [StudentLessonController::class, 'progress']);

    // Exercises
    Route::get('exercises', [StudentExerciseController::class, 'index']);
    Route::get('exercises/{exercise}', [StudentExerciseController::class, 'show']);
    Route::post('exercises/{exercise}/check', [StudentExerciseController::class, 'checkAnswer']);
    Route::get('exercises/{exercise}/statistics', [StudentExerciseController::class, 'statistics']);

    // Exercise type-specific endpoints (consolidated into main controller)
    Route::post('exercises/{exercise}/submit-answer', [StudentExerciseController::class, 'submitAnswer']);
    Route::get('exercises/by-type/{type}', [StudentExerciseController::class, 'getByType']);
    Route::get('exercises/by-language/{languageCode}', [StudentExerciseController::class, 'getByLanguage']);

    // Vocabulary
    Route::prefix('vocabulary')->group(function () {
        Route::get('/', [StudentVocabularyController::class, 'index']);
        Route::get('/review', [StudentVocabularyController::class, 'reviewItems']);
        Route::get('/mistakes', [StudentVocabularyController::class, 'mistakeItems']);
        Route::get('/unit/{unitId}', [StudentVocabularyController::class, 'unitVocabulary']);
        Route::post('/{vocabulary}/check', [StudentVocabularyController::class, 'checkTranslation']);
        Route::get('/statistics', [StudentVocabularyController::class, 'statistics']);
        Route::get('/{vocabulary}', [StudentVocabularyController::class, 'show']);
    });

    // Words
    Route::prefix('words')->group(function () {
        Route::get('/', [StudentWordController::class, 'index']);
        Route::get('/{word}', [StudentWordController::class, 'show']);
        Route::get('/{word}/translations', [StudentWordController::class, 'translations']);
        Route::post('/batch', [StudentWordController::class, 'batch']);
    });

    // Guide Entries
    Route::get('guide-entries', [StudentGuideController::class, 'index']);
    Route::get('guide-entries/{guideEntry}', [StudentGuideController::class, 'show']);
});
