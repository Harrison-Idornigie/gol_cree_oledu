<?php

namespace App\Http\Controllers\API\Tenant\Student;

use App\Http\Controllers\API\BaseAPIController;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\Word;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Student Word Controller
 * 
 * Handles word lookup and reference for students.
 * Access Level: Student
 * Scope: Tenant-specific (read-only)
 * 
 * This controller allows students to look up words and access
 * word information within their learning context.
 */
class StudentWordController extends BaseAPIController
{
    use BelongsToTenant;

    /**
     * Constructor - Apply student middleware
     */
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'verified', 'tenant', 'membership:student']);
    }

    /**
     * Display a listing of words.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // TODO: Implement words listing
        // - Available words for student
        // - Filter by language, difficulty
        // - Include learning status
        return $this->sendResponse([], 'Words retrieved successfully.');
    }

    /**
     * Display the specified word.
     * 
     * @param Request $request
     * @param Word $word
     * @return JsonResponse
     */
    public function show(Request $request, Word $word): JsonResponse
    {
        // TODO: Implement word details
        // - Validate word is accessible
        // - Include pronunciation and audio
        // - Show usage examples
        return $this->sendResponse($word, 'Word retrieved successfully.');
    }

    /**
     * Get translations for a word.
     * 
     * @param Request $request
     * @param Word $word
     * @return JsonResponse
     */
    public function translations(Request $request, Word $word): JsonResponse
    {
        // TODO: Implement word translations
        // - All available translations for word
        // - Include pronunciation and audio
        // - Show context and usage
        return $this->sendResponse([], 'Word translations retrieved successfully.');
    }

    /**
     * Get multiple words in batch.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function batch(Request $request): JsonResponse
    {
        // TODO: Implement batch word retrieval
        // - Multiple words in single request
        // - Efficient for sentence/text processing
        // - Include basic translations
        return $this->sendResponse([], 'Batch words retrieved successfully.');
    }
}
