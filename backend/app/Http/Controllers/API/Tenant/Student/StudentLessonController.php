<?php

namespace App\Http\Controllers\API\Tenant\Student;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\Tenants\Course\LessonService;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\Lesson;
use App\Models\Tenants\Topic;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Student Lesson Controller
 * 
 * Handles lesson access and completion for students.
 * Access Level: Student
 * Scope: Tenant-specific (read-only)
 * 
 * This controller allows students to access lessons within topics
 * with sequential learning enforcement.
 */
class StudentLessonController extends BaseAPIController
{
    use BelongsToTenant;

    protected LessonService $lessonService;

    /**
     * Constructor - Apply student middleware and inject services
     */
    public function __construct(LessonService $lessonService)
    {
        $this->lessonService = $lessonService;
    }

    /**
     * Display lessons for a topic.
     *
     * @param Request $request
     * @param string $topic
     * @return JsonResponse
     */
    public function index(Request $request, string $tenant, string $topic): JsonResponse
    {
        try {
            $this->authorize('viewAny', Lesson::class);

            // Find the topic within tenant context
            $topicModel = Topic::findOrFail((int) $topic);

            // Check if the topic is accessible to the student
            $this->authorize('view', $topicModel);

            $user = Auth::user();
            $lessons = $this->lessonService->getLessonsForStudent($topicModel->id, $user);

            return $this->sendResponse([
                'lessons' => $lessons,
                'topic' => [
                    'id' => $topicModel->id,
                    'title' => $topicModel->title,
                    'description' => $topicModel->description,
                    'unit' => $topicModel->unit ? [
                        'id' => $topicModel->unit->id,
                        'title' => $topicModel->unit->title
                    ] : null
                ],
                'meta' => [
                    'total_lessons' => $lessons->count(),
                    'completed_lessons' => $lessons->where('progress.completed', true)->count(),
                    'accessible_lessons' => $lessons->where('accessible', true)->count()
                ]
            ], 'Lessons retrieved successfully.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->sendError('Topic not accessible.', ['error' => $e->getMessage()], 403);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('Topic not found.', ['error' => 'The requested topic does not exist.'], 404);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve lessons.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified lesson.
     *
     * @param Request $request
     * @param string $lesson
     * @return JsonResponse
     */
    public function show(Request $request, string $tenant, string $lesson): JsonResponse
    {
        try {
            // Find the lesson within tenant context
            $lessonModel = Lesson::findOrFail($lesson);
            $this->authorize('view', $lessonModel);

            $user = Auth::user();
            \Illuminate\Support\Facades\Log::info("StudentLessonController: Show method called for lesson {$lessonModel->id} by user {$user->id}");
            $lessonData = $this->lessonService->getLessonForStudent($lessonModel, $user);

            return $this->sendResponse($lessonData, 'Lesson retrieved successfully.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->sendError($e->getMessage(), ['error' => $e->getMessage()], 403);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('Lesson not found.', ['error' => 'The requested lesson does not exist.'], 404);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve lesson.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get student's progress in a lesson.
     *
     * @param Request $request
     * @param string $lesson
     * @return JsonResponse
     */
    public function progress(Request $request, string $tenant, string $lesson): JsonResponse
    {
        try {
            // Find the lesson within tenant context
            $lessonModel = Lesson::findOrFail($lesson);
            $this->authorize('view', $lessonModel);

            $user = Auth::user();
            \Illuminate\Support\Facades\Log::info("StudentLessonController: Progress method called for lesson {$lessonModel->id} by user {$user->id}");
            $progress = $this->lessonService->getLessonProgress($lessonModel, $user);

            // Return simple progress data structure expected by tests
            $progressData = [
                'lesson_id' => $lessonModel->id,
                'progress' => $progress['progress_percentage'] ?? 0,
                'completed' => $progress['completed'] ?? false,
                'last_accessed_at' => $progress['last_accessed'] ?? null
            ];

            return $this->sendResponse($progressData, 'Lesson progress retrieved successfully.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->sendError('Lesson not accessible.', ['error' => $e->getMessage()], 403);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('Lesson not found.', ['error' => 'The requested lesson does not exist.'], 404);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve lesson progress.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get next recommended action for student.
     */
    private function getNextAction(array $progress): string
    {
        if ($progress['completed']) {
            return 'lesson_completed';
        }

        if ($progress['next_exercise_id']) {
            return 'continue_exercises';
        }

        if ($progress['exercises_completed'] === 0) {
            return 'start_exercises';
        }

        return 'review_lesson';
    }

    /**
     * Get suggested study time based on progress.
     */
    private function getSuggestedStudyTime(array $progress): int
    {
        if ($progress['completed']) {
            return 0; // No more time needed
        }

        $remainingExercises = $progress['exercises_total'] - $progress['exercises_completed'];
        return max(10, $remainingExercises * 5); // 5 minutes per exercise, minimum 10 minutes
    }

    /**
     * Get difficulty rating for the lesson based on user performance.
     */
    private function getDifficultyRating(Lesson $lesson, $user): string
    {
        // This would analyze user's attempt patterns and success rates
        // For now, return a default based on completion rate
        $completionRate = $lesson->exercises()->count() > 0
            ? ($this->lessonService->getLessonProgress($lesson, $user)['exercises_completed'] / $lesson->exercises()->count())
            : 0;

        if ($completionRate >= 0.8) {
            return 'easy';
        } elseif ($completionRate >= 0.5) {
            return 'medium';
        } else {
            return 'hard';
        }
    }
}
