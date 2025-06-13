<?php

namespace App\Services\Tenants\Exercise\ExerciseTypes;

use App\Models\Tenants\Exercise;
use Illuminate\Support\Arr;

class MatchingHandler implements ExerciseTypeHandler
{
    /**
     * Check if the given answer is correct
     */
    public function checkAnswer(Exercise $exercise, $userAnswer): bool
    {
        if (!is_array($userAnswer) || !isset($exercise->answers['correct'])) {
            return false;
        }
        
        $correctMatches = $exercise->answers['correct'];
        
        // Check if all matches are correct
        foreach ($correctMatches as $index => $match) {
            if (!isset($userAnswer[$index]) || $userAnswer[$index] != $match) {
                return false;
            }
        }
        
        return true;
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
        
        return "Check your matches and try again!";
    }
    
    /**
     * Validate the exercise content structure
     */
    public function validateContent(array $content): bool
    {
        return isset($content['items']) && 
               is_array($content['items']) &&
               isset($content['matches']) && 
               is_array($content['matches']);
    }
}
