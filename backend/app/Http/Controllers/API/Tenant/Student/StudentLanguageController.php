<?php

namespace App\Http\Controllers\API\Tenant\Student;

use App\Http\Controllers\API\BaseAPIController;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\Language;
use App\Services\Tenants\Language\LanguageManagementService;
use App\Services\Tenants\Course\LearningPathService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Student Language Controller
 * 
 * Handles language browsing and selection for students.
 * Access Level: Student
 * Scope: Tenant-specific (read-only)
 * 
 * This controller allows students to browse available languages
 * and access language-specific content within their tenant scope.
 */
class StudentLanguageController extends BaseAPIController
{
    use BelongsToTenant;

    protected LanguageManagementService $languageService;
    protected LearningPathService $learningPathService;

    /**
     * Constructor - Apply student middleware and inject services
     */
    public function __construct(
        LanguageManagementService $languageService,
        LearningPathService $learningPathService
    ) {
        $this->languageService = $languageService;
        $this->learningPathService = $learningPathService;
        // Apply policies - students can view languages
        $this->authorizeResource(\App\Models\Tenants\Language::class, 'language');
    }

    /**
     * Display a listing of available languages.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Language::class);

        $languages = $this->languageService->getFilteredLanguages($request, 'student');

        return $this->sendResponse($languages, 'Languages retrieved successfully.');
    }

    /**
     * Get languages with their learning paths.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function withLearningPaths(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Language::class);

        // Create a request for learning paths grouped by language
        $filteredRequest = $request->duplicate();
        $filteredRequest->merge(['with_language' => true]);

        $learningPaths = $this->learningPathService->getFilteredLearningPaths($filteredRequest, 'student');

        return $this->sendResponse($learningPaths, 'Languages with learning paths retrieved successfully.');
    }

    /**
     * Display the specified language.
     * 
     * @param Request $request
     * @param Language $language
     * @return JsonResponse
     */
    public function show(Request $request, Language $language): JsonResponse
    {
        $this->authorize('view', $language);

        try {
            $languageDetails = $this->languageService->getLanguage($language->id, 'student', ['learningPaths']);
            
            if (!$languageDetails) {
                return $this->sendErrorResponse('Language not found or not available', [], 404);
            }

            $contentOverview = $this->languageService->getLanguageContentOverview($languageDetails);
            
            $result = array_merge($languageDetails->toArray(), ['content_overview' => $contentOverview]);

            return $this->sendResponse($result, 'Language retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve language details', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get learning paths for a language.
     * 
     * @param Request $request
     * @param Language $language
     * @return JsonResponse
     */
    public function learningPaths(Request $request, Language $language): JsonResponse
    {
        $this->authorize('view', $language);

        try {
            $learningPaths = $this->learningPathService->getLearningPathsForLanguage($language->id, 'student');
            
            // Add enrollment status for current user
            $user = $request->user();
            $enrichedPaths = $learningPaths->map(function ($path) use ($user) {
                $pathArray = $path->toArray();
                $pathArray['user_progress'] = $this->learningPathService->getUserProgress($path, $user);
                $pathArray['can_access'] = $this->learningPathService->canUserAccess($path, $user);
                return $pathArray;
            });

            return $this->sendResponse($enrichedPaths, 'Learning paths retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve learning paths', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get proficiency levels for a language.
     * 
     * @param Request $request
     * @param Language $language
     * @return JsonResponse
     */
    public function proficiencyLevels(Request $request, Language $language): JsonResponse
    {
        $this->authorize('view', $language);

        try {
            $proficiencyLevels = $this->languageService->getLanguageProficiencyLevels($language);

            return $this->sendResponse($proficiencyLevels, 'Proficiency levels retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve proficiency levels', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get user's progress in a language.
     * 
     * @param Request $request
     * @param Language $language
     * @return JsonResponse
     */
    public function userProgress(Request $request, Language $language): JsonResponse
    {
        $this->authorize('view', $language);

        try {
            $user = $request->user();
            $progress = $this->languageService->getUserLanguageProgress($language, $user);

            return $this->sendResponse($progress, 'User progress retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve user progress', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get language dashboard for student.
     * 
     * @param Request $request
     * @param Language $language
     * @return JsonResponse
     */
    public function dashboard(Request $request, Language $language): JsonResponse
    {
        $this->authorize('view', $language);

        try {
            $user = $request->user();
            $dashboard = $this->languageService->getLanguageDashboard($language, $user);

            return $this->sendResponse($dashboard, 'Language dashboard retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve language dashboard', ['error' => $e->getMessage()], 500);
        }
    }
}
