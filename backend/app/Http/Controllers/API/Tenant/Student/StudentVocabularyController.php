<?php

namespace App\Http\Controllers\API\Tenant\Student;

use App\Http\Controllers\API\BaseAPIController;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\VocabularyItem;
use App\Services\Tenants\Language\VocabularyService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

/**
 * Student Vocabulary Controller
 * 
 * Handles vocabulary practice and review for students.
 * Access Level: Student
 * Scope: Tenant-specific (read-only)
 * 
 * This controller allows students to practice vocabulary
 * and track their vocabulary learning progress.
 */
class StudentVocabularyController extends BaseAPIController
{
    use BelongsToTenant;

    protected VocabularyService $vocabularyService;

    /**
     * Constructor - Apply student middleware
     */
    public function __construct(VocabularyService $vocabularyService)
    {
        $this->vocabularyService = $vocabularyService;
        // Apply policies - students can view and practice vocabulary
        $this->middleware(function ($request, $next) {
            $this->authorize('viewAny', 'App\Models\Tenants\VocabularyItem');
            return $next($request);
        });
    }

    /**
     * Display a listing of vocabulary items.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', VocabularyItem::class);

        try {
            $vocabularyItems = $this->vocabularyService->getFilteredVocabularyItems($request, 'student');

            return $this->sendResponse($vocabularyItems, 'Vocabulary items retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve vocabulary items', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get vocabulary items for review.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function reviewItems(Request $request): JsonResponse
    {
        $this->authorize('viewAny', VocabularyItem::class);

        try {
            $user = $request->user();
            $filters = $request->only(['language_id', 'limit']);
            $reviewItems = $this->vocabularyService->getVocabularyForReview($user, $filters);

            return $this->sendResponse($reviewItems, 'Review vocabulary items retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve review items', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get vocabulary items that were answered incorrectly.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function mistakeItems(Request $request): JsonResponse
    {
        $this->authorize('viewAny', VocabularyItem::class);

        try {
            $user = $request->user();
            $filters = $request->only(['language_id', 'limit']);
            $mistakeItems = $this->vocabularyService->getMistakeVocabulary($user, $filters);

            return $this->sendResponse($mistakeItems, 'Mistake vocabulary items retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve mistake items', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get vocabulary for a specific unit.
     * 
     * @param Request $request
     * @param int $unitId
     * @return JsonResponse
     */
    public function unitVocabulary(Request $request, int $unitId): JsonResponse
    {
        $this->authorize('viewAny', VocabularyItem::class);

        try {
            $user = $request->user();
            $unitVocabulary = $this->vocabularyService->getVocabularyByUnit($unitId, $user);

            return $this->sendResponse($unitVocabulary, 'Unit vocabulary retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve unit vocabulary', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Check translation for vocabulary item.
     * 
     * @param Request $request
     * @param VocabularyItem $vocabulary
     * @return JsonResponse
     */
    public function checkTranslation(Request $request, VocabularyItem $vocabulary): JsonResponse
    {
        $this->authorize('practice', $vocabulary);

        $request->validate([
            'translation' => 'required|string|max:255'
        ]);

        try {
            $user = $request->user();
            $result = $this->vocabularyService->checkTranslation($vocabulary, $request->translation, $user);

            return $this->sendResponse($result, 'Translation checked successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to check translation', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get vocabulary statistics for student.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', VocabularyItem::class);

        try {
            $user = $request->user();
            $filters = $request->only(['language_id', 'date_range']);
            $statistics = $this->vocabularyService->getUserVocabularyStatistics($user, $filters);

            return $this->sendResponse($statistics, 'Vocabulary statistics retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve vocabulary statistics', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified vocabulary item.
     * 
     * @param Request $request
     * @param VocabularyItem $vocabulary
     * @return JsonResponse
     */
    public function show(Request $request, VocabularyItem $vocabulary): JsonResponse
    {
        $this->authorize('view', $vocabulary);

        try {
            $vocabularyDetails = $this->vocabularyService->getVocabularyItem($vocabulary->id, 'student', ['language', 'lesson', 'unit']);

            if (!$vocabularyDetails) {
                return $this->sendErrorResponse('Vocabulary item not found or not available', [], 404);
            }

            return $this->sendResponse($vocabularyDetails, 'Vocabulary item retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve vocabulary item', ['error' => $e->getMessage()], 500);
        }
    }
}
