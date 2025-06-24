<?php

namespace App\Http\Controllers\API\Tenant\Team;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\Tenants\Course\ContentTemplateService;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\ContentTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Team Content Template Controller
 * 
 * Handles content template management for team members.
 * Access Level: Team (Teams/Content Creators)
 * Scope: Tenant-specific
 * 
 * This controller allows team members to:
 * - Browse and search content templates by type
 * - Create custom content templates
 * - Clone and modify existing templates
 * - Track template usage and effectiveness
 * - Get template recommendations
 */
class TeamContentTemplateController extends BaseAPIController
{
    use BelongsToTenant;

    protected ContentTemplateService $templateService;

    public function __construct(ContentTemplateService $templateService)
    {
        $this->templateService = $templateService;

        // Apply policies
        $this->authorizeResource(\App\Models\Tenants\ContentTemplate::class, 'template');
    }

    /**
     * Display available content templates with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\Tenants\ContentTemplate::class);

        try {
            $filters = [
                'template_type' => $request->get('template_type'),
                'skill_focus' => $request->get('skill_focus'),
                'difficulty_level' => $request->get('difficulty_level'),
                'search' => $request->get('search'),
                'created_by' => $request->get('created_by'),
            ];

            $sorts = [
                'field' => $request->get('sort_by', 'created_at'),
                'direction' => $request->get('sort_direction', 'desc'),
            ];

            $perPage = $request->get('per_page', 15);

            $templates = $this->templateService->getTemplates($filters, $sorts, $perPage);

            return $this->sendResponse($templates, 'Content templates retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve templates.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Store a new content template.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', \App\Models\Tenants\ContentTemplate::class);

        try {
            $validated = $request->validate([
                'template_type' => 'required|string|in:unit,topic,lesson,exercise',
                'name' => 'required|string|max:255',
                'description' => 'required|string',
                'template_data' => 'required|array',
                'difficulty_level' => 'required|integer|min:1|max:10',
                'skill_focus' => 'required|string|in:vocabulary,grammar,listening,speaking,reading,writing,conversation',
                'exercise_types' => 'nullable|array',
                'vocabulary_requirements' => 'nullable|array',
            ]);

            $template = $this->templateService->createTemplate($validated, Auth::user());

            return $this->sendResponse($template, 'Content template created successfully.', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to create template.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Display a specific content template.
     */
    public function show(Request $request, ContentTemplate $template): JsonResponse
    {
        $this->authorize('view', $template);

        try {
            $template->load(['createdBy']);

            // Get template usage statistics
            $stats = $this->templateService->getTemplateStats($template);
            $template->stats = $stats;

            return $this->sendResponse($template, 'Template retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve template.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Update an existing content template.
     */
    public function update(Request $request, ContentTemplate $template): JsonResponse
    {
        $this->authorize('update', $template);

        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
                'template_data' => 'sometimes|array',
                'difficulty_level' => 'sometimes|integer|min:1|max:10',
                'skill_focus' => 'sometimes|string|in:vocabulary,grammar,listening,speaking,reading,writing,conversation',
                'exercise_types' => 'sometimes|array',
                'vocabulary_requirements' => 'sometimes|array',
            ]);

            $updatedTemplate = $this->templateService->updateTemplate($template, $validated, Auth::user());

            return $this->sendResponse($updatedTemplate, 'Template updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to update template.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Delete a content template.
     */
    public function destroy(Request $request, ContentTemplate $template): JsonResponse
    {
        $this->authorize('delete', $template);

        try {
            $this->templateService->deleteTemplate($template, Auth::user());

            return $this->sendNoContentResponse();
        } catch (\Exception $e) {
            return $this->sendError('Failed to delete template.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get templates filtered by type.
     */
    public function byType(Request $request, string $type): JsonResponse
    {
        try {
            $validTypes = ['unit', 'topic', 'lesson', 'exercise'];
            if (!in_array($type, $validTypes)) {
                return $this->sendError('Invalid template type.', [], 400);
            }

            $filters = ['template_type' => $type];
            $sorts = [
                'field' => $request->get('sort_by', 'usage_count'),
                'direction' => 'desc',
            ];
            $perPage = $request->get('per_page', 15);

            $templates = $this->templateService->getTemplates($filters, $sorts, $perPage);

            return $this->sendResponse($templates, "Templates of type '{$type}' retrieved successfully.");
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve templates by type.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Clone an existing template.
     */
    public function clone(Request $request, ContentTemplate $template): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'modifications' => 'nullable|array',
            ]);

            $clonedTemplate = $this->templateService->cloneTemplate($template, $validated, Auth::user());

            return $this->sendResponse($clonedTemplate, 'Template cloned successfully.', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to clone template.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get template usage statistics.
     */
    public function usageStats(Request $request, ContentTemplate $template): JsonResponse
    {
        try {
            $stats = $this->templateService->getTemplateStats($template);
            $detailedStats = $this->templateService->getDetailedUsageStats($template);

            return $this->sendResponse([
                'basic_stats' => $stats,
                'detailed_stats' => $detailedStats,
            ], 'Template usage statistics retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve usage statistics.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get template recommendations based on user's content creation patterns.
     */
    public function recommendations(Request $request): JsonResponse
    {
        try {
            $filters = [
                'skill_focus' => $request->get('skill_focus'),
                'difficulty_level' => $request->get('difficulty_level'),
                'template_type' => $request->get('template_type'),
            ];

            $recommendations = $this->templateService->getRecommendations(Auth::user(), $filters);

            return $this->sendResponse($recommendations, 'Template recommendations retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve recommendations.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Preview a template's structure without instantiating it.
     */
    public function preview(Request $request, ContentTemplate $template): JsonResponse
    {
        try {
            $preview = $this->templateService->getTemplatePreview($template);

            return $this->sendResponse($preview, 'Template preview generated successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to generate template preview.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Instantiate a content template into actual content.
     */
    public function instantiate(Request $request, ContentTemplate $template): JsonResponse
    {
        try {
            $validated = $request->validate([
                'parent_id' => 'required|integer', // lesson_id, topic_id, etc.
                'customizations' => 'nullable|array',
                'vocabulary_ids' => 'nullable|array',
                'vocabulary_ids.*' => 'exists:words,id',
            ]);

            $instantiated = $this->templateService->instantiateTemplate(
                $template,
                $validated['parent_id'],
                $validated['customizations'] ?? [],
                $validated['vocabulary_ids'] ?? [],
                Auth::user()
            );

            return $this->sendResponse($instantiated, 'Template instantiated successfully.', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to instantiate template.', ['error' => $e->getMessage()]);
        }
    }
}
