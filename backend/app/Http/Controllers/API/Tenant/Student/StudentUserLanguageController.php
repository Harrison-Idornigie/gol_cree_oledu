<?php

namespace App\Http\Controllers\API\Tenant\Student;

use App\Http\Controllers\API\BaseAPIController;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\UserLanguage;
use App\Services\Tenants\User\UserLanguageService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

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

    protected UserLanguageService $userLanguageService;

    /**
     * Constructor - Apply student middleware
     */
    public function __construct(UserLanguageService $userLanguageService)
    {
        $this->userLanguageService = $userLanguageService;
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
        $this->authorize('viewAny', UserLanguage::class);

        try {
            $user = $request->user();
            $userLanguages = $this->userLanguageService->getUserLanguages($user);

            return $this->sendResponse($userLanguages, 'Selected languages retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve languages', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Add a language to student's learning list.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', UserLanguage::class);

        $request->validate([
            'language_id' => 'required|exists:languages,id',
            'proficiency_level' => 'nullable|string|in:beginner,elementary,intermediate,advanced,native',
            'is_primary' => 'nullable|boolean',
            'goal' => 'nullable|string|in:general_fluency,business,academic,travel,personal'
        ]);

        try {
            $user = $request->user();
            $options = [
                'proficiency_level' => $request->proficiency_level ?? 'beginner',
                'is_primary' => $request->boolean('is_primary', false),
                'goal' => $request->goal ?? 'general_fluency'
            ];

            $userLanguage = $this->userLanguageService->addLanguage(
                $user,
                $request->language_id,
                $options
            );

            return $this->sendCreatedResponse($userLanguage, 'Language added successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to add language', ['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Display available languages that user can select.
     */
    public function available(Request $request): JsonResponse
    {
        $this->authorize('viewAny', UserLanguage::class);

        try {
            $user = $request->user();
            $availableLanguages = $this->userLanguageService->getAvailableLanguages($user);

            return $this->sendResponse($availableLanguages, 'Available languages retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve available languages', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update language proficiency or settings.
     */
    public function update(Request $request, UserLanguage $userLanguage): JsonResponse
    {
        $this->authorize('update', $userLanguage);
        $request->validate([
            'proficiency_level' => 'nullable|string|in:beginner,elementary,intermediate,advanced,native',
            'is_primary' => 'nullable|boolean'
        ]);

        try {
            $user = $request->user();

            if ($request->has('proficiency_level')) {
                $userLanguage = $this->userLanguageService->updateProficiencyLevel(
                    $user,
                    $userLanguage->language_id,
                    $request->proficiency_level
                );
            }

            if ($request->boolean('is_primary', false)) {
                $userLanguage = $this->userLanguageService->setPrimaryLanguage(
                    $user,
                    $userLanguage->language_id
                );
            }

            return $this->sendResponse($userLanguage, 'Language settings updated successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to update language settings', ['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Remove a language from student's learning list.
     * 
     * @param Request $request
     * @param UserLanguage $userLanguage
     * @return JsonResponse
     */
    public function destroy(Request $request, UserLanguage $userLanguage): JsonResponse
    {
        $this->authorize('delete', $userLanguage);

        try {
            $user = $request->user();
            $this->userLanguageService->removeLanguage($user, $userLanguage->language_id);

            return $this->sendNoContentResponse();
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to remove language', ['error' => $e->getMessage()], 422);
        }
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

        try {
            $user = $request->user();
            $updatedUserLanguage = $this->userLanguageService->setPrimaryLanguage(
                $user,
                $languageId
            );

            return $this->sendResponse($updatedUserLanguage, 'Primary language set successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to set primary language', ['error' => $e->getMessage()], 422);
        }
    }
}
