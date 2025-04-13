<?php
namespace App\Services\ExerciseTypes;

use App\Models\Exercise;
use Illuminate\Support\Arr;

class SpeakingHandler implements ExerciseTypeHandler
{
    /**
     * Check if the given answer is correct
     * Note: For speaking exercises, this method is not used directly.
     * Instead, the SpeakingExerciseController handles the audio processing.
     * This method is kept for compatibility with the ExerciseTypeHandler interface.
     */
    public function checkAnswer(Exercise $exercise, $userAnswer): bool
    {
        // If the answer is a JSON string (from SpeakingExerciseController),
        // parse it and check the score
        if (is_string($userAnswer) && $this->isJson($userAnswer)) {
            $data = json_decode($userAnswer, true);
            return isset($data['score']) && $data['score'] >= 0.7;
        }

        // For direct API calls without audio processing, we can't determine correctness
        return false;
    }

    /**
     * Get a hint or correct answer for the exercise
     */
    public function getHint(Exercise $exercise): mixed
    {
        return [
            'text_to_speak'         => $exercise->content['text_to_speak'] ?? $exercise->content['prompt'] ?? null,
            'correct_pronunciation' => $exercise->content['correct_pronunciation'] ?? null,
        ];
    }

    /**
     * Get feedback for the exercise attempt
     */
    public function getFeedback(Exercise $exercise, bool $isCorrect): string
    {
        if ($isCorrect) {
            return Arr::random([
                "Excellent pronunciation!",
                "Great job with your pronunciation!",
                "Your pronunciation sounds very natural!",
            ]);
        }

        return "Keep practicing your pronunciation. Listen to the example and try again.";
    }

    /**
     * Validate the exercise content structure
     */
    public function validateContent(array $content): bool
    {
        return isset($content['text_to_speak']) &&
        isset($content['language']) &&
        isset($content['duration']) &&
        is_numeric($content['duration']);
    }

    /**
     * Check if a string is valid JSON
     */
    private function isJson($string): bool
    {
        if (! is_string($string)) {
            return false;
        }

        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}