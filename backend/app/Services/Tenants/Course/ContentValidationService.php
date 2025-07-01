<?php

namespace App\Services\Tenants\Course;

use App\Models\Tenants\Exercise;
use App\Models\Tenants\Lesson;
use App\Models\Tenants\Word;
use App\Models\Tenants\Sentence;
use App\Models\Tenants\LearningPath;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Content Validation Service
 * 
 * Handles content quality validation and improvement operations including:
 * - Content quality assessment and scoring
 * - Cultural appropriateness validation
 * - Grammar progression validation
 * - Automated content improvement suggestions
 * - Quality report generation
 */
class ContentValidationService
{
    // Quality thresholds
    public const QUALITY_EXCELLENT = 90;
    public const QUALITY_GOOD = 75;
    public const QUALITY_ACCEPTABLE = 60;
    public const QUALITY_POOR = 40;

    // Validation categories
    public const CATEGORY_GRAMMAR = 'grammar';
    public const CATEGORY_GUIDEBOOK = 'guidebook';
    public const CATEGORY_CULTURAL = 'cultural';
    public const CATEGORY_PEDAGOGICAL = 'pedagogical';
    public const CATEGORY_TECHNICAL = 'technical';

    protected DifficultyAnalysisService $difficultyService;

    public function __construct(DifficultyAnalysisService $difficultyService)
    {
        $this->difficultyService = $difficultyService;
    }

    /**
     * Validate content quality for various content types.
     */
    public function validateContentQuality(array $content): array
    {
        $results = [
            'overall_score' => 0,
            'category_scores' => [],
            'issues' => [],
            'recommendations' => [],
            'validation_timestamp' => now()->toISOString()
        ];

        // Validate different aspects
        $grammarValidation = $this->validateGrammarQuality($content);
        $guidebookValidation = $this->validateGuidebookQuality($content);
        $culturalValidation = $this->validateCulturalContent($content);
        $pedagogicalValidation = $this->validatePedagogicalStructure($content);
        $technicalValidation = $this->validateTechnicalAspects($content);

        // Compile results
        $results['category_scores'] = [
            self::CATEGORY_GRAMMAR => $grammarValidation['score'],
            self::CATEGORY_GUIDEBOOK => $guidebookValidation['score'],
            self::CATEGORY_CULTURAL => $culturalValidation['score'],
            self::CATEGORY_PEDAGOGICAL => $pedagogicalValidation['score'],
            self::CATEGORY_TECHNICAL => $technicalValidation['score']
        ];

        // Calculate overall score (weighted average)
        $weights = [
            self::CATEGORY_GRAMMAR => 0.25,
            self::CATEGORY_GUIDEBOOK => 0.25,
            self::CATEGORY_CULTURAL => 0.15,
            self::CATEGORY_PEDAGOGICAL => 0.25,
            self::CATEGORY_TECHNICAL => 0.10
        ];

        $weightedScore = 0;
        foreach ($results['category_scores'] as $category => $score) {
            $weightedScore += $score * $weights[$category];
        }
        $results['overall_score'] = round($weightedScore, 2);

        // Compile issues and recommendations
        $allValidations = [$grammarValidation, $guidebookValidation, $culturalValidation, $pedagogicalValidation, $technicalValidation];
        foreach ($allValidations as $validation) {
            $results['issues'] = array_merge($results['issues'], $validation['issues']);
            $results['recommendations'] = array_merge($results['recommendations'], $validation['recommendations']);
        }

        // Add quality level
        $results['quality_level'] = $this->getQualityLevel($results['overall_score']);

        Log::info('Content quality validation completed', [
            'overall_score' => $results['overall_score'],
            'quality_level' => $results['quality_level'],
            'issues_count' => count($results['issues']),
            'tenant_id' => tenant('id')
        ]);

        return $results;
    }

    /**
     * Check cultural appropriateness of content.
     */
    public function checkCulturalAppropriateness(array $content, string $targetCulture): array
    {
        $issues = [];
        $score = 100;

        // Check for potentially sensitive content
        $sensitiveTopics = $this->getSensitiveTopics($targetCulture);
        $culturalTaboos = $this->getCulturalTaboos($targetCulture);

        foreach ($content as $item) {
            if (isset($item['text'])) {
                $text = strtolower($item['text']);

                // Check for sensitive topics
                foreach ($sensitiveTopics as $topic) {
                    if (str_contains($text, strtolower($topic))) {
                        $issues[] = [
                            'type' => 'sensitive_topic',
                            'severity' => 'medium',
                            'message' => "Content contains potentially sensitive topic: {$topic}",
                            'item' => $item,
                            'suggestion' => 'Consider alternative phrasing or context'
                        ];
                        $score -= 10;
                    }
                }

                // Check for cultural taboos
                foreach ($culturalTaboos as $taboo) {
                    if (str_contains($text, strtolower($taboo))) {
                        $issues[] = [
                            'type' => 'cultural_taboo',
                            'severity' => 'high',
                            'message' => "Content contains culturally inappropriate reference: {$taboo}",
                            'item' => $item,
                            'suggestion' => 'Remove or replace with culturally appropriate alternative'
                        ];
                        $score -= 20;
                    }
                }
            }
        }

        return [
            'score' => max(0, $score),
            'issues' => $issues,
            'recommendations' => $this->generateCulturalRecommendations($issues, $targetCulture)
        ];
    }

    /**
     * Validate grammar progression in lessons.
     */
    public function validateGrammarProgression(Collection $lessons): array
    {
        $progression = [];
        $issues = [];
        $score = 100;

        foreach ($lessons as $lesson) {
            $grammarElements = $this->extractGrammarElements($lesson);
            $progression[] = [
                'lesson_id' => $lesson->id,
                'lesson_title' => $lesson->title,
                'grammar_elements' => $grammarElements,
                'complexity_score' => $this->calculateGrammarComplexity($grammarElements)
            ];
        }

        // Check progression logic
        for ($i = 1; $i < count($progression); $i++) {
            $current = $progression[$i];
            $previous = $progression[$i - 1];

            $complexityJump = $current['complexity_score'] - $previous['complexity_score'];

            if ($complexityJump > 30) {
                $issues[] = [
                    'type' => 'grammar_complexity_spike',
                    'severity' => 'high',
                    'message' => "Large grammar complexity jump between lessons {$previous['lesson_id']} and {$current['lesson_id']}",
                    'lessons' => [$previous, $current]
                ];
                $score -= 15;
            }

            // Check for prerequisite grammar
            $missingPrerequisites = $this->checkGrammarPrerequisites($current['grammar_elements'], $previous['grammar_elements']);
            if (!empty($missingPrerequisites)) {
                $issues[] = [
                    'type' => 'missing_grammar_prerequisites',
                    'severity' => 'medium',
                    'message' => "Lesson {$current['lesson_id']} uses grammar without proper prerequisites",
                    'missing_prerequisites' => $missingPrerequisites
                ];
                $score -= 10;
            }
        }

        return [
            'progression' => $progression,
            'score' => max(0, $score),
            'issues' => $issues,
            'recommendations' => $this->generateGrammarProgressionRecommendations($issues)
        ];
    }

    /**
     * Suggest content improvements.
     */
    public function suggestContentImprovements(array $content): array
    {
        $suggestions = [];

        // Analyze content structure
        $structureAnalysis = $this->analyzeContentStructure($content);
        if ($structureAnalysis['score'] < self::QUALITY_GOOD) {
            $suggestions[] = [
                'category' => 'structure',
                'priority' => 'high',
                'description' => 'Improve content organization and flow',
                'specific_actions' => $structureAnalysis['suggestions']
            ];
        }

        // Analyze guidebook content structure
        $guidebookAnalysis = $this->analyzeGuidebookDistribution($content);
        if ($guidebookAnalysis['score'] < self::QUALITY_GOOD) {
            $suggestions[] = [
                'category' => 'guidebook',
                'priority' => 'medium',
                'description' => 'Improve guidebook structure and word integration',
                'specific_actions' => $guidebookAnalysis['suggestions']
            ];
        }

        // Analyze exercise variety
        $exerciseAnalysis = $this->analyzeExerciseVariety($content);
        if ($exerciseAnalysis['score'] < self::QUALITY_GOOD) {
            $suggestions[] = [
                'category' => 'exercises',
                'priority' => 'medium',
                'description' => 'Increase exercise type variety and engagement',
                'specific_actions' => $exerciseAnalysis['suggestions']
            ];
        }

        return [
            'suggestions' => $suggestions,
            'priority_order' => $this->prioritizeSuggestions($suggestions),
            'estimated_impact' => $this->estimateImprovementImpact($suggestions)
        ];
    }

    /**
     * Auto-fix common content issues.
     */
    public function autoFixCommonIssues(array $content): array
    {
        $fixedContent = $content;
        $appliedFixes = [];

        foreach ($fixedContent as $index => &$item) {
            // Fix common text issues
            if (isset($item['text'])) {
                $originalText = $item['text'];
                $item['text'] = $this->fixTextIssues($item['text']);

                if ($item['text'] !== $originalText) {
                    $appliedFixes[] = [
                        'type' => 'text_cleanup',
                        'item_index' => $index,
                        'original' => $originalText,
                        'fixed' => $item['text']
                    ];
                }
            }

            // Fix exercise structure issues
            if (isset($item['type']) && isset($item['content'])) {
                $originalContent = $item['content'];
                $item['content'] = $this->fixExerciseStructure($item['content'], $item['type']);

                if ($item['content'] !== $originalContent) {
                    $appliedFixes[] = [
                        'type' => 'exercise_structure',
                        'item_index' => $index,
                        'exercise_type' => $item['type'],
                        'changes' => $this->getStructureChanges($originalContent, $item['content'])
                    ];
                }
            }
        }

        return [
            'fixed_content' => $fixedContent,
            'applied_fixes' => $appliedFixes,
            'fix_summary' => [
                'total_fixes' => count($appliedFixes),
                'text_fixes' => count(array_filter($appliedFixes, fn($fix) => $fix['type'] === 'text_cleanup')),
                'structure_fixes' => count(array_filter($appliedFixes, fn($fix) => $fix['type'] === 'exercise_structure'))
            ]
        ];
    }

    /**
     * Generate comprehensive quality report.
     */
    public function generateQualityReport(Collection $content): array
    {
        $report = [
            'summary' => [
                'total_items' => $content->count(),
                'content_types' => $this->analyzeContentTypes($content),
                'overall_quality_score' => 0,
                'quality_distribution' => []
            ],
            'detailed_analysis' => [],
            'recommendations' => [],
            'action_plan' => [],
            'generated_at' => now()->toISOString()
        ];

        $totalScore = 0;
        $qualityDistribution = [
            'excellent' => 0,
            'good' => 0,
            'acceptable' => 0,
            'poor' => 0
        ];

        foreach ($content as $item) {
            $itemAnalysis = $this->analyzeIndividualItem($item);
            $report['detailed_analysis'][] = $itemAnalysis;

            $totalScore += $itemAnalysis['quality_score'];
            $qualityLevel = $this->getQualityLevel($itemAnalysis['quality_score']);
            $qualityDistribution[strtolower($qualityLevel)]++;
        }

        $report['summary']['overall_quality_score'] = $content->count() > 0 ? round($totalScore / $content->count(), 2) : 0;
        $report['summary']['quality_distribution'] = $qualityDistribution;

        // Generate recommendations and action plan
        $report['recommendations'] = $this->generateReportRecommendations($report['detailed_analysis']);
        $report['action_plan'] = $this->generateActionPlan($report['recommendations']);

        return $report;
    }

    /**
     * Validate grammar quality.
     */
    private function validateGrammarQuality(array $content): array
    {
        $score = 100;
        $issues = [];
        $recommendations = [];

        foreach ($content as $item) {
            if (isset($item['text'])) {
                $grammarIssues = $this->checkGrammarIssues($item['text']);
                if (!empty($grammarIssues)) {
                    $issues = array_merge($issues, $grammarIssues);
                    $score -= count($grammarIssues) * 5;
                }
            }
        }

        if ($score < self::QUALITY_GOOD) {
            $recommendations[] = 'Review and correct grammar issues in content';
        }

        return [
            'score' => max(0, $score),
            'issues' => $issues,
            'recommendations' => $recommendations
        ];
    }

    /**
     * Validate guidebook quality.
     */
    private function validateGuidebookQuality(array $content): array
    {
        $score = 100;
        $issues = [];
        $recommendations = [];

        $guidebookStats = $this->analyzeGuidebookStats($content);

        // Check guidebook completeness and structure
        if ($guidebookStats['completeness_score'] < 0.7) {
            $issues[] = [
                'type' => 'incomplete_guidebook',
                'severity' => 'medium',
                'message' => 'Guidebook content appears incomplete or lacks proper structure'
            ];
            $score -= 15;
            $recommendations[] = 'Improve guidebook completeness and organization';
        }

        // Check word tracking and vocabulary integration
        if ($guidebookStats['word_integration_score'] < 0.6) {
            $issues[] = [
                'type' => 'poor_word_integration',
                'severity' => 'medium',
                'message' => 'Word tracking and vocabulary integration needs improvement'
            ];
            $score -= 10;
            $recommendations[] = 'Better integrate word tracking with lesson content';
        }

        return [
            'score' => max(0, $score),
            'issues' => $issues,
            'recommendations' => $recommendations
        ];
    }

    /**
     * Validate cultural content.
     */
    private function validateCulturalContent(array $content): array
    {
        $score = 100;
        $issues = [];
        $recommendations = [];

        // This would be more sophisticated in practice
        foreach ($content as $item) {
            if (isset($item['text'])) {
                $culturalIssues = $this->checkCulturalSensitivity($item['text']);
                if (!empty($culturalIssues)) {
                    $issues = array_merge($issues, $culturalIssues);
                    $score -= count($culturalIssues) * 10;
                }
            }
        }

        if (!empty($issues)) {
            $recommendations[] = 'Review content for cultural sensitivity and appropriateness';
        }

        return [
            'score' => max(0, $score),
            'issues' => $issues,
            'recommendations' => $recommendations
        ];
    }

    /**
     * Validate pedagogical structure.
     */
    private function validatePedagogicalStructure(array $content): array
    {
        $score = 100;
        $issues = [];
        $recommendations = [];

        // Check content organization
        $structureScore = $this->evaluateContentStructure($content);
        if ($structureScore < 0.7) {
            $issues[] = [
                'type' => 'poor_content_structure',
                'severity' => 'medium',
                'message' => 'Content structure needs improvement'
            ];
            $score -= 20;
            $recommendations[] = 'Reorganize content for better learning flow';
        }

        // Check exercise distribution
        $exerciseDistribution = $this->analyzeExerciseDistribution($content);
        if ($exerciseDistribution['balance_score'] < 0.6) {
            $issues[] = [
                'type' => 'unbalanced_exercises',
                'severity' => 'low',
                'message' => 'Exercise types are not well balanced'
            ];
            $score -= 10;
            $recommendations[] = 'Balance different types of exercises';
        }

        return [
            'score' => max(0, $score),
            'issues' => $issues,
            'recommendations' => $recommendations
        ];
    }

    /**
     * Validate technical aspects.
     */
    private function validateTechnicalAspects(array $content): array
    {
        $score = 100;
        $issues = [];
        $recommendations = [];

        foreach ($content as $item) {
            // Check required fields
            $missingFields = $this->checkRequiredFields($item);
            if (!empty($missingFields)) {
                $issues[] = [
                    'type' => 'missing_required_fields',
                    'severity' => 'high',
                    'message' => 'Missing required fields: ' . implode(', ', $missingFields),
                    'item' => $item
                ];
                $score -= count($missingFields) * 5;
            }

            // Check data format
            $formatIssues = $this->checkDataFormat($item);
            if (!empty($formatIssues)) {
                $issues = array_merge($issues, $formatIssues);
                $score -= count($formatIssues) * 3;
            }
        }

        if (!empty($issues)) {
            $recommendations[] = 'Fix technical issues and data format problems';
        }

        return [
            'score' => max(0, $score),
            'issues' => $issues,
            'recommendations' => $recommendations
        ];
    }

    /**
     * Get quality level from score.
     */
    private function getQualityLevel(float $score): string
    {
        if ($score >= self::QUALITY_EXCELLENT) {
            return 'Excellent';
        } elseif ($score >= self::QUALITY_GOOD) {
            return 'Good';
        } elseif ($score >= self::QUALITY_ACCEPTABLE) {
            return 'Acceptable';
        } else {
            return 'Poor';
        }
    }

    /**
     * Get sensitive topics for a culture.
     */
    private function getSensitiveTopics(string $culture): array
    {
        // This would be loaded from a database or configuration
        return [
            'politics',
            'religion',
            'war',
            'violence',
            'discrimination'
        ];
    }

    /**
     * Get cultural taboos for a culture.
     */
    private function getCulturalTaboos(string $culture): array
    {
        // This would be culture-specific and loaded from a database
        return [
            'inappropriate gestures',
            'offensive language',
            'cultural stereotypes'
        ];
    }

    /**
     * Generate cultural recommendations.
     */
    private function generateCulturalRecommendations(array $issues, string $culture): array
    {
        $recommendations = [];

        foreach ($issues as $issue) {
            switch ($issue['type']) {
                case 'sensitive_topic':
                    $recommendations[] = 'Consider providing cultural context for sensitive topics';
                    break;
                case 'cultural_taboo':
                    $recommendations[] = 'Replace culturally inappropriate content with suitable alternatives';
                    break;
            }
        }

        return array_unique($recommendations);
    }

    /**
     * Extract grammar elements from lesson.
     */
    private function extractGrammarElements(Lesson $lesson): array
    {
        // This would be more sophisticated in practice
        $elements = [];

        foreach ($lesson->exercises as $exercise) {
            if (isset($exercise->content['grammar_focus'])) {
                $elements[] = $exercise->content['grammar_focus'];
            }
        }

        return array_unique($elements);
    }

    /**
     * Calculate grammar complexity.
     */
    private function calculateGrammarComplexity(array $grammarElements): float
    {
        $complexityMap = [
            'present_simple' => 10,
            'present_continuous' => 20,
            'past_simple' => 25,
            'past_continuous' => 35,
            'present_perfect' => 50,
            'past_perfect' => 60,
            'future_simple' => 30,
            'conditional' => 70,
            'subjunctive' => 80
        ];

        $totalComplexity = 0;
        foreach ($grammarElements as $element) {
            $totalComplexity += $complexityMap[$element] ?? 40;
        }

        return count($grammarElements) > 0 ? $totalComplexity / count($grammarElements) : 0;
    }

    /**
     * Check grammar prerequisites.
     */
    private function checkGrammarPrerequisites(array $currentElements, array $previousElements): array
    {
        $prerequisites = [
            'present_continuous' => ['present_simple'],
            'past_continuous' => ['past_simple', 'present_continuous'],
            'present_perfect' => ['past_simple'],
            'past_perfect' => ['present_perfect'],
            'conditional' => ['future_simple']
        ];

        $missing = [];
        foreach ($currentElements as $element) {
            if (isset($prerequisites[$element])) {
                foreach ($prerequisites[$element] as $prerequisite) {
                    if (!in_array($prerequisite, $previousElements)) {
                        $missing[] = $prerequisite;
                    }
                }
            }
        }

        return array_unique($missing);
    }

    // Helper methods for validation

    /**
     * Check grammar issues in text.
     */
    private function checkGrammarIssues(string $text): array
    {
        $issues = [];

        // Simple grammar checks (would be more sophisticated in practice)
        if (preg_match('/\b(a|an)\s+(a|an)\b/i', $text)) {
            $issues[] = [
                'type' => 'duplicate_articles',
                'severity' => 'medium',
                'message' => 'Duplicate articles found in text'
            ];
        }

        if (preg_match('/\s{2,}/', $text)) {
            $issues[] = [
                'type' => 'extra_spaces',
                'severity' => 'low',
                'message' => 'Extra spaces found in text'
            ];
        }

        return $issues;
    }

    /**
     * Analyze guidebook statistics.
     */
    private function analyzeGuidebookStats(array $content): array
    {
        $hasIntroduction = false;
        $hasExamples = false;
        $hasWordTracking = false;
        $contentSections = 0;

        foreach ($content as $item) {
            if (isset($item['category'])) {
                switch ($item['category']) {
                    case 'introduction':
                        $hasIntroduction = true;
                        break;
                    case 'examples':
                        $hasExamples = true;
                        break;
                    case 'vocabulary':
                        $hasWordTracking = true;
                        break;
                }
                $contentSections++;
            }
        }

        // Calculate completeness score
        $completenessFactors = [$hasIntroduction, $hasExamples, $contentSections >= 3];
        $completenessScore = count(array_filter($completenessFactors)) / count($completenessFactors);

        // Calculate word integration score
        $wordIntegrationScore = $hasWordTracking ? 0.8 : 0.4;

        return [
            'completeness_score' => $completenessScore,
            'word_integration_score' => $wordIntegrationScore,
            'has_introduction' => $hasIntroduction,
            'has_examples' => $hasExamples,
            'has_word_tracking' => $hasWordTracking,
            'content_sections' => $contentSections
        ];
    }

    /**
     * Check cultural sensitivity.
     */
    private function checkCulturalSensitivity(string $text): array
    {
        $issues = [];
        $sensitiveWords = ['stereotype', 'prejudice', 'discrimination'];

        foreach ($sensitiveWords as $word) {
            if (stripos($text, $word) !== false) {
                $issues[] = [
                    'type' => 'potentially_sensitive',
                    'severity' => 'medium',
                    'message' => "Text contains potentially sensitive word: {$word}"
                ];
            }
        }

        return $issues;
    }

    /**
     * Evaluate content structure.
     */
    private function evaluateContentStructure(array $content): float
    {
        $score = 1.0;

        // Check if content has logical progression
        $hasIntroduction = false;
        $hasExercises = false;
        $hasConclusion = false;

        foreach ($content as $item) {
            if (isset($item['type'])) {
                switch ($item['type']) {
                    case 'introduction':
                        $hasIntroduction = true;
                        break;
                    case 'exercise':
                        $hasExercises = true;
                        break;
                    case 'conclusion':
                        $hasConclusion = true;
                        break;
                }
            }
        }

        if (!$hasIntroduction) $score -= 0.2;
        if (!$hasExercises) $score -= 0.3;
        if (!$hasConclusion) $score -= 0.1;

        return max(0, $score);
    }

    /**
     * Analyze exercise distribution.
     */
    private function analyzeExerciseDistribution(array $content): array
    {
        $exerciseTypes = [];
        $totalExercises = 0;

        foreach ($content as $item) {
            if (isset($item['type']) && str_contains($item['type'], 'exercise')) {
                $type = $item['type'];
                $exerciseTypes[$type] = ($exerciseTypes[$type] ?? 0) + 1;
                $totalExercises++;
            }
        }

        $typeCount = count($exerciseTypes);
        $balanceScore = $typeCount > 0 ? min(1.0, $typeCount / 5) : 0; // Assume 5 types is ideal

        return [
            'balance_score' => $balanceScore,
            'exercise_types' => $exerciseTypes,
            'total_exercises' => $totalExercises,
            'type_variety' => $typeCount
        ];
    }

    /**
     * Check required fields.
     */
    private function checkRequiredFields(array $item): array
    {
        $requiredFields = ['type', 'content'];
        $missing = [];

        foreach ($requiredFields as $field) {
            if (!isset($item[$field])) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * Check data format.
     */
    private function checkDataFormat(array $item): array
    {
        $issues = [];

        // Check if content is properly structured
        if (isset($item['content']) && !is_array($item['content'])) {
            $issues[] = [
                'type' => 'invalid_content_format',
                'severity' => 'medium',
                'message' => 'Content should be an array'
            ];
        }

        return $issues;
    }

    /**
     * Analyze content structure.
     */
    private function analyzeContentStructure(array $content): array
    {
        $score = $this->evaluateContentStructure($content);
        $suggestions = [];

        if ($score < 0.8) {
            $suggestions[] = 'Add clear introduction and conclusion sections';
            $suggestions[] = 'Ensure logical flow between content sections';
        }

        return [
            'score' => $score * 100,
            'suggestions' => $suggestions
        ];
    }

    /**
     * Analyze guidebook distribution.
     */
    private function analyzeGuidebookDistribution(array $content): array
    {
        $stats = $this->analyzeGuidebookStats($content);
        $score = ($stats['completeness_score'] + $stats['word_integration_score']) / 2 * 100;
        $suggestions = [];

        if ($score < 70) {
            $suggestions[] = 'Improve guidebook structure and organization';
            $suggestions[] = 'Better integrate word tracking with content';
            $suggestions[] = 'Add more comprehensive examples and explanations';
        }

        return [
            'score' => $score,
            'suggestions' => $suggestions
        ];
    }

    /**
     * Analyze exercise variety.
     */
    private function analyzeExerciseVariety(array $content): array
    {
        $distribution = $this->analyzeExerciseDistribution($content);
        $score = $distribution['balance_score'] * 100;
        $suggestions = [];

        if ($score < 70) {
            $suggestions[] = 'Add more variety in exercise types';
            $suggestions[] = 'Balance different learning activities';
        }

        return [
            'score' => $score,
            'suggestions' => $suggestions
        ];
    }

    /**
     * Prioritize suggestions.
     */
    private function prioritizeSuggestions(array $suggestions): array
    {
        usort($suggestions, function ($a, $b) {
            $priorityOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
            return ($priorityOrder[$b['priority']] ?? 0) <=> ($priorityOrder[$a['priority']] ?? 0);
        });

        return $suggestions;
    }

    /**
     * Estimate improvement impact.
     */
    private function estimateImprovementImpact(array $suggestions): array
    {
        $totalImpact = 0;
        $impactByCategory = [];

        foreach ($suggestions as $suggestion) {
            $impact = match ($suggestion['priority']) {
                'high' => 20,
                'medium' => 10,
                'low' => 5,
                default => 5
            };

            $totalImpact += $impact;
            $impactByCategory[$suggestion['category']] = ($impactByCategory[$suggestion['category']] ?? 0) + $impact;
        }

        return [
            'total_impact' => $totalImpact,
            'impact_by_category' => $impactByCategory,
            'estimated_score_improvement' => min(30, $totalImpact)
        ];
    }

    /**
     * Fix text issues.
     */
    private function fixTextIssues(string $text): string
    {
        // Remove extra spaces
        $text = preg_replace('/\s+/', ' ', $text);

        // Fix common punctuation issues
        $text = preg_replace('/\s+([,.!?])/', '$1', $text);

        // Trim whitespace
        return trim($text);
    }

    /**
     * Fix exercise structure.
     */
    private function fixExerciseStructure(array $content, string $type): array
    {
        // Add required fields if missing
        if (!isset($content['instructions'])) {
            $content['instructions'] = $this->getDefaultInstructions($type);
        }

        return $content;
    }

    /**
     * Get default instructions for exercise type.
     */
    private function getDefaultInstructions(string $type): string
    {
        return match ($type) {
            'multiple_choice' => 'Choose the correct answer.',
            'fill_blank' => 'Fill in the blanks.',
            'matching' => 'Match the items.',
            'writing' => 'Write your answer.',
            default => 'Complete the exercise.'
        };
    }

    /**
     * Get structure changes.
     */
    private function getStructureChanges(array $original, array $fixed): array
    {
        $changes = [];

        foreach ($fixed as $key => $value) {
            if (!isset($original[$key])) {
                $changes[] = "Added field: {$key}";
            } elseif ($original[$key] !== $value) {
                $changes[] = "Modified field: {$key}";
            }
        }

        return $changes;
    }

    /**
     * Analyze content types.
     */
    private function analyzeContentTypes(Collection $content): array
    {
        $types = [];

        foreach ($content as $item) {
            $type = class_basename($item);
            $types[$type] = ($types[$type] ?? 0) + 1;
        }

        return $types;
    }

    /**
     * Analyze individual item.
     */
    private function analyzeIndividualItem($item): array
    {
        $analysis = [
            'item_id' => $item->id ?? null,
            'item_type' => class_basename($item),
            'quality_score' => 75, // Default score
            'issues' => [],
            'strengths' => []
        ];

        // Analyze based on item type
        if ($item instanceof Exercise) {
            $analysis['quality_score'] = $this->difficultyService->calculateExerciseDifficulty($item);
        }

        return $analysis;
    }

    /**
     * Generate report recommendations.
     */
    private function generateReportRecommendations(array $detailedAnalysis): array
    {
        $recommendations = [];
        $lowQualityCount = 0;

        foreach ($detailedAnalysis as $analysis) {
            if ($analysis['quality_score'] < self::QUALITY_ACCEPTABLE) {
                $lowQualityCount++;
            }
        }

        if ($lowQualityCount > 0) {
            $recommendations[] = [
                'priority' => 'high',
                'description' => "Review and improve {$lowQualityCount} low-quality items",
                'category' => 'quality_improvement'
            ];
        }

        return $recommendations;
    }

    /**
     * Generate action plan.
     */
    private function generateActionPlan(array $recommendations): array
    {
        $plan = [];

        foreach ($recommendations as $index => $recommendation) {
            $plan[] = [
                'step' => $index + 1,
                'action' => $recommendation['description'],
                'priority' => $recommendation['priority'],
                'estimated_effort' => $this->estimateEffort($recommendation),
                'expected_outcome' => $this->getExpectedOutcome($recommendation)
            ];
        }

        return $plan;
    }

    /**
     * Estimate effort for recommendation.
     */
    private function estimateEffort(array $recommendation): string
    {
        return match ($recommendation['priority']) {
            'high' => 'High (2-4 hours)',
            'medium' => 'Medium (1-2 hours)',
            'low' => 'Low (30-60 minutes)',
            default => 'Medium (1-2 hours)'
        };
    }

    /**
     * Get expected outcome for recommendation.
     */
    private function getExpectedOutcome(array $recommendation): string
    {
        return match ($recommendation['category']) {
            'quality_improvement' => 'Improved content quality and user experience',
            'structure' => 'Better content organization and flow',
            'guidebook' => 'Enhanced guidebook structure and word tracking',
            'exercises' => 'More engaging and varied learning activities',
            default => 'Overall content improvement'
        };
    }
}
