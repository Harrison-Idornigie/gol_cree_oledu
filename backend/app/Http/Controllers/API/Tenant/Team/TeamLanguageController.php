<?php

namespace App\Http\Controllers\API\Tenant\Team;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\Tenants\Language\LanguageManagementService;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\Language;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Exception;

/**
 * Team Language Controller
 *
 * Handles language management operations for team members.
 * Access Level: Team (Teams/Content Creators)
 * Scope: Tenant-specific
 *
 * This controller allows team members to create and manage languages
 * and language pairs within their tenant scope.
 */
class TeamLanguageController extends BaseAPIController
{
    use BelongsToTenant;

    protected LanguageManagementService $languageService;

    /**
     * Constructor - Apply team middleware
     */
    public function __construct(LanguageManagementService $languageService)
    {
        $this->languageService = $languageService;
    }

    /**
     * Display a listing of languages in the tenant.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $languages = $this->languageService->getFilteredLanguages($request, 'team');
            return $this->sendResponse($languages, 'Languages retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendError('Failed to retrieve languages.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Store a newly created language.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'code' => 'required|string|max:10|regex:/^[a-z]{2,3}(-[A-Z]{2})?$/',
                'name' => 'required|string|max:255',
                'native_name' => 'nullable|string|max:255',
                'is_active' => 'nullable|boolean',
            ]);

            $language = $this->languageService->createLanguage($validated, Auth::user());

            return $this->sendCreatedResponse([
                'language' => $language->getPreviewData()
            ], 'Language created successfully.');
        } catch (Exception $e) {
            return $this->sendError('Failed to create language: ' . $e->getMessage());
        }
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
        try {
            // Load relationships for detailed view
            $language->load([
                'words' => function ($query) {
                    $query->limit(5); // Sample words
                },
                'sentences' => function ($query) {
                    $query->limit(5); // Sample sentences
                },
                'learningPaths',
                'sourceLanguagePairs.targetLanguage',
                'targetLanguagePairs.sourceLanguage'
            ]);

            $languageData = $language->getPreviewData();

            // Add statistics
            $languageData['statistics'] = [
                'words_count' => $language->words()->count(),
                'sentences_count' => $language->sentences()->count(),
                'learning_paths_count' => $language->learningPaths()->count(),
                'source_pairs_count' => $language->sourceLanguagePairs()->count(),
                'target_pairs_count' => $language->targetLanguagePairs()->count(),
            ];

            // Add language pairs
            $languageData['language_pairs'] = [
                'as_source' => $language->sourceLanguagePairs->map(function ($pair) {
                    return [
                        'target_language' => $pair->targetLanguage->getPreviewData(),
                        'is_active' => $pair->is_active,
                    ];
                }),
                'as_target' => $language->targetLanguagePairs->map(function ($pair) {
                    return [
                        'source_language' => $pair->sourceLanguage->getPreviewData(),
                        'is_active' => $pair->is_active,
                    ];
                }),
            ];

            return $this->sendResponse($languageData, 'Language retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendError('Failed to retrieve language details.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Update the specified language.
     *
     * @param Request $request
     * @param Language $language
     * @return JsonResponse
     */
    public function update(Request $request, Language $language): JsonResponse
    {
        try {
            $validated = $request->validate([
                'code' => 'sometimes|string|max:10|regex:/^[a-z]{2,3}(-[A-Z]{2})?$/',
                'name' => 'sometimes|string|max:255',
                'native_name' => 'sometimes|nullable|string|max:255',
                'is_active' => 'sometimes|boolean',
            ]);

            $updatedLanguage = $this->languageService->updateLanguage($language, $validated, Auth::user());

            return $this->sendResponse([
                'language' => $updatedLanguage->getPreviewData()
            ], 'Language updated successfully.');
        } catch (Exception $e) {
            return $this->sendError('Failed to update language: ' . $e->getMessage());
        }
    }

    /**
     * Update language status.
     *
     * @param Request $request
     * @param Language $language
     * @return JsonResponse
     */
    public function updateStatus(Request $request, Language $language): JsonResponse
    {
        try {
            $validated = $request->validate([
                'is_active' => 'required|boolean',
            ]);

            $updatedLanguage = $this->languageService->updateLanguage(
                $language,
                ['is_active' => $validated['is_active']],
                Auth::user()
            );

            return $this->sendResponse([
                'language' => $updatedLanguage->getPreviewData()
            ], 'Language status updated successfully.');
        } catch (Exception $e) {
            return $this->sendError('Failed to update language status: ' . $e->getMessage());
        }
    }

    /**
     * Create a language pair.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createPair(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'source_language_id' => 'required|exists:languages,id',
                'target_language_id' => 'required|exists:languages,id',
            ]);

            $languagePair = $this->languageService->createLanguagePair(
                $validated['source_language_id'],
                $validated['target_language_id'],
                Auth::user()
            );

            return $this->sendCreatedResponse([
                'language_pair' => [
                    'source_language' => $languagePair->sourceLanguage->getPreviewData(),
                    'target_language' => $languagePair->targetLanguage->getPreviewData(),
                    'is_active' => $languagePair->is_active,
                    'created_at' => $languagePair->created_at,
                ]
            ], 'Language pair created successfully.');
        } catch (Exception $e) {
            return $this->sendError('Failed to create language pair: ' . $e->getMessage());
        }
    }

    /**
     * Delete a language pair.
     *
     * @param Request $request
     * @param Language $source
     * @param Language $target
     * @return JsonResponse
     */
    public function deletePair(Request $request, Language $source, Language $target): JsonResponse
    {
        try {
            $this->languageService->deleteLanguagePair($source->id, $target->id, Auth::user());
            return $this->sendNoContentResponse();
        } catch (Exception $e) {
            return $this->sendError('Failed to delete language pair: ' . $e->getMessage());
        }
    }

    /**
     * Update language pair status.
     *
     * @param Request $request
     * @param Language $source
     * @param Language $target
     * @return JsonResponse
     */
    public function updatePairStatus(Request $request, Language $source, Language $target): JsonResponse
    {
        try {
            $validated = $request->validate([
                'is_active' => 'required|boolean',
            ]);

            $languagePair = $this->languageService->updateLanguagePairStatus(
                $source->id,
                $target->id,
                $validated['is_active'],
                Auth::user()
            );

            return $this->sendResponse([
                'language_pair' => [
                    'source_language' => $languagePair->sourceLanguage->getPreviewData(),
                    'target_language' => $languagePair->targetLanguage->getPreviewData(),
                    'is_active' => $languagePair->is_active,
                    'updated_at' => $languagePair->updated_at,
                ]
            ], 'Language pair status updated successfully.');
        } catch (Exception $e) {
            return $this->sendError('Failed to update language pair status: ' . $e->getMessage());
        }
    }
}
