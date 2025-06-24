<?php

namespace App\Http\Controllers\API\Tenant\Student;

use App\Http\Controllers\API\BaseAPIController;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\UserLanguage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Student User Language Controller
 * 
 * Handles language preferences and selections for students.
 * Access Level: Student
 * Scope: Tenant-specific
 * 
 * This controller allows students to manage their language
 * preferences and learning selections.
 */
class StudentUserLanguageController extends BaseAPIController
{
    use BelongsToTenant;

    /**
     * Constructor - Apply student middleware
     */
    public function __construct()
    {
        // Apply policies - students can manage their own language selections
        $this->authorizeResource(UserLanguage::class, 'userLanguage');
    }

    /**
     * Display student's selected languages.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // TODO: Implement selected languages listing
        // - All languages student is learning
        // - Include progress and proficiency levels
        // - Show primary language selection
        return $this->sendResponse([], 'Selected languages retrieved successfully.');
    }

    /**
     * Add a language to student's learning list.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        // TODO: Implement language selection
        // - Add language to student's learning list
        // - Validate language availability in tenant
        // - Initialize progress tracking
        return $this->sendCreatedResponse([], 'Language added successfully.');
    }

    /**
     * Remove a language from student's learning list.
     * 
     * @param Request $request
     * @param int $languageId
     * @return JsonResponse
     */
    public function destroy(Request $request, int $languageId): JsonResponse
    {
        // TODO: Implement language removal
        // - Remove language from learning list
        // - Handle progress data preservation
        // - Update primary language if needed
        return $this->sendNoContentResponse();
    }

    /**
     * Set primary learning language.
     * 
     * @param Request $request
     * @param int $languageId
     * @return JsonResponse
     */
    public function setPrimary(Request $request, int $languageId): JsonResponse
    {
        // Find the user's language selection to authorize
        $userLanguage = UserLanguage::where('user_id', $request->user()->id)
            ->where('language_id', $languageId)
            ->firstOrFail();

        $this->authorize('update', $userLanguage);

        // TODO: Implement primary language setting
        // - Set language as primary learning language
        // - Update user preferences
        // - Adjust dashboard and recommendations
        return $this->sendResponse([], 'Primary language set successfully.');
    }
}
