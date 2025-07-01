<?php

namespace App\Services\Tenants\Course;

use App\Models\Tenants\CurriculumTemplate;
use App\Models\Tenants\LearningPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Curriculum Template Service
 * 
 * Handles curriculum template management operations including:
 * - Template creation, updates, and deletion
 * - Template instantiation into learning paths
 * - Template customization and validation
 * - Template analytics and effectiveness tracking
 * - Template recommendation system
 */
class CurriculumTemplateService
{
    /**
     * Get available templates with filters and pagination.
     */
    public function getAvailableTemplates(
        ?string $languagePair = null,
        ?string $level = null,
        array $filters = [],
        int $perPage = 15
    ): \Illuminate\Pagination\LengthAwarePaginator {
        $query = CurriculumTemplate::with(['language', 'createdBy'])
            ->withCount(['instantiations', 'reviews']);

        // Apply language pair filter
        if ($languagePair) {
            $query->where('language_pair_id', $languagePair);
        }

        // Apply proficiency level filter
        if ($level) {
            $query->where('proficiency_level', $level);
        }

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Apply official/custom filter
        if (isset($filters['is_official'])) {
            $query->where('is_official', $filters['is_official']);
        }

        // Apply effectiveness filter
        if (!empty($filters['min_effectiveness'])) {
            $query->where('effectiveness_score', '>=', $filters['min_effectiveness']);
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'effectiveness_score';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        switch ($sortBy) {
            case 'usage_count':
                $query->orderBy('usage_count', $sortOrder);
                break;
            case 'created_at':
                $query->orderBy('created_at', $sortOrder);
                break;
            case 'name':
                $query->orderBy('name', $sortOrder);
                break;
            default:
                $query->orderBy('effectiveness_score', $sortOrder);
        }

        return $query->paginate(min($perPage, 100));
    }

    /**
     * Instantiate a template into a learning path.
     */
    public function instantiateTemplate(int $templateId, array $customizations = []): LearningPath
    {
        return DB::transaction(function () use ($templateId, $customizations) {
            $template = CurriculumTemplate::findOrFail($templateId);

            // Validate template data
            $this->validateTemplateData($template->template_data);

            // Create learning path from template
            $learningPath = $this->createLearningPathFromTemplate($template, $customizations);

            // Update template usage statistics
            $template->increment('usage_count');

            // Log template instantiation
            Log::info('Template instantiated', [
                'template_id' => $templateId,
                'learning_path_id' => $learningPath->id,
                'customizations_applied' => !empty($customizations),
                'tenant_id' => tenant('id')
            ]);

            return $learningPath->load(['units.topics.lessons.exercises']);
        });
    }

    /**
     * Create a learning path from template data.
     */
    private function createLearningPathFromTemplate(CurriculumTemplate $template, array $customizations): LearningPath
    {
        $templateData = $template->template_data;

        // Apply customizations to template data
        if (!empty($customizations)) {
            $templateData = $this->applyCustomizations($templateData, $customizations);
        }

        // Get language pair information
        $languagePair = $template->languagePair;

        // Create the learning path
        $learningPath = LearningPath::create([
            'title' => $customizations['title'] ?? $templateData['title'] ?? $template->name,
            'language_id' => $customizations['language_id'] ?? $languagePair->target_language_id, // Legacy support
            'language_pair_id' => $template->language_pair_id,
            'description' => $customizations['description'] ?? $templateData['description'] ?? $template->description,
            'target_level' => $template->proficiency_level,
            'status' => 'draft',
            'template_id' => $template->id,
            'template_customizations' => $customizations,
            'generation_metadata' => [
                'template_version' => $template->updated_at->timestamp,
                'instantiated_at' => now()->toISOString(),
                'customization_level' => $this->calculateCustomizationLevel($customizations),
                'source_language_id' => $languagePair->source_language_id,
                'target_language_id' => $languagePair->target_language_id,
                'language_pair_id' => $template->language_pair_id,
            ],
            'auto_generated' => true,
            'tenant_id' => tenant('id')
        ]);

        // Create units from template
        $this->createUnitsFromTemplate($learningPath, $templateData['units'] ?? []);

        return $learningPath;
    }

    /**
     * Apply customizations to template data.
     */
    private function applyCustomizations(array $templateData, array $customizations): array
    {
        // Apply guidebook customizations
        if (isset($customizations['guidebook_replacements'])) {
            $templateData = $this->applyGuidebookCustomizations($templateData, $customizations['guidebook_replacements']);
        }

        // Apply difficulty adjustments
        if (isset($customizations['difficulty_adjustment'])) {
            $templateData = $this->applyDifficultyAdjustments($templateData, $customizations['difficulty_adjustment']);
        }

        // Apply content modifications
        if (isset($customizations['content_modifications'])) {
            $templateData = $this->applyContentModifications($templateData, $customizations['content_modifications']);
        }

        return $templateData;
    }

    /**
     * Calculate customization level (0-100).
     */
    private function calculateCustomizationLevel(array $customizations): int
    {
        if (empty($customizations)) {
            return 0;
        }

        $score = 0;
        $maxScore = 0;

        // Guidebook replacements (0-30 points)
        $maxScore += 30;
        if (isset($customizations['guidebook_replacements'])) {
            $replacementCount = count($customizations['guidebook_replacements']);
            $score += min(30, $replacementCount * 2);
        }

        // Difficulty adjustments (0-25 points)
        $maxScore += 25;
        if (isset($customizations['difficulty_adjustment'])) {
            $score += 25;
        }

        // Content modifications (0-25 points)
        $maxScore += 25;
        if (isset($customizations['content_modifications'])) {
            $modificationCount = count($customizations['content_modifications']);
            $score += min(25, $modificationCount * 5);
        }

        // Title/description changes (0-20 points)
        $maxScore += 20;
        if (isset($customizations['title']) || isset($customizations['description'])) {
            $score += 20;
        }

        return (int) round(($score / $maxScore) * 100);
    }

    /**
     * Validate template data structure.
     */
    public function validateTemplateData(array $templateData): array
    {
        $errors = [];

        // Check required fields
        if (empty($templateData['title'])) {
            $errors[] = 'Template title is required';
        }

        if (empty($templateData['units']) || !is_array($templateData['units'])) {
            $errors[] = 'Template must contain at least one unit';
        }

        // Validate units structure
        foreach ($templateData['units'] ?? [] as $index => $unit) {
            $unitErrors = $this->validateUnitData($unit, $index);
            $errors = array_merge($errors, $unitErrors);
        }

        if (!empty($errors)) {
            throw new Exception('Template validation failed: ' . implode(', ', $errors));
        }

        return $templateData;
    }

    /**
     * Validate unit data structure.
     */
    private function validateUnitData(array $unitData, int $index): array
    {
        $errors = [];
        $prefix = "Unit {$index}: ";

        if (empty($unitData['title'])) {
            $errors[] = $prefix . 'title is required';
        }

        if (empty($unitData['topics']) || !is_array($unitData['topics'])) {
            $errors[] = $prefix . 'must contain at least one topic';
        }

        // Validate topics
        foreach ($unitData['topics'] ?? [] as $topicIndex => $topic) {
            if (empty($topic['title'])) {
                $errors[] = $prefix . "Topic {$topicIndex}: title is required";
            }

            if (empty($topic['lessons']) || !is_array($topic['lessons'])) {
                $errors[] = $prefix . "Topic {$topicIndex}: must contain at least one lesson";
            }
        }

        return $errors;
    }

    /**
     * Create units from template data.
     */
    private function createUnitsFromTemplate(LearningPath $learningPath, array $unitsData): void
    {
        foreach ($unitsData as $order => $unitData) {
            $unit = $learningPath->units()->create([
                'title' => $unitData['title'],
                'description' => $unitData['description'] ?? null,
                'order' => $order + 1,
                'status' => 'draft',
                'tenant_id' => tenant('id')
            ]);

            // Create topics for this unit
            $this->createTopicsFromTemplate($unit, $unitData['topics'] ?? []);
        }
    }

    /**
     * Create topics from template data.
     */
    private function createTopicsFromTemplate($unit, array $topicsData): void
    {
        foreach ($topicsData as $order => $topicData) {
            $topic = $unit->topics()->create([
                'title' => $topicData['title'],
                'description' => $topicData['description'] ?? null,
                'order' => $order + 1,
                'status' => 'draft',
                'tenant_id' => tenant('id')
            ]);

            // Create lessons for this topic
            $this->createLessonsFromTemplate($topic, $topicData['lessons'] ?? []);
        }
    }

    /**
     * Create lessons from template data.
     */
    private function createLessonsFromTemplate($topic, array $lessonsData): void
    {
        foreach ($lessonsData as $order => $lessonData) {
            $lesson = $topic->lessons()->create([
                'title' => $lessonData['title'],
                'description' => $lessonData['description'] ?? null,
                'order' => $order + 1,
                'status' => 'draft',
                'tenant_id' => tenant('id')
            ]);

            // Create exercises for this lesson if specified
            if (!empty($lessonData['exercises'])) {
                $this->createExercisesFromTemplate($lesson, $lessonData['exercises']);
            }
        }
    }

    /**
     * Create exercises from template data.
     */
    private function createExercisesFromTemplate($lesson, array $exercisesData): void
    {
        foreach ($exercisesData as $order => $exerciseData) {
            $lesson->exercises()->create([
                'title' => $exerciseData['title'] ?? "Exercise " . ($order + 1),
                'type' => $exerciseData['type'],
                'content' => $exerciseData['content'] ?? [],
                'answers' => $exerciseData['answers'] ?? [],
                'order' => $order + 1,
                'generated_from_template' => true,
                'generation_source' => [
                    'template_type' => 'curriculum',
                    'source_data' => $exerciseData
                ],
                'auto_generated' => true,
                'tenant_id' => tenant('id')
            ]);
        }
    }

    /**
     * Apply guidebook customizations to template data.
     */
    private function applyGuidebookCustomizations(array $templateData, array $replacements): array
    {
        // This would recursively search through the template data and replace guidebook
        // Implementation would depend on how guidebook is stored in templates
        foreach ($replacements as $original => $replacement) {
            $templateData = $this->replaceGuidebookInData($templateData, $original, $replacement);
        }

        return $templateData;
    }

    /**
     * Apply difficulty adjustments to template data.
     */
    private function applyDifficultyAdjustments(array $templateData, string $adjustment): array
    {
        // Adjust difficulty levels throughout the template
        // This could involve changing exercise types, complexity, etc.
        $multiplier = match ($adjustment) {
            'easier' => 0.8,
            'harder' => 1.2,
            default => 1.0
        };

        // Apply adjustments to exercises and content
        return $this->adjustTemplatedifficulty($templateData, $multiplier);
    }

    /**
     * Apply content modifications to template data.
     */
    private function applyContentModifications(array $templateData, array $modifications): array
    {
        foreach ($modifications as $modification) {
            $templateData = $this->applyContentModification($templateData, $modification);
        }

        return $templateData;
    }

    /**
     * Replace guidebook in template data recursively.
     */
    private function replaceGuidebookInData(array $data, string $original, string $replacement): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->replaceGuidebookInData($value, $original, $replacement);
            } elseif (is_string($value)) {
                $data[$key] = str_replace($original, $replacement, $value);
            }
        }

        return $data;
    }

    /**
     * Adjust template difficulty recursively.
     */
    private function adjustTemplatedifficulty(array $data, float $multiplier): array
    {
        // This would adjust difficulty-related parameters throughout the template
        // Implementation depends on how difficulty is encoded in templates
        return $data;
    }

    /**
     * Apply a single content modification.
     */
    private function applyContentModification(array $data, array $modification): array
    {
        // Apply specific content modifications based on type
        switch ($modification['type']) {
            case 'replace_text':
                return $this->replaceTextInData($data, $modification['find'], $modification['replace']);
            case 'add_exercise':
                return $this->addExerciseToData($data, $modification['exercise_data']);
            case 'remove_exercise':
                return $this->removeExerciseFromData($data, $modification['exercise_id']);
            default:
                return $data;
        }
    }

    /**
     * Replace text in data recursively.
     */
    private function replaceTextInData(array $data, string $find, string $replace): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->replaceTextInData($value, $find, $replace);
            } elseif (is_string($value)) {
                $data[$key] = str_replace($find, $replace, $value);
            }
        }

        return $data;
    }

    /**
     * Add exercise to template data.
     */
    private function addExerciseToData(array $data, array $exerciseData): array
    {
        // Add exercise to appropriate location in template
        // Implementation depends on template structure
        return $data;
    }

    /**
     * Remove exercise from template data.
     */
    private function removeExerciseFromData(array $data, string $exerciseId): array
    {
        // Remove exercise from template data
        // Implementation depends on template structure
        return $data;
    }

    /**
     * Customize an existing template.
     */
    public function customizeTemplate(CurriculumTemplate $template, array $changes): CurriculumTemplate
    {
        return DB::transaction(function () use ($template, $changes) {
            // Create a copy of the template with customizations
            $customizedTemplate = $template->replicate();
            $customizedTemplate->name = $changes['name'] ?? $template->name . ' (Customized)';
            $customizedTemplate->description = $changes['description'] ?? $template->description;
            $customizedTemplate->is_official = false;
            $customizedTemplate->created_by = auth()->id();
            $customizedTemplate->usage_count = 0;
            $customizedTemplate->effectiveness_score = null;

            // Apply changes to template data
            $templateData = $template->template_data;
            if (!empty($changes['template_modifications'])) {
                $templateData = $this->applyCustomizations($templateData, $changes['template_modifications']);
            }
            $customizedTemplate->template_data = $templateData;

            $customizedTemplate->save();

            Log::info('Template customized', [
                'original_template_id' => $template->id,
                'customized_template_id' => $customizedTemplate->id,
                'tenant_id' => tenant('id')
            ]);

            return $customizedTemplate;
        });
    }

    /**
     * Get template effectiveness analytics.
     */
    public function getTemplateEffectiveness(int $templateId): array
    {
        $template = CurriculumTemplate::findOrFail($templateId);

        // Get learning paths created from this template
        $learningPaths = LearningPath::where('template_id', $templateId)->get();

        if ($learningPaths->isEmpty()) {
            return [
                'template_id' => $templateId,
                'usage_count' => 0,
                'effectiveness_score' => null,
                'completion_rate' => 0,
                'average_progress' => 0,
                'student_satisfaction' => null,
                'recommendations' => ['No usage data available yet']
            ];
        }

        // Calculate effectiveness metrics
        $totalStudents = 0;
        $completedStudents = 0;
        $totalProgress = 0;
        $satisfactionScores = [];

        foreach ($learningPaths as $learningPath) {
            // Get student progress for this learning path
            $progress = $learningPath->progress()->get();
            $totalStudents += $progress->count();

            foreach ($progress as $studentProgress) {
                $totalProgress += $studentProgress->completion_percentage ?? 0;
                if (($studentProgress->completion_percentage ?? 0) >= 100) {
                    $completedStudents++;
                }

                if ($studentProgress->satisfaction_score) {
                    $satisfactionScores[] = $studentProgress->satisfaction_score;
                }
            }
        }

        $completionRate = $totalStudents > 0 ? ($completedStudents / $totalStudents) * 100 : 0;
        $averageProgress = $totalStudents > 0 ? $totalProgress / $totalStudents : 0;
        $averageSatisfaction = !empty($satisfactionScores) ? array_sum($satisfactionScores) / count($satisfactionScores) : null;

        // Calculate overall effectiveness score (0-100)
        $effectivenessScore = $this->calculateEffectivenessScore($completionRate, $averageProgress, $averageSatisfaction);

        // Generate recommendations
        $recommendations = $this->generateEffectivenessRecommendations($completionRate, $averageProgress, $averageSatisfaction);

        return [
            'template_id' => $templateId,
            'usage_count' => $template->usage_count,
            'effectiveness_score' => $effectivenessScore,
            'completion_rate' => round($completionRate, 2),
            'average_progress' => round($averageProgress, 2),
            'student_satisfaction' => $averageSatisfaction ? round($averageSatisfaction, 2) : null,
            'total_students' => $totalStudents,
            'learning_paths_created' => $learningPaths->count(),
            'recommendations' => $recommendations
        ];
    }

    /**
     * Calculate effectiveness score based on metrics.
     */
    private function calculateEffectivenessScore(?float $completionRate, ?float $averageProgress, ?float $satisfaction): ?float
    {
        if ($completionRate === null && $averageProgress === null) {
            return null;
        }

        $score = 0;
        $weights = 0;

        // Completion rate (40% weight)
        if ($completionRate !== null) {
            $score += $completionRate * 0.4;
            $weights += 0.4;
        }

        // Average progress (35% weight)
        if ($averageProgress !== null) {
            $score += $averageProgress * 0.35;
            $weights += 0.35;
        }

        // Satisfaction (25% weight)
        if ($satisfaction !== null) {
            $score += ($satisfaction / 5) * 100 * 0.25; // Convert 1-5 scale to 0-100
            $weights += 0.25;
        }

        return $weights > 0 ? $score / $weights : null;
    }

    /**
     * Generate recommendations based on effectiveness metrics.
     */
    private function generateEffectivenessRecommendations(float $completionRate, float $averageProgress, ?float $satisfaction): array
    {
        $recommendations = [];

        if ($completionRate < 50) {
            $recommendations[] = 'Low completion rate - consider reducing difficulty or improving engagement';
        }

        if ($averageProgress < 60) {
            $recommendations[] = 'Students struggling with progress - review content difficulty and pacing';
        }

        if ($satisfaction !== null && $satisfaction < 3.5) {
            $recommendations[] = 'Low satisfaction scores - gather feedback and improve content quality';
        }

        if ($completionRate > 80 && $averageProgress > 85) {
            $recommendations[] = 'Excellent performance - consider this template as a model for others';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Template performing well - continue monitoring for optimization opportunities';
        }

        return $recommendations;
    }

    /**
     * Get usage statistics for a template.
     */
    public function getUsageStatistics(int $templateId): array
    {
        $template = CurriculumTemplate::findOrFail($templateId);

        $learningPaths = LearningPath::where('template_id', $templateId)
            ->withCount(['units', 'students'])
            ->get();

        $usageByMonth = LearningPath::where('template_id', $templateId)
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as count')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('count', 'month')
            ->toArray();

        return [
            'template_id' => $templateId,
            'total_instantiations' => $template->usage_count,
            'active_learning_paths' => $learningPaths->where('status', 'published')->count(),
            'total_students_enrolled' => $learningPaths->sum('students_count'),
            'average_units_per_path' => $learningPaths->avg('units_count'),
            'usage_by_month' => $usageByMonth,
            'last_used' => $learningPaths->max('created_at'),
            'most_popular_customizations' => $this->getMostPopularCustomizations($templateId)
        ];
    }

    /**
     * Get most popular customizations for a template.
     */
    private function getMostPopularCustomizations(int $templateId): array
    {
        $learningPaths = LearningPath::where('template_id', $templateId)
            ->whereNotNull('template_customizations')
            ->get();

        $customizations = [];

        foreach ($learningPaths as $path) {
            $pathCustomizations = $path->template_customizations ?? [];
            foreach ($pathCustomizations as $key => $value) {
                if (!isset($customizations[$key])) {
                    $customizations[$key] = 0;
                }
                $customizations[$key]++;
            }
        }

        arsort($customizations);

        return array_slice($customizations, 0, 10, true);
    }

    /**
     * Recommend templates for a user based on their preferences and history.
     */
    public function recommendTemplates($user): \Illuminate\Database\Eloquent\Collection
    {
        // Get user's language preferences and history
        $userLanguages = $this->getUserLanguagePreferences($user);
        $userLevel = $this->getUserProficiencyLevel($user);
        $userHistory = $this->getUserTemplateHistory($user);

        $query = CurriculumTemplate::with(['language'])
            ->where('effectiveness_score', '>', 70) // Only recommend effective templates
            ->orderBy('effectiveness_score', 'desc');

        // Filter by user's languages
        if (!empty($userLanguages)) {
            $query->whereIn('language_pair_id', $userLanguages);
        }

        // Filter by appropriate level
        if ($userLevel) {
            $query->where('proficiency_level', $userLevel);
        }

        // Exclude templates user has already used
        if (!empty($userHistory)) {
            $query->whereNotIn('id', $userHistory);
        }

        return $query->limit(10)->get();
    }

    /**
     * Get user's language preferences.
     */
    private function getUserLanguagePreferences($user): array
    {
        // Implementation would depend on how user preferences are stored
        return [];
    }

    /**
     * Get user's proficiency level.
     */
    private function getUserProficiencyLevel($user): ?string
    {
        // Implementation would depend on how user level is determined
        return null;
    }

    /**
     * Get user's template usage history.
     */
    private function getUserTemplateHistory($user): array
    {
        return LearningPath::where('created_by', $user->id)
            ->whereNotNull('template_id')
            ->pluck('template_id')
            ->toArray();
    }
}
