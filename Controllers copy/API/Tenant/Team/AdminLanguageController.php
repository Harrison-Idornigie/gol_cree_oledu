<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\LanguagePair;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Exception;

class AdminLanguageController extends Controller
{
    /**
     * Display a listing of languages.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $languages = Language::with(['sourceLanguages', 'targetLanguages'])
                ->get()
                ->map->getPreviewData();

            return response()->json([
                'success' => true,
                'data' => $languages
            ]);
        } catch (Exception $e) {
            Log::error('Failed to fetch languages: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch languages',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a new language.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'code' => [
                    'required', 
                    'string', 
                    'size:2', 
                    Rule::in(Language::getValidIsoCodes()),
                    Rule::unique('languages', 'code')
                ],
                'name' => 'required|string|max:255',
                'native_name' => 'required|string|max:255',
                'is_active' => 'boolean'
            ]);

            $language = Language::create($validated);

            return response()->json([
                'success' => true,
                'data' => $language->getPreviewData()
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Failed to create language: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create language',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified language.
     *
     * @param Language $language
     * @return JsonResponse
     */
    public function show(Language $language): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $language->getPreviewData()
            ]);
        } catch (Exception $e) {
            Log::error('Failed to fetch language details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch language details',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified language.
     *
     * @param Request $request
     * @param Language $language
     * @return JsonResponse
     */
    public function update(Request $request, Language $language): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'string|max:255',
                'native_name' => 'string|max:255',
                'is_active' => 'boolean'
            ]);

            $language->update($validated);

            return response()->json([
                'success' => true,
                'data' => $language->fresh()->getPreviewData()
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Failed to update language: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update language',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update language status.
     *
     * @param Request $request
     * @param Language $language
     * @return JsonResponse
     */
    public function updateStatus(Request $request, Language $language): JsonResponse
    {
        try {
            $validated = $request->validate([
                'is_active' => 'required|boolean'
            ]);

            $language->update($validated);

            return response()->json([
                'success' => true,
                'data' => [
                    'is_active' => $language->is_active
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Failed to update language status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update language status',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Create a new language pair.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createPair(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'source_language_id' => 'required|exists:languages,id',
                'target_language_id' => [
                    'required',
                    'exists:languages,id',
                    'different:source_language_id',
                    Rule::unique('language_pairs', 'target_language_id')
                        ->where('source_language_id', $request->source_language_id)
                ]
            ]);

            $sourceLanguage = Language::findOrFail($validated['source_language_id']);
            $sourceLanguage->targetLanguages()->attach($validated['target_language_id'], ['is_active' => true]);

            return response()->json([
                'success' => true,
                'data' => [
                    'source_language_id' => $validated['source_language_id'],
                    'target_language_id' => $validated['target_language_id'],
                    'is_active' => true
                ]
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Language not found'
            ], 404);
        } catch (Exception $e) {
            Log::error('Failed to create language pair: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create language pair',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Delete a language pair.
     *
     * @param Language $source
     * @param Language $target
     * @return JsonResponse
     */
    public function deletePair(Language $source, Language $target): JsonResponse
    {
        try {
            // Check if the pair exists
            $pairExists = $source->targetLanguages()->where('target_language_id', $target->id)->exists();
            if (!$pairExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Language pair does not exist'
                ], 404);
            }

            $source->targetLanguages()->detach($target->id);

            return response()->json([
                'success' => true,
                'message' => 'Language pair deleted successfully'
            ]);
        } catch (Exception $e) {
            Log::error('Failed to delete language pair: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete language pair',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update language pair status.
     *
     * @param Request $request
     * @param Language $source
     * @param Language $target
     * @return JsonResponse
     */
    public function updatePairStatus(Request $request, Language $source, Language $target): JsonResponse
    {
        try {
            $validated = $request->validate([
                'is_active' => 'required|boolean'
            ]);

            // Check if the pair exists
            $pairExists = $source->targetLanguages()->where('target_language_id', $target->id)->exists();
            if (!$pairExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Language pair does not exist'
                ], 404);
            }

            $source->targetLanguages()
                ->updateExistingPivot($target->id, ['is_active' => $validated['is_active']]);

            return response()->json([
                'success' => true,
                'data' => [
                    'is_active' => $validated['is_active']
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Failed to update language pair status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update language pair status',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get language statistics.
     *
     * @param Language $language
     * @return JsonResponse
     */
    public function getStatistics(Language $language): JsonResponse
    {
        try {
            $stats = [
                'word_count' => $language->words()->count(),
                'sentence_count' => $language->sentences()->count(),
                'audio_coverage' => [
                    'words' => $this->calculateAudioCoverage($language, 'Word'),
                    'sentences' => $this->calculateAudioCoverage($language, 'Sentence')
                ],
                'translation_coverage' => $this->getTranslationCoverage($language),
                'learner_count' => $this->getLearnerCount($language)
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (Exception $e) {
            Log::error('Failed to get language statistics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get language statistics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Calculate audio coverage for content type.
     *
     * @param Language $language
     * @param string $type
     * @return array
     */
    private function calculateAudioCoverage(Language $language, string $type): array
    {
        try {
            $modelClass = "App\\Models\\$type";
            if (!class_exists($modelClass)) {
                throw new Exception("Model class {$modelClass} does not exist");
            }
            
            $total = $modelClass::where('language_id', $language->id)->count();
            $withAudio = $modelClass::where('language_id', $language->id)
                ->whereHas('media', function ($query) {
                    $query->where('collection_name', 'pronunciation');
                })
                ->count();

            return [
                'total' => $total,
                'with_audio' => $withAudio,
                'percentage' => $total > 0 ? round(($withAudio / $total) * 100, 2) : 0
            ];
        } catch (Exception $e) {
            Log::error("Error calculating audio coverage: " . $e->getMessage());
            return [
                'total' => 0,
                'with_audio' => 0,
                'percentage' => 0,
                'error' => 'Failed to calculate audio coverage'
            ];
        }
    }

    /**
     * Get translation coverage stats.
     *
     * @param Language $language
     * @return array
     */
    private function getTranslationCoverage(Language $language): array
    {
        try {
            $targetLanguages = $language->targetLanguages;
            $coverage = [];
            $wordCount = $language->words()->count();
            $sentenceCount = $language->sentences()->count();

            foreach ($targetLanguages as $target) {
                $wordTranslations = DB::table('word_translations')
                    ->where('language_id', $target->id)
                    ->whereIn('word_id', $language->words()->pluck('id'))
                    ->count();

                $sentenceTranslations = DB::table('sentence_translations')
                    ->where('language_id', $target->id)
                    ->whereIn('sentence_id', $language->sentences()->pluck('id'))
                    ->count();

                $coverage[$target->code] = [
                    'language_name' => $target->name,
                    'words' => [
                        'total' => $wordCount,
                        'translated' => $wordTranslations,
                        'percentage' => $wordCount > 0 
                            ? round(($wordTranslations / $wordCount) * 100, 2) 
                            : 0
                    ],
                    'sentences' => [
                        'total' => $sentenceCount,
                        'translated' => $sentenceTranslations,
                        'percentage' => $sentenceCount > 0 
                            ? round(($sentenceTranslations / $sentenceCount) * 100, 2) 
                            : 0
                    ]
                ];
            }

            return $coverage;
        } catch (Exception $e) {
            Log::error("Error calculating translation coverage: " . $e->getMessage());
            return ['error' => 'Failed to calculate translation coverage'];
        }
    }

    /**
     * Get number of users learning this language.
     *
     * @param Language $language
     * @return array
     */
    private function getLearnerCount(Language $language): array
    {
        try {
            return [
                'total_learners' => DB::table('user_progress')
                    ->where('trackable_type', 'App\Models\LearningPath')
                    ->whereIn('trackable_id', function ($query) use ($language) {
                        $query->select('id')
                            ->from('learning_paths')
                            ->where('target_language_id', $language->id);
                    })
                    ->distinct('user_id')
                    ->count('user_id'),
                'active_learners' => DB::table('user_progress')
                    ->where('trackable_type', 'App\Models\LearningPath')
                    ->whereIn('trackable_id', function ($query) use ($language) {
                        $query->select('id')
                            ->from('learning_paths')
                            ->where('target_language_id', $language->id);
                    })
                    ->where('updated_at', '>=', now()->subDays(30))
                    ->distinct('user_id')
                    ->count('user_id')
            ];
        } catch (Exception $e) {
            Log::error("Error calculating learner count: " . $e->getMessage());
            return [
                'total_learners' => 0,
                'active_learners' => 0,
                'error' => 'Failed to calculate learner count'
            ];
        }
    }
}