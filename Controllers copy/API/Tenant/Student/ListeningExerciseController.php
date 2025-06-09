<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\ListeningAttempt;
use App\Services\AttemptTrackingService;
use App\Services\ExerciseTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ListeningExerciseController extends BaseAPIController
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
     * Check a listening exercise answer
     */
    public function checkAnswer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'exercise_id' => 'required|integer|exists:exercises,id',
            'transcript' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $exerciseId = $request->input('exercise_id');
        $userTranscript = $request->input('transcript');

        // Get the exercise
        $exercise = Exercise::findOrFail($exerciseId);

        // Check if the exercise is a listening exercise
        if ($exercise->type !== Exercise::TYPE_LISTENING) {
            return $this->sendError('Invalid exercise type.', [], 422);
        }

        // Check if the answer is correct
        $isCorrect = $this->exerciseTypeService->checkAnswer($exercise, $userTranscript);

        // Get the attempt number
        $attemptNumber = ListeningAttempt::where('user_id', Auth::id())
            ->where('exercise_id', $exerciseId)
            ->count() + 1;

        // Record the attempt
        ListeningAttempt::create([
            'user_id' => Auth::id(),
            'exercise_id' => $exerciseId,
            'user_transcript' => $userTranscript,
            'is_correct' => $isCorrect,
            'attempt_number' => $attemptNumber,
        ]);

        // Record the exercise attempt
        $this->attemptTracker->recordExerciseAttempt(
            exercise: $exercise,
            userId: Auth::id(),
            userAnswer: $userTranscript,
            isCorrect: $isCorrect,
            timeTaken: null
        );

        // Get feedback
        $feedback = $this->exerciseTypeService->getFeedback($exercise, $isCorrect);

        // Get hint if the answer is incorrect
        $hint = null;
        if (!$isCorrect && $attemptNumber >= 2) {
            $hint = $this->exerciseTypeService->getHint($exercise);
        }

        return $this->sendResponse([
            'is_correct' => $isCorrect,
            'feedback' => $feedback,
            'hint' => $hint,
            'attempt_number' => $attemptNumber,
        ]);
    }

    /**
     * Get listening exercises by language
     */
    public function getByLanguage(string $languageCode): JsonResponse
    {
        // Get all listening exercises for the language
        $exercises = Exercise::where('type', Exercise::TYPE_LISTENING)
            ->where('status', 'published')
            ->whereJsonContains('content->language', $languageCode)
            ->get()
            ->map(function ($exercise) {
                // Get the user's attempts for this exercise
                $attempts = ListeningAttempt::where('user_id', Auth::id())
                    ->where('exercise_id', $exercise->id)
                    ->orderBy('created_at', 'desc')
                    ->get();
                
                $latestAttempt = $attempts->first();
                $isCompleted = $attempts->contains('is_correct', true);
                
                return [
                    'id' => $exercise->id,
                    'title' => $exercise->title,
                    'prompt' => $exercise->content['prompt'] ?? '',
                    'audio_url' => $exercise->content['audio_url'] ?? '',
                    'difficulty' => $exercise->content['difficulty'] ?? 'beginner',
                    'is_completed' => $isCompleted,
                    'attempts' => $attempts->count(),
                    'latest_attempt' => $latestAttempt ? [
                        'transcript' => $latestAttempt->user_transcript,
                        'is_correct' => $latestAttempt->is_correct,
                        'attempt_number' => $latestAttempt->attempt_number,
                        'created_at' => $latestAttempt->created_at,
                    ] : null,
                ];
            });

        return $this->sendResponse(['exercises' => $exercises]);
    }
}