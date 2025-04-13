<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\PictureAttempt;
use App\Services\AttemptTrackingService;
use App\Services\ExerciseTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PictureExerciseController extends BaseAPIController
{
    protected AttemptTrackingService $attemptTracker;
    protected ExerciseTypeService $exerciseTypeService;

    public function __construct(
        AttemptTrackingService $attemptTracker,
        ExerciseTypeService $exerciseTypeService
    ) {
        $this->attemptTracker = $attemptTracker;
        $this->exerciseTypeService = $exerciseTypeService;
    }

    /**
     * Check a picture exercise answer
     */
    public function checkAnswer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'exercise_id' => 'required|integer|exists:exercises,id',
            'selected_option' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $exerciseId = $request->input('exercise_id');
        $selectedOption = $request->input('selected_option');

        // Get the exercise
        $exercise = Exercise::findOrFail($exerciseId);

        // Check if the exercise is a picture exercise
        if ($exercise->type !== Exercise::TYPE_PICTURE) {
            return $this->sendError('Invalid exercise type.', [], 422);
        }

        // Check if the answer is correct
        $isCorrect = $this->exerciseTypeService->checkAnswer($exercise, $selectedOption);

        // Get the attempt number
        $attemptNumber = PictureAttempt::where('user_id', Auth::id())
            ->where('exercise_id', $exerciseId)
            ->count() + 1;

        // Record the attempt
        PictureAttempt::create([
            'user_id' => Auth::id(),
            'exercise_id' => $exerciseId,
            'selected_option' => $selectedOption,
            'is_correct' => $isCorrect,
            'attempt_number' => $attemptNumber,
        ]);

        // Record the exercise attempt
        $this->attemptTracker->recordExerciseAttempt(
            exercise: $exercise,
            userId: Auth::id(),
            userAnswer: $selectedOption,
            isCorrect: $isCorrect,
            timeTaken: null
        );

        // Get feedback
        $feedback = $this->exerciseTypeService->getFeedback($exercise, $isCorrect);

        return $this->sendResponse([
            'is_correct' => $isCorrect,
            'feedback' => $feedback,
            'attempt_number' => $attemptNumber,
        ]);
    }

    /**
     * Get picture exercises by language
     */
    public function getByLanguage(string $languageCode): JsonResponse
    {
        // Get all picture exercises for the language
        $exercises = Exercise::where('type', Exercise::TYPE_PICTURE)
            ->where('status', 'published')
            ->whereJsonContains('content->language', $languageCode)
            ->get()
            ->map(function ($exercise) {
                // Get the user's attempts for this exercise
                $attempts = PictureAttempt::where('user_id', Auth::id())
                    ->where('exercise_id', $exercise->id)
                    ->orderBy('created_at', 'desc')
                    ->get();
                
                $latestAttempt = $attempts->first();
                $isCompleted = $attempts->contains('is_correct', true);
                
                return [
                    'id' => $exercise->id,
                    'title' => $exercise->title,
                    'question' => $exercise->content['question'] ?? '',
                    'mode' => $exercise->content['mode'] ?? 'word_to_image',
                    'images' => $exercise->content['images'] ?? [],
                    'words' => $exercise->content['words'] ?? [],
                    'target_image' => $exercise->content['target_image'] ?? '',
                    'is_completed' => $isCompleted,
                    'attempts' => $attempts->count(),
                    'latest_attempt' => $latestAttempt ? [
                        'selected_option' => $latestAttempt->selected_option,
                        'is_correct' => $latestAttempt->is_correct,
                        'attempt_number' => $latestAttempt->attempt_number,
                        'created_at' => $latestAttempt->created_at,
                    ] : null,
                ];
            });

        return $this->sendResponse(['exercises' => $exercises]);
    }
}
