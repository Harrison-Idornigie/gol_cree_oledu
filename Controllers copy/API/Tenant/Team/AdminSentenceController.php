<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Sentence, SentenceTranslation, Word, Language};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Validation\Rule;

class AdminSentenceController extends Controller
{
    /**
     * Display a listing of sentences.
     */
    public function index(Request $request)
    {
        try {
            $query = Sentence::with(['language', 'translations', 'words']);

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

            // Filter by difficulty level
            if ($request->has('difficulty')) {
                $query->whereJsonContains('metadata->difficulty', $request->difficulty);
            }

            $sentences = $query->paginate($request->per_page ?? 25);
            $targetLanguage = $request->target_language ?? 'en';

            return response()->json([
                'success' => true,
                'data' => $sentences->collect()->map(function ($sentence) use ($targetLanguage) {
                    return $sentence->getPreviewData($targetLanguage);
                }),
                'meta' => [
                    'current_page' => $sentences->currentPage(),
                    'last_page' => $sentences->lastPage(),
                    'per_page' => $sentences->perPage(),
                    'total' => $sentences->total()
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Failed to fetch sentences: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sentences',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new sentence.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'language_id' => 'required|exists:languages,id',
                'text' => 'required|string|max:1000',
                'pronunciation_key' => 'nullable|string',
                'metadata' => 'nullable|array',
                'words' => 'nullable|array',
                'words.*.word_id' => 'required_with:words|exists:words,id',
                'words.*.position' => 'required_with:words|integer|min:0',
                'translations' => 'nullable|array',
                'translations.*.language_id' => 'required_with:translations|exists:languages,id',
                'translations.*.text' => 'required_with:translations|string|max:1000',
                'translations.*.pronunciation_key' => 'nullable|string',
                'translations.*.context_notes' => 'nullable|string',
                'audio' => 'nullable|file|mimes:mp3,wav|max:10240',
                'audio_slow' => 'nullable|file|mimes:mp3,wav|max:10240'
            ]);

            $sentence = DB::transaction(function () use ($validated, $request) {
                $sentence = Sentence::create([
                    'language_id' => $validated['language_id'],
                    'text' => $validated['text'],
                    'pronunciation_key' => $validated['pronunciation_key'] ?? null,
                    'metadata' => $validated['metadata'] ?? null
                ]);

                // Add words with positions if provided
                if (!empty($validated['words'])) {
                    foreach ($validated['words'] as $wordData) {
                        $sentence->words()->attach($wordData['word_id'], [
                            'position' => $wordData['position']
                        ]);
                    }
                }

                // Add translations if provided
                if (!empty($validated['translations'])) {
                    foreach ($validated['translations'] as $translation) {
                        $sentence->translations()->create([
                            'language_id' => $translation['language_id'],
                            'text' => $translation['text'],
                            'pronunciation_key' => $translation['pronunciation_key'] ?? null,
                            'context_notes' => $translation['context_notes'] ?? null
                        ]);
                    }
                }

                // Handle audio files
                if ($request->hasFile('audio')) {
                    $sentence->addAudioFile($request->file('audio'));
                }

                if ($request->hasFile('audio_slow')) {
                    $sentence->addAudioFile($request->file('audio_slow'), true);
                }

                return $sentence;
            });

            return response()->json([
                'success' => true,
                'data' => $sentence->getPreviewData()
            ], 201);
        } catch (Exception $e) {
            Log::error('Failed to create sentence: ' . $e->getMessage(), [
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create sentence',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing sentence.
     */
    public function update(Request $request, Sentence $sentence)
    {
        try {
            $validated = $request->validate([
                'text' => 'sometimes|required|string|max:1000',
                'pronunciation_key' => 'nullable|string',
                'metadata' => 'nullable|array',
                'words' => 'nullable|array',
                'words.*.word_id' => 'required_with:words|exists:words,id',
                'words.*.position' => 'required_with:words|integer|min:0',
            ]);

            DB::transaction(function () use ($sentence, $validated, $request) {
                $sentence->update([
                    'text' => $validated['text'] ?? $sentence->text,
                    'pronunciation_key' => $validated['pronunciation_key'] ?? $sentence->pronunciation_key,
                    'metadata' => $validated['metadata'] ?? $sentence->metadata,
                ]);

                // Update words if provided
                if (!empty($validated['words'])) {
                    // Remove existing word associations
                    $sentence->words()->detach();
                    
                    // Add new word associations
                    foreach ($validated['words'] as $wordData) {
                        $sentence->words()->attach($wordData['word_id'], [
                            'position' => $wordData['position']
                        ]);
                    }
                }
            });

            return response()->json([
                'success' => true,
                'data' => $sentence->getPreviewData()
            ]);
        } catch (Exception $e) {
            Log::error('Failed to update sentence: ' . $e->getMessage(), [
                'sentence_id' => $sentence->id,
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update sentence',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update word timings in a sentence.
     */
    public function updateWordTimings(Request $request, Sentence $sentence)
    {
        try {
            $validated = $request->validate([
                'timings' => 'required|array',
                'timings.*.word_id' => [
                    'required',
                    'integer',
                    function ($attribute, $value, $fail) use ($sentence) {
                        if (!$sentence->words()->where('word_id', $value)->exists()) {
                            $fail('The selected word_id is invalid for this sentence.');
                        }
                    }
                ],
                'timings.*.start_time' => 'required|numeric|min:0',
                'timings.*.end_time' => 'required|numeric|gt:timings.*.start_time',
                'timings.*.metadata' => 'nullable|array'
            ]);

            DB::transaction(function () use ($sentence, $validated) {
                foreach ($validated['timings'] as $timing) {
                    $sentence->words()->updateExistingPivot($timing['word_id'], [
                        'start_time' => $timing['start_time'],
                        'end_time' => $timing['end_time'],
                        'metadata' => $timing['metadata'] ?? null
                    ]);
                }
            });

            return response()->json([
                'success' => true,
                'data' => $sentence->getWordTimings()
            ]);
        } catch (Exception $e) {
            Log::error('Failed to update word timings: ' . $e->getMessage(), [
                'sentence_id' => $sentence->id,
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update word timings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload audio files for a sentence.
     */
    public function uploadAudio(Request $request, Sentence $sentence)
    {
        try {
            $request->validate([
                'audio' => 'required|file|mimes:mp3,wav|max:10240'
            ]);

            $sentence->clearMediaCollection('audio');
            $media = $sentence->addAudioFile($request->file('audio'));

            return response()->json([
                'success' => true,
                'data' => [
                    'audio_url' => $media->getUrl()
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Failed to upload audio: ' . $e->getMessage(), [
                'sentence_id' => $sentence->id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload audio',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload slow pronunciation audio.
     */
    public function uploadSlowAudio(Request $request, Sentence $sentence)
    {
        try {
            $request->validate([
                'audio' => 'required|file|mimes:mp3,wav|max:10240'
            ]);

            $sentence->clearMediaCollection('audio_slow');
            $media = $sentence->addAudioFile($request->file('audio'), true);

            return response()->json([
                'success' => true,
                'data' => [
                    'audio_url' => $media->getUrl()
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Failed to upload slow audio: ' . $e->getMessage(), [
                'sentence_id' => $sentence->id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload slow audio',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add a translation to a sentence.
     */
    public function addTranslation(Request $request, Sentence $sentence)
    {
        try {
            $validated = $request->validate([
                'language_id' => [
                    'required',
                    'exists:languages,id',
                    Rule::unique('sentence_translations')->where(function ($query) use ($sentence) {
                        return $query->where('sentence_id', $sentence->id);
                    })
                ],
                'text' => 'required|string|max:1000',
                'pronunciation_key' => 'nullable|string',
                'context_notes' => 'nullable|string',
                'audio' => 'nullable|file|mimes:mp3,wav|max:10240'
            ]);

            $translation = DB::transaction(function () use ($sentence, $validated, $request) {
                $translation = $sentence->translations()->create([
                    'language_id' => $validated['language_id'],
                    'text' => $validated['text'],
                    'pronunciation_key' => $validated['pronunciation_key'] ?? null,
                    'context_notes' => $validated['context_notes'] ?? null
                ]);

                if ($request->hasFile('audio')) {
                    $translation->addMedia($request->file('audio'))
                        ->usingFileName("{$sentence->id}_{$translation->id}_audio.mp3")
                        ->toMediaCollection('audio');
                }

                return $translation;
            });

            return response()->json([
                'success' => true,
                'data' => $translation->getPreviewData()
            ], 201);
        } catch (Exception $e) {
            Log::error('Failed to add translation: ' . $e->getMessage(), [
                'sentence_id' => $sentence->id,
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to add translation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a sentence translation.
     */
    public function updateTranslation(Request $request, Sentence $sentence, SentenceTranslation $translation)
    {
        try {
            if ($translation->sentence_id !== $sentence->id) {
                throw new AuthorizationException('This translation does not belong to the specified sentence.');
            }

            $validated = $request->validate([
                'text' => 'string|max:1000',
                'pronunciation_key' => 'nullable|string',
                'context_notes' => 'nullable|string'
            ]);

            $translation->update($validated);

            return response()->json([
                'success' => true,
                'data' => $translation->fresh()->getPreviewData()
            ]);
        } catch (AuthorizationException $e) {
            Log::warning('Authorization error updating translation: ' . $e->getMessage(), [
                'sentence_id' => $sentence->id,
                'translation_id' => $translation->id,
                'user_id' => $request->user()->id ?? null
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 403);
        } catch (Exception $e) {
            Log::error('Failed to update translation: ' . $e->getMessage(), [
                'sentence_id' => $sentence->id,
                'translation_id' => $translation->id,
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update translation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reorder words in a sentence.
     */
    public function reorderWords(Request $request, Sentence $sentence)
    {
        try {
            $validated = $request->validate([
                'words' => 'required|array',
                'words.*.id' => 'required|integer|exists:words,id',
                'words.*.position' => 'required|integer|min:0'
            ]);

            DB::transaction(function () use ($sentence, $validated) {
                // First, set all positions to negative values to avoid unique constraint conflicts
                foreach ($validated['words'] as $index => $item) {
                    $sentence->words()->updateExistingPivot($item['id'], [
                        'position' => -($index + 1) // Temporary negative position
                    ]);
                }
                
                // Then set the final positions
                foreach ($validated['words'] as $item) {
                    $sentence->words()->updateExistingPivot($item['id'], [
                        'position' => $item['position']
                    ]);
                }
            });

            return response()->json([
                'success' => true,
                'data' => $sentence->fresh()->getWordTimings()
            ]);
        } catch (Exception $e) {
            Log::error('Failed to reorder words: ' . $e->getMessage(), [
                'sentence_id' => $sentence->id,
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder words',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}