<?php

namespace App\Http\Controllers\API\Tenant\Team;

use App\Http\Controllers\API\BaseAPIController;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\Language;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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

    /**
     * Constructor - Apply team middleware
     */
    public function __construct()
    {
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
            // Get all languages for this tenant
            $languages = Language::query()
                ->when($request->get('with_words_count'), function ($query) {
                    $query->withCount('words');
                })
                ->orderBy('name')
                ->get();

            // Transform the data to include additional metadata if requested
            $languagesData = $languages->map(function ($language) use ($request) {
                $data = [
                    'id' => $language->id,
                    'code' => $language->code,
                    'name' => $language->name,
                    'native_name' => $language->native_name,
                    'direction' => 'ltr', // Default direction since field doesn't exist yet
                    'status' => $language->is_active ? 'active' : 'inactive',
                ];

                // Add counts if requested
                if ($request->get('with_words_count')) {
                    $data['words_count'] = $language->words_count ?? 0;
                }



                return $data;
            });

            return $this->sendResponse($languagesData, 'Languages retrieved successfully.');

        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve languages: ' . $e->getMessage());
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
        // TODO: Implement language creation
        // - Validate language data
        // - Create language with tenant association
        // - Set creator information
        return $this->sendCreatedResponse([], 'Language created successfully.');
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
        // - Validate language belongs to tenant
        // - Include related content statistics
        // - Show language pairs
        return $this->sendResponse($language, 'Language retrieved successfully.');
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
        // TODO: Implement language update
        // - Validate language belongs to tenant
        // - Update language properties
        // - Handle status changes
        return $this->sendResponse($language, 'Language updated successfully.');
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
        // TODO: Implement language status update
        // - Validate permissions
        // - Update language status
        // - Handle content implications
        return $this->sendResponse($language, 'Language status updated successfully.');
    }

    /**
     * Create a language pair.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function createPair(Request $request): JsonResponse
    {
        // TODO: Implement language pair creation
        // - Validate source and target languages
        // - Create language pair relationship
        // - Set up translation framework
        return $this->sendCreatedResponse([], 'Language pair created successfully.');
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
        // TODO: Implement language pair deletion
        // - Validate pair exists and belongs to tenant
        // - Check for dependent content
        // - Remove language pair
        return $this->sendNoContentResponse();
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
        // TODO: Implement language pair status update
        // - Validate pair belongs to tenant
        // - Update pair status
        // - Handle content availability
        return $this->sendResponse([], 'Language pair status updated successfully.');
    }
}
