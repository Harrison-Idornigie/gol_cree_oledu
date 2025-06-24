<?php

namespace App\Http\Controllers\API\Tenant\Team;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\Tenants\Course\GuideBookEntryService;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\GuideBookEntry;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Exception;

/**
 * Team Guide Book Entry Controller
 * 
 * Handles guide book entry management operations for team members.
 * Access Level: Team (Teams/Content Creators)
 * Scope: Tenant-specific
 * 
 * This controller allows team members to create and manage guide book
 * entries and educational content within their tenant scope.
 */
class TeamGuideBookEntryController extends BaseAPIController
{
    use BelongsToTenant;

    protected GuideBookEntryService $guideBookService;

    /**
     * Constructor - Apply team middleware
     */
    public function __construct(GuideBookEntryService $guideBookService)
    {
        $this->guideBookService = $guideBookService;
    }

    /**
     * Display a listing of guide book entries.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', GuideBookEntry::class);

        try {
            $guideBookEntries = $this->guideBookService->getFilteredGuideBookEntries($request, 'team');
            return $this->sendResponse($guideBookEntries, 'Guide book entries retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendError('Failed to retrieve guide book entries.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Store a newly created guide book entry.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', GuideBookEntry::class);

        try {
            $validatedData = $request->validate([
                'unit_id' => 'required|exists:units,id',
                'topic' => 'required|string|max:255',
                'content' => 'required|string',
                'difficulty_level' => 'nullable|integer|between:1,10',
                'tags' => 'nullable|array',
                'references' => 'nullable|array',
                'order' => 'nullable|integer|min:1',
            ]);

            $guideEntry = $this->guideBookService->createGuideBookEntry($validatedData, Auth::user());

            return $this->sendCreatedResponse($guideEntry, 'Guide book entry created successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Failed to create guide book entry.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified guide book entry.
     * 
     * @param Request $request
     * @param GuideBookEntry $guideEntry
     * @return JsonResponse
     */
    public function show(Request $request, GuideBookEntry $guideEntry): JsonResponse
    {
        $this->authorize('view', $guideEntry);

        try {
            $withRelations = [];

            if ($request->has('with_unit')) {
                $withRelations[] = 'unit';
            }
            if ($request->has('with_media')) {
                $withRelations[] = 'media';
            }

            $guideBookEntry = $this->guideBookService->getGuideBookEntry(
                $guideEntry->id,
                'team',
                $withRelations
            );

            if (!$guideBookEntry) {
                return $this->sendError('Guide book entry not found.', [], 404);
            }

            return $this->sendResponse($guideBookEntry, 'Guide book entry retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendError('Failed to retrieve guide book entry.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Update the specified guide book entry.
     * 
     * @param Request $request
     * @param GuideBookEntry $guideEntry
     * @return JsonResponse
     */
    public function update(Request $request, GuideBookEntry $guideEntry): JsonResponse
    {
        $this->authorize('update', $guideEntry);

        try {
            $validatedData = $request->validate([
                'topic' => 'sometimes|required|string|max:255',
                'content' => 'sometimes|required|string',
                'difficulty_level' => 'nullable|integer|between:1,10',
                'tags' => 'nullable|array',
                'references' => 'nullable|array',
                'order' => 'nullable|integer|min:1',
            ]);

            $updatedGuideEntry = $this->guideBookService->updateGuideBookEntry(
                $guideEntry,
                $validatedData,
                Auth::user()
            );

            return $this->sendResponse($updatedGuideEntry, 'Guide book entry updated successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Failed to update guide book entry.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified guide book entry.
     * 
     * @param Request $request
     * @param GuideBookEntry $guideEntry
     * @return JsonResponse
     */
    public function destroy(Request $request, GuideBookEntry $guideEntry): JsonResponse
    {
        $this->authorize('delete', $guideEntry);

        try {
            $deleted = $this->guideBookService->deleteGuideBookEntry($guideEntry, Auth::user());

            if ($deleted) {
                return $this->sendNoContentResponse();
            }

            return $this->sendError('Failed to delete guide book entry.');
        } catch (Exception $e) {
            return $this->sendError('Failed to delete guide book entry.', ['error' => $e->getMessage()]);
        }
    }
}
