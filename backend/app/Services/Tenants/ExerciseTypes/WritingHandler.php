<?php

namespace App\Services\ExerciseTypes;

use App\Models\Exercise;
use Illuminate\Support\Arr;

class WritingHandler implements ExerciseTypeHandler
{
    /**
     * Check if the given answer is correct
     */
    public function checkAnswer(Exercise $exercise, $userAnswer): bool
    {
        if (!is_array($userAnswer) || !isset($exercise->answers['correct'])) {
            return false;
        }
        
        // Join the words with spaces to form a sentence
        $userSentence = implode(' ', $userAnswer);
        
        // Get the correct sentence
        $correctSentence = implode(' ', $exercise->answers['correct']);
        
        // Compare (case-insensitive)
        return strtolower($userSentence) === strtolower($correctSentence);
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
        
        return "Check the word order and try again!";
    }
    
    /**
     * Validate the exercise content structure
     */
    public function validateContent(array $content): bool
    {
        return isset($content['prompt']) && 
               isset($content['word_ids']) && 
               is_array($content['word_ids']);
    }
}
