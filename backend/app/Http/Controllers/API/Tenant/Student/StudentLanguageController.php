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
        // TODO: Implement language details
        // - Validate language is published and in tenant
        // - Include language information
        // - Show available content overview
        return $this->sendResponse($language, 'Language retrieved successfully.');
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
        // TODO: Implement language learning paths
        // - All published learning paths for language
        // - Include difficulty levels
        // - Show enrollment and progress status
        return $this->sendResponse([], 'Learning paths retrieved successfully.');
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
        // TODO: Implement proficiency levels
        // - Available proficiency levels
        // - Content available per level
        // - Student's current level
        return $this->sendResponse([], 'Proficiency levels retrieved successfully.');
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
        // TODO: Implement user progress
        // - Student's progress in the language
        // - Completed content statistics
        // - Current learning path position
        return $this->sendResponse([], 'User progress retrieved successfully.');
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
        // TODO: Implement language dashboard
        // - Overview of student's progress
        // - Next recommended content
        // - Recent activity and achievements
        return $this->sendResponse([], 'Language dashboard retrieved successfully.');
    }
}
