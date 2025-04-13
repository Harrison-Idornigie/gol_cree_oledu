<?php

namespace App\Services\ExerciseTypes;

use App\Models\Exercise;

class PictureHandler implements ExerciseTypeHandler
{
    /**
     * Check if the given answer is correct
     */
    public function checkAnswer(Exercise $exercise, $userAnswer): bool
    {
        if (!is_numeric($userAnswer)) {
            return false;
        }
        
        $selectedOption = (int) $userAnswer;
        
        // Check if the selected option is the correct one
        if (!isset($exercise->answers['correct'])) {
            return false;
        }
        
        $correctOption = (int) $exercise->answers['correct'];
        
        return $selectedOption === $correctOption;
    }
    
    /**
     * Get a hint or correct answer for the exercise
     */
    public function getHint(Exercise $exercise): mixed
    {
        // For picture exercises, we don't provide hints
        // as it would give away the answer
        return null;
    }
    
    /**
     * Get feedback for the exercise attempt
     */
    public function getFeedback(Exercise $exercise, bool $isCorrect): string
    {
        if ($isCorrect) {
            return 'Great job! You selected the correct image.';
        }
        
        return 'That\'s not the right image. Try again.';
    }
    
    /**
     * Validate the exercise content structure
     */
    public function validateContent(array $content): bool
    {
        // Check if the content has the required fields
        if (!isset($content['question']) || !isset($content['mode']) || !isset($content['language'])) {
            return false;
        }
        
        // Check if the mode is valid
        if (!in_array($content['mode'], ['word_to_image', 'image_to_word'])) {
            return false;
        }
        
        // Validate based on the mode
        if ($content['mode'] === 'word_to_image') {
            // Check if images array exists and has at least 2 elements
            if (!isset($content['images']) || !is_array($content['images']) || count($content['images']) < 2) {
                return false;
            }
            
            // Check if each image has url and alt
            foreach ($content['images'] as $image) {
                if (!isset($image['url']) || !isset($image['alt'])) {
                    return false;
                }
            }
        } else if ($content['mode'] === 'image_to_word') {
            // Check if words array exists and has at least 2 elements
            if (!isset($content['words']) || !is_array($content['words']) || count($content['words']) < 2) {
                return false;
            }
            
            // Check if target_image exists
            if (!isset($content['target_image'])) {
                return false;
            }
        }
        
        return true;
    }
}
