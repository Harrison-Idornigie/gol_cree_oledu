<?php

namespace App\Services\ExerciseTypes;

use App\Models\Exercise;
use Illuminate\Support\Arr;

class MultipleChoiceHandler implements ExerciseTypeHandler
{
    /**
     * Check if the given answer is correct
     */
    public function checkAnswer(Exercise $exercise, $userAnswer): bool
    {
        return $userAnswer === $exercise->answers['correct'];
    }
    
    /**
     * Get a hint or correct answer for the exercise
     */
    public function getHint(Exercise $exercise): mixed
    {
        return $exercise->answers['correct'];
    }
    
    /**
     * Get feedback for the exercise attempt
     */
    public function getFeedback(Exercise $exercise, bool $isCorrect): string
    {
        if ($isCorrect) {
            return Arr::random([
                "¡Excelente! (Excellent!)",
                "¡Muy bien! (Very good!)",
                "¡Perfecto! (Perfect!)",
            ]);
        }
        
        return "Not quite right. Try again!";
    }
    
    /**
     * Validate the exercise content structure
     */
    public function validateContent(array $content): bool
    {
        return isset($content['question']) && 
               isset($content['options']) && 
               is_array($content['options']);
    }
}
