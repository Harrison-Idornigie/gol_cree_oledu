<?php

namespace App\Http\Controllers\API\Tenant\Team;

use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\Tenant\Team\Language\CreateSentenceRequest;
use App\Services\Tenants\Language\SentenceManagementService;
use App\Services\Tenants\Language\SentenceWordMappingService;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\Sentence;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
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
    protected SentenceWordMappingService $mappingService;

    /**
     * Constructor - Apply team middleware
     */
    public function __construct(
        SentenceManagementService $sentenceService,
        SentenceWordMappingService $mappingService
    ) {
        $this->sentenceService = $sentenceService;
        $this->mappingService = $mappingService;

        // Apply policies
        $this->authorizeResource(Sentence::class, 'sentence');
    }

    /**
     * Display a listing of sentences.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Sentence::class);

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
            return $this->sendError('Failed to retrieve sentences: ' . $e->getMessage());
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
            $this->authorize('create', Sentence::class);

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
            return $this->sendError('Failed to create sentence: ' . $e->getMessage());
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
        try {
            $this->authorize('view', $sentence);

            $withRelations = $request->boolean('with_relations', true);
            $context = $request->get('context', 'team');

            $detailedSentence = $this->sentenceService->getDetailedSentence(
                $sentence->id,
                $context
            );

            if (!$detailedSentence) {
                return $this->sendError('Sentence not found.', [], 404);
            }

            return $this->sendResponse($detailedSentence, 'Sentence retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendError('Failed to retrieve sentence.', ['error' => $e->getMessage()]);
        }
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
        try {
            $this->authorize('update', $sentence);

            $validatedData = $request->validate([
                'text' => 'sometimes|required|string|max:1000',
                'pronunciation_key' => 'nullable|string|max:255',
                'metadata' => 'nullable|array',
                'words' => 'nullable|array',
                'words.*.word_id' => 'required_with:words|exists:words,id',
                'words.*.position' => 'required_with:words|integer|min:1',
                'words.*.start_time' => 'nullable|numeric|min:0',
                'words.*.end_time' => 'nullable|numeric|min:0',
                'words.*.metadata' => 'nullable|array',
            ]);

            $updateData = $request->only(['text', 'pronunciation_key', 'metadata']);
            $wordData = $validatedData['words'] ?? null;

            $updatedSentence = $this->sentenceService->updateSentence(
                $sentence,
                $updateData,
                $wordData
            );

            return $this->sendResponse($updatedSentence, 'Sentence updated successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Failed to update sentence.', ['error' => $e->getMessage()]);
        }
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
        try {
            $this->authorize('delete', $sentence);

            $deleted = $this->sentenceService->deleteSentence($sentence);

            if ($deleted) {
                return $this->sendNoContentResponse();
            }

            return $this->sendError('Failed to delete sentence.');
        } catch (Exception $e) {
            return $this->sendError('Failed to delete sentence.', ['error' => $e->getMessage()]);
        }
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
        try {
            $this->authorize('update', $sentence);

            $validatedData = $request->validate([
                'language_id' => 'required|exists:languages,id',
                'text' => 'required|string|max:1000',
                'pronunciation_key' => 'nullable|string|max:255',
                'context_notes' => 'nullable|string|max:1000',
                'audio' => 'nullable|file|mimes:mp3,wav,m4a|max:10240'
            ]);

            $audioFile = $request->file('audio');
            $translationData = $request->only(['language_id', 'text', 'pronunciation_key', 'context_notes']);

            $translation = $this->sentenceService->addTranslation(
                $sentence,
                $translationData,
                $audioFile
            );

            return $this->sendCreatedResponse($translation, 'Translation added successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Failed to add translation.', ['error' => $e->getMessage()]);
        }
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
        try {
            $this->authorize('update', $sentence);

            $translation = $sentence->translations()->findOrFail($translationId);

            $validatedData = $request->validate([
                'language_id' => 'sometimes|required|exists:languages,id',
                'text' => 'sometimes|required|string|max:1000',
                'pronunciation_key' => 'nullable|string|max:255',
                'context_notes' => 'nullable|string|max:1000',
                'audio' => 'nullable|file|mimes:mp3,wav,m4a|max:10240'
            ]);

            $audioFile = $request->file('audio');
            $updateData = $request->only(['language_id', 'text', 'pronunciation_key', 'context_notes']);

            $updatedTranslation = $this->sentenceService->updateTranslation(
                $translation,
                $updateData,
                $audioFile
            );

            return $this->sendResponse($updatedTranslation, 'Translation updated successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Failed to update translation.', ['error' => $e->getMessage()]);
        }
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
        try {
            $this->authorize('update', $sentence);

            $translation = $sentence->translations()->findOrFail($translationId);

            $deleted = $this->sentenceService->deleteTranslation($translation);

            if ($deleted) {
                return $this->sendNoContentResponse();
            }

            return $this->sendError('Failed to delete translation.');
        } catch (Exception $e) {
            return $this->sendError('Failed to delete translation.', ['error' => $e->getMessage()]);
        }
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
        try {
            $this->authorize('update', $sentence);

            $request->validate([
                'audio' => 'required|file|mimes:mp3,wav,m4a|max:20480' // 20MB limit
            ]);

            $audioFile = $request->file('audio');
            $result = $this->sentenceService->uploadSentenceAudio($sentence, $audioFile, false);

            return $this->sendResponse($result, 'Audio uploaded successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Failed to upload audio.', ['error' => $e->getMessage()]);
        }
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
        try {
            $this->authorize('update', $sentence);

            $request->validate([
                'audio' => 'required|file|mimes:mp3,wav,m4a|max:20480' // 20MB limit
            ]);

            $audioFile = $request->file('audio');
            $result = $this->sentenceService->uploadSentenceAudio($sentence, $audioFile, true);

            return $this->sendResponse($result, 'Slow audio uploaded successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Failed to upload slow audio.', ['error' => $e->getMessage()]);
        }
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
        try {
            $this->authorize('update', $sentence);

            $translation = $sentence->translations()->findOrFail($translationId);

            $request->validate([
                'audio' => 'required|file|mimes:mp3,wav,m4a|max:10240' // 10MB limit
            ]);

            $audioFile = $request->file('audio');
            $result = $this->sentenceService->updateTranslation($translation, [], $audioFile);

            return $this->sendResponse($result, 'Translation audio uploaded successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Failed to upload translation audio.', ['error' => $e->getMessage()]);
        }
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
        try {
            $this->authorize('update', $sentence);

            $validatedData = $request->validate([
                'timings' => 'required|array',
                'timings.*.word_id' => 'required|exists:words,id',
                'timings.*.start_time' => 'required|numeric|min:0',
                'timings.*.end_time' => 'required|numeric|min:0',
                'timings.*.metadata' => 'nullable|array',
                'audio_duration' => 'required|numeric|min:0'
            ]);

            $timings = $validatedData['timings'];
            $audioDuration = $validatedData['audio_duration'];

            $result = $this->sentenceService->updateWordTimings($sentence, $timings, $audioDuration);

            return $this->sendResponse($result, 'Word timings updated successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Failed to update word timings.', ['error' => $e->getMessage()]);
        }
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
        try {
            $this->authorize('update', $sentence);

            $validatedData = $request->validate([
                'word_order' => 'required|array',
                'word_order.*.word_id' => 'required|exists:words,id',
                'word_order.*.start_time' => 'nullable|numeric|min:0',
                'word_order.*.end_time' => 'nullable|numeric|min:0',
                'word_order.*.metadata' => 'nullable|array',
            ]);

            $wordOrder = $validatedData['word_order'];
            $reorderedSentence = $this->sentenceService->reorderWords($sentence, $wordOrder);

            return $this->sendResponse($reorderedSentence, 'Words reordered successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Failed to reorder words.', ['error' => $e->getMessage()]);
        }
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
            $this->authorize('viewAny', Sentence::class);

            $languageId = $request->get('language_id');
            $search = $request->get('search', '');
            $limit = min($request->get('limit', 50), 100);

            if (!$languageId) {
                return $this->sendError('Language ID is required.');
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
            return $this->sendError('Failed to retrieve available words: ' . $e->getMessage());
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
            $this->authorize('create', Sentence::class);

            $sentenceText = $request->get('text');
            $wordData = $request->get('words', []);
            $languageId = $request->get('language_id');

            if (!$sentenceText || !$languageId) {
                return $this->sendError('Sentence text and language ID are required.');
            }

            $validation = $this->sentenceService->validateSentenceWords(
                $sentenceText,
                $wordData,
                $languageId
            );

            return $this->sendResponse($validation, 'Sentence validation completed.');
        } catch (Exception $e) {
            return $this->sendError('Failed to validate sentence: ' . $e->getMessage());
        }
    }

    /**
     * Analyze sentence text and suggest word mappings.
     */
    public function analyzeSentenceText(Request $request): JsonResponse
    {
        try {
            $this->authorize('create', Sentence::class);

            $validated = $request->validate([
                'text' => 'required|string|max:1000',
                'language_id' => 'required|exists:languages,id'
            ]);

            $analysis = $this->mappingService->analyzeSentence(
                $validated['text'],
                $validated['language_id']
            );

            return $this->sendResponse([
                'analysis' => $analysis,
                'suggestions' => [
                    'auto_create_missing' => count($analysis['missing_words'] ?? []) <= 3,
                    'mapping_quality' => $this->getMappingQuality((float) ($analysis['mapping_percentage'] ?? 0)),
                    'recommended_action' => $this->getRecommendedAction($analysis)
                ]
            ], 'Sentence analysis completed successfully.');
        } catch (Exception $e) {
            Log::error('Sentence analysis failed: ' . $e->getMessage());
            return $this->sendError('Failed to analyze sentence.', [], 500);
        }
    }

    /**
     * Create sentence with automatic word mapping and creation.
     */
    public function createWithAutoMapping(Request $request): JsonResponse
    {
        try {
            $this->authorize('create', Sentence::class);

            $validated = $request->validate([
                'text' => 'required|string|max:1000',
                'language_id' => 'required|exists:languages,id',
                'auto_create_words' => 'boolean',
                'missing_word_data' => 'array',
                'difficulty' => 'nullable|string|in:beginner,intermediate,advanced',
                'metadata' => 'nullable|array'
            ]);

            if ($validated['auto_create_words'] ?? false) {
                $mappingResult = $this->mappingService->createMissingWordsAndMap(
                    $validated['text'],
                    $validated['language_id'],
                    $validated['missing_word_data'] ?? []
                );
            } else {
                $mappingResult = $this->mappingService->analyzeSentence(
                    $validated['text'],
                    $validated['language_id']
                );
            }

            // Create the sentence with mapped words
            $sentence = $this->sentenceService->createSentence([
                'language_id' => $validated['language_id'],
                'text' => $validated['text'],
                'metadata' => array_merge($validated['metadata'] ?? [], [
                    'difficulty' => $validated['difficulty'] ?? 'beginner',
                    'auto_mapped' => true,
                    'mapping_percentage' => $mappingResult['mapped_words'] ?
                        (count($mappingResult['mapped_words']) / $mappingResult['total_words'] * 100) : 0
                ])
            ], $mappingResult['mapped_words'] ?? []);

            return $this->sendResponse([
                'sentence' => $sentence->load(['words', 'translations']),
                'mapping_result' => $mappingResult,
                'created_words' => $mappingResult['created_words'] ?? []
            ], 'Sentence created successfully with automatic word mapping.');
        } catch (Exception $e) {
            Log::error('Auto-mapping sentence creation failed: ' . $e->getMessage());
            return $this->sendError('Failed to create sentence with auto-mapping.', [], 500);
        }
    }

    private function getMappingQuality(float $percentage): string
    {
        if ($percentage >= 90) return 'excellent';
        if ($percentage >= 70) return 'good';
        if ($percentage >= 50) return 'fair';
        return 'poor';
    }

    private function getRecommendedAction(array $analysis): string
    {
        $missingCount = count($analysis['missing_words']);
        $mappingPercentage = $analysis['mapping_percentage'];

        if ($missingCount === 0) {
            return 'ready_to_create';
        }

        if ($missingCount <= 2 && $mappingPercentage >= 70) {
            return 'auto_create_recommended';
        }

        if ($missingCount <= 5) {
            return 'manual_review_recommended';
        }

        return 'create_words_first';
    }
}
