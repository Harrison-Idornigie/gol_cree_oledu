<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Word;
use App\Models\WordTranslation;
use App\Models\Language;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WordController extends BaseAPIController
{
    /**
     * Get words with optional filtering
     */
    public function index(Request $request): JsonResponse
    {
        $query = Word::query()
            ->with(['language']);

        // Filter by language
        if ($request->has('language_code')) {
            $query->whereHas('language', function ($q) use ($request) {
                $q->where('code', $request->language_code);
            });
        } elseif ($request->has('language_id')) {
            $query->where('language_id', $request->language_id);
        }

        // Filter by part of speech
        if ($request->has('part_of_speech')) {
            $query->where('part_of_speech', $request->part_of_speech);
        }

        // Filter by text (exact match or partial)
        if ($request->has('text')) {
            if ($request->has('exact_match') && $request->exact_match) {
                $query->where('text', $request->text);
            } else {
                $query->where('text', 'like', '%' . $request->text . '%');
            }
        }

        // Filter by difficulty level
        if ($request->has('difficulty')) {
            $query->whereJsonContains('metadata->difficulty', $request->difficulty);
        }

        // Pagination
        $perPage = $request->input('per_page', 20);
        $words = $query->paginate($perPage);

        return $this->sendResponse($words);
    }

    /**
     * Get a specific word with translations
     */
    public function show(Word $word): JsonResponse
    {
        $word->load(['language', 'translations.language']);
        
        // Get the pronunciation URL if available
        $pronunciationUrl = $word->getPronunciationUrl();
        
        // Format the response data
        $data = $word->getPreviewData();
        
        if ($pronunciationUrl) {
            $data['pronunciation_url'] = $pronunciationUrl;
        }

        return $this->sendResponse($data);
    }

    /**
     * Get translations for a word
     */
    public function translations(Word $word, Request $request): JsonResponse
    {
        $query = $word->translations()->with('language');
        
        // Filter by target language
        if ($request->has('language_code')) {
            $query->whereHas('language', function ($q) use ($request) {
                $q->where('code', $request->language_code);
            });
        } elseif ($request->has('language_id')) {
            $query->where('language_id', $request->language_id);
        }
        
        $translations = $query->get();
        
        return $this->sendResponse($translations);
    }

    /**
     * Get words by batch
     */
    public function batch(Request $request): JsonResponse
    {
        $request->validate([
            'words' => 'required|array',
            'words.*' => 'string',
            'language_code' => 'nullable|string|exists:languages,code',
            'target_language_code' => 'nullable|string|exists:languages,code',
        ]);

        $words = $request->input('words');
        $languageCode = $request->input('language_code');
        $targetLanguageCode = $request->input('target_language_code');

        // Get language IDs if codes are provided
        $languageId = null;
        $targetLanguageId = null;

        if ($languageCode) {
            $language = Language::where('code', $languageCode)->first();
            $languageId = $language ? $language->id : null;
        }

        if ($targetLanguageCode) {
            $targetLanguage = Language::where('code', $targetLanguageCode)->first();
            $targetLanguageId = $targetLanguage ? $targetLanguage->id : null;
        }

        // Query words
        $query = Word::query()
            ->with(['language']);

        // Add translations if target language is specified
        if ($targetLanguageId) {
            $query->with(['translations' => function ($q) use ($targetLanguageId) {
                $q->where('language_id', $targetLanguageId);
                $q->with('language');
            }]);
        } else {
            $query->with(['translations.language']);
        }

        // Filter by language if specified
        if ($languageId) {
            $query->where('language_id', $languageId);
        }

        // Filter by the requested words
        $query->whereIn('text', $words);

        $results = $query->get();

        // Format the response as a dictionary keyed by word
        $wordDictionary = [];
        foreach ($results as $word) {
            $wordData = $word->getPreviewData($targetLanguageCode);
            $wordDictionary[strtolower($word->text)] = $wordData;
        }

        return $this->sendResponse($wordDictionary);
    }
}