<?php

namespace App\Http\Controllers\API\Tenant\Student;

use App\Http\Controllers\API\BaseAPIController;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\GuideBookEntry;
use App\Services\Tenants\Course\GuideBookEntryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

/**
 * Student Guide Controller
 * 
 * Handles guide book access for students.
 * Access Level: Student
 * Scope: Tenant-specific (read-only)
 * 
 * This controller allows students to access educational guides
 * and reference materials within their learning context.
 */
class StudentGuideController extends BaseAPIController
{
    use BelongsToTenant;

    protected GuideBookEntryService $guideBookEntryService;

    /**
     * Constructor - Apply student middleware
     */
    public function __construct(GuideBookEntryService $guideBookEntryService)
    {
        $this->guideBookEntryService = $guideBookEntryService;
        // Apply policies - students can view guide entries
        $this->authorizeResource(\App\Models\Tenants\GuideBookEntry::class, 'guide');
    }

    /**
     * Display a listing of guide entries.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\Tenants\GuideBookEntry::class);

        try {
            $guideEntries = $this->guideBookEntryService->getFilteredGuideBookEntries($request, 'student');

            return $this->sendResponse($guideEntries, 'Guide entries retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve guide entries', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified guide entry.
     * 
     * @param Request $request
     * @param GuideBookEntry $guideEntry
     * @return JsonResponse
     */
    public function show(Request $request, GuideBookEntry $guideEntry): JsonResponse
    {
        $this->authorize('view', $guideEntry);

        try {
            $guideEntryDetails = $this->guideBookEntryService->getGuideBookEntry($guideEntry->id, 'student', ['language', 'topic', 'lessons']);

            if (!$guideEntryDetails) {
                return $this->sendErrorResponse('Guide entry not found or not available', [], 404);
            }

            return $this->sendResponse($guideEntryDetails, 'Guide entry retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve guide entry', ['error' => $e->getMessage()], 500);
        }
    }
}
