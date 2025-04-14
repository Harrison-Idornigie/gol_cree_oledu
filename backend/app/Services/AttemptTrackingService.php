<?php
namespace App\Services;

use App\Models\Exercise;
use App\Models\ExerciseAttempt;
 
use App\Models\UserProgress;
use Illuminate\Support\Facades\DB;

class AttemptTrackingService
{
    /**
     * Record an exercise attempt
     */
    public function recordExerciseAttempt(
        Exercise $exercise,
        int $userId,
        mixed $userAnswer,
        bool $isCorrect,
        ?int $timeTaken = null
    ): void {
        DB::transaction(function () use ($exercise, $userId, $userAnswer, $isCorrect, $timeTaken) {
            // Record detailed attempt
            ExerciseAttempt::create([
                'exercise_id'        => $exercise->id,
                'user_id'            => $userId,
                'is_correct'         => $isCorrect,
                'user_answer'        => $userAnswer,
                'time_taken_seconds' => $timeTaken,
            ]);

            // Update overall progress
            UserProgress::updateOrCreate(
                [
                    'user_id'        => $userId,
                    'trackable_type' => Exercise::class,
                    'trackable_id'   => $exercise->id,
                ],
                [
                    'status'    => $isCorrect ? UserProgress::STATUS_COMPLETED : UserProgress::STATUS_IN_PROGRESS,
                    'meta_data' => [
                        'attempts'     => DB::raw('COALESCE(meta_data->>"$.attempts", 0) + 1'),
                        'last_attempt' => now(),
                        'correct'      => $isCorrect,
                        'time_taken'   => $timeTaken,
                    ],
                ]
            );
        });
    }

 
}