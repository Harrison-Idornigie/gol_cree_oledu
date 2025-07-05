<?php

namespace App\Http\Controllers\API\Tenant\Student;

use App\Http\Controllers\API\BaseAPIController;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\Word;
use App\Services\Tenants\Language\WordManagementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

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

    protected WordManagementService $wordService;

    /**
     * Constructor - Apply student middleware and inject dependencies
     */
    public function __construct(WordManagementService $wordService)
    {
        $this->wordService = $wordService;
    }

    /**
     * Display a listing of words.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Word::class);

        try {
            $request->validate([
                'language_id' => 'sometimes|integer|exists:languages,id',
                'search' => 'sometimes|string|max:255',
                'part_of_speech' => 'sometimes|string|max:50',
                'proficiency_level' => 'sometimes|string|in:A1,A2,B1,B2,C1,C2',
                'max_proficiency_level' => 'sometimes|string|in:A1,A2,B1,B2,C1,C2',
                'include_audio' => 'sometimes|in:true,false,1,0',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
                'sort_by' => 'sometimes|string|in:text,created_at,updated_at',
                'sort_direction' => 'sometimes|string|in:asc,desc'
            ]);

            $filters = [
                'language_id' => $request->get('language_id'),
                'search' => $request->get('search'),
                'part_of_speech' => $request->get('part_of_speech'),
                'proficiency_level' => $request->get('proficiency_level'),
                'max_proficiency_level' => $request->get('max_proficiency_level'),
                'include_audio' => filter_var($request->get('include_audio'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                'sort_by' => $request->get('sort_by', 'text'),
                'sort_direction' => $request->get('sort_direction', 'asc')
            ];

            $perPage = $request->get('per_page', 20);
            $page = $request->get('page', 1);

            // Get words with student-appropriate data (read-only, no sensitive info)
            $result = $this->wordService->getWordsForStudents($filters, $perPage, $page);

            return $this->sendResponse($result, 'Words retrieved successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve words.', [$e->getMessage()], 500);
        }
    }

    /**
     * Display the specified word.
     *
     * @param Request $request
     * @param string|int $word
     * @return JsonResponse
     */
    public function show(Request $request, $tenant, $word): JsonResponse
    {
        try {
            // Manual model binding for tenant context with proper scoping
            // Students can only access published words
            $word = Word::where('id', $word)
                ->where('tenant_id', tenant('id'))
                ->where('status', 'published')
                ->firstOrFail();

            $this->authorize('view', $word);

            // Get detailed word information for students
            $wordData = $this->wordService->getWordDetailsForStudent($word);

            return $this->sendResponse($wordData, 'Word retrieved successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('Word not found.', [], 404);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve word details.', [], 500);
        }
    }

    /**
     * Get translations for a word.
     *
     * @param Request $request
     * @param Word $word
     * @return JsonResponse
     */
    public function translations(Request $request, $tenant, $word): JsonResponse
    {
        try {
            // Manual model binding for tenant context with proper scoping
            $word = Word::where('id', $word)
                ->where('tenant_id', tenant('id'))
                ->firstOrFail();

            $this->authorize('view', $word);

            $request->validate([
                'target_language_id' => 'sometimes|integer|exists:languages,id',
                'include_audio' => 'sometimes|boolean'
            ]);

            $targetLanguageId = $request->get('target_language_id');
            $includeAudio = $request->get('include_audio', true);

            // Get translations for students
            $translations = $this->wordService->getWordTranslationsForStudent(
                $word,
                $targetLanguageId,
                $includeAudio
            );

            return $this->sendResponse($translations, 'Word translations retrieved successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('Word not found.', [], 404);
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve word translations.', [], 500);
        }
    }

    /**
     * Get multiple words in batch.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function batch(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'word_ids' => 'required|array|max:50',
                'word_ids.*' => 'integer|exists:words,id',
                'include_translations' => 'sometimes|boolean',
                'include_audio' => 'sometimes|boolean',
                'target_language_id' => 'sometimes|integer|exists:languages,id'
            ]);

            $wordIds = $request->get('word_ids');
            $includeTranslations = $request->get('include_translations', true);
            $includeAudio = $request->get('include_audio', true);
            $targetLanguageId = $request->get('target_language_id');

            // Get batch words for students
            $words = $this->wordService->getBatchWordsForStudent(
                $wordIds,
                $includeTranslations,
                $includeAudio,
                $targetLanguageId
            );

            return $this->sendResponse($words, 'Batch words retrieved successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve batch words.', [], 500);
        }
    }
}
