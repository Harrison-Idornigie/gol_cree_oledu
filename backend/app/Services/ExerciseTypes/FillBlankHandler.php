<?php

namespace App\Services\ExerciseTypes;

use App\Models\Exercise;
use Illuminate\Support\Arr;

class FillBlankHandler implements ExerciseTypeHandler
{
    /**
     * Check if the given answer is correct
     */
    public function checkAnswer(Exercise $exercise, $userAnswer): bool
    {
        if (!is_array($userAnswer) || !isset($exercise->answers['correct'])) {
            return false;
        }
        
        $correctAnswers = $exercise->answers['correct'];
        
        // Check if all blanks are filled and correct
        if (count($userAnswer) !== count($correctAnswers)) {
            return false;
        }
        
        foreach ($userAnswer as $index => $answer) {
            if (!isset($correctAnswers[$index]) || 
                strtolower($answer) !== strtolower($correctAnswers[$index])) {
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
        
        return "Check your answers and try again!";
    }
    
    /**
     * Validate the exercise content structure
     */
    public function validateContent(array $content): bool
    {
        return isset($content['text']) && 
               isset($content['blanks']) && 
               is_array($content['blanks']);
    }
}
