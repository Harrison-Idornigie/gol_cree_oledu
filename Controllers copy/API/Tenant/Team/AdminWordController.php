<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\{Word, WordTranslation, Language};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Exception;

class AdminWordController extends BaseAPIController
{
    /**
     * Display a listing of words.
     */
    public function index(Request $request)
    {
        try {
            $query = Word::with(['language', 'translations.language']);

            // Filter by language
            if ($request->has('language')) {
                $query->whereHas('language', function ($q) use ($request) {
                    $q->where('code', $request->language);
                });
            }

            // Filter by search term
            if ($request->has('search')) {
                $query->where('text', 'like', "%{$request->search}%")
                    ->orWhereHas('translations', function ($q) use ($request) {
                        $q->where('text', 'like', "%{$request->search}%");
                    });
            }

            // Filter by part of speech
            if ($request->has('part_of_speech')) {
                $query->where('part_of_speech', $request->part_of_speech);
            }

            $words = $query->paginate($request->per_page ?? 25);
            $targetLanguage = $request->target_language ?? null;

            return $this->sendResponse([
                'data' => $words->collect()->map(function ($word) use ($targetLanguage) {
                    return $word->getPreviewData($targetLanguage);
                }),
                'meta' => [
                    'current_page' => $words->currentPage(),
                    'last_page' => $words->lastPage(),
                    'per_page' => $words->perPage(),
                    'total' => $words->total()
                ]
            ], 'Words retrieved successfully');
        } catch (Exception $e) {
            Log::error('Failed to fetch words: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to fetch words', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a new word.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'language_id' => 'required|exists:languages,id',
                'text' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('words')->where(function ($query) use ($request) {
                        return $query->where('language_id', $request->language_id)
                            ->where('part_of_speech', $request->part_of_speech);
                    })
                ],
                'pronunciation_key' => 'nullable|string|max:255',
                'part_of_speech' => 'nullable|string|max:50',
                'metadata' => 'nullable|array',
                'translations' => 'array',
                'translations.*.language_id' => 'required|exists:languages,id',
                'translations.*.text' => 'required|string|max:255',
                'translations.*.pronunciation_key' => 'nullable|string|max:255',
                'translations.*.context_notes' => 'nullable|string',
                'translations.*.usage_examples' => 'nullable|array',
                'pronunciation_audio' => 'nullable|file|mimes:mp3,wav|max:10240'
            ]);

            $word = DB::transaction(function () use ($validated, $request) {
                $word = Word::create([
                    'language_id' => $validated['language_id'],
                    'text' => $validated['text'],
                    'pronunciation_key' => $validated['pronunciation_key'] ?? null,
                    'part_of_speech' => $validated['part_of_speech'] ?? null,
                    'metadata' => $validated['metadata'] ?? null
                ]);

                // Add translations if provided
                if (!empty($validated['translations'])) {
                    foreach ($validated['translations'] as $index => $translation) {
                        $word->translations()->create([
                            'language_id' => $translation['language_id'],
                            'text' => $translation['text'],
                            'pronunciation_key' => $translation['pronunciation_key'] ?? null,
                            'context_notes' => $translation['context_notes'] ?? null,
                            'usage_examples' => $translation['usage_examples'] ?? null,
                            'translation_order' => $index + 1
                        ]);
                    }
                }

                // Handle pronunciation audio if provided
                if ($request->hasFile('pronunciation_audio')) {
                    $word->addMedia($request->file('pronunciation_audio'))
                        ->usingFileName("{$word->id}_pronunciation.mp3")
                        ->toMediaCollection('pronunciation');
                }

                return $word;
            });

            return $this->sendCreatedResponse($word->getPreviewData(), 'Word created successfully');
        } catch (Exception $e) {
            Log::error('Failed to create word: ' . $e->getMessage(), [
                'request' => $request->except('pronunciation_audio'),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to create word', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified word.
     */
    public function show(Word $word, Request $request)
    {
        try {
            $word->load(['language', 'translations.language']);
            $targetLanguage = $request->target_language ?? null;

            return $this->sendResponse($word->getPreviewData($targetLanguage), 'Word details retrieved successfully');
        } catch (Exception $e) {
            Log::error('Failed to retrieve word: ' . $e->getMessage(), [
                'word_id' => $word->id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to retrieve word', ['error' => $e->getMessage()], 500);
        }
    }

    // Continue with the rest of the methods with similar error handling...
    
    /**
     * Update the specified word.
     */
    public function update(Request $request, Word $word)
    {
        try {
            $validated = $request->validate([
                'text' => [
                    'string',
                    'max:255',
                    Rule::unique('words')->where(function ($query) use ($request, $word) {
                        return $query->where('language_id', $word->language_id)
                            ->where('part_of_speech', $request->part_of_speech ?? $word->part_of_speech)
                            ->where('id', '!=', $word->id);
                    })
                ],
                'pronunciation_key' => 'nullable|string|max:255',
                'part_of_speech' => 'nullable|string|max:50',
                'metadata' => 'nullable|array'
            ]);

            $word->update($validated);
            $word->load(['language', 'translations.language']);

            return $this->sendResponse($word->fresh()->getPreviewData(), 'Word updated successfully');
        } catch (Exception $e) {
            Log::error('Failed to update word: ' . $e->getMessage(), [
                'word_id' => $word->id,
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to update word', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Add a translation to a word.
     */
    public function addTranslation(Request $request, Word $word)
    {
        try {
            $validated = $request->validate([
                'language_id' => [
                    'required',
                    'exists:languages,id',
                    Rule::unique('word_translations')->where(function ($query) use ($word) {
                        return $query->where('word_id', $word->id);
                    })
                ],
                'text' => 'required|string|max:255',
                'pronunciation_key' => 'nullable|string|max:255',
                'context_notes' => 'nullable|string',
                'usage_examples' => 'nullable|array',
                'pronunciation_audio' => 'nullable|file|mimes:mp3,wav|max:10240'
            ]);

            $translation = DB::transaction(function () use ($word, $validated, $request) {
                $translation = $word->translations()->create([
                    'language_id' => $validated['language_id'],
                    'text' => $validated['text'],
                    'pronunciation_key' => $validated['pronunciation_key'] ?? null,
                    'context_notes' => $validated['context_notes'] ?? null,
                    'usage_examples' => $validated['usage_examples'] ?? null,
                    'translation_order' => $word->translations()->count() + 1
                ]);

                if ($request->hasFile('pronunciation_audio')) {
                    $translation->addMedia($request->file('pronunciation_audio'))
                        ->usingFileName("{$word->id}_{$translation->id}_pronunciation.mp3")
                        ->toMediaCollection('pronunciation');
                }

                return $translation;
            });

            $translation->load('language');

            return $this->sendCreatedResponse($translation->getPreviewData(), 'Translation added successfully');
        } catch (Exception $e) {
            Log::error('Failed to add translation: ' . $e->getMessage(), [
                'word_id' => $word->id,
                'request' => $request->except('pronunciation_audio'),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to add translation', ['error' => $e->getMessage()], 500);
        }
    }
}
