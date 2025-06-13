<?php

namespace App\Services\Tenants\Exercise;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SpeechRecognitionService
{
    /**
     * Process audio using Whisper API or local Whisper model
     * 
     * @param string $audioPath Path to the audio file
     * @param string $language Language code (e.g., 'en', 'es', 'crk' for Plains Cree)
     * @param string $expectedText The text that should be spoken
     * @return array Result with score, phonemes, and feedback
     */
    public function processAudio(string $audioPath, string $language, string $expectedText): array
    {
        try {
            // For development, we'll use a simulated response
            // In production, this would connect to Whisper API or a local Whisper model
            if (app()->environment('testing', 'local')) {
                return $this->simulateRecognition($language, $expectedText);
            }
            
            // In production, use the actual Whisper API or local model
            return $this->recognizeWithWhisper($audioPath, $language, $expectedText);
        } catch (\Exception $e) {
            Log::error('Speech recognition error: ' . $e->getMessage(), [
                'audio_path' => $audioPath,
                'language' => $language,
                'expected_text' => $expectedText,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return a fallback response
            return [
                'score' => 0.0,
                'phonemes' => $this->textToPhonemes($expectedText),
                'message' => 'Sorry, we could not process your recording. Please try again.'
            ];
        }
    }
    
    /**
     * Recognize speech using Whisper API or local model
     */
    private function recognizeWithWhisper(string $audioPath, string $language, string $expectedText): array
    {
        // This is where you would implement the actual Whisper API call or local model inference
        // For now, we'll use a simulated response
        return $this->simulateRecognition($language, $expectedText);
    }
    
    /**
     * Simulate speech recognition for development and testing
     */
    private function simulateRecognition(string $language, string $expectedText): array
    {
        // Simulate a recognition result with random accuracy
        $accuracy = mt_rand(60, 95) / 100;
        
        // Create phoneme-level feedback
        $phonemes = $this->textToPhonemes($expectedText);
        
        // Randomly mark some phonemes as incorrect
        foreach ($phonemes as &$phoneme) {
            $phonemeScore = mt_rand(50, 100) / 100;
            $phoneme['score'] = $phonemeScore;
            $phoneme['isCorrect'] = $phonemeScore >= 0.7;
        }
        
        // Generate appropriate feedback message
        $message = $this->generateFeedbackMessage($accuracy, $language);
        
        return [
            'score' => $accuracy,
            'phonemes' => $phonemes,
            'message' => $message
        ];
    }
    
    /**
     * Convert text to phonemes for visualization
     */
    private function textToPhonemes(string $text): array
    {
        // Split the text into words and then into phonemes
        // This is a simplified version - in production, you would use a proper phonetic dictionary
        $words = explode(' ', $text);
        $phonemes = [];
        
        foreach ($words as $word) {
            // For simplicity, we're treating each word as a single phoneme
            // In a real implementation, you would break words into actual phonemes
            $phonemes[] = [
                'text' => $word,
                'score' => 1.0,
                'isCorrect' => true
            ];
        }
        
        return $phonemes;
    }
    
    /**
     * Generate appropriate feedback message based on score and language
     */
    private function generateFeedbackMessage(float $score, string $language): string
    {
        if ($score >= 0.9) {
            return $this->getExcellentFeedback($language);
        } elseif ($score >= 0.7) {
            return $this->getGoodFeedback($language);
        } else {
            return $this->getNeedsPracticeFeedback($language);
        }
    }
    
    /**
     * Get feedback message for excellent pronunciation
     */
    private function getExcellentFeedback(string $language): string
    {
        $messages = [
            'en' => 'Excellent pronunciation! You sound like a native speaker.',
            'es' => '¡Excelente pronunciación! Suenas como un hablante nativo.',
            'crk' => 'Mitoni kwayask kitâcimowinis! You sound like a fluent speaker.'
        ];
        
        return $messages[$language] ?? $messages['en'];
    }
    
    /**
     * Get feedback message for good pronunciation
     */
    private function getGoodFeedback(string $language): string
    {
        $messages = [
            'en' => 'Good job! Your pronunciation is clear and understandable.',
            'es' => '¡Buen trabajo! Tu pronunciación es clara y comprensible.',
            'crk' => 'Miyo-atoskêwin! Your pronunciation is clear and understandable.'
        ];
        
        return $messages[$language] ?? $messages['en'];
    }
    
    /**
     * Get feedback message for pronunciation that needs practice
     */
    private function getNeedsPracticeFeedback(string $language): string
    {
        $messages = [
            'en' => 'Keep practicing! Focus on the highlighted words to improve your pronunciation.',
            'es' => '¡Sigue practicando! Concéntrate en las palabras resaltadas para mejorar tu pronunciación.',
            'crk' => 'Âhkamêyimo! Focus on the highlighted words to improve your pronunciation.'
        ];
        
        return $messages[$language] ?? $messages['en'];
    }
}