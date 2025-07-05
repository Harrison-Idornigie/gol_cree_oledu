<?php

namespace App\Http\Controllers\API\Tenant\Team;

use App\Http\Controllers\API\BaseAPIController;
use App\Http\Requests\Tenant\Team\Language\CreateWordRequest;
use App\Http\Requests\Tenant\Team\Language\UpdateWordRequest;
use App\Http\Requests\Tenant\Team\Language\BulkWordRequest;
use App\Models\Tenants\Word;
use App\Models\Tenants\WordTranslation;
use App\Services\Tenants\Language\WordManagementService;
use App\Services\Tenants\Language\WordConstraintService;
use App\Services\Tenants\Media\AudioProcessingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Team Word Controller
 * 
 * Handles word and translation management operations for team members.
 * Access Level: Team (Teams/Content Creators)
 * Scope: Tenant-specific
 * 
 * This controller allows team members to create and manage words,
 * translations, and audio content within their tenant scope.
 */
class TeamWordController extends BaseAPIController
{
    use BelongsToTenant;

    protected WordManagementService $wordService;
    protected WordConstraintService $constraintService;
    protected AudioProcessingService $audioService;

    /**
     * Constructor - Inject services
     */
    public function __construct(
        WordManagementService $wordService,
        WordConstraintService $constraintService,
        AudioProcessingService $audioService
    ) {
        $this->wordService = $wordService;
        $this->constraintService = $constraintService;
        $this->audioService = $audioService;
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
            $filters = [
                'search' => $request->get('search'),
                'language_id' => $request->get('language_id'),
                'difficulty' => $request->get('difficulty'),
                'tags' => $request->get('tags'),
                'part_of_speech' => $request->get('part_of_speech'),
                'has_audio' => $request->get('has_audio'),
            ];

            $sorts = [
                'sort_by' => $request->get('sort_by', 'text'),
                'sort_order' => $request->get('sort_order', 'asc'),
            ];

            $perPage = min($request->get('per_page', 15), 100);
            $words = $this->wordService->getWords($filters, $sorts, $perPage);

            // Transform the data to include additional metadata
            $words->getCollection()->transform(function ($word) {
                $wordData = $word->getPreviewData();
                $wordData['has_audio'] = $word->hasMedia('pronunciation');
                $wordData['audio_url'] = $word->getPronunciationUrl();
                $wordData['translations_count'] = $word->translations_count;
                return $wordData;
            });

            return $this->sendPaginatedResponse($words, 'Words retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve words: ' . $e->getMessage());
        }
    }

    /**
     * Store a newly created word.
     * 
     * @param CreateWordRequest $request
     * @return JsonResponse
     */
    public function store(CreateWordRequest $request): JsonResponse
    {
        $this->authorize('create', Word::class);

        try {
            $wordData = $request->only([
                'language_id',
                'text',
                'pronunciation_key',
                'part_of_speech',
                'metadata'
            ]);

            $audioFile = $request->hasFile('pronunciation_audio')
                ? $request->file('pronunciation_audio')
                : null;

            $translations = $request->get('translations', []);

            // Process translation audio files
            foreach ($translations as $index => &$translation) {
                if ($request->hasFile("translations.{$index}.pronunciation_audio")) {
                    $translation['audio_file'] = $request->file("translations.{$index}.pronunciation_audio");
                }
            }

            $word = $this->wordService->createWord($wordData, $audioFile, $translations);

            return $this->sendCreatedResponse(
                $word->getPreviewData(),
                'Word created successfully.'
            );
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to create word: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified word.
     *
     * @param Request $request
     * @param string $tenant
     * @param string|int $word
     * @return JsonResponse
     */
    public function show(Request $request, string $tenant, $word): JsonResponse
    {
        // Debug logging
        \Log::info('TeamWordController::show called', [
            'word_id' => $word,
            'tenant_id' => tenant('id'),
            'tenant_slug' => tenant('slug')
        ]);

        // Manual model binding for tenant context
        try {
            $word = Word::findOrFail($word);
            \Log::info('Word found', ['word' => $word->toArray()]);
        } catch (\Exception $e) {
            \Log::error('Word not found', ['word_id' => $word, 'error' => $e->getMessage()]);
            return $this->sendError('Word not found.', [], 404);
        }

        // Validate that the word belongs to the current tenant
        if ($word->tenant_id !== tenant('id')) {
            \Log::warning('Word tenant mismatch', [
                'word_tenant_id' => $word->tenant_id,
                'current_tenant_id' => tenant('id')
            ]);
            return $this->sendError('Word not found.', [], 404);
        }

        $this->authorize('view', $word);

        try {
            $wordData = $this->wordService->getWordDetails($word);
            return $this->sendResponse($wordData, 'Word retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve word: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified word.
     *
     * @param UpdateWordRequest $request
     * @param string $tenant
     * @param string|int $word
     * @return JsonResponse
     */
    public function update(UpdateWordRequest $request, string $tenant, $word): JsonResponse
    {
        // Manual model binding for tenant context
        $word = Word::findOrFail($word);

        // Validate that the word belongs to the current tenant
        if ($word->tenant_id !== tenant('id')) {
            return $this->sendError('Word not found.', [], 404);
        }

        $this->authorize('update', $word);

        try {
            $updateData = $request->only([
                'language_id',
                'text',
                'pronunciation_key',
                'part_of_speech',
                'metadata'
            ]);

            $audioFile = $request->hasFile('pronunciation_audio')
                ? $request->file('pronunciation_audio')
                : null;

            $translations = $request->get('translations', []);

            // Process translation audio files
            foreach ($translations as $index => &$translation) {
                if ($request->hasFile("translations.{$index}.pronunciation_audio")) {
                    $translation['audio_file'] = $request->file("translations.{$index}.pronunciation_audio");
                }
            }

            $updatedWord = $this->wordService->updateWord($word, $updateData, $audioFile, $translations);

            return $this->sendResponse(
                $updatedWord->getPreviewData(),
                'Word updated successfully.'
            );
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to update word: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified word.
     *
     * @param Request $request
     * @param string $tenant
     * @param string|int $word
     * @return JsonResponse
     */
    public function destroy(Request $request, string $tenant, $word): JsonResponse
    {
        // Manual model binding for tenant context
        $word = Word::findOrFail($word);

        // Validate that the word belongs to the current tenant
        if ($word->tenant_id !== tenant('id')) {
            return $this->sendError('Word not found.', [], 404);
        }

        $this->authorize('delete', $word);

        try {
            $this->wordService->deleteWord($word);
            return $this->sendResponse([], 'Word deleted successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to delete word: ' . $e->getMessage());
        }
    }

    /**
     * Perform bulk operations on words.
     * 
     * @param BulkWordRequest $request
     * @return JsonResponse
     */
    public function bulkStore(BulkWordRequest $request): JsonResponse
    {
        $this->authorize('create', Word::class);

        try {
            $operation = $request->get('operation', 'create');
            $data = ['words' => $request->get('words', [])];

            $results = $this->wordService->bulkOperation($operation, $data);

            // Return 201 for create operations, 200 for others
            if ($operation === 'create') {
                return $this->sendCreatedResponse($results, 'Bulk operation completed.');
            }

            return $this->sendResponse($results, 'Bulk operation completed.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Bulk operation failed: ' . $e->getMessage());
        }
    }

    /**
     * Bulk update words.
     * 
     * @param BulkWordRequest $request
     * @return JsonResponse
     */
    public function bulkUpdate(BulkWordRequest $request): JsonResponse
    {
        $this->authorize('create', Word::class); // Use create permission for bulk operations

        try {
            $data = ['words' => $request->get('words', [])];
            $results = $this->wordService->bulkOperation('update', $data);

            return $this->sendResponse($results, 'Words updated successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Bulk update failed: ' . $e->getMessage());
        }
    }

    /**
     * Bulk delete words.
     * 
     * @param BulkWordRequest $request
     * @return JsonResponse
     */
    public function bulkDelete(BulkWordRequest $request): JsonResponse
    {
        $this->authorize('create', Word::class); // Use create permission for bulk operations

        try {
            $wordIds = $request->get('words', []);
            $data = ['words' => $wordIds];
            $results = $this->wordService->bulkOperation('delete', $data);

            return $this->sendResponse($results, 'Bulk delete completed.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Bulk delete failed: ' . $e->getMessage());
        }
    }

    /**
     * Export words to CSV/JSON format.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function export(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Word::class);

        $request->validate([
            'language_id' => 'nullable|exists:languages,id',
            'format' => 'nullable|string|in:csv,json',
            'search' => 'nullable|string|max:255',
            'part_of_speech' => 'nullable|string',
            'difficulty' => 'nullable|string|in:beginner,intermediate,advanced',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'has_audio' => 'nullable|boolean'
        ]);

        try {
            $filters = $request->only([
                'language_id',
                'search',
                'part_of_speech',
                'difficulty',
                'tags',
                'has_audio'
            ]);

            $format = $request->get('format', 'json');

            // Get words using existing service method (use large page size for export)
            $words = $this->wordService->getWords($filters, [], 1000);

            // Simple export implementation
            $exportData = [
                'words' => $words->map(function ($word) {
                    return $word->getPreviewData();
                }),
                'format' => $format,
                'exported_at' => now()->toISOString(),
                'total_count' => $words->count()
            ];

            return $this->sendResponse($exportData, 'Words exported successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to export words: ' . $e->getMessage());
        }
    }

    /**
     * Add translation to word.
     *
     * @param Request $request
     * @param string $tenant
     * @param string|Word $word
     * @return JsonResponse
     */
    public function addTranslation(Request $request, string $tenant, $word): JsonResponse
    {
        // Handle both string ID and model binding
        if (is_string($word) || is_numeric($word)) {
            $word = Word::findOrFail($word);
        }

        $this->authorize('update', $word);

        $request->validate([
            'language_id' => 'required|exists:languages,id',
            'text' => 'required|string|max:255',
            'pronunciation_key' => 'nullable|string|max:255',
            'context_notes' => 'nullable|string',
            'usage_examples' => 'nullable|array',
            'translation_order' => 'nullable|integer|min:0',
            'pronunciation_audio' => 'nullable|file|mimes:mp3,wav|max:10240'
        ]);

        try {
            $translationData = $request->only([
                'language_id',
                'text',
                'pronunciation_key',
                'context_notes',
                'usage_examples',
                'translation_order'
            ]);

            $audioFile = $request->hasFile('pronunciation_audio')
                ? $request->file('pronunciation_audio')
                : null;

            $translation = $this->wordService->addTranslation($word, $translationData, $audioFile);

            return $this->sendCreatedResponse(
                $translation->getPreviewData(),
                'Translation added successfully.'
            );
        } catch (\Exception $e) {
            return $this->sendError('Failed to add translation: ' . $e->getMessage());
        }
    }

    /**
     * Update word translation.
     *
     * @param Request $request
     * @param string $tenant
     * @param string|int $word
     * @param int $translationId
     * @return JsonResponse
     */
    public function updateTranslation(Request $request, string $tenant, $word, int $translationId): JsonResponse
    {
        // Manual model binding for tenant context
        $word = Word::findOrFail($word);

        $request->validate([
            'language_id' => 'sometimes|exists:languages,id',
            'text' => 'sometimes|string|max:255',
            'pronunciation_key' => 'nullable|string|max:255',
            'context_notes' => 'nullable|string',
            'usage_examples' => 'nullable|array',
            'translation_order' => 'nullable|integer|min:0',
            'pronunciation_audio' => 'nullable|file|mimes:mp3,wav|max:10240'
        ]);

        try {
            $translation = $word->translations()->findOrFail($translationId);

            $updateData = $request->only([
                'language_id',
                'text',
                'pronunciation_key',
                'context_notes',
                'usage_examples',
                'translation_order'
            ]);

            $audioFile = $request->hasFile('pronunciation_audio')
                ? $request->file('pronunciation_audio')
                : null;

            $updatedTranslation = $this->wordService->updateTranslation($translation, $updateData, $audioFile);

            return $this->sendResponse(
                $updatedTranslation->getPreviewData(),
                'Translation updated successfully.'
            );
        } catch (\Exception $e) {
            return $this->sendError('Failed to update translation: ' . $e->getMessage());
        }
    }

    /**
     * Delete word translation.
     *
     * @param Request $request
     * @param string $tenant
     * @param string|int $word
     * @param int $translationId
     * @return JsonResponse
     */
    public function deleteTranslation(Request $request, string $tenant, $word, int $translationId): JsonResponse
    {
        // Manual model binding for tenant context
        $word = Word::findOrFail($word);

        try {
            $translation = $word->translations()->findOrFail($translationId);
            $this->wordService->deleteTranslation($translation);

            return $this->sendNoContentResponse();
        } catch (\Exception $e) {
            return $this->sendError('Failed to delete translation: ' . $e->getMessage());
        }
    }

    /**
     * Upload audio for word.
     *
     * @param Request $request
     * @param string $tenant
     * @param string|int $word
     * @return JsonResponse
     */
    public function uploadAudio(Request $request, string $tenant, $word): JsonResponse
    {
        // Manual model binding for tenant context
        $word = Word::findOrFail($word);

        // Validate that the word belongs to the current tenant
        if ($word->tenant_id !== tenant('id')) {
            return $this->sendError('Word not found.', [], 404);
        }

        $request->validate([
            'audio_file' => 'required|file|mimes:mp3,wav|max:10240'
        ]);

        try {
            $audioFile = $request->file('audio_file');
            $result = $this->wordService->uploadWordAudio($word, $audioFile);

            return $this->sendResponse($result, 'Audio uploaded successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to upload audio: ' . $e->getMessage());
        }
    }

    /**
     * Upload audio for word translation.
     *
     * @param Request $request
     * @param string|int $word
     * @param int $translationId
     * @return JsonResponse
     */
    public function uploadTranslationAudio(Request $request, $word, int $translationId): JsonResponse
    {
        // Manual model binding for tenant context
        $word = Word::findOrFail($word);

        $request->validate([
            'audio' => 'required|file|mimes:mp3,wav|max:10240'
        ]);

        try {
            $translation = $word->translations()->findOrFail($translationId);
            $audioFile = $request->file('audio');
            $result = $this->wordService->uploadTranslationAudio($translation, $audioFile);

            return $this->sendResponse($result, 'Translation audio uploaded successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to upload translation audio: ' . $e->getMessage());
        }
    }

    /**
     * Get available words for lesson/exercise builders.
     * 
     * @param Request $request
     * @param string|null $exerciseType
     * @return JsonResponse
     */
    public function getAvailableWords(Request $request, ?string $exerciseType = null): JsonResponse
    {
        $request->validate([
            'source_language_id' => 'nullable|exists:languages,id',
            'target_language_id' => 'nullable|exists:languages,id',
            'difficulty' => 'nullable|string|in:beginner,intermediate,advanced',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'limit' => 'nullable|integer|min:1|max:200'
        ]);

        try {
            $words = $this->constraintService->getAvailableWords(
                $request->get('source_language_id'),
                $request->get('target_language_id'),
                $exerciseType,
                $request->get('difficulty'),
                $request->get('tags'),
                $request->get('limit', 100)
            );

            $wordsData = $words->map(function ($word) {
                return $word->getPreviewData();
            });

            return $this->sendResponse($wordsData, 'Available words retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve available words: ' . $e->getMessage());
        }
    }

    /**
     * Validate word constraints for exercises/lessons.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function validateWordConstraints(Request $request): JsonResponse
    {
        $request->validate([
            'word_ids' => 'required|array',
            'word_ids.*' => 'integer|exists:words,id',
            'context' => 'nullable|string|in:listening,speaking,pronunciation,reading,writing'
        ]);

        try {
            $validation = $this->constraintService->validateWordConstraints(
                $request->get('word_ids'),
                $request->get('context')
            );

            return $this->sendResponse($validation, 'Word constraints validated.');
        } catch (\Exception $e) {
            return $this->sendError('Failed to validate word constraints: ' . $e->getMessage());
        }
    }
}
