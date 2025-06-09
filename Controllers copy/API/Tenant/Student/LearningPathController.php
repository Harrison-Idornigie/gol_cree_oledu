<?php
namespace App\Http\Controllers\API;

use App\Http\Requests\API\LearningPath\StoreLearningPathRequest;
use App\Http\Requests\API\LearningPath\UpdateLearningPathRequest;
use App\Models\LearningPath;
use App\Models\UserProgress;
use App\Services\LearningPathService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LearningPathController extends BaseAPIController
{
    protected LearningPathService $learningPathService;

    public function __construct(LearningPathService $learningPathService)
    {
        $this->learningPathService = $learningPathService;
    }

    /**
     * Display a listing of learning paths.
     */
    public function index(Request $request): JsonResponse
    {
        $userRole = $request->user()?->isStudent() ? 'student' : 'team';
        $learningPaths = $this->learningPathService->getFilteredLearningPaths($request, $userRole);

        return $this->sendPaginatedResponse($learningPaths);
    }

    /**
     * Store a newly created learning path.
     */
    public function store(StoreLearningPathRequest $request): JsonResponse
    {
        try {
            $learningPath = $this->learningPathService->createLearningPath(
                $request->validated(),
                $request->user()
            );

            return $this->sendCreatedResponse($learningPath, 'Learning path created successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to create learning path', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified learning path.
     */
    public function show(Request $request, LearningPath $learningPath): JsonResponse
    {
        // Load relationships if requested
        if ($request->has('with_units')) {
            $learningPath->load(['units' => function ($query) {
                $query->orderBy('order')->with(['lessons' => function ($query) {
                    $query->orderBy('order');
                }]);
            }]);
        }

        if ($request->has('with_progress') && $request->user()) {
            $learningPath->load(['progress' => function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            }]);
        }

        return $this->sendResponse($learningPath);
    }

    /**
     * Update the specified learning path.
     */
    public function update(UpdateLearningPathRequest $request, LearningPath $learningPath): JsonResponse
    {
        try {
            $updatedLearningPath = $this->learningPathService->updateLearningPath(
                $learningPath,
                $request->validated(),
                $request->user()
            );

            return $this->sendResponse($updatedLearningPath, 'Learning path updated successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to update learning path', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified learning path.
     */
    public function destroy(LearningPath $learningPath): JsonResponse
    {
        if ($learningPath->status === 'published') {
            return $this->sendError('Cannot delete a published learning path.');
        }

        $learningPath->delete();

        return $this->sendNoContentResponse();
    }

    /**
     * Get learning paths by target level.
     */
    public function byLevel(string $level): JsonResponse
    {
        $learningPaths = $this->learningPathService->getByLevel($level);
        return $this->sendResponse($learningPaths);
    }

    /**
     * Get learning paths by language.
     */
    public function byLanguage(int $languageId): JsonResponse
    {
        $learningPaths = $this->learningPathService->getByLanguage($languageId);
        return $this->sendResponse($learningPaths);
    }

    /**
     * Get user progress for a learning path.
     */
    public function progress(Request $request, LearningPath $learningPath): JsonResponse
    {
        $progressData = $this->learningPathService->getUserProgress($learningPath, $request->user());
        return $this->sendResponse($progressData);
    }

    /**
     * Update the status of a learning path.
     */
    public function updateStatus(Request $request, LearningPath $learningPath): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string', 'in:draft,published,archived'],
        ]);

        $learningPath->status = $request->status;
        $learningPath->save();

        return $this->sendResponse($learningPath, 'Learning path status updated successfully.');
    }

    /**
     * Enroll a user in a learning path.
     * This creates or updates a progress record for the user and learning path.
     */
    public function enroll(Request $request, LearningPath $learningPath): JsonResponse
    {
        // Check if the learning path is published
        if ($learningPath->status !== 'published') {
            return $this->sendError('Cannot enroll in an unpublished learning path.', [], 400);
        }

        // Check if the user is already enrolled
        $progress = UserProgress::firstOrNew([
            'user_id'        => Auth::id(),
            'trackable_type' => LearningPath::class,
            'trackable_id'   => $learningPath->id,
        ]);

        // If it's a new enrollment, set the status to in_progress
        if (! $progress->exists) {
            $progress->status = UserProgress::STATUS_IN_PROGRESS;
            $progress->save();
            $message = 'Successfully enrolled in learning path.';
        } else {
            $message = 'Already enrolled in this learning path.';
        }

        return $this->sendResponse($progress, $message);
    }

    // The languages method has been moved to LanguageController
}
