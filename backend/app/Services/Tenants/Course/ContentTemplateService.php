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
}
