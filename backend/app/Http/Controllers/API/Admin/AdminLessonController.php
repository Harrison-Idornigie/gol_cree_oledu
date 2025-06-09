<?php
namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Http\Controllers\API\LessonController as BaseLessonController;
use App\Http\Requests\API\Lesson\StoreLessonRequest;
use App\Http\Requests\API\Lesson\UpdateLessonRequest;
use App\Models\AuditLog;
use App\Models\Lesson;
use App\Models\Topic;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminLessonController extends BaseAPIController
{
    /**
     * The base lesson controller instance.
     */
    protected $baseLessonController;

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->baseLessonController = new BaseLessonController();
    }

    /**
     * Display a listing of all lessons for admin.
     * Admins can see all lessons including drafts and archived.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Lesson::query();

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('topic_id')) {
                $query->where('topic_id', $request->topic_id);
            }

            // Include relationships if requested
            if ($request->has('with_exercises')) {
                $query->with(['exercises' => function ($query) {
                    $query->orderBy('order');
                }]);
            }

            if ($request->has('with_topic')) {
                $query->with('topic');
            }

            $perPage = $request->input('per_page', 15);
            $lessons = $query->paginate($perPage);

            return $this->sendPaginatedResponse($lessons);
        } catch (Exception $e) {
            Log::error('Error fetching lessons: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->sendError('Failed to retrieve lessons', [], 500);
        }
    }

    /**
     * Store a newly created lesson.
     */
    public function store(StoreLessonRequest $request): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request) {
                $lesson = Lesson::create($request->validated());

                // Set topic_id if not already set
                if (! $lesson->topic_id && $request->has('topic_id')) {
                    try {
                        $topic            = Topic::findOrFail($request->topic_id);
                        $lesson->topic_id = $topic->id;
                        $lesson->save();
                    } catch (ModelNotFoundException $e) {
                        throw new Exception('Topic not found', 404);
                    }
                }

                // Log the creation for audit trail
                AuditLog::log(
                    'create',
                    'lessons',
                    $lesson,
                    [],
                    $request->validated()
                );

                return $this->sendCreatedResponse($lesson, 'Lesson created successfully.');
            });
        } catch (Exception $e) {
            Log::error('Error creating lesson: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->sendError('Failed to create lesson: ' . $e->getMessage(), [], $statusCode);
        }
    }

    /**
     * Display the specified lesson for admin.
     * Admins can see additional information like version history.
     */
    public function show(Request $request, Lesson $lesson): JsonResponse
    {
        try {
            // Load relationships if requested
            if ($request->has('with_exercises')) {
                $lesson->load(['exercises' => function ($query) {
                    $query->orderBy('order');
                }]);
            }

            if ($request->has('with_topic')) {
                $lesson->load('topic');
            }

            if ($request->has('with_versions')) {
                // Load version history if requested
                $lesson->load('versions');
            }

            return $this->sendResponse($lesson);
        } catch (Exception $e) {
            Log::error('Error fetching lesson: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->sendError('Failed to retrieve lesson details', [], 500);
        }
    }

    /**
     * Update the specified lesson.
     */
    public function update(UpdateLessonRequest $request, Lesson $lesson): JsonResponse
    {
        try {
            $oldData = $lesson->toArray();
            $lesson->update($request->validated());

            // Log the update for audit trail
            AuditLog::logChange(
                $lesson,
                'update',
                $oldData,
                $lesson->toArray()
            );

            return $this->sendResponse($lesson, 'Lesson updated successfully.');
        } catch (Exception $e) {
            Log::error('Error updating lesson: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->sendError('Failed to update lesson: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Remove the specified lesson.
     * Admins can delete lessons that aren't published.
     */
    public function destroy(Request $request, Lesson $lesson): JsonResponse
    {
        try {
            // Prevent deletion of published lessons
            if ($lesson->status === 'published') {
                return $this->sendError('Cannot delete a published lesson. Archive it first.', [], 422);
            }

            $data = $lesson->toArray();

            DB::transaction(function () use ($lesson, $data) {
                // No need to detach from topic as it's a direct relationship

                $lesson->delete();

                // Log the deletion for audit trail
                AuditLog::log(
                    'delete',
                    'lessons',
                    $lesson,
                    $data,
                    []
                );
            });

            return $this->sendNoContentResponse();
        } catch (Exception $e) {
            Log::error('Error deleting lesson: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->sendError('Failed to delete lesson: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Update the status of a lesson.
     * Admin-specific method to change lesson status.
     */
    public function updateStatus(Request $request, Lesson $lesson): JsonResponse
    {
        try {
            $request->validate([
                'status' => ['required', 'string', 'in:draft,published,archived'],
            ]);

            $oldStatus      = $lesson->status;
            $lesson->status = $request->status;
            $lesson->save();

            // Log the status change for audit trail
            AuditLog::log(
                'status_update',
                'lessons',
                $lesson,
                ['status' => $oldStatus],
                ['status' => $request->status]
            );

            return $this->sendResponse($lesson, 'Lesson status updated successfully.');
        } catch (Exception $e) {
            Log::error('Error updating lesson status: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->sendError('Failed to update lesson status: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Reorder exercises within a lesson.
     * Admin-specific method to reorder exercises.
     */
    public function reorderExercises(Request $request, Lesson $lesson): JsonResponse
    {
        try {
            $request->validate([
                'exercise_ids'   => ['required', 'array'],
                'exercise_ids.*' => ['exists:exercises,id'],
            ]);

            $exerciseIds = $request->exercise_ids;

            DB::transaction(function () use ($lesson, $exerciseIds) {
                // Update the order of each exercise
                foreach ($exerciseIds as $index => $exerciseId) {
                    $lesson->exercises()->where('id', $exerciseId)->update(['order' => $index + 1]);
                }
            });

            return $this->sendResponse($lesson->load('exercises'), 'Exercises reordered successfully.');
        } catch (Exception $e) {
            Log::error('Error reordering exercises: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->sendError('Failed to reorder exercises: ' . $e->getMessage(), [], 500);
        }
    }
}
