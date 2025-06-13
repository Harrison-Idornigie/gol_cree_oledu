<?php

namespace App\Http\Controllers\API\Tenant\Student;

use App\Http\Controllers\API\BaseAPIController;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\GuideBookEntry;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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

    /**
     * Constructor - Apply student middleware
     */
    public function __construct()
    {

    }

    /**
     * Display a listing of guide entries.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // TODO: Implement guide entries listing
        // - Available guide entries for student
        // - Filter by category, language, topic
        // - Include relevance to current learning
        return $this->sendResponse([], 'Guide entries retrieved successfully.');
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
        // TODO: Implement guide entry details
        // - Validate guide entry is accessible
        // - Include full content and examples
        // - Show related learning materials
        return $this->sendResponse($guideEntry, 'Guide entry retrieved successfully.');
    }
}
