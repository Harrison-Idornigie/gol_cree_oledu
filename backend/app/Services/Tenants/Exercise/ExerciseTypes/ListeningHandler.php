<?php

namespace App\Services\Tenants\Exercise\ExerciseTypes;

use App\Models\Tenants\Exercise;
use Illuminate\Support\Str;

class ListeningHandler implements ExerciseTypeHandler
{
    /**
     * Check if the given answer is correct
     */
    public function checkAnswer(Exercise $exercise, $userAnswer): bool
    {
        if (!is_string($userAnswer)) {
            return false;
        }
        
        // Normalize the user's answer
        $normalizedUserAnswer = $this->normalizeText($userAnswer);
        
        // Check against the correct answers
        if (!isset($exercise->answers['correct']) || !is_array($exercise->answers['correct'])) {
            return false;
        }
        
        // Check if the normalized user answer matches any of the correct answers
        foreach ($exercise->answers['correct'] as $correctAnswer) {
            $normalizedCorrectAnswer = $this->normalizeText($correctAnswer);
            
            if ($normalizedUserAnswer === $normalizedCorrectAnswer) {
                return true;
            }
        }
        
        // Check against alternative answers if available
        if (isset($exercise->answers['alternatives']) && is_array($exercise->answers['alternatives'])) {
            foreach ($exercise->answers['alternatives'] as $alternativeAnswer) {
                $normalizedAlternativeAnswer = $this->normalizeText($alternativeAnswer);
                
                if ($normalizedUserAnswer === $normalizedAlternativeAnswer) {
                    return true;
                }
            }
        }
        
        // Check for close matches (e.g., minor typos)
        if (isset($exercise->answers['correct'][0])) {
            $primaryCorrectAnswer = $this->normalizeText($exercise->answers['correct'][0]);
            
            // If the answer is very close (e.g., one character difference), consider it correct
            if ($this->isCloseMatch($normalizedUserAnswer, $primaryCorrectAnswer)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get a hint or correct answer for the exercise
     */
    public function getHint(Exercise $exercise): mixed
    {
        if (!isset($exercise->answers['correct']) || !is_array($exercise->answers['correct']) || empty($exercise->answers['correct'])) {
            return null;
        }
        
        $correctAnswer = $exercise->answers['correct'][0];
        
        // Return the first word as a hint
        $words = explode(' ', $correctAnswer);
        if (count($words) > 0) {
            return $words[0] . '...';
        }
        
        return null;
    }
    
    /**
     * Get feedback for the exercise attempt
     */
    public function getFeedback(Exercise $exercise, bool $isCorrect): string
    {
        if ($isCorrect) {
            return 'Great job! Your transcription is correct.';
        }
        
        return 'That\'s not quite right. Listen again and try once more.';
    }
    
    /**
     * Validate the exercise content structure
     */
    public function validateContent(array $content): bool
    {
        // Check if the content has the required fields
        if (!isset($content['audio_url']) || !isset($content['transcript']) || !isset($content['prompt']) || !isset($content['language']) || !isset($content['difficulty'])) {
            return false;
        }
        
        // Check if the difficulty is valid
        if (!in_array($content['difficulty'], ['beginner', 'intermediate', 'advanced'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Normalize text for comparison
     */
    private function normalizeText(string $text): string
    {
        // Convert to lowercase
        $text = Str::lower($text);
        
        // Remove punctuation
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);
        
        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Trim whitespace
        $text = trim($text);
        
        return $text;
    }
    
    /**
     * Check if two strings are close matches
     */
    private function isCloseMatch(string $str1, string $str2): bool
    {
        // If the strings are identical, they're a match
        if ($str1 === $str2) {
            return true;
        }
        
        // If the strings are very short, require exact match
        if (strlen($str1) < 3 || strlen($str2) < 3) {
            return false;
        }
        
        // Calculate Levenshtein distance
        $distance = levenshtein($str1, $str2);
        
        // Allow for more differences in longer strings
        $maxLength = max(strlen($str1), strlen($str2));
        $threshold = min(2, floor($maxLength * 0.1)); // 10% of length or max 2 characters
        
        return $distance <= $threshold;
    }
}