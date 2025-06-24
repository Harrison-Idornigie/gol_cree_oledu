<?php

namespace App\Http\Controllers\API\Tenant\Team;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\Tenants\Course\CurriculumTemplateService;
use App\Services\Tenants\Course\LearningPathService;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\CurriculumTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Team Curriculum Template Controller
 * 
 * Handles curriculum template management and instantiation for team members.
 * Access Level: Team (Teams/Content Creators)
 * Scope: Tenant-specific
 * 
 * This controller allows team members to:
 * - Browse and search curriculum templates
 * - Instantiate templates into learning paths
 * - Customize templates for their specific needs
 * - Track template effectiveness and usage
 */
class TeamCurriculumTemplateController extends BaseAPIController
{
    use BelongsToTenant;

    protected CurriculumTemplateService $templateService;
    protected LearningPathService $learningPathService;

    public function __construct(
        CurriculumTemplateService $templateService,
        LearningPathService $learningPathService
    ) {
        $this->templateService = $templateService;
        $this->learningPathService = $learningPathService;

        // Apply policies
        $this->authorizeResource(CurriculumTemplate::class, 'template');
    }

    /**
     * Display available curriculum templates.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CurriculumTemplate::class);

        try {
            $languagePair = $request->get('language_pair');
            $level = $request->get('level');
            $filters = [
                'search' => $request->get('search'),
                'is_official' => $request->get('is_official'),
                'effectiveness_min' => $request->get('effectiveness_min'),
                'created_by' => $request->get('created_by'),
            ];
            $perPage = $request->get('per_page', 15);

            $templates = $this->templateService->getAvailableTemplates(
                $languagePair,
                $level,
                $filters,
                $perPage
            );

            return $this->sendResponse($templates, 'Curriculum templates retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve templates.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Display a specific curriculum template.
     */
    public function show(Request $request, CurriculumTemplate $template): JsonResponse
    {
        $this->authorize('view', $template);

        try {
            $template->load(['languagePair', 'createdBy', 'instantiations', 'reviews']);

            // Get template analytics
            $analytics = $this->templateService->getTemplateAnalytics($template->id);
            $template->analytics = $analytics;

            return $this->sendResponse($template, 'Template retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve template.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Instantiate a curriculum template into a learning path.
     */
    public function instantiate(Request $request, CurriculumTemplate $template): JsonResponse
    {
        $this->authorize('instantiate', $template);

        try {
            $validated = $request->validate([
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'language_id' => 'nullable|exists:languages,id',
                'customizations' => 'nullable|array',
                'customizations.units' => 'nullable|array',
                'customizations.difficulty_adjustments' => 'nullable|array',
                'customizations.vocabulary_focus' => 'nullable|array',
                'customizations.exercise_preferences' => 'nullable|array',
            ]);

            $customizations = $validated['customizations'] ?? [];
            $customizations['title'] = $validated['title'] ?? null;
            $customizations['description'] = $validated['description'] ?? null;
            $customizations['language_id'] = $validated['language_id'] ?? null;

            $learningPath = $this->templateService->instantiateTemplate($template->id, $customizations);

            return $this->sendCreatedResponse($learningPath, 'Learning path created from template successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to instantiate template.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Customize an existing template.
     */
    public function customize(Request $request, CurriculumTemplate $template): JsonResponse
    {
        $this->authorize('customize', $template);

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'template_data' => 'required|array',
                'proficiency_level' => 'nullable|string|in:A1,A2,B1,B2,C1,C2',
                'estimated_hours' => 'nullable|integer|min:1',
                'prerequisites' => 'nullable|array',
            ]);

            $customizedTemplate = $this->templateService->customizeTemplate($template, $validated);

            return $this->sendCreatedResponse($customizedTemplate, 'Template customized successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to customize template.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get template recommendations for the current user.
     */
    public function recommendations(Request $request): JsonResponse
    {
        $this->authorize('viewRecommendations', CurriculumTemplate::class);

        try {
            $user = Auth::user();
            $recommendations = $this->templateService->recommendTemplates($user);

            return $this->sendResponse($recommendations, 'Template recommendations retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to get recommendations.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get template effectiveness analytics.
     */
    public function effectiveness(Request $request, CurriculumTemplate $template): JsonResponse
    {
        try {
            $analytics = $this->templateService->getTemplateAnalytics($template->id);
            $effectiveness = $this->templateService->calculateTemplateEffectiveness($template->id);

            return $this->sendResponse([
                'template_id' => $template->id,
                'effectiveness_score' => $effectiveness,
                'analytics' => $analytics,
                'recommendations' => $this->templateService->getImprovementRecommendations($template->id)
            ], 'Template effectiveness data retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve effectiveness data.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Preview what a template instantiation would create.
     */
    public function preview(Request $request, CurriculumTemplate $template): JsonResponse
    {
        try {
            $customizations = $request->get('customizations', []);

            $preview = $this->templateService->previewTemplateInstantiation($template->id, $customizations);

            return $this->sendResponse($preview, 'Template preview generated successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to generate preview.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Validate template data structure.
     */
    public function validate(Request $request, CurriculumTemplate $template): JsonResponse
    {
        try {
            $validation = $this->templateService->validateTemplate($template);

            return $this->sendResponse($validation, 'Template validation completed.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to validate template.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get usage statistics for a template.
     */
    public function usage(Request $request, CurriculumTemplate $template): JsonResponse
    {
        try {
            $usage = $this->templateService->getTemplateUsageStats($template->id);

            return $this->sendResponse($usage, 'Template usage statistics retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve usage statistics.', ['error' => $e->getMessage()]);
        }
    }
}
