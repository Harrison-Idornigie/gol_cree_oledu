<?php

namespace App\Http\Controllers\API\Tenant\Team;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\Tenants\Course\ContentScaffoldingService;
use App\Services\Tenants\Course\ExerciseService;
use App\Services\Tenants\Course\LessonService;
use App\Services\Tenants\Language\WordManagementService;
use App\Services\Tenants\Language\SentenceManagementService;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\ContentTemplate;
use App\Models\Tenants\Exercise;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Team Content Scaffolding Controller
 * 
 * Handles automated content generation and scaffolding for team members.
 * Access Level: Team (Teams/Content Creators)
 * Scope: Tenant-specific
 * 
 * This controller provides Duolingo-style automated content generation:
 * - Generate exercises from vocabulary words
 * - Create lessons from predefined word banks
 * - Scaffold content using templates
 * - Bulk generate content with language pair context
 */
class TeamContentScaffoldingController extends BaseAPIController
{
    use BelongsToTenant;

    protected ContentScaffoldingService $scaffoldingService;
    protected ExerciseService $exerciseService;
    protected LessonService $lessonService;
    protected WordManagementService $wordService;
    protected SentenceManagementService $sentenceService;

    public function __construct(
        ContentScaffoldingService $scaffoldingService,
        ExerciseService $exerciseService,
        LessonService $lessonService,
        WordManagementService $wordService,
        SentenceManagementService $sentenceService
    ) {
        $this->scaffoldingService = $scaffoldingService;
        $this->exerciseService = $exerciseService;
        $this->lessonService = $lessonService;
        $this->wordService = $wordService;
        $this->sentenceService = $sentenceService;

        // Apply middleware for all scaffolding operations
        $this->middleware('can:generateExercises,App\Policies\ContentScaffoldingPolicy');
    }

    /**
     * Generate exercises from vocabulary words.
     * Core Duolingo-style functionality for creating interactive exercises.
     */
    public function generateExercises(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'word_ids' => 'required|array|min:1',
                'word_ids.*' => 'exists:words,id',
                'exercise_types' => 'required|array|min:1',
                'exercise_types.*' => 'string|in:multiple_choice,fill_blank,matching,writing,speaking,listening',
                'source_language_id' => 'required|exists:languages,id',
                'target_language_id' => 'required|exists:languages,id',
                'lesson_id' => 'nullable|exists:lessons,id',
                'exercise_count' => 'nullable|integer|min:1|max:20',
                'difficulty_level' => 'nullable|string|in:beginner,intermediate,advanced',
                'save_exercises' => 'nullable|boolean',
            ]);

            $options = [
                'source_language_id' => $validated['source_language_id'],
                'target_language_id' => $validated['target_language_id'],
                'exercise_count' => $validated['exercise_count'] ?? 5,
                'difficulty_level' => $validated['difficulty_level'] ?? 'intermediate',
            ];

            $exercises = $this->scaffoldingService->generateExercisesFromVocabulary(
                $validated['word_ids'],
                $validated['exercise_types'],
                $options
            );

            // Save exercises if requested
            if ($validated['save_exercises'] ?? false) {
                $savedExercises = [];
                foreach ($exercises as $exerciseData) {
                    if (isset($validated['lesson_id'])) {
                        $exerciseData['lesson_id'] = $validated['lesson_id'];
                    }
                    $savedExercises[] = $this->exerciseService->createExercise($exerciseData, Auth::user());
                }
                $exercises = $savedExercises;
            }

            return $this->sendResponse($exercises, 'Exercises generated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to generate exercises.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Generate exercises from sentences for listening/comprehension practice.
     */
    public function generateFromSentences(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'sentence_ids' => 'required|array|min:1',
                'sentence_ids.*' => 'exists:sentences,id',
                'exercise_types' => 'required|array|min:1',
                'exercise_types.*' => 'string|in:listening,fill_blank,writing,conversation',
                'lesson_id' => 'nullable|exists:lessons,id',
                'exercise_count' => 'nullable|integer|min:1|max:10',
                'difficulty_level' => 'nullable|string|in:beginner,intermediate,advanced',
                'save_exercises' => 'nullable|boolean',
            ]);

            $options = [
                'exercise_count' => $validated['exercise_count'] ?? 3,
                'difficulty_level' => $validated['difficulty_level'] ?? 'intermediate',
            ];

            $exercises = $this->scaffoldingService->generateExercisesFromSentences(
                $validated['sentence_ids'],
                $validated['exercise_types'],
                $options
            );

            // Save exercises if requested
            if ($validated['save_exercises'] ?? false) {
                $savedExercises = [];
                foreach ($exercises as $exerciseData) {
                    if (isset($validated['lesson_id'])) {
                        $exerciseData['lesson_id'] = $validated['lesson_id'];
                    }
                    $savedExercises[] = $this->exerciseService->createExercise($exerciseData, Auth::user());
                }
                $exercises = $savedExercises;
            }

            return $this->sendResponse($exercises, 'Exercises generated from sentences successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to generate exercises from sentences.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Generate a complete lesson from a content template.
     */
    public function generateLesson(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'template_id' => 'required|exists:content_templates,id',
                'topic_id' => 'required|exists:topics,id',
                'vocabulary_ids' => 'required|array|min:1',
                'vocabulary_ids.*' => 'exists:words,id',
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'source_language_id' => 'required|exists:languages,id',
                'target_language_id' => 'required|exists:languages,id',
                'save_lesson' => 'nullable|boolean',
            ]);

            $template = ContentTemplate::findOrFail($validated['template_id']);

            $options = [
                'topic_id' => $validated['topic_id'],
                'title' => $validated['title'],
                'description' => $validated['description'],
                'source_language_id' => $validated['source_language_id'],
                'target_language_id' => $validated['target_language_id'],
            ];

            $lesson = $this->scaffoldingService->generateLessonFromTemplate(
                $template,
                $validated['vocabulary_ids'],
                $options
            );

            // Save lesson if requested
            if ($validated['save_lesson'] ?? false) {
                $lessonData = [
                    'topic_id' => $validated['topic_id'],
                    'template_id' => $template->id,
                    'title' => $lesson->title,
                    'description' => $lesson->description,
                ];
                $lesson = $this->lessonService->createLesson($lessonData, Auth::user());
            }

            return $this->sendResponse($lesson, 'Lesson generated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to generate lesson.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Preview exercises before generating them.
     */
    public function previewExercises(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'word_ids' => 'required|array|min:1|max:5', // Limit for preview
                'word_ids.*' => 'exists:words,id',
                'exercise_types' => 'required|array|min:1|max:3', // Limit for preview
                'exercise_types.*' => 'string|in:multiple_choice,fill_blank,matching,writing',
                'source_language_id' => 'required|exists:languages,id',
                'target_language_id' => 'required|exists:languages,id',
                'difficulty_level' => 'nullable|string|in:beginner,intermediate,advanced',
            ]);

            $options = [
                'source_language_id' => $validated['source_language_id'],
                'target_language_id' => $validated['target_language_id'],
                'exercise_count' => 1, // Only one per type for preview
                'difficulty_level' => $validated['difficulty_level'] ?? 'intermediate',
            ];

            $exercises = $this->scaffoldingService->generateExercisesFromVocabulary(
                $validated['word_ids'],
                $validated['exercise_types'],
                $options
            );

            return $this->sendResponse($exercises, 'Exercise preview generated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to generate preview.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Bulk generate content for multiple lessons.
     */
    public function bulkGenerate(Request $request): JsonResponse
    {
        $this->authorize('bulkGenerate', 'App\Policies\ContentScaffoldingPolicy');

        try {
            $validated = $request->validate([
                'lessons' => 'required|array|min:1|max:10',
                'lessons.*.topic_id' => 'required|exists:topics,id',
                'lessons.*.template_id' => 'nullable|exists:content_templates,id',
                'lessons.*.vocabulary_ids' => 'required|array|min:3',
                'lessons.*.vocabulary_ids.*' => 'exists:words,id',
                'lessons.*.title' => 'required|string|max:255',
                'lessons.*.exercise_types' => 'required|array|min:1',
                'source_language_id' => 'required|exists:languages,id',
                'target_language_id' => 'required|exists:languages,id',
                'save_content' => 'nullable|boolean',
            ]);

            $results = [];
            foreach ($validated['lessons'] as $lessonData) {
                try {
                    $options = [
                        'topic_id' => $lessonData['topic_id'],
                        'title' => $lessonData['title'],
                        'source_language_id' => $validated['source_language_id'],
                        'target_language_id' => $validated['target_language_id'],
                    ];

                    if (isset($lessonData['template_id'])) {
                        $template = ContentTemplate::findOrFail($lessonData['template_id']);
                        $lesson = $this->scaffoldingService->generateLessonFromTemplate(
                            $template,
                            $lessonData['vocabulary_ids'],
                            $options
                        );
                    } else {
                        // Generate exercises directly
                        $exercises = $this->scaffoldingService->generateExercisesFromVocabulary(
                            $lessonData['vocabulary_ids'],
                            $lessonData['exercise_types'],
                            $options
                        );
                        $lesson = ['exercises' => $exercises, 'title' => $lessonData['title']];
                    }

                    $results[] = [
                        'status' => 'success',
                        'lesson' => $lesson,
                        'topic_id' => $lessonData['topic_id']
                    ];
                } catch (\Exception $e) {
                    $results[] = [
                        'status' => 'error',
                        'error' => $e->getMessage(),
                        'topic_id' => $lessonData['topic_id']
                    ];
                }
            }

            return $this->sendResponse($results, 'Bulk content generation completed.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to bulk generate content.', ['error' => $e->getMessage()]);
        }
    }
}
