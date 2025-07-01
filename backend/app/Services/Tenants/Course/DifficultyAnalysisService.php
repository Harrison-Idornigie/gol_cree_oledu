<?php

namespace App\Services\Tenants\Course;

use App\Models\Tenants\Exercise;
use App\Models\Tenants\Word;
use App\Models\Tenants\Sentence;
use App\Models\Tenants\LearningPath;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Difficulty Analysis Service
 * 
 * Handles difficulty calculation and analysis operations including:
 * - Text difficulty calculation based on various metrics
 * - Word difficulty assessment through vocabulary analysis
 * - Exercise difficulty scoring
 * - Learning progression validation
 * - Difficulty optimization recommendations
 */
class DifficultyAnalysisService
{
    // Difficulty level constants
    public const DIFFICULTY_BEGINNER = 'beginner';
    public const DIFFICULTY_INTERMEDIATE = 'intermediate';
    public const DIFFICULTY_ADVANCED = 'advanced';

    // Scoring weights for different factors
    private const WEIGHTS = [
        'word_frequency' => 0.3,
        'sentence_length' => 0.2,
        'grammar_complexity' => 0.25,
        'vocabulary_rarity' => 0.25
    ];

    /**
     * Calculate text difficulty based on multiple factors.
     */
    public function calculateTextDifficulty(string $text, string $languageCode): float
    {
        $score = 0;
        $factors = [];

        // Factor 1: Average word length
        $avgWordLength = $this->calculateAverageWordLength($text);
        $factors['avg_word_length'] = $avgWordLength;
        $score += $this->normalizeWordLength($avgWordLength) * self::WEIGHTS['word_frequency'];

        // Factor 2: Sentence length
        $sentenceLength = $this->calculateSentenceLength($text);
        $factors['sentence_length'] = $sentenceLength;
        $score += $this->normalizeSentenceLength($sentenceLength) * self::WEIGHTS['sentence_length'];

        // Factor 3: Vocabulary complexity
        $vocabComplexity = $this->calculateVocabularyComplexity($text, $languageCode);
        $factors['vocab_complexity'] = $vocabComplexity;
        $score += $vocabComplexity * self::WEIGHTS['vocabulary_rarity'];

        // Factor 4: Grammar complexity (simplified)
        $grammarComplexity = $this->calculateGrammarComplexity($text);
        $factors['grammar_complexity'] = $grammarComplexity;
        $score += $grammarComplexity * self::WEIGHTS['grammar_complexity'];

        Log::debug('Text difficulty calculated', [
            'text_length' => strlen($text),
            'language' => $languageCode,
            'factors' => $factors,
            'final_score' => $score
        ]);

        return min(100, max(0, $score * 100)); // Normalize to 0-100
    }

    /**
     * Calculate vocabulary difficulty based on word rarity and complexity.
     */
    public function calculateVocabularyDifficulty(array $wordIds): float
    {
        if (empty($wordIds)) {
            return 0;
        }

        $words = Word::with(['translations', 'sentences'])->whereIn('id', $wordIds)->get();

        if ($words->isEmpty()) {
            return 0;
        }

        $totalScore = 0;
        $wordCount = 0;

        foreach ($words as $word) {
            $wordScore = $this->calculateWordDifficulty($word);
            $totalScore += $wordScore;
            $wordCount++;
        }

        $averageScore = $wordCount > 0 ? $totalScore / $wordCount : 0;

        Log::debug('Vocabulary difficulty calculated', [
            'word_count' => $wordCount,
            'average_score' => $averageScore,
            'word_ids' => $wordIds
        ]);

        return $averageScore;
    }

    /**
     * Calculate exercise difficulty based on type and content.
     */
    public function calculateExerciseDifficulty(Exercise $exercise): float
    {
        $baseScore = $this->getExerciseTypeBaseScore($exercise->type);
        $contentScore = $this->analyzeExerciseContent($exercise);

        // Combine scores with weights
        $finalScore = ($baseScore * 0.4) + ($contentScore * 0.6);

        Log::debug('Exercise difficulty calculated', [
            'exercise_id' => $exercise->id,
            'type' => $exercise->type,
            'base_score' => $baseScore,
            'content_score' => $contentScore,
            'final_score' => $finalScore
        ]);

        return $finalScore;
    }

    /**
     * Validate difficulty progression in a collection of content.
     */
    public function validateDifficultyProgression(Collection $content): array
    {
        $difficulties = [];
        $issues = [];

        foreach ($content as $item) {
            if ($item instanceof Exercise) {
                $difficulty = $this->calculateExerciseDifficulty($item);
            } elseif ($item instanceof Sentence) {
                $difficulty = $this->calculateTextDifficulty($item->text, $item->language->code);
            } else {
                continue;
            }

            $difficulties[] = [
                'item_id' => $item->id,
                'item_type' => class_basename($item),
                'difficulty' => $difficulty,
                'order' => $item->order ?? count($difficulties)
            ];
        }

        // Sort by order
        usort($difficulties, fn($a, $b) => $a['order'] <=> $b['order']);

        // Check for progression issues
        for ($i = 1; $i < count($difficulties); $i++) {
            $current = $difficulties[$i];
            $previous = $difficulties[$i - 1];

            // Check for sudden difficulty spikes
            $difficultyJump = $current['difficulty'] - $previous['difficulty'];
            if ($difficultyJump > 25) {
                $issues[] = [
                    'type' => 'difficulty_spike',
                    'severity' => 'high',
                    'message' => "Large difficulty jump (" . number_format($difficultyJump, 1) . " points) between items {$previous['item_id']} and {$current['item_id']}",
                    'items' => [$previous, $current]
                ];
            }

            // Check for difficulty regression
            if ($difficultyJump < -15) {
                $issues[] = [
                    'type' => 'difficulty_regression',
                    'severity' => 'medium',
                    'message' => "Difficulty regression (" . number_format($difficultyJump, 1) . " points) between items {$previous['item_id']} and {$current['item_id']}",
                    'items' => [$previous, $current]
                ];
            }
        }

        return [
            'progression' => $difficulties,
            'issues' => $issues,
            'overall_trend' => $this->calculateProgressionTrend($difficulties),
            'recommendations' => $this->generateProgressionRecommendations($issues)
        ];
    }

    /**
     * Suggest difficulty adjustments for content.
     */
    public function suggestDifficultyAdjustments(Collection $content): array
    {
        $analysis = $this->validateDifficultyProgression($content);
        $suggestions = [];

        foreach ($analysis['issues'] as $issue) {
            switch ($issue['type']) {
                case 'difficulty_spike':
                    $suggestions[] = [
                        'type' => 'add_intermediate_content',
                        'priority' => 'high',
                        'description' => 'Add intermediate exercises between items to smooth progression',
                        'affected_items' => $issue['items']
                    ];
                    break;

                case 'difficulty_regression':
                    $suggestions[] = [
                        'type' => 'reorder_content',
                        'priority' => 'medium',
                        'description' => 'Consider reordering content to maintain progression',
                        'affected_items' => $issue['items']
                    ];
                    break;
            }
        }

        return [
            'analysis' => $analysis,
            'suggestions' => $suggestions,
            'optimization_score' => $this->calculateOptimizationScore($analysis)
        ];
    }

    /**
     * Optimize learning curve for a learning path.
     */
    public function optimizeLearningCurve(LearningPath $learningPath): array
    {
        $allContent = collect();

        // Collect all exercises from the learning path
        foreach ($learningPath->units as $unit) {
            foreach ($unit->topics as $topic) {
                foreach ($topic->lessons as $lesson) {
                    $allContent = $allContent->merge($lesson->exercises);
                }
            }
        }

        $analysis = $this->validateDifficultyProgression($allContent);
        $optimizations = [];

        // Generate specific optimizations
        if (!empty($analysis['issues'])) {
            $optimizations = $this->generateOptimizations($analysis['issues'], $allContent);
        }

        return [
            'current_analysis' => $analysis,
            'optimizations' => $optimizations,
            'estimated_improvement' => $this->estimateImprovementScore($analysis, $optimizations)
        ];
    }

    /**
     * Calculate average word length in text.
     */
    private function calculateAverageWordLength(string $text): float
    {
        $words = str_word_count($text, 1);
        if (empty($words)) {
            return 0;
        }

        $totalLength = array_sum(array_map('strlen', $words));
        return $totalLength / count($words);
    }

    /**
     * Calculate sentence length.
     */
    private function calculateSentenceLength(string $text): int
    {
        return str_word_count($text);
    }

    /**
     * Calculate vocabulary complexity based on word frequency and rarity.
     */
    private function calculateVocabularyComplexity(string $text, string $languageCode): float
    {
        $words = str_word_count(strtolower($text), 1);
        $totalComplexity = 0;
        $wordCount = 0;

        foreach ($words as $wordText) {
            // Look up word in database
            $word = Word::whereHas('language', function ($query) use ($languageCode) {
                $query->where('code', $languageCode);
            })->where('text', $wordText)->first();

            if ($word) {
                $complexity = $this->calculateWordComplexity($word);
                $totalComplexity += $complexity;
            } else {
                // Unknown word - assume high complexity
                $totalComplexity += 0.8;
            }
            $wordCount++;
        }

        return $wordCount > 0 ? $totalComplexity / $wordCount : 0.5;
    }

    /**
     * Calculate grammar complexity (simplified heuristic).
     */
    private function calculateGrammarComplexity(string $text): float
    {
        $complexity = 0;

        // Count complex punctuation
        $complexPunctuation = [';', ':', '(', ')', '"', "'"];
        foreach ($complexPunctuation as $punct) {
            $complexity += substr_count($text, $punct) * 0.1;
        }

        // Count subordinate clauses (simplified)
        $subordinateWords = ['because', 'although', 'while', 'since', 'unless', 'whereas'];
        foreach ($subordinateWords as $word) {
            if (stripos($text, $word) !== false) {
                $complexity += 0.2;
            }
        }

        return min(1.0, $complexity);
    }

    /**
     * Normalize word length to 0-1 scale.
     */
    private function normalizeWordLength(float $avgLength): float
    {
        // Assume 3-letter words are easy, 8+ letter words are hard
        return min(1.0, max(0.0, ($avgLength - 3) / 5));
    }

    /**
     * Normalize sentence length to 0-1 scale.
     */
    private function normalizeSentenceLength(int $wordCount): float
    {
        // Assume 5-word sentences are easy, 20+ word sentences are hard
        return min(1.0, max(0.0, ($wordCount - 5) / 15));
    }

    /**
     * Calculate difficulty for a single word.
     */
    private function calculateWordDifficulty(Word $word): float
    {
        $score = 0;

        // Factor 1: Word length
        $length = strlen($word->text);
        $score += $this->normalizeWordLength($length) * 0.3;

        // Factor 2: Number of translations (fewer = more specialized/difficult)
        $translationCount = $word->translations->count();
        $translationScore = $translationCount > 0 ? min(1.0, 3 / $translationCount) : 1.0;
        $score += $translationScore * 0.2;

        // Factor 3: Usage frequency (based on sentence count)
        $usageCount = $word->sentences()->count();
        $usageScore = $usageCount > 0 ? min(1.0, 10 / $usageCount) : 1.0;
        $score += $usageScore * 0.3;

        // Factor 4: Part of speech complexity
        $posScore = $this->getPartOfSpeechComplexity($word->part_of_speech);
        $score += $posScore * 0.2;

        return min(100, $score * 100);
    }

    /**
     * Calculate word complexity based on various factors.
     */
    private function calculateWordComplexity(Word $word): float
    {
        $complexity = 0;

        // Length factor
        $length = strlen($word->text);
        if ($length > 8) {
            $complexity += 0.3;
        } elseif ($length > 5) {
            $complexity += 0.1;
        }

        // Frequency factor (based on usage in sentences)
        $usageCount = $word->sentences()->count();
        if ($usageCount < 2) {
            $complexity += 0.4; // Rare words are more complex
        } elseif ($usageCount < 5) {
            $complexity += 0.2;
        }

        // Part of speech factor
        $complexity += $this->getPartOfSpeechComplexity($word->part_of_speech);

        return min(1.0, $complexity);
    }

    /**
     * Get complexity score for part of speech.
     */
    private function getPartOfSpeechComplexity(?string $partOfSpeech): float
    {
        return match ($partOfSpeech) {
            'noun', 'verb' => 0.1,
            'adjective', 'adverb' => 0.2,
            'preposition', 'conjunction' => 0.3,
            'pronoun' => 0.15,
            default => 0.25
        };
    }

    /**
     * Get base difficulty score for exercise type.
     */
    private function getExerciseTypeBaseScore(string $exerciseType): float
    {
        return match ($exerciseType) {
            Exercise::TYPE_MULTIPLE_CHOICE => 20,
            Exercise::TYPE_MATCHING => 30,
            Exercise::TYPE_FILL_BLANK => 40,
            Exercise::TYPE_WRITING => 60,
            Exercise::TYPE_SPEAKING => 70,
            Exercise::TYPE_CONVERSATION => 75,
            Exercise::TYPE_LISTENING => 50,
            Exercise::TYPE_PICTURE => 35,
            default => 40
        };
    }

    /**
     * Analyze exercise content for difficulty.
     */
    private function analyzeExerciseContent(Exercise $exercise): float
    {
        $content = $exercise->content ?? [];
        $score = 0;

        // Analyze based on exercise type
        switch ($exercise->type) {
            case Exercise::TYPE_MULTIPLE_CHOICE:
                $score = $this->analyzeMultipleChoiceContent($content);
                break;
            case Exercise::TYPE_FILL_BLANK:
                $score = $this->analyzeFillBlankContent($content);
                break;
            case Exercise::TYPE_WRITING:
                $score = $this->analyzeWritingContent($content);
                break;
            default:
                $score = 50; // Default medium difficulty
        }

        return $score;
    }

    /**
     * Analyze multiple choice content difficulty.
     */
    private function analyzeMultipleChoiceContent(array $content): float
    {
        $score = 30; // Base score

        // More options = harder
        $optionCount = count($content['options'] ?? []);
        $score += min(20, $optionCount * 3);

        // Question complexity
        if (isset($content['question'])) {
            $questionComplexity = $this->calculateTextDifficulty($content['question'], 'en');
            $score += $questionComplexity * 0.3;
        }

        return min(100, $score);
    }

    /**
     * Analyze fill blank content difficulty.
     */
    private function analyzeFillBlankContent(array $content): float
    {
        $score = 40; // Base score

        // Text complexity
        if (isset($content['text'])) {
            $textComplexity = $this->calculateTextDifficulty($content['text'], 'en');
            $score += $textComplexity * 0.4;
        }

        // Number of blanks
        $blankCount = substr_count($content['text'] ?? '', '____');
        $score += min(20, $blankCount * 5);

        return min(100, $score);
    }

    /**
     * Analyze writing content difficulty.
     */
    private function analyzeWritingContent(array $content): float
    {
        $score = 60; // Base score for writing

        // Prompt complexity
        if (isset($content['prompt'])) {
            $promptComplexity = $this->calculateTextDifficulty($content['prompt'], 'en');
            $score += $promptComplexity * 0.3;
        }

        // Required word count
        $minLength = $content['min_length'] ?? 0;
        if ($minLength > 50) {
            $score += 15;
        } elseif ($minLength > 20) {
            $score += 10;
        }

        return min(100, $score);
    }

    /**
     * Calculate progression trend.
     */
    private function calculateProgressionTrend(array $difficulties): string
    {
        if (count($difficulties) < 2) {
            return 'insufficient_data';
        }

        $first = $difficulties[0]['difficulty'];
        $last = $difficulties[count($difficulties) - 1]['difficulty'];
        $difference = $last - $first;

        if ($difference > 15) {
            return 'increasing';
        } elseif ($difference < -15) {
            return 'decreasing';
        } else {
            return 'stable';
        }
    }

    /**
     * Generate progression recommendations.
     */
    private function generateProgressionRecommendations(array $issues): array
    {
        $recommendations = [];

        $spikeCount = count(array_filter($issues, fn($issue) => $issue['type'] === 'difficulty_spike'));
        $regressionCount = count(array_filter($issues, fn($issue) => $issue['type'] === 'difficulty_regression'));

        if ($spikeCount > 0) {
            $recommendations[] = [
                'type' => 'smooth_progression',
                'priority' => 'high',
                'description' => "Add {$spikeCount} intermediate exercises to smooth difficulty spikes"
            ];
        }

        if ($regressionCount > 0) {
            $recommendations[] = [
                'type' => 'reorder_content',
                'priority' => 'medium',
                'description' => "Reorder {$regressionCount} exercises to maintain progression"
            ];
        }

        if (empty($recommendations)) {
            $recommendations[] = [
                'type' => 'maintain_quality',
                'priority' => 'low',
                'description' => 'Difficulty progression is well-balanced'
            ];
        }

        return $recommendations;
    }

    /**
     * Calculate optimization score.
     */
    private function calculateOptimizationScore(array $analysis): float
    {
        $issueCount = count($analysis['issues']);
        $totalItems = count($analysis['progression']);

        if ($totalItems === 0) {
            return 0;
        }

        $errorRate = $issueCount / $totalItems;
        return max(0, 100 - ($errorRate * 100));
    }

    /**
     * Generate optimizations based on issues.
     */
    private function generateOptimizations(array $issues, Collection $content): array
    {
        $optimizations = [];

        foreach ($issues as $issue) {
            $optimization = [
                'issue' => $issue,
                'solution' => $this->generateSolutionForIssue($issue),
                'estimated_effort' => $this->estimateEffortForIssue($issue),
                'impact' => $this->estimateImpactForIssue($issue)
            ];

            $optimizations[] = $optimization;
        }

        return $optimizations;
    }

    /**
     * Generate solution for a specific issue.
     */
    private function generateSolutionForIssue(array $issue): array
    {
        switch ($issue['type']) {
            case 'difficulty_spike':
                return [
                    'type' => 'add_content',
                    'description' => 'Add intermediate exercises between the identified items',
                    'action' => 'create_bridging_exercises'
                ];
            case 'difficulty_regression':
                return [
                    'type' => 'reorder',
                    'description' => 'Reorder exercises to maintain progression',
                    'action' => 'swap_exercise_order'
                ];
            default:
                return [
                    'type' => 'review',
                    'description' => 'Manual review required',
                    'action' => 'manual_intervention'
                ];
        }
    }

    /**
     * Estimate effort required to fix an issue.
     */
    private function estimateEffortForIssue(array $issue): string
    {
        return match ($issue['severity']) {
            'high' => 'high',
            'medium' => 'medium',
            default => 'low'
        };
    }

    /**
     * Estimate impact of fixing an issue.
     */
    private function estimateImpactForIssue(array $issue): string
    {
        return match ($issue['type']) {
            'difficulty_spike' => 'high',
            'difficulty_regression' => 'medium',
            default => 'low'
        };
    }

    /**
     * Estimate improvement score after optimizations.
     */
    private function estimateImprovementScore(array $analysis, array $optimizations): float
    {
        $currentScore = $this->calculateOptimizationScore($analysis);
        $potentialImprovement = 0;

        foreach ($optimizations as $optimization) {
            $impact = match ($optimization['impact']) {
                'high' => 15,
                'medium' => 10,
                'low' => 5,
                default => 5
            };
            $potentialImprovement += $impact;
        }

        return min(100, $currentScore + $potentialImprovement);
    }
}
