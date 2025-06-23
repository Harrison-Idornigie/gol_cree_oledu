<?php

use App\Http\Controllers\API\Tenant\Team\TeamContentController;
use App\Http\Controllers\API\Tenant\Team\TeamContentScaffoldingController;
use App\Http\Controllers\API\Tenant\Team\TeamCurriculumTemplateController;
use App\Http\Controllers\API\Tenant\Team\TeamExerciseController;
use App\Http\Controllers\API\Tenant\Team\TeamGamificationController;
use App\Http\Controllers\API\Tenant\Team\TeamGuideBookEntryController;
use App\Http\Controllers\API\Tenant\Team\TeamLanguageController;
use App\Http\Controllers\API\Tenant\Team\TeamLearningPathController;
use App\Http\Controllers\API\Tenant\Team\TeamLessonController;
use App\Http\Controllers\API\Tenant\Team\TeamMediaController;
use App\Http\Controllers\API\Tenant\Team\TeamProgressController;
use App\Http\Controllers\API\Tenant\Team\TeamSentenceController;
use App\Http\Controllers\API\Tenant\Team\TeamTopicController;
use App\Http\Controllers\API\Tenant\Team\TeamUnitController;
use App\Http\Controllers\API\Tenant\Team\TeamVocabularyController;
use App\Http\Controllers\API\Tenant\Team\TeamWordController;
use Illuminate\Support\Facades\Route;

/**
 * Team Routes
 *
 * These routes are for teams who can create and manage content
 * within their tenant space.
 *
 * URL Pattern: api/{tenant-slug}/team/*
 */

Route::prefix('team')->middleware(['auth:tenant', 'verified-grace'])->group(function () {

    // Language Management
    Route::prefix('languages')->group(function () {
        Route::get('/', [TeamLanguageController::class, 'index']);
        Route::post('/', [TeamLanguageController::class, 'store']);
        Route::get('{language}', [TeamLanguageController::class, 'show']);
        Route::put('{language}', [TeamLanguageController::class, 'update']);
        Route::patch('{language}/status', [TeamLanguageController::class, 'updateStatus']);

        // Language Pairs
        Route::post('pairs', [TeamLanguageController::class, 'createPair']);
        Route::delete('pairs/{source}/{target}', [TeamLanguageController::class, 'deletePair']);
        Route::patch('pairs/{source}/{target}/status', [TeamLanguageController::class, 'updatePairStatus']);
    });

    // Word Management
    Route::prefix('words')->group(function () {
        Route::get('/', [TeamWordController::class, 'index']);
        Route::post('/', [TeamWordController::class, 'store']);
        Route::get('{word}', [TeamWordController::class, 'show']);
        Route::put('{word}', [TeamWordController::class, 'update']);
        Route::delete('{word}', [TeamWordController::class, 'destroy']);

        // Bulk Operations
        Route::post('bulk', [TeamWordController::class, 'bulkStore']);
        Route::put('bulk', [TeamWordController::class, 'bulkUpdate']);
        Route::post('bulk-delete', [TeamWordController::class, 'bulkDelete']);
        Route::get('export', [TeamWordController::class, 'export']);

        // Word Translations
        Route::post('{word}/translations', [TeamWordController::class, 'addTranslation']);
        Route::put('{word}/translations/{translation}', [TeamWordController::class, 'updateTranslation']);
        Route::delete('{word}/translations/{translation}', [TeamWordController::class, 'deleteTranslation']);

        // Word Audio
        Route::post('{word}/audio', [TeamWordController::class, 'uploadAudio']);
        Route::post('{word}/translations/{translation}/audio', [TeamWordController::class, 'uploadTranslationAudio']);

        // Word Constraint System
        Route::get('available/{exerciseType?}', [TeamWordController::class, 'getAvailableWords']);
        Route::post('validate-constraints', [TeamWordController::class, 'validateWordConstraints']);
    });

    // Sentence Management
    Route::prefix('sentences')->group(function () {
        Route::get('/', [TeamSentenceController::class, 'index']);
        Route::post('/', [TeamSentenceController::class, 'store']);
        Route::get('available-words', [TeamSentenceController::class, 'getAvailableWords']);
        Route::post('validate-words', [TeamSentenceController::class, 'validateSentenceWords']);

        // New AI-assisted endpoints
        Route::post('analyze-text', [TeamSentenceController::class, 'analyzeSentenceText']);
        Route::post('create-with-mapping', [TeamSentenceController::class, 'createWithAutoMapping']);

        Route::get('{sentence}', [TeamSentenceController::class, 'show']);
        Route::put('{sentence}', [TeamSentenceController::class, 'update']);
        Route::delete('{sentence}', [TeamSentenceController::class, 'destroy']);

        // Sentence Translations
        Route::post('{sentence}/translations', [TeamSentenceController::class, 'addTranslation']);
        Route::put('{sentence}/translations/{translation}', [TeamSentenceController::class, 'updateTranslation']);
        Route::delete('{sentence}/translations/{translation}', [TeamSentenceController::class, 'deleteTranslation']);

        // Sentence Audio
        Route::post('{sentence}/audio', [TeamSentenceController::class, 'uploadAudio']);
        Route::post('{sentence}/audio-slow', [TeamSentenceController::class, 'uploadSlowAudio']);
        Route::post('{sentence}/translations/{translation}/audio', [TeamSentenceController::class, 'uploadTranslationAudio']);

        // Word Timings
        Route::put('{sentence}/word-timings', [TeamSentenceController::class, 'updateWordTimings']);
        Route::put('{sentence}/words/reorder', [TeamSentenceController::class, 'reorderWords']);
    });

    // Learning Management - Full CRUD operations for teams
    Route::apiResource('learning-paths', TeamLearningPathController::class);
    Route::apiResource('units', TeamUnitController::class);
    Route::apiResource('topics', TeamTopicController::class);
    Route::apiResource('lessons', TeamLessonController::class);
    Route::apiResource('exercises', TeamExerciseController::class);
    Route::apiResource('vocabulary', TeamVocabularyController::class);
    Route::apiResource('guide-entries', TeamGuideBookEntryController::class);

    // Review Workflow for Learning Paths
    Route::prefix('learning-paths')->group(function () {
        Route::post('{learning_path}/submit-for-review', [TeamLearningPathController::class, 'submitForReview']);
        Route::patch('{learning_path}/status', [TeamLearningPathController::class, 'updateStatus']);
        Route::post('{learning_path}/reorder-units', [TeamLearningPathController::class, 'reorderUnits']);
    });

    // Review Workflow for Units
    Route::prefix('units')->group(function () {
        Route::post('{unit}/submit-for-review', [TeamUnitController::class, 'submitForReview']);
        Route::patch('{unit}/status', [TeamUnitController::class, 'updateStatus']);
        Route::post('{unit}/reorder-topics', [TeamUnitController::class, 'reorderTopics']);
    });

    // Review Workflow for Topics
    Route::prefix('topics')->group(function () {
        Route::post('{topic}/submit-for-review', [TeamTopicController::class, 'submitForReview']);
        Route::patch('{topic}/status', [TeamTopicController::class, 'updateStatus']);
        Route::post('{topic}/reorder-lessons', [TeamTopicController::class, 'reorderLessons']);
    });

    // Review Workflow for Lessons
    Route::prefix('lessons')->group(function () {
        Route::post('{lesson}/submit-for-review', [TeamLessonController::class, 'submitForReview']);
        Route::patch('{lesson}/status', [TeamLessonController::class, 'updateStatus']);
        Route::post('{lesson}/reorder-exercises', [TeamLessonController::class, 'reorderExercises']);
    });

    // Progress Management (for team's content)
    Route::prefix('progress')->group(function () {
        Route::get('overview', [TeamProgressController::class, 'overview']);
        Route::get('my-content', [TeamProgressController::class, 'myContentProgress']);
        Route::get('students', [TeamProgressController::class, 'studentsProgress']);
        Route::get('content/{type}/{id}', [TeamProgressController::class, 'contentProgress']);
    });

    // Media Management
    Route::prefix('media')->group(function () {
        Route::post('upload', [TeamMediaController::class, 'upload']);
        Route::delete('{media}', [TeamMediaController::class, 'destroy']);
        Route::get('my-media', [TeamMediaController::class, 'myMedia']);
    });

    // Curriculum Template Management
    Route::prefix('curriculum-templates')->group(function () {
        Route::get('/', [TeamCurriculumTemplateController::class, 'index']);
        Route::get('recommendations', [TeamCurriculumTemplateController::class, 'recommendations']);
        Route::get('{template}', [TeamCurriculumTemplateController::class, 'show']);
        Route::get('{template}/preview', [TeamCurriculumTemplateController::class, 'preview']);
        Route::get('{template}/effectiveness', [TeamCurriculumTemplateController::class, 'effectiveness']);
        Route::get('{template}/usage', [TeamCurriculumTemplateController::class, 'usage']);
        Route::post('{template}/instantiate', [TeamCurriculumTemplateController::class, 'instantiate']);
        Route::post('{template}/customize', [TeamCurriculumTemplateController::class, 'customize']);
        Route::post('{template}/validate', [TeamCurriculumTemplateController::class, 'validate']);
    });

    // Content Scaffolding (Automated Content Generation)
    Route::prefix('scaffolding')->group(function () {
        // Exercise Generation
        Route::post('generate-exercises', [TeamContentScaffoldingController::class, 'generateExercises']);
        Route::post('generate-from-sentences', [TeamContentScaffoldingController::class, 'generateFromSentences']);
        Route::post('preview-exercises', [TeamContentScaffoldingController::class, 'previewExercises']);

        // Lesson Generation
        Route::post('generate-lesson', [TeamContentScaffoldingController::class, 'generateLesson']);

        // Bulk Operations
        Route::post('bulk-generate', [TeamContentScaffoldingController::class, 'bulkGenerate']);
    });

    // Gamification Management (limited for teams)
    Route::prefix('gamification')->group(function () {
        Route::get('achievements', [TeamGamificationController::class, 'getAchievements']);
        Route::get('statistics', [TeamGamificationController::class, 'getStatistics']);
    });
});
