<?php
namespace App\Http\Controllers\API;

use App\Http\Requests\API\Lesson\StoreLessonRequest;
use App\Http\Requests\API\Lesson\UpdateLessonRequest;
use App\Models\Lesson;
use App\Models\Unit;
use App\Models\VocabularyItem;
use App\Services\SequentialLearningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LessonController extends BaseAPIController
{
    protected $sequentialLearningService;

    /**
     * Create a new controller instance.
     */
    public function __construct(SequentialLearningService $sequentialLearningService)
    {
        $this->sequentialLearningService = $sequentialLearningService;
    }

    /**
     * Display a listing of lessons for a unit.
     */
    public function index(Request $request, Unit $unit): JsonResponse
    {
        $query = $unit->lessons()->orderBy('order');

        if ($request->has('with_sections')) {
            $query->with(['sections' => function ($query) {
                $query->orderBy('order');
            }]);
        }

        if ($request->has('with_vocabulary')) {
            $query->with('vocabularyItems');
        }

        $perPage = $request->input('per_page', 15);
        $lessons = $query->paginate($perPage);

        return $this->sendPaginatedResponse($lessons);
    }

    /**
     * Store a newly created lesson.
     */
    public function store(StoreLessonRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $lesson = Lesson::create($request->safe()->except('vocabulary_items'));

            // Create vocabulary items if provided
            if ($request->has('vocabulary_items')) {
                $vocabularyItems = collect($request->vocabulary_items)
                    ->map(function ($item) use ($lesson) {
                        return new VocabularyItem([
                            'word'        => $item['word'],
                            'translation' => $item['translation'],
                            'example'     => $item['example'] ?? null,
                        ]);
                    });

                $lesson->vocabularyItems()->saveMany($vocabularyItems);
            }

            $lesson->load('vocabularyItems');
            return $this->sendCreatedResponse($lesson, 'Lesson created successfully.');
        });
    }

    /**
     * Display the specified lesson.
     */
    public function show(Request $request, Lesson $lesson): JsonResponse
    {
        // Check if the lesson is unlocked for the user
        $isUnlocked = $this->sequentialLearningService->isLessonUnlocked($lesson);

        if ($request->has('with_sections')) {
            $lesson->load(['sections' => function ($query) {
                $query->orderBy('order');
            }]);
        }

        if ($request->has('with_vocabulary')) {
            $lesson->load('vocabularyItems');
        }

        if ($request->has('with_progress') && $request->user()) {
            $lesson->load(['progress' => function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            }]);
        }

        // Add unlocked status to the response
        $lessonData                = $lesson->toArray();
        $lessonData['is_unlocked'] = $isUnlocked;

        return $this->sendResponse($lessonData);
    }

    /**
     * Update the specified lesson.
     */
    public function update(UpdateLessonRequest $request, Lesson $lesson): JsonResponse
    {
        return DB::transaction(function () use ($request, $lesson) {
            $lesson->update($request->safe()->except('vocabulary_items'));

            // Handle vocabulary items if provided
            if ($request->has('vocabulary_items')) {
                foreach ($request->vocabulary_items as $item) {
                    if (isset($item['_remove']) && $item['_remove']) {
                        // Remove item
                        if (isset($item['id'])) {
                            $lesson->vocabularyItems()->where('id', $item['id'])->delete();
                        }
                    } elseif (isset($item['id'])) {
                        // Update existing item
                        $lesson->vocabularyItems()->where('id', $item['id'])->update([
                            'word'        => $item['word'],
                            'translation' => $item['translation'],
                            'example'     => $item['example'] ?? null,
                        ]);
                    } else {
                        // Create new item
                        $lesson->vocabularyItems()->create([
                            'word'        => $item['word'],
                            'translation' => $item['translation'],
                            'example'     => $item['example'] ?? null,
                        ]);
                    }
                }
            }

            $lesson->load('vocabularyItems');
            return $this->sendResponse($lesson, 'Lesson updated successfully.');
        });
    }

    /**
     * Remove the specified lesson.
     */
    public function destroy(Lesson $lesson): JsonResponse
    {
        // Check if unit's learning path is published
        if ($lesson->unit->learningPath->status === 'published') {
            return $this->sendError('Cannot delete a lesson from a published learning path.');
        }

        return DB::transaction(function () use ($lesson) {
            // Reorder remaining lessons
            Lesson::where('unit_id', $lesson->unit_id)
                ->where('order', '>', $lesson->order)
                ->decrement('order');

            $lesson->delete();

            return $this->sendNoContentResponse();
        });
    }

    /**
     * Update the order of lessons.
     */
    public function reorder(Request $request, Unit $unit): JsonResponse
    {
        $request->validate([
            'lessons'   => ['required', 'array'],
            'lessons.*' => ['required', 'integer', 'distinct'],
        ]);

        $lessonIds = $request->lessons;
        $order     = 1;

        // Verify all lessons belong to the unit
        $lessons = Lesson::whereIn('id', $lessonIds)
            ->where('unit_id', $unit->id)
            ->get();

        if ($lessons->count() !== count($lessonIds)) {
            return $this->sendError('Invalid lesson IDs provided.');
        }

        // Update order
        foreach ($lessonIds as $lessonId) {
            Lesson::where('id', $lessonId)->update(['order' => $order++]);
        }

        return $this->sendResponse(
            $unit->lessons()->orderBy('order')->get(),
            'Lessons reordered successfully.'
        );
    }

    /**
     * Get lesson progress for the authenticated user.
     */
    public function progress(Request $request, Lesson $lesson): JsonResponse
    {
        $progress = $lesson->progress()
            ->where('user_id', $request->user()->id)
            ->first();

        $sectionsProgress = $lesson->sections()
            ->with(['progress' => function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            }])
            ->get()
            ->map(function ($section) {
                $progress = $section->progress->first();
                return [
                    'section_id' => $section->id,
                    'status'     => $progress ? $progress->status : 'not_started',
                ];
            });

        return $this->sendResponse([
            'lesson_progress'   => $progress ? $progress->status : 'not_started',
            'completed'         => $lesson->isCompletedByUser($request->user()->id),
            'sections_progress' => $sectionsProgress,
            'is_unlocked'       => $this->sequentialLearningService->isLessonUnlocked($lesson),
        ]);
    }
}
