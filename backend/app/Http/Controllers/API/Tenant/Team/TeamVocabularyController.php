<?php

namespace App\Http\Controllers\API\Tenant\Team;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\Tenants\Language\VocabularyService;
use App\Models\Tenants\VocabularyItem;
use App\Models\Tenants\Word;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Exception;

/**
 * Team VocabularyItem Controller
 * 
 * Handles vocabulary management operations for team members.
 * Access Level: Team (Teams/Content Creators)
 * Scope: Tenant-specific
 * 
 * This controller allows team members to create and manage vocabulary
 * items and collections within their tenant scope.
 */
class TeamVocabularyController extends BaseAPIController
{
    use BelongsToTenant;

    protected VocabularyService $vocabularyService;

    /**
     * Constructor - Apply team middleware
     */
    public function __construct(VocabularyService $vocabularyService)
    {
        $this->vocabularyService = $vocabularyService;
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
            $vocabularyItems = $this->vocabularyService->getFilteredVocabularyItems($request, 'team');
            return $this->sendResponse($vocabularyItems, 'Vocabulary items retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendError('Failed to retrieve vocabulary items.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Store a newly created vocabulary item.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', VocabularyItem::class);

        try {
            $validatedData = $request->validate([
                'lesson_id' => 'required|exists:lessons,id',
                'word' => 'required|string|max:255',
                'translation' => 'required|string|max:255',
                'example' => 'nullable|string',
                'phonetic' => 'nullable|string|max:255',
                'part_of_speech' => 'nullable|string|in:noun,verb,adjective,adverb,pronoun,preposition,conjunction,interjection,article',
                'difficulty_level' => 'nullable|integer|between:1,10',
            ]);

            $vocabularyItem = $this->vocabularyService->createVocabularyItem($validatedData, Auth::user());

            return $this->sendCreatedResponse($vocabularyItem, 'Vocabulary item created successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Failed to create vocabulary item.', ['error' => $e->getMessage()]);
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
            $withRelations = [];

            if ($request->has('with_lesson')) {
                $withRelations[] = 'lesson';
            }
            if ($request->has('with_media')) {
                $withRelations[] = 'media';
            }

            $vocabularyItem = $this->vocabularyService->getVocabularyItem(
                $vocabulary->id,
                'team',
                $withRelations
            );

            if (!$vocabularyItem) {
                return $this->sendError('Vocabulary item not found.', [], 404);
            }

            return $this->sendResponse($vocabularyItem, 'Vocabulary item retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendError('Failed to retrieve vocabulary item.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Update the specified vocabulary item.
     * 
     * @param Request $request
     * @param VocabularyItem $vocabulary
     * @return JsonResponse
     */
    public function update(Request $request, VocabularyItem $vocabulary): JsonResponse
    {
        $this->authorize('update', $vocabulary);

        try {
            $validatedData = $request->validate([
                'word' => 'sometimes|required|string|max:255',
                'translation' => 'sometimes|required|string|max:255',
                'example' => 'nullable|string',
                'phonetic' => 'nullable|string|max:255',
                'part_of_speech' => 'nullable|string|in:noun,verb,adjective,adverb,pronoun,preposition,conjunction,interjection,article',
                'difficulty_level' => 'nullable|integer|between:1,10',
            ]);

            $updatedVocabularyItem = $this->vocabularyService->updateVocabularyItem(
                $vocabulary,
                $validatedData,
                Auth::user()
            );

            return $this->sendResponse($updatedVocabularyItem, 'Vocabulary item updated successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Failed to update vocabulary item.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified vocabulary item.
     * 
     * @param Request $request
     * @param VocabularyItem $vocabulary
     * @return JsonResponse
     */
    public function destroy(Request $request, VocabularyItem $vocabulary): JsonResponse
    {
        $this->authorize('delete', $vocabulary);

        try {
            $deleted = $this->vocabularyService->deleteVocabularyItem($vocabulary, Auth::user());

            if (!$deleted) {
                return $this->sendError('Failed to delete vocabulary item.');
            }

            return $this->sendNoContentResponse();
        } catch (Exception $e) {
            return $this->sendError('Failed to delete vocabulary item.', ['error' => $e->getMessage()]);
        }
    }
}
