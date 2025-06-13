<?php

namespace App\Services\Tenants\Exercise\ExerciseTypes;

use App\Models\Tenants\Exercise;

class ConversationHandler implements ExerciseTypeHandler
{
    /**
     * Check if the given answer is correct
     */
    public function checkAnswer(Exercise $exercise, $userAnswer): bool
    {
        // For conversation exercises, we need to check which step the user is on
        if (!is_array($userAnswer) || !isset($userAnswer['step_index']) || !isset($userAnswer['answer'])) {
            return false;
        }
        
        $stepIndex = $userAnswer['step_index'];
        $answer = $userAnswer['answer'];
        
        // Get the step from the exercise content
        if (!isset($exercise->content['steps'][$stepIndex])) {
            return false;
        }
        
        $step = $exercise->content['steps'][$stepIndex];
        
        // Check the answer based on the step type
        switch ($step['type']) {
            case 'dialogue':
                // Dialogue steps don't have correct/incorrect answers
                return true;
                
            case 'question':
                // For questions, check if the answer matches any of the correct answers
                if (!isset($exercise->answers['steps'][$stepIndex]['correct'])) {
                    return false;
                }
                
                $correctAnswers = $exercise->answers['steps'][$stepIndex]['correct'];
                
                // If the correct answers is an array, check if the user's answer is in the array
                if (is_array($correctAnswers)) {
                    foreach ($correctAnswers as $correctAnswer) {
                        if (strtolower(trim($answer)) === strtolower(trim($correctAnswer))) {
                            return true;
                        }
                    }
                    return false;
                }
                
                // If the correct answer is a string, check if the user's answer matches
                return strtolower(trim($answer)) === strtolower(trim($correctAnswers));
                
            case 'choice':
                // For choices, check if the answer matches the correct choice
                if (!isset($exercise->answers['steps'][$stepIndex]['correct'])) {
                    return false;
                }
                
                $correctChoice = $exercise->answers['steps'][$stepIndex]['correct'];
                
                // The answer should be the index of the selected choice
                return (int)$answer === (int)$correctChoice;
                
            default:
                return false;
        }
    }
    
    /**
     * Get a hint or correct answer for the exercise
     */
    public function getHint(Exercise $exercise): mixed
    {
        // For conversation exercises, hints depend on the current step
        // We'll return the first correct answer for the current step
        if (!isset($exercise->content['current_step']) || !isset($exercise->answers['steps'])) {
            return null;
        }
        
        $stepIndex = $exercise->content['current_step'];
        
        if (!isset($exercise->answers['steps'][$stepIndex])) {
            return null;
        }
        
        $stepAnswer = $exercise->answers['steps'][$stepIndex];
        
        if (isset($stepAnswer['correct'])) {
            if (is_array($stepAnswer['correct']) && !empty($stepAnswer['correct'])) {
                return $stepAnswer['correct'][0];
            }
            
            return $stepAnswer['correct'];
        }
        
        return null;
    }
    
    /**
     * Get feedback for the exercise attempt
     */
    public function getFeedback(Exercise $exercise, bool $isCorrect): string
    {
        if ($isCorrect) {
            return 'Great job! That\'s correct.';
        }
        
        return 'That\'s not quite right. Try again.';
    }
    
    /**
     * Validate the exercise content structure
     */
    public function validateContent(array $content): bool
    {
        // Check if the content has the required fields
        if (!isset($content['title']) || !isset($content['description']) || !isset($content['steps'])) {
            return false;
        }
        
        // Check if steps is an array with at least 2 elements
        if (!is_array($content['steps']) || count($content['steps']) < 2) {
            return false;
        }
        
        // Check each step
        foreach ($content['steps'] as $step) {
            // Check if the step has a type
            if (!isset($step['type'])) {
                return false;
            }
            
            // Check if the type is valid
            if (!in_array($step['type'], ['dialogue', 'question', 'choice'])) {
                return false;
            }
            
            // Check if the step has content
            if (!isset($step['content'])) {
                return false;
            }
            
            // Additional validation based on step type
            switch ($step['type']) {
                case 'dialogue':
                    // Dialogue steps should have a speaker and text
                    if (!isset($step['content']['speaker']) || !isset($step['content']['text'])) {
                        return false;
                    }
                    break;
                    
                case 'question':
                    // Question steps should have a question
                    if (!isset($step['content']['question'])) {
                        return false;
                    }
                    break;
                    
                case 'choice':
                    // Choice steps should have a question and options
                    if (!isset($step['content']['question']) || !isset($step['content']['options']) || !is_array($step['content']['options'])) {
                        return false;
                    }
                    
                    // Check if options is an array with at least 2 elements
                    if (count($step['content']['options']) < 2) {
                        return false;
                    }
                    break;
            }
        }
        
        return true;
    }
}