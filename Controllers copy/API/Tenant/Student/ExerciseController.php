<?php
namespace App\Http\Controllers\API;

use App\Models\Exercise;
use App\Services\AttemptTrackingService;
use App\Services\ExerciseTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExerciseController extends BaseAPIController
{
    protected AttemptTrackingService $attemptTracker;
    protected ExerciseTypeService $exerciseTypeService;

    public function __construct(
        AttemptTrackingService $attemptTracker,
        ExerciseTypeService $exerciseTypeService
    ) {
        $this->attemptTracker      = $attemptTracker;
        $this->exerciseTypeService = $exerciseTypeService;
    }

    /**
     * Get exercises for a specific section
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'section_id' => 'required|integer|exists:sections,id',
        ]);

        $sectionId = $request->input('section_id');

        $exercises = Exercise::where('section_id', $sectionId)
            ->orderBy('order')
            ->with(['media'])
            ->get()
            ->map(function ($exercise) {
                // Remove correct answers for client-side display
                $content = $exercise->content;
                if (isset($content['answers'])) {
                    unset($content['answers']);
                }
                return [
                    'id'      => $exercise->id,
                    'type'    => $exercise->type,
                    'content' => $content,
                    'order'   => $exercise->order,
                    'media'   => $exercise->media,
                ];
            });

        return $this->sendResponse($exercises);
    }

    /**
     * Check exercise answer
     */
    public function checkAnswer(Request $request, Exercise $exercise): JsonResponse
    {
        $request->validate([
            'answer'     => 'required',
            'started_at' => 'required|date', // Client sends the time when exercise was started
        ]);

        $isCorrect = $this->exerciseTypeService->checkAnswer($exercise, $request->answer);
        $timeTaken = now()->diffInSeconds($request->started_at);

        // Record the attempt
        $this->attemptTracker->recordExerciseAttempt(
            exercise: $exercise,
            userId: Auth::id(),
            userAnswer: $request->answer,
            isCorrect: $isCorrect,
            timeTaken: $timeTaken
        );

        return $this->sendResponse([
            'correct'        => $isCorrect,
            'feedback'       => $this->exerciseTypeService->getFeedback($exercise, $isCorrect),
            'correct_answer' => $isCorrect ? null : $this->exerciseTypeService->getHint($exercise),
            'time_taken'     => $timeTaken,
        ]);
    }

    /**
     * Get exercise statistics for the current user
     */
    public function statistics(Exercise $exercise): JsonResponse
    {
        $userStats = DB::table('exercise_attempts')
            ->where('exercise_id', $exercise->id)
            ->where('user_id', Auth::id())
            ->select([
                DB::raw('COUNT(*) as total_attempts'),
                DB::raw('SUM(CASE WHEN is_correct THEN 1 ELSE 0 END) as correct_attempts'),
                DB::raw('AVG(time_taken_seconds) as avg_time'),
                DB::raw('MIN(time_taken_seconds) as best_time'),
            ])
            ->first();

        $overallStats = DB::table('exercise_attempts')
            ->where('exercise_id', $exercise->id)
            ->select([
                DB::raw('COUNT(DISTINCT user_id) as total_users'),
                DB::raw('AVG(CASE WHEN is_correct THEN 1 ELSE 0 END) * 100 as success_rate'),
                DB::raw('AVG(time_taken_seconds) as avg_time'),
            ])
            ->first();

        return $this->sendResponse([
            'user_statistics'    => [
                'total_attempts' => $userStats->total_attempts,
                'success_rate'   => $userStats->total_attempts > 0
                ? ($userStats->correct_attempts / $userStats->total_attempts) * 100
                : 0,
                'average_time'   => round($userStats->avg_time, 1),
                'best_time'      => $userStats->best_time,
                'mastered'       => $userStats->total_attempts >= 3 &&
                ($userStats->correct_attempts / $userStats->total_attempts) >= 0.8,
            ],
            'overall_statistics' => [
                'total_users'  => $overallStats->total_users,
                'success_rate' => round($overallStats->success_rate, 1),
                'average_time' => round($overallStats->avg_time, 1),
            ],
        ]);
    }

    // Removed getFeedback method as we're now using the ExerciseTypeService
}