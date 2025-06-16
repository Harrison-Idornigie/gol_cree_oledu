<?php

namespace App\Http\Controllers\API\Tenant\Team;

use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\Tenant\Team\Language\CreateSentenceRequest;
use App\Services\Tenants\Language\SentenceManagementService;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\Sentence;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

/**
 * Team Sentence Controller
 *
 * Handles sentence and audio management operations for team members.
 * Access Level: Team (Teams/Content Creators)
 * Scope: Tenant-specific
 *
 * This controller allows team members to create and manage sentences,
 * translations, audio content, and word timings within their tenant scope.
 */
class TeamSentenceController extends BaseAPIController
{
    use BelongsToTenant;

    protected SentenceManagementService $sentenceService;

    /**
     * Constructor - Apply team middleware
     */
    public function __construct(SentenceManagementService $sentenceService)
    {
        $this->sentenceService = $sentenceService;
    }

    /**
     * Display a listing of sentences.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'search' => $request->get('search'),
                'language_id' => $request->get('language_id'),
                'difficulty' => $request->get('difficulty'),
                'has_audio' => $request->boolean('has_audio')
            ];

            $sorts = [];
            if ($request->has('sort_by')) {
                $sorts[] = [
                    'field' => $request->get('sort_by', 'created_at'),
                    'direction' => $request->get('sort_order', 'desc')
                ];
            }

            $perPage = min($request->get('per_page', 15), 50);
            $sentences = $this->sentenceService->getSentences($filters, $sorts, $perPage);

            return $this->sendResponse([
                'sentences' => $sentences->items(),
                'pagination' => [
                    'current_page' => $sentences->currentPage(),
                    'last_page' => $sentences->lastPage(),
                    'per_page' => $sentences->perPage(),
                    'total' => $sentences->total()
                ]
            ], 'Sentences retrieved successfully.');

        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve sentences: ' . $e->getMessage());
        }
    }

    /**
     * Store a newly created sentence.
     *
     * @param CreateSentenceRequest $request
     * @return JsonResponse
     */
    public function store(CreateSentenceRequest $request): JsonResponse
    {
        try {
            $sentenceData = $request->only(['language_id', 'text', 'pronunciation_key', 'metadata']);
            $wordData = $request->get('words', []);

            $audioFile = $request->file('audio');
            $slowAudioFile = $request->file('audio_slow');

            $sentence = $this->sentenceService->createSentence(
                $sentenceData,
                $wordData,
                $audioFile,
                $slowAudioFile
            );

            return $this->sendCreatedResponse([
                'sentence' => $sentence->getPreviewData()
            ], 'Sentence created successfully.');

        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to create sentence: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified sentence.
     * 
     * @param Request $request
     * @param Sentence $sentence
     * @return JsonResponse
     */
    public function show(Request $request, Sentence $sentence): JsonResponse
    {
        // TODO: Implement sentence details
        // - Validate sentence belongs to tenant
        // - Include all translations
        // - Show audio files (normal and slow)
        // - Display word timings
        return $this->sendResponse($sentence, 'Sentence retrieved successfully.');
    }

    /**
     * Update the specified sentence.
     * 
     * @param Request $request
     * @param Sentence $sentence
     * @return JsonResponse
     */
    public function update(Request $request, Sentence $sentence): JsonResponse
    {
        // TODO: Implement sentence update
        // - Validate sentence belongs to tenant
        // - Update sentence text
        // - Reparse words if text changed
        // - Update metadata
        return $this->sendResponse($sentence, 'Sentence updated successfully.');
    }

    /**
     * Remove the specified sentence.
     * 
     * @param Request $request
     * @param Sentence $sentence
     * @return JsonResponse
     */
    public function destroy(Request $request, Sentence $sentence): JsonResponse
    {
        // TODO: Implement sentence deletion
        // - Validate sentence belongs to tenant
        // - Check for usage in exercises/content
        // - Handle cascading deletions
        // - Clean up associated audio files
        return $this->sendNoContentResponse();
    }

    /**
     * Add translation to sentence.
     * 
     * @param Request $request
     * @param Sentence $sentence
     * @return JsonResponse
     */
    public function addTranslation(Request $request, Sentence $sentence): JsonResponse
    {
        // TODO: Implement translation addition
        // - Validate sentence belongs to tenant
        // - Create new translation
        // - Handle language pair validation
        // - Parse translation words
        return $this->sendCreatedResponse([], 'Translation added successfully.');
    }

    /**
     * Update sentence translation.
     * 
     * @param Request $request
     * @param Sentence $sentence
     * @param int $translationId
     * @return JsonResponse
     */
    public function updateTranslation(Request $request, Sentence $sentence, int $translationId): JsonResponse
    {
        // TODO: Implement translation update
        // - Validate translation belongs to sentence and tenant
        // - Update translation text
        // - Reparse words if needed
        // - Update metadata
        return $this->sendResponse([], 'Translation updated successfully.');
    }

    /**
     * Delete sentence translation.
     * 
     * @param Request $request
     * @param Sentence $sentence
     * @param int $translationId
     * @return JsonResponse
     */
    public function deleteTranslation(Request $request, Sentence $sentence, int $translationId): JsonResponse
    {
        // TODO: Implement translation deletion
        // - Validate translation belongs to sentence and tenant
        // - Check for usage in content
        // - Delete translation and associated files
        return $this->sendNoContentResponse();
    }

    /**
     * Upload audio for sentence.
     * 
     * @param Request $request
     * @param Sentence $sentence
     * @return JsonResponse
     */
    public function uploadAudio(Request $request, Sentence $sentence): JsonResponse
    {
        // TODO: Implement audio upload
        // - Validate audio file
        // - Process and store audio
        // - Update sentence with audio reference
        // - Generate word timings if possible
        return $this->sendResponse([], 'Audio uploaded successfully.');
    }

    /**
     * Upload slow audio for sentence.
     * 
     * @param Request $request
     * @param Sentence $sentence
     * @return JsonResponse
     */
    public function uploadSlowAudio(Request $request, Sentence $sentence): JsonResponse
    {
        // TODO: Implement slow audio upload
        // - Validate audio file
        // - Process and store slow audio
        // - Update sentence with slow audio reference
        // - Generate slow word timings
        return $this->sendResponse([], 'Slow audio uploaded successfully.');
    }

    /**
     * Upload audio for sentence translation.
     * 
     * @param Request $request
     * @param Sentence $sentence
     * @param int $translationId
     * @return JsonResponse
     */
    public function uploadTranslationAudio(Request $request, Sentence $sentence, int $translationId): JsonResponse
    {
        // TODO: Implement translation audio upload
        // - Validate translation belongs to sentence and tenant
        // - Process and store audio file
        // - Update translation with audio reference
        // - Generate word timings for translation
        return $this->sendResponse([], 'Translation audio uploaded successfully.');
    }

    /**
     * Update word timings for sentence.
     * 
     * @param Request $request
     * @param Sentence $sentence
     * @return JsonResponse
     */
    public function updateWordTimings(Request $request, Sentence $sentence): JsonResponse
    {
        // TODO: Implement word timings update
        // - Validate sentence belongs to tenant
        // - Update word timing data
        // - Validate timing consistency
        // - Store timing information
        return $this->sendResponse([], 'Word timings updated successfully.');
    }

    /**
     * Reorder words in sentence.
     * 
     * @param Request $request
     * @param Sentence $sentence
     * @return JsonResponse
     */
    public function reorderWords(Request $request, Sentence $sentence): JsonResponse
    {
        // TODO: Implement word reordering
        // - Validate sentence belongs to tenant
        // - Update word order
        // - Maintain sentence structure
        // - Update related timings
        return $this->sendResponse([], 'Words reordered successfully.');
    }

    /**
     * Get available words for sentence creation.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getAvailableWords(Request $request): JsonResponse
    {
        try {
            $languageId = $request->get('language_id');
            $search = $request->get('search', '');
            $limit = min($request->get('limit', 50), 100);

            if (!$languageId) {
                return $this->sendErrorResponse('Language ID is required.');
            }

            $words = $this->sentenceService->getAvailableWordsForSentence(
                $languageId,
                $search,
                $limit
            );

            return $this->sendResponse([
                'words' => $words->map(function ($word) {
                    if (isset($word->type) && $word->type === 'exception') {
                        return [
                            'id' => $word->id,
                            'text' => $word->text,
                            'type' => 'exception',
                            'exception_type' => $word->exception_type,
                            'description' => $word->description,
                            'translations' => []
                        ];
                    }

                    return [
                        'id' => $word->id,
                        'text' => $word->text,
                        'type' => 'word',
                        'part_of_speech' => $word->part_of_speech ?? null,
                        'translations' => $word->translations->map(function ($translation) {
                            return [
                                'id' => $translation->id,
                                'text' => $translation->text,
                                'language_code' => $translation->language->code ?? null
                            ];
                        })
                    ];
                })
            ], 'Available words retrieved successfully.');

        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve available words: ' . $e->getMessage());
        }
    }

    /**
     * Validate sentence words before creation.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validateSentenceWords(Request $request): JsonResponse
    {
        try {
            $sentenceText = $request->get('text');
            $wordData = $request->get('words', []);
            $languageId = $request->get('language_id');

            if (!$sentenceText || !$languageId) {
                return $this->sendErrorResponse('Sentence text and language ID are required.');
            }

            $validation = $this->sentenceService->validateSentenceWords(
                $sentenceText,
                $wordData,
                $languageId
            );

            return $this->sendResponse($validation, 'Sentence validation completed.');

        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to validate sentence: ' . $e->getMessage());
        }
    }
}
