<?php
use App\Http\Controllers\API\ExerciseController;
use App\Http\Controllers\API\GuideController;
use App\Http\Controllers\API\LanguageController;
use App\Http\Controllers\API\LearningPathController;
use App\Http\Controllers\API\LessonController;
use App\Http\Controllers\API\QuizController;
use App\Http\Controllers\API\QuizQuestionController;
use App\Http\Controllers\API\SectionController;
use App\Http\Controllers\API\UnitController;
use App\Http\Controllers\API\UserLanguageController;
use App\Http\Controllers\API\UserProgressController;
use App\Http\Controllers\API\UserSettingsController;
use App\Http\Controllers\API\VocabularyController;
use Illuminate\Support\Facades\Route;

// All Google Auth Routes are now in auth.php

// Routes that require authentication but not email verification
Route::middleware(['auth:sanctum'])->group(function () {
    // User Progress Routes - Allow users to track their own progress even without verification
    Route::prefix('progress')->group(function () {
        Route::get('/', [UserProgressController::class, 'index']);
        Route::post('/{type}/{id}', [UserProgressController::class, 'store']);
        Route::get('/{type}/{id}', [UserProgressController::class, 'show']);
        Route::put('/{type}/{id}', [UserProgressController::class, 'update']);
    });
});

// Routes that require both authentication and email verification
Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    // Learning Content Routes - Read-only access for regular users
    // These routes should only provide access to published content

    // Languages
    Route::prefix('languages')->group(function () {
        Route::get('/', [LanguageController::class, 'index']);
        Route::get('/with-learning-paths', [LanguageController::class, 'withLearningPaths']);
        Route::get('/{language}', [LanguageController::class, 'show']);
        Route::get('/{language}/learning-paths', [LanguageController::class, 'learningPaths']);
        Route::get('/{language}/proficiency-levels', [LanguageController::class, 'proficiencyLevels']);
        Route::get('/{language}/progress', [LanguageController::class, 'userProgress']);
        Route::get('/{language}/dashboard', [LanguageController::class, 'dashboard']);
    });

    // User Selected Languages
    Route::prefix('user/selected-languages')->group(function () {
        Route::get('/', [UserLanguageController::class, 'index']);
        Route::post('/', [UserLanguageController::class, 'store']);
        Route::delete('/{languageId}', [UserLanguageController::class, 'destroy']);
        Route::patch('/{languageId}/set-primary', [UserLanguageController::class, 'setPrimary']);
    });

    // User Settings Routes
    Route::prefix('user/settings')->group(function () {
        Route::get('/', [UserSettingsController::class, 'getSettings']);
        Route::get('/languages', [UserSettingsController::class, 'getAvailableLanguages']);
        Route::patch('/interface-language', [UserSettingsController::class, 'updateInterfaceLanguage']);
    });

    // Learning Paths
    Route::get('learning-paths', [LearningPathController::class, 'index']);
    Route::get('learning-paths/{learningPath}', [LearningPathController::class, 'show']);
    Route::get('learning-paths/{learningPath}/progress', [LearningPathController::class, 'progress']);
    Route::post('learning-paths/{learningPath}/enroll', [LearningPathController::class, 'enroll']);
    Route::get('learning-paths/by-level/{level}', [LearningPathController::class, 'byLevel']);

    // Units
    Route::get('units', [UnitController::class, 'index']);
    Route::get('units/{unit}', [UnitController::class, 'show']);

    // Lessons
    Route::get('lessons', [LessonController::class, 'index']);
    Route::get('lessons/{lesson}', [LessonController::class, 'show']);

    // Sections
    Route::get('sections', [SectionController::class, 'index']);
    Route::get('sections/{section}', [SectionController::class, 'show']);

    // Exercises
    Route::get('exercises', [ExerciseController::class, 'index']);
    Route::get('exercises/{exercise}', [ExerciseController::class, 'show']);
    Route::post('exercises/{exercise}/check', [ExerciseController::class, 'checkAnswer']);
    Route::get('exercises/{exercise}/statistics', [ExerciseController::class, 'statistics']);

    // Quizzes
    Route::get('quizzes', [QuizController::class, 'index']);
    Route::get('quizzes/{quiz}', [QuizController::class, 'show']);
    Route::post('quizzes/{quiz}/submit', [QuizController::class, 'submit']);
    Route::get('quizzes/{quiz}/history', [QuizController::class, 'history']);
    Route::get('quizzes/{quiz}/statistics', [QuizController::class, 'statistics']);

    // Quiz Questions
    Route::get('quiz-questions', [QuizQuestionController::class, 'index']);
    Route::get('quiz-questions/{quizQuestion}', [QuizQuestionController::class, 'show']);

    // Vocabulary
    Route::get('vocabulary', [VocabularyController::class, 'index']);
    Route::get('vocabulary/{vocabulary}', [VocabularyController::class, 'show']);

    // Guide Entries
    Route::get('guide-entries', [GuideController::class, 'index']);
    Route::get('guide-entries/{guideEntry}', [GuideController::class, 'show']);

});
