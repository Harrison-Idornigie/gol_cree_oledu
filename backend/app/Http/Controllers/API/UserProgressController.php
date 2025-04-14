<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\UserProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserProgressController extends BaseAPIController
{
    /**
     * Display a listing of the user's progress.
     */
    public function index(Request $request): JsonResponse
    {
        $query = UserProgress::where('user_id', Auth::id());

        // Filter by content type if specified
        if ($request->has('type')) {
            $query->where('progressable_type', $request->type);
        }

        // Include relationships if requested
        if ($request->has('with_content')) {
            $query->with('progressable');
        }

        $perPage = $request->input('per_page', 15);
        $progress = $query->paginate($perPage);

        return $this->sendPaginatedResponse($progress);
    }

    /**
     * Store user's progress for a specific content item.
     */
    public function store(Request $request, string $type, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:started,in_progress,completed',
            'progress' => 'required|integer|min:0|max:100',
            'metadata' => 'nullable|array'
        ]);

        $progress = UserProgress::create([
            'user_id' => Auth::id(),
            'progressable_type' => $this->getProgressableType($type),
            'progressable_id' => $id,
            'status' => $validated['status'],
            'progress' => $validated['progress'],
            'metadata' => $validated['metadata'] ?? [],
        ]);

        return $this->sendCreatedResponse($progress);
    }

    /**
     * Display the specified progress.
     */
    public function show(string $type, int $id): JsonResponse
    {
        $progress = UserProgress::where([
            'user_id' => Auth::id(),
            'progressable_type' => $this->getProgressableType($type),
            'progressable_id' => $id,
        ])->firstOrFail();

        return $this->sendResponse($progress);
    }

    /**
     * Update the specified progress.
     */
    public function update(Request $request, string $type, int $id): JsonResponse
    {
        $progress = UserProgress::where([
            'user_id' => Auth::id(),
            'progressable_type' => $this->getProgressableType($type),
            'progressable_id' => $id,
        ])->firstOrFail();

        $validated = $request->validate([
            'status' => 'sometimes|string|in:started,in_progress,completed',
            'progress' => 'sometimes|integer|min:0|max:100',
            'metadata' => 'sometimes|array'
        ]);

        $progress->update($validated);

        return $this->sendResponse($progress);
    }

    /**
     * Get the progressable model type from the route parameter.
     */
    protected function getProgressableType(string $type): string
    {
        return match ($type) {
            'learning-paths' => 'App\Models\LearningPath',
            'units' => 'App\Models\Unit',
            'lessons' => 'App\Models\Lesson',
            'sections' => 'App\Models\Section',
            'exercises' => 'App\Models\Exercise',
            
            default => abort(400, 'Invalid content type'),
        };
    }
}