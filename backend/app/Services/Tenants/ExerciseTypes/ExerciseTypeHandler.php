<?php

namespace App\Services\ExerciseTypes;

use App\Models\Exercise;

interface ExerciseTypeHandler
{
    /**
     * Check if the given answer is correct
     */
    public function checkAnswer(Exercise $exercise, $userAnswer): bool;
    
    /**
     * Get a hint or correct answer for the exercise
     */
    public function getHint(Exercise $exercise): mixed;
    
    /**
     * Get feedback for the exercise attempt
     */
    public function getFeedback(Exercise $exercise, bool $isCorrect): string;
    
    /**
     * Validate the exercise content structure
     */
    public function validateContent(array $content): bool;
}
