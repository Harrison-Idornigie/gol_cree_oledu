<?php
namespace App\Http\Controllers\API;

use App\Http\Requests\API\Section\StoreSectionRequest;
use App\Http\Requests\API\Section\UpdateSectionRequest;
use App\Models\Exercise;
use App\Models\Lesson;
use App\Models\Section;
use App\Services\SequentialLearningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SectionController extends BaseAPIController
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
     * Display a listing of sections for a lesson.
     */
    public function index(Request $request, Lesson $lesson): JsonResponse
    {
        $query = $lesson->sections()->orderBy('order');

        if ($request->has('with_exercises')) {
            $query->with(['exercises' => function ($query) {
                $query->orderBy('order');
            }]);
        }

        $perPage  = $request->input('per_page', 15);
        $sections = $query->paginate($perPage);

        return $this->sendPaginatedResponse($sections);
    }

    /**
     * Store a newly created section.
     */
    public function store(StoreSectionRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $section = Section::create($request->safe()->except('exercises'));

            // Create exercises if provided
            if ($request->has('exercises')) {
                $exercises = collect($request->exercises)
                    ->map(function ($exercise) {
                        return new Exercise([
                            'type'    => $exercise['type'],
                            'content' => $exercise['content'],
                            'answers' => $exercise['answers'],
                            'order'   => $exercise['order'],
                        ]);
                    });

                $section->exercises()->saveMany($exercises);
            }

            $section->load('exercises');
            return $this->sendCreatedResponse($section, 'Section created successfully.');
        });
    }

    /**
     * Display the specified section.
     */
    public function show(Request $request, Section $section): JsonResponse
    {
        // Check if the section is unlocked for the user
        $isUnlocked = $this->sequentialLearningService->isSectionUnlocked($section);

        if ($request->has('with_exercises')) {
            $section->load(['exercises' => function ($query) {
                $query->orderBy('order');
            }]);
        }

        if ($request->has('with_progress') && $request->user()) {
            $section->load(['progress' => function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            }]);
        }

        // Add unlocked status to the response
        $sectionData                = $section->toArray();
        $sectionData['is_unlocked'] = $isUnlocked;

        return $this->sendResponse($sectionData);
    }

    /**
     * Update the specified section.
     */
    public function update(UpdateSectionRequest $request, Section $section): JsonResponse
    {
        return DB::transaction(function () use ($request, $section) {
            $section->update($request->safe()->except('exercises'));

            // Handle exercises if provided
            if ($request->has('exercises')) {
                foreach ($request->exercises as $exerciseData) {
                    if (isset($exerciseData['_remove']) && $exerciseData['_remove']) {
                        // Remove exercise
                        if (isset($exerciseData['id'])) {
                            $section->exercises()->where('id', $exerciseData['id'])->delete();
                        }
                    } elseif (isset($exerciseData['id'])) {
                        // Update existing exercise
                        $section->exercises()->where('id', $exerciseData['id'])->update([
                            'type'    => $exerciseData['type'],
                            'content' => $exerciseData['content'],
                            'answers' => $exerciseData['answers'],
                            'order'   => $exerciseData['order'],
                        ]);
                    } else {
                        // Create new exercise
                        $section->exercises()->create([
                            'type'    => $exerciseData['type'],
                            'content' => $exerciseData['content'],
                            'answers' => $exerciseData['answers'],
                            'order'   => $exerciseData['order'],
                        ]);
                    }
                }
            }

            $section->load('exercises');
            return $this->sendResponse($section, 'Section updated successfully.');
        });
    }

    /**
     * Remove the specified section.
     */
    public function destroy(Section $section): JsonResponse
    {
        // Check if lesson's learning path is published
        if ($section->lesson->unit->learningPath->status === 'published') {
            return $this->sendError('Cannot delete a section from a published learning path.');
        }

        return DB::transaction(function () use ($section) {
            // Reorder remaining sections
            Section::where('lesson_id', $section->lesson_id)
                ->where('order', '>', $section->order)
                ->decrement('order');

            $section->delete();

            return $this->sendNoContentResponse();
        });
    }

    /**
     * Update the order of sections.
     */
    public function reorder(Request $request, Lesson $lesson): JsonResponse
    {
        $request->validate([
            'sections'   => ['required', 'array'],
            'sections.*' => ['required', 'integer', 'distinct'],
        ]);

        $sectionIds = $request->sections;
        $order      = 1;

        // Verify all sections belong to the lesson
        $sections = Section::whereIn('id', $sectionIds)
            ->where('lesson_id', $lesson->id)
            ->get();

        if ($sections->count() !== count($sectionIds)) {
            return $this->sendError('Invalid section IDs provided.');
        }

        // Update order
        foreach ($sectionIds as $sectionId) {
            Section::where('id', $sectionId)->update(['order' => $order++]);
        }

        return $this->sendResponse(
            $lesson->sections()->orderBy('order')->get(),
            'Sections reordered successfully.'
        );
    }

    /**
     * Get section progress for the authenticated user.
     */
    public function progress(Request $request, Section $section): JsonResponse
    {
        $progress = $section->progress()
            ->where('user_id', $request->user()->id)
            ->first();

        $exercisesProgress = $section->exercises()
            ->with(['progress' => function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            }])
            ->get()
            ->map(function ($exercise) {
                $progress = $exercise->progress->first();
                return [
                    'exercise_id' => $exercise->id,
                    'status'      => $progress ? $progress->status : 'not_started',
                    'meta_data'   => $progress ? $progress->meta_data : null,
                ];
            });

        return $this->sendResponse([
            'section_progress'   => $progress ? $progress->status : 'not_started',
            'exercises_progress' => $exercisesProgress,
            'is_unlocked'        => $this->sequentialLearningService->isSectionUnlocked($section),
        ]);
    }
}
