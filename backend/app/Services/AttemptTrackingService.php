<?php
namespace App\Services;

use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\Quiz;
use App\Models\QuizAttempt;
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

    /**
     * Record a quiz attempt
     */
    public function recordQuizAttempt(
        Quiz $quiz,
        int $userId,
        array $answers,
        int $timeTaken
    ): array {
        return DB::transaction(function () use ($quiz, $userId, $answers, $timeTaken) {
            $score  = $quiz->calculateScore($answers);
            $passed = $score >= $quiz->passing_score;

            // Process each question's result
            $questionResults = $quiz->questions->map(function ($question) use ($answers) {
                $answer    = $answers[$question->id] ?? null;
                $isCorrect = $question->checkAnswer($answer);

                return [
                    'question_id' => $question->id,
                    'correct'     => $isCorrect,
                    'answer'      => $answer,
                    'time_spent'  => null, // Could be added if we track per-question timing
                ];
            })->toArray();

            // Record detailed attempt
            QuizAttempt::create([
                'quiz_id'            => $quiz->id,
                'user_id'            => $userId,
                'answers'            => $answers,
                'score'              => $score,
                'passed'             => $passed,
                'time_taken_seconds' => $timeTaken,
                'question_results'   => $questionResults,
            ]);

            // Update overall progress
            UserProgress::updateOrCreate(
                [
                    'user_id'        => $userId,
                    'trackable_type' => Quiz::class,
                    'trackable_id'   => $quiz->id,
                ],
                [
                    'status'    => $passed ? UserProgress::STATUS_COMPLETED : UserProgress::STATUS_IN_PROGRESS,
                    'meta_data' => [
                        'attempts'     => DB::raw('COALESCE(meta_data->>"$.attempts", 0) + 1'),
                        'last_attempt' => now(),
                        'best_score'   => DB::raw('GREATEST(COALESCE(meta_data->>"$.best_score", 0), ' . $score . ')'),
                        'latest_score' => $score,
                        'passed'       => $passed,
                    ],
                ]
            );

            return [
                'score'          => $score,
                'passed'         => $passed,
                'required_score' => $quiz->passing_score,
            ];
        });
    }
}