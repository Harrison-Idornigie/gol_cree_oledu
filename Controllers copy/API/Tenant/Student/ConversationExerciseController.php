<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\ConversationProgress;
use App\Services\AttemptTrackingService;
use App\Services\ExerciseTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ConversationExerciseController extends BaseAPIController
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
     * Submit an answer for a conversation exercise step
     */
    public function submitAnswer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'exercise_id' => 'required|integer|exists:exercises,id',
            'step_index' => 'required|integer|min:0',
            'answer' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $exerciseId = $request->input('exercise_id');
        $stepIndex = $request->input('step_index');
        $answer = $request->input('answer');

        // Get the exercise
        $exercise = Exercise::findOrFail($exerciseId);

        // Check if the exercise is a conversation exercise
        if ($exercise->type !== Exercise::TYPE_CONVERSATION) {
            return $this->sendError('Invalid exercise type.', [], 422);
        }

        // Check if the step index is valid
        if (!isset($exercise->content['steps'][$stepIndex])) {
            return $this->sendError('Invalid step index.', [], 422);
        }

        // Check if the answer is correct
        $userAnswer = [
            'step_index' => $stepIndex,
            'answer' => $answer,
        ];

        $isCorrect = $this->exerciseTypeService->checkAnswer($exercise, $userAnswer);

        // Record the attempt
        $this->attemptTracker->recordExerciseAttempt(
            exercise: $exercise,
            userId: Auth::id(),
            userAnswer: $userAnswer,
            isCorrect: $isCorrect,
            timeTaken: null
        );

        // Get feedback
        $feedback = $this->exerciseTypeService->getFeedback($exercise, $isCorrect);

        // Update conversation progress if the answer is correct
        if ($isCorrect) {
            $this->updateProgress($exercise->id, $stepIndex);
        }

        return $this->sendResponse([
            'is_correct' => $isCorrect,
            'feedback' => $feedback,
        ]);
    }

    /**
     * Track progress in a conversation exercise
     */
    public function trackProgress(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'exercise_id' => 'required|integer|exists:exercises,id',
            'last_step_completed' => 'required|integer|min:0',
            'is_completed' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors(), 422);
        }

        $exerciseId = $request->input('exercise_id');
        $lastStepCompleted = $request->input('last_step_completed');
        $isCompleted = $request->input('is_completed');

        // Update the progress
        $this->updateProgress($exerciseId, $lastStepCompleted, $isCompleted);

        return $this->sendResponse(['success' => true]);
    }

    /**
     * Get conversation progress for a specific exercise
     */
    public function getProgress(int $exerciseId): JsonResponse
    {
        // Get the exercise
        $exercise = Exercise::findOrFail($exerciseId);

        // Check if the exercise is a conversation exercise
        if ($exercise->type !== Exercise::TYPE_CONVERSATION) {
            return $this->sendError('Invalid exercise type.', [], 422);
        }

        // Get the progress
        $progress = ConversationProgress::where('user_id', Auth::id())
            ->where('exercise_id', $exerciseId)
            ->first();

        if (!$progress) {
            return $this->sendResponse([
                'last_step_completed' => 0,
                'is_completed' => false,
            ]);
        }

        return $this->sendResponse([
            'last_step_completed' => $progress->last_step_completed,
            'is_completed' => $progress->is_completed,
        ]);
    }

    /**
     * Get all conversation exercises for a specific language
     */
    public function getExercisesByLanguage(int $languageId): JsonResponse
    {
        // Get all conversation exercises for the language
        $exercises = DB::table('exercises')
            ->join('sections', 'exercises.section_id', '=', 'sections.id')
            ->join('lessons', 'sections.lesson_id', '=', 'lessons.id')
            ->join('units', 'lessons.unit_id', '=', 'units.id')
            ->join('learning_paths', 'units.learning_path_id', '=', 'learning_paths.id')
            ->where('learning_paths.language_id', $languageId)
            ->where('exercises.type', Exercise::TYPE_CONVERSATION)
            ->where('exercises.status', 'published')
            ->select(
                'exercises.id',
                'exercises.title',
                'exercises.content'
            )
            ->get()
            ->map(function ($exercise) {
                $content = json_decode($exercise->content, true);
                
                // Get progress for this exercise
                $progress = ConversationProgress::where('user_id', Auth::id())
                    ->where('exercise_id', $exercise->id)
                    ->first();
                
                $isCompleted = $progress ? $progress->is_completed : false;
                $lastStepCompleted = $progress ? $progress->last_step_completed : 0;
                
                // Calculate progress percentage
                $totalSteps = count($content['steps'] ?? []);
                $progressPercentage = $totalSteps > 0 
                    ? round(($lastStepCompleted / $totalSteps) * 100) 
                    : 0;
                
                // If completed, set progress to 100%
                if ($isCompleted) {
                    $progressPercentage = 100;
                }
                
                return [
                    'id' => $exercise->id,
                    'title' => $exercise->title,
                    'description' => $content['description'] ?? '',
                    'is_completed' => $isCompleted,
                    'progress_percentage' => $progressPercentage,
                ];
            });

        return $this->sendResponse(['exercises' => $exercises]);
    }

    /**
     * Update conversation progress
     */
    private function updateProgress(int $exerciseId, int $lastStepCompleted, bool $isCompleted = false): void
    {
        // Get the current progress
        $progress = ConversationProgress::where('user_id', Auth::id())
            ->where('exercise_id', $exerciseId)
            ->first();

        if (!$progress) {
            // Create a new progress record
            ConversationProgress::create([
                'user_id' => Auth::id(),
                'exercise_id' => $exerciseId,
                'last_step_completed' => $lastStepCompleted,
                'is_completed' => $isCompleted,
            ]);
        } else {
            // Update the existing progress record if the new step is further along
            // or if the exercise is now completed
            if ($lastStepCompleted > $progress->last_step_completed || $isCompleted) {
                $progress->update([
                    'last_step_completed' => max($lastStepCompleted, $progress->last_step_completed),
                    'is_completed' => $isCompleted || $progress->is_completed,
                ]);
            }
        }
    }
}
