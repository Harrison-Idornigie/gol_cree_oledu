<?php

namespace App\Services\Tenants\Course;

use App\Models\Tenants\ContentTemplate;
use App\Models\Tenants\User;
use App\Models\Tenants\AuditLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Content Template Service
 * 
 * Handles CRUD operations and business logic for content templates including:
 * - Template creation, updates, and deletion
 * - Template validation and structure management
 * - Template usage tracking and analytics
 * - Template recommendation system
 */
class ContentTemplateService
{
    /**
     * Get paginated content templates with filters.
     */
    public function getTemplates(array $filters = [], array $sorts = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ContentTemplate::with(['createdBy'])
            ->withCount(['instantiations']);

        // Apply search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Apply filters
        if (!empty($filters['template_type'])) {
            $query->where('template_type', $filters['template_type']);
        }

        if (!empty($filters['skill_focus'])) {
            $query->where('skill_focus', $filters['skill_focus']);
        }

        if (!empty($filters['difficulty_level'])) {
            $query->where('difficulty_level', $filters['difficulty_level']);
        }

        if (!empty($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        // Apply sorting
        foreach ($sorts as $field => $direction) {
            if (in_array($field, ['name', 'template_type', 'difficulty_level', 'usage_count', 'created_at'])) {
                $query->orderBy($field, $direction);
            }
        }

        // Default sorting
        if (empty($sorts)) {
            $query->orderBy('usage_count', 'desc')->orderBy('name');
        }

        return $query->paginate($perPage);
    }

    /**
     * Get templates by type.
     */
    public function getTemplatesByType(string $type): Collection
    {
        return ContentTemplate::byType($type)
            ->with(['createdBy'])
            ->withCount(['instantiations'])
            ->orderBy('usage_count', 'desc')
            ->get();
    }

    /**
     * Create a new content template.
     */
    public function createTemplate(array $data, User $user): ContentTemplate
    {
        return DB::transaction(function () use ($data, $user) {
            // Validate template data structure
            $this->validateTemplateStructure($data['template_type'], $data['template_data']);

            // Set defaults
            $data['tenant_id'] = tenant('id');
            $data['created_by'] = $user->id;
            $data['usage_count'] = 0;
            $data['difficulty_level'] = $data['difficulty_level'] ?? 1;

            $template = ContentTemplate::create($data);

            // Log the creation for audit trail
            AuditLog::log(
                'create',
                'content_templates',
                $template,
                [],
                $data,
                $user->id
            );

            Log::info('Content template created via service', [
                'template_id' => $template->id,
                'template_type' => $template->template_type,
                'name' => $template->name,
                'created_by' => $user->id,
                'tenant_id' => tenant('id')
            ]);

            return $template->load(['createdBy']);
        });
    }

    /**
     * Update an existing content template.
     */
    public function updateTemplate(ContentTemplate $template, array $data, User $user): ContentTemplate
    {
        return DB::transaction(function () use ($template, $data, $user) {
            $originalData = $template->toArray();

            // Validate template data if being updated
            if (isset($data['template_data'])) {
                $this->validateTemplateStructure($template->template_type, $data['template_data']);
            }

            $template->update($data);

            // Log the update for audit trail
            AuditLog::log(
                'update',
                'content_templates',
                $template,
                $originalData,
                $data,
                $user->id
            );

            Log::info('Content template updated via service', [
                'template_id' => $template->id,
                'changes' => array_keys($data),
                'updated_by' => $user->id,
                'tenant_id' => tenant('id')
            ]);

            return $template->fresh(['createdBy']);
        });
    }

    /**
     * Delete a content template.
     */
    public function deleteTemplate(ContentTemplate $template, User $user): bool
    {
        return DB::transaction(function () use ($template, $user) {
            // Check if template has been used
            $instantiationCount = $template->instantiations()->count();
            if ($instantiationCount > 0) {
                throw new Exception("Cannot delete template that has been used {$instantiationCount} times. Consider archiving instead.");
            }

            $templateData = $template->toArray();
            $deleted = $template->delete();

            if ($deleted) {
                // Log the deletion for audit trail
                AuditLog::log(
                    'delete',
                    'content_templates',
                    null,
                    $templateData,
                    [],
                    $user->id
                );

                Log::info('Content template deleted via service', [
                    'template_id' => $template->id,
                    'name' => $template->name,
                    'deleted_by' => $user->id,
                    'tenant_id' => tenant('id')
                ]);
            }

            return $deleted;
        });
    }

    /**
     * Duplicate a content template.
     */
    public function duplicateTemplate(ContentTemplate $template, array $overrides, User $user): ContentTemplate
    {
        return DB::transaction(function () use ($template, $overrides, $user) {
            $templateData = $template->toArray();

            // Remove ID and timestamps
            unset($templateData['id'], $templateData['created_at'], $templateData['updated_at']);

            // Apply overrides
            $templateData = array_merge($templateData, $overrides);

            // Update name to indicate it's a copy
            if (!isset($overrides['name'])) {
                $templateData['name'] = $template->name . ' (Copy)';
            }

            // Reset usage count
            $templateData['usage_count'] = 0;

            // Create new template
            $newTemplate = $this->createTemplate($templateData, $user);

            Log::info('Content template duplicated via service', [
                'original_template_id' => $template->id,
                'new_template_id' => $newTemplate->id,
                'duplicated_by' => $user->id,
                'tenant_id' => tenant('id')
            ]);

            return $newTemplate;
        });
    }

    /**
     * Validate template data structure based on type.
     */
    public function validateTemplateStructure(string $type, array $templateData): bool
    {
        $errors = [];

        switch ($type) {
            case ContentTemplate::TYPE_UNIT:
                $errors = $this->validateUnitTemplate($templateData);
                break;
            case ContentTemplate::TYPE_TOPIC:
                $errors = $this->validateTopicTemplate($templateData);
                break;
            case ContentTemplate::TYPE_LESSON:
                $errors = $this->validateLessonTemplate($templateData);
                break;
            case ContentTemplate::TYPE_EXERCISE:
                $errors = $this->validateExerciseTemplate($templateData);
                break;
            default:
                throw new Exception("Unknown template type: {$type}");
        }

        if (!empty($errors)) {
            throw new Exception("Template validation failed: " . implode(', ', $errors));
        }

        return true;
    }

    /**
     * Get template usage statistics.
     */
    public function getTemplateStats(ContentTemplate $template): array
    {
        $instantiations = $template->instantiations()->get();

        return [
            'usage_count' => $template->usage_count,
            'instantiation_count' => $instantiations->count(),
            'template_type' => $template->template_type,
            'difficulty_level' => $template->difficulty_level,
            'skill_focus' => $template->skill_focus,
            'created_at' => $template->created_at,
            'last_used' => $instantiations->max('created_at'),
            'average_rating' => 0, // TODO: Calculate from reviews
        ];
    }

    /**
     * Increment template usage count.
     */
    public function incrementUsage(ContentTemplate $template): void
    {
        $template->increment('usage_count');

        Log::info('Template usage incremented', [
            'template_id' => $template->id,
            'new_usage_count' => $template->fresh()->usage_count,
            'tenant_id' => tenant('id')
        ]);
    }

    /**
     * Validate unit template structure.
     */
    private function validateUnitTemplate(array $data): array
    {
        $errors = [];

        if (empty($data['title'])) {
            $errors[] = 'Unit template must have a title';
        }

        if (empty($data['topics']) || !is_array($data['topics'])) {
            $errors[] = 'Unit template must have topics array';
        }

        return $errors;
    }

    /**
     * Validate topic template structure.
     */
    private function validateTopicTemplate(array $data): array
    {
        $errors = [];

        if (empty($data['title'])) {
            $errors[] = 'Topic template must have a title';
        }

        if (empty($data['lessons']) || !is_array($data['lessons'])) {
            $errors[] = 'Topic template must have lessons array';
        }

        return $errors;
    }

    /**
     * Validate lesson template structure.
     */
    private function validateLessonTemplate(array $data): array
    {
        $errors = [];

        if (empty($data['title'])) {
            $errors[] = 'Lesson template must have a title';
        }

        if (empty($data['exercises']) || !is_array($data['exercises'])) {
            $errors[] = 'Lesson template must have exercises array';
        }

        return $errors;
    }

    /**
     * Validate exercise template structure.
     */
    private function validateExerciseTemplate(array $data): array
    {
        $errors = [];

        if (empty($data['type'])) {
            $errors[] = 'Exercise template must have a type';
        }

        if (empty($data['content_structure'])) {
            $errors[] = 'Exercise template must have content structure';
        }

        return $errors;
    }

    /**
     * Clone an existing template with modifications.
     */
    public function cloneTemplate(ContentTemplate $template, array $data, User $user): ContentTemplate
    {
        return DB::transaction(function () use ($template, $data, $user) {
            $cloneData = $template->toArray();

            // Remove unique fields
            unset($cloneData['id'], $cloneData['created_at'], $cloneData['updated_at']);

            // Apply modifications
            $cloneData['name'] = $data['name'];
            $cloneData['description'] = $data['description'] ?? $cloneData['description'];
            $cloneData['created_by'] = $user->id;
            $cloneData['usage_count'] = 0;

            // Apply template data modifications if provided
            if (isset($data['modifications'])) {
                $cloneData['template_data'] = array_merge(
                    $cloneData['template_data'],
                    $data['modifications']
                );
            }

            // Validate the cloned template
            $this->validateTemplateStructure($cloneData['template_type'], $cloneData['template_data']);

            $clonedTemplate = ContentTemplate::create($cloneData);

            // Log the cloning
            AuditLog::log(
                'clone',
                'content_templates',
                $clonedTemplate,
                [],
                ['original_template_id' => $template->id],
                $user->id
            );

            Log::info('Content template cloned', [
                'original_template_id' => $template->id,
                'cloned_template_id' => $clonedTemplate->id,
                'cloned_by' => $user->id,
                'tenant_id' => tenant('id')
            ]);

            return $clonedTemplate->load(['createdBy']);
        });
    }

    /**
     * Get detailed usage statistics for a template.
     */
    public function getDetailedUsageStats(ContentTemplate $template): array
    {
        $instantiations = $template->instantiations()->get();

        return [
            'total_instantiations' => $instantiations->count(),
            'recent_usage' => $instantiations->where('created_at', '>=', now()->subDays(30))->count(),
            'usage_by_month' => $this->getUsageByMonth($template),
            'most_active_users' => $this->getMostActiveUsers($template),
            'average_content_quality' => $this->getAverageContentQuality($template),
            'success_rate' => $this->getTemplateSuccessRate($template),
        ];
    }

    /**
     * Get template recommendations for a user.
     */
    public function getRecommendations(User $user, array $filters = []): array
    {
        $query = ContentTemplate::with(['createdBy'])
            ->withCount(['instantiations'])
            ->where('usage_count', '>', 0)
            ->orderBy('usage_count', 'desc');

        // Apply filters
        if (!empty($filters['skill_focus'])) {
            $query->where('skill_focus', $filters['skill_focus']);
        }

        if (!empty($filters['difficulty_level'])) {
            $query->where('difficulty_level', $filters['difficulty_level']);
        }

        if (!empty($filters['template_type'])) {
            $query->where('template_type', $filters['template_type']);
        }

        $popularTemplates = $query->limit(10)->get();

        // Get user's recent activity to suggest similar templates
        $userTemplates = $this->getUserRecentTemplates($user);
        $similarTemplates = $this->getSimilarTemplates($userTemplates);

        return [
            'popular' => $popularTemplates,
            'similar_to_recent' => $similarTemplates,
            'trending' => $this->getTrendingTemplates(),
            'new_releases' => $this->getNewTemplates(),
        ];
    }

    /**
     * Get template preview data.
     */
    public function getTemplatePreview(ContentTemplate $template): array
    {
        return [
            'template_info' => [
                'id' => $template->id,
                'name' => $template->name,
                'type' => $template->template_type,
                'difficulty_level' => $template->difficulty_level,
                'skill_focus' => $template->skill_focus,
            ],
            'structure_preview' => $this->generateStructurePreview($template),
            'estimated_content' => $this->estimateGeneratedContent($template),
            'requirements' => $template->guidebook_requirements,
            'exercise_types' => $template->exercise_types,
        ];
    }

    /**
     * Instantiate a template into actual content.
     */
    public function instantiateTemplate(
        ContentTemplate $template,
        int $parentId,
        array $customizations = [],
        array $guidebookIds = [],
        User $user
    ): array {
        return DB::transaction(function () use ($template, $parentId, $customizations, $guidebookIds, $user) {
            // Increment usage count
            $this->incrementUsage($template);

            // Generate content based on template type
            $instantiated = match ($template->template_type) {
                ContentTemplate::TYPE_EXERCISE => $this->instantiateExerciseTemplate($template, $parentId, $customizations, $guidebookIds, $user),
                ContentTemplate::TYPE_LESSON => $this->instantiateLessonTemplate($template, $parentId, $customizations, $guidebookIds, $user),
                ContentTemplate::TYPE_TOPIC => $this->instantiateTopicTemplate($template, $parentId, $customizations, $guidebookIds, $user),
                ContentTemplate::TYPE_UNIT => $this->instantiateUnitTemplate($template, $parentId, $customizations, $guidebookIds, $user),
                default => throw new Exception("Unsupported template type: {$template->template_type}")
            };

            // Log the instantiation
            AuditLog::log(
                'instantiate',
                'content_templates',
                $template,
                [],
                [
                    'parent_id' => $parentId,
                    'instantiated_content_id' => $instantiated['id'] ?? null,
                    'customizations' => $customizations
                ],
                $user->id
            );

            return $instantiated;
        });
    }

    /**
     * Get usage statistics by month for a template.
     */
    private function getUsageByMonth(ContentTemplate $template): array
    {
        $instantiations = $template->instantiations()
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as count')
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return $instantiations->pluck('count', 'month')->toArray();
    }

    /**
     * Get most active users for a template.
     */
    private function getMostActiveUsers(ContentTemplate $template): array
    {
        // This would need to be implemented based on your user tracking system
        return [];
    }

    /**
     * Get average content quality for template instantiations.
     */
    private function getAverageContentQuality(ContentTemplate $template): float
    {
        // This would integrate with your content quality scoring system
        return 0.0;
    }

    /**
     * Get template success rate.
     */
    private function getTemplateSuccessRate(ContentTemplate $template): float
    {
        // This would be based on completion rates, user feedback, etc.
        return 0.0;
    }

    /**
     * Get user's recently used templates.
     */
    private function getUserRecentTemplates(User $user): array
    {
        // This would track user's template usage history
        return [];
    }

    /**
     * Get templates similar to user's recent usage.
     */
    private function getSimilarTemplates(array $userTemplates): array
    {
        // This would implement similarity matching logic
        return [];
    }

    /**
     * Get trending templates.
     */
    private function getTrendingTemplates(): array
    {
        return ContentTemplate::withCount(['instantiations'])
            ->where('created_at', '>=', now()->subDays(30))
            ->orderBy('instantiations_count', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
    }

    /**
     * Get newly created templates.
     */
    private function getNewTemplates(): array
    {
        return ContentTemplate::with(['createdBy'])
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
    }

    /**
     * Generate structure preview for a template.
     */
    private function generateStructurePreview(ContentTemplate $template): array
    {
        $templateData = $template->template_data;

        return match ($template->template_type) {
            ContentTemplate::TYPE_EXERCISE => [
                'exercise_type' => $templateData['exercise_type'] ?? 'unknown',
                'content_structure' => $templateData['content_structure'] ?? [],
            ],
            ContentTemplate::TYPE_LESSON => [
                'lesson_structure' => $templateData['lesson_structure'] ?? [],
                'exercise_patterns' => $templateData['exercise_patterns'] ?? [],
            ],
            ContentTemplate::TYPE_TOPIC => [
                'topic_structure' => $templateData['topic_structure'] ?? [],
                'lesson_patterns' => $templateData['lesson_patterns'] ?? [],
            ],
            ContentTemplate::TYPE_UNIT => [
                'unit_structure' => $templateData['unit_structure'] ?? [],
                'topic_patterns' => $templateData['topic_patterns'] ?? [],
            ],
            default => []
        };
    }

    /**
     * Estimate content that would be generated from template.
     */
    private function estimateGeneratedContent(ContentTemplate $template): array
    {
        $templateData = $template->template_data;

        return [
            'estimated_exercises' => $this->estimateExerciseCount($template),
            'estimated_duration' => $this->estimateDuration($template),
            'content_complexity' => $template->difficulty_level,
            'guidebook_needed' => $template->guidebook_requirements['min_words'] ?? 0,
        ];
    }

    /**
     * Estimate exercise count from template.
     */
    private function estimateExerciseCount(ContentTemplate $template): int
    {
        $templateData = $template->template_data;

        return match ($template->template_type) {
            ContentTemplate::TYPE_EXERCISE => 1,
            ContentTemplate::TYPE_LESSON => count($templateData['exercise_patterns'] ?? []),
            ContentTemplate::TYPE_TOPIC => array_sum(array_column($templateData['lesson_patterns'] ?? [], 'exercise_count')),
            ContentTemplate::TYPE_UNIT => array_sum(array_column($templateData['topic_patterns'] ?? [], 'exercise_count')),
            default => 0
        };
    }

    /**
     * Estimate duration from template.
     */
    private function estimateDuration(ContentTemplate $template): int
    {
        // Return estimated minutes
        return match ($template->template_type) {
            ContentTemplate::TYPE_EXERCISE => 5,
            ContentTemplate::TYPE_LESSON => 30,
            ContentTemplate::TYPE_TOPIC => 120,
            ContentTemplate::TYPE_UNIT => 480,
            default => 0
        };
    }

    /**
     * Instantiate an exercise template.
     */
    private function instantiateExerciseTemplate(
        ContentTemplate $template,
        int $lessonId,
        array $customizations,
        array $guidebookIds,
        User $user
    ): array {
        // This would integrate with ExerciseService to create actual exercises
        return [
            'type' => 'exercise',
            'template_id' => $template->id,
            'lesson_id' => $lessonId,
            'status' => 'generated',
            'message' => 'Exercise template instantiation would be handled by ExerciseService'
        ];
    }

    /**
     * Instantiate a lesson template.
     */
    private function instantiateLessonTemplate(
        ContentTemplate $template,
        int $topicId,
        array $customizations,
        array $guidebookIds,
        User $user
    ): array {
        // This would integrate with LessonService to create actual lessons
        return [
            'type' => 'lesson',
            'template_id' => $template->id,
            'topic_id' => $topicId,
            'status' => 'generated',
            'message' => 'Lesson template instantiation would be handled by LessonService'
        ];
    }

    /**
     * Instantiate a topic template.
     */
    private function instantiateTopicTemplate(
        ContentTemplate $template,
        int $unitId,
        array $customizations,
        array $guidebookIds,
        User $user
    ): array {
        // This would integrate with TopicService to create actual topics
        return [
            'type' => 'topic',
            'template_id' => $template->id,
            'unit_id' => $unitId,
            'status' => 'generated',
            'message' => 'Topic template instantiation would be handled by TopicService'
        ];
    }

    /**
     * Instantiate a unit template.
     */
    private function instantiateUnitTemplate(
        ContentTemplate $template,
        int $learningPathId,
        array $customizations,
        array $guidebookIds,
        User $user
    ): array {
        // This would integrate with UnitService to create actual units
        return [
            'type' => 'unit',
            'template_id' => $template->id,
            'learning_path_id' => $learningPathId,
            'status' => 'generated',
            'message' => 'Unit template instantiation would be handled by UnitService'
        ];
    }
}
