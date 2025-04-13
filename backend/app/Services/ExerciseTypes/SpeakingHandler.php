<?php

namespace App\Services\ExerciseTypes;

use App\Models\Exercise;
use Illuminate\Support\Arr;

class SpeakingHandler implements ExerciseTypeHandler
{
    /**
     * Check if the given answer is correct
     * Note: Speaking exercises typically require manual review or advanced speech recognition
     */
    public function checkAnswer(Exercise $exercise, $userAnswer): bool
    {
        // For now, speaking exercises require manual review
        // In the future, this could integrate with a speech recognition service
        return false;
    }
    
    /**
     * Get a hint or correct answer for the exercise
     */
    public function getHint(Exercise $exercise): mixed
    {
        return $exercise->content['prompt'] ?? null;
    }
    
    /**
     * Get feedback for the exercise attempt
     */
    public function getFeedback(Exercise $exercise, bool $isCorrect): string
    {
        // Since speaking exercises require manual review, we provide a generic message
        return "Your speaking exercise has been submitted for review.";
    }
    
    /**
     * Validate the exercise content structure
     */
    public function validateContent(array $content): bool
    {
        return isset($content['prompt']) && 
               isset($content['duration']) && 
               is_numeric($content['duration']);
    }
}
