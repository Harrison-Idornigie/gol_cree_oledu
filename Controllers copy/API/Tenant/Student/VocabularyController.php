<?php
namespace App\Http\Controllers\API;

use App\Models\Lesson;
use App\Models\Unit;
use App\Models\UserProgress;
use App\Models\VocabularyItem;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VocabularyController extends BaseAPIController
{
    /**
     * Get vocabulary items with filtering options
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'lesson_id'   => 'nullable|integer|exists:lessons,id',
            'language_id' => 'nullable|integer|exists:languages,id',
            'difficulty'  => 'nullable|integer|min:1|max:5',
        ]);

        $query = VocabularyItem::with(['media']);

        // Filter by lesson if provided
        if ($request->has('lesson_id')) {
            $query->where('lesson_id', $request->lesson_id);
        }

        // Filter by language if provided
        if ($request->has('language_id')) {
            $query->whereHas('lesson.unit.learningPath', function ($q) use ($request) {
                $q->where('language_id', $request->language_id);
            });
        }

        // Filter by difficulty if provided
        if ($request->has('difficulty')) {
            $query->where('difficulty_level', $request->difficulty);
        }

        $items = $query->orderBy('difficulty_level')
            ->get()
            ->map(function ($item) {
                return $item->getWithExamples();
            });

        return $this->sendResponse($items);
    }

    /**
     * Get vocabulary review items
     */
    public function reviewItems(Request $request): JsonResponse
    {
        $request->validate([
            'count'       => 'nullable|integer|min:1|max:50',
            'difficulty'  => 'nullable|integer|min:1|max:5',
            'unit_id'     => 'nullable|integer|exists:units,id',
            'language_id' => 'nullable|integer|exists:languages,id',
            'review_type' => 'nullable|string|in:due,mistakes,all',
        ]);

        $reviewType = $request->input('review_type', 'due');

        // Get items due for review based on spaced repetition
        $items = $this->getReviewDueItems(
            auth()->id(),
            $request->input('count', 10),
            $request->difficulty,
            $request->unit_id,
            $request->language_id,
            $reviewType
        );

        return $this->sendResponse($items);
    }

    /**
     * Check vocabulary item translation
     */
    public function checkTranslation(Request $request, VocabularyItem $item): JsonResponse
    {
        $request->validate([
            'translation' => 'required|string',
        ]);

        $isCorrect = $item->checkTranslation($request->translation);

        // Update progress using spaced repetition
        $this->updateProgress($item, $isCorrect);

        return $this->sendResponse([
            'correct'             => $isCorrect,
            'correct_translation' => $isCorrect ? null : $item->translation,
            'similar_words'       => $isCorrect ? $item->getSimilarWords(3) : [],
        ]);
    }

    /**
     * Get vocabulary statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $request->validate([
            'unit_id'     => 'nullable|integer|exists:units,id',
            'language_id' => 'nullable|integer|exists:languages,id',
        ]);

        $userId = auth()->id();

        // Base query for user progress on vocabulary items
        $query = UserProgress::where('user_id', $userId)
            ->where('trackable_type', VocabularyItem::class)
            ->with('trackable');

        // Filter by unit if provided
        if ($request->has('unit_id')) {
            $unitId    = $request->unit_id;
            $lessonIds = Lesson::where('unit_id', $unitId)->pluck('id')->toArray();

            $query->whereHas('trackable', function ($q) use ($lessonIds) {
                $q->whereIn('lesson_id', $lessonIds);
            });
        }

        // Filter by language if provided
        if ($request->has('language_id')) {
            $languageId = $request->language_id;

            $query->whereHas('trackable.lesson.unit.learningPath', function ($q) use ($languageId) {
                $q->where('language_id', $languageId);
            });
        }

        // Get progress data
        $progress = $query->get();

        // Calculate items due for review today
        $dueItems = $progress->filter(function ($item) {
            if (! isset($item->meta_data['next_review'])) {
                return false;
            }

            $nextReview = Carbon::parse($item->meta_data['next_review']);
            return $nextReview->isToday();
        })->count();

        // Get recent vocabulary items
        $recentItems = $progress->sortByDesc(function ($item) {
            return $item->meta_data['last_review'] ?? '1970-01-01';
        })->take(5)->map(function ($progress) {
            $item          = $progress->trackable;
            $correctStreak = $progress->meta_data['correct_streak'] ?? 0;
            $reviewCount   = $progress->meta_data['review_count'] ?? 0;

            // Calculate mastery percentage (20% per correct streak, max 100%)
            $mastery = min(($correctStreak / 5) * 100, 100);

            return [
                'id'           => $item->id,
                'word'         => $item->word,
                'translation'  => $item->translation,
                'phonetic'     => $item->phonetic,
                'example'      => $item->example,
                'mastery'      => $mastery,
                'review_count' => $reviewCount,
                'last_review'  => $progress->meta_data['last_review'] ?? null,
            ];
        })->values()->toArray();

        // Get mistake items (items with incorrect answers)
        $mistakeItems = $progress->filter(function ($item) {
            return isset($item->meta_data['incorrect_count']) && $item->meta_data['incorrect_count'] > 0;
        })->count();

        $stats = [
            'total_words_learned' => $progress->where('status', 'completed')->count(),
            'words_in_progress'   => $progress->where('status', 'in_progress')->count(),
            'mastery_levels'      => $this->calculateMasteryLevels($progress),
            'daily_progress'      => $this->getDailyProgress($userId),
            'recent_activity'     => $this->getRecentActivity($userId),
            'due_today'           => $dueItems,
            'mistakes'            => $mistakeItems,
            'recent_vocabulary'   => $recentItems,
        ];

        return $this->sendResponse($stats);
    }

    /**
     * Get vocabulary items for a specific unit
     */
    public function unitVocabulary(Request $request, int $unitId): JsonResponse
    {
        $unit = Unit::findOrFail($unitId);

        // Get all lessons in this unit
        $lessonIds = $unit->lessons()->pluck('id')->toArray();

        // Get vocabulary items for these lessons
        $items = VocabularyItem::whereIn('lesson_id', $lessonIds)
            ->with(['media', 'lesson'])
            ->when($request->has('difficulty'), function ($query) use ($request) {
                $query->where('difficulty_level', $request->difficulty);
            })
            ->orderBy('difficulty_level')
            ->get()
            ->map(function ($item) {
                return $item->getWithExamples();
            });

        return $this->sendResponse($items);
    }

    /**
     * Get vocabulary items that the user has struggled with
     */
    public function mistakeItems(Request $request): JsonResponse
    {
        $request->validate([
            'count'       => 'nullable|integer|min:1|max:50',
            'unit_id'     => 'nullable|integer|exists:units,id',
            'language_id' => 'nullable|integer|exists:languages,id',
        ]);

        $userId = auth()->id();
        $count  = $request->input('count', 10);

        // Get items with low correct streak
        $query = VocabularyItem::whereHas('progress', function ($query) use ($userId) {
            $query->where('user_id', $userId)
                ->where('status', 'in_progress')
                ->whereJsonLength('meta_data->incorrect_count', '>', 0);
        });

        // Filter by unit if provided
        if ($request->has('unit_id')) {
            $unitId    = $request->unit_id;
            $lessonIds = Lesson::where('unit_id', $unitId)->pluck('id')->toArray();
            $query->whereIn('lesson_id', $lessonIds);
        }

        // Filter by language if provided
        if ($request->has('language_id')) {
            $languageId = $request->language_id;
            $query->whereHas('lesson.unit.learningPath', function ($q) use ($languageId) {
                $q->where('language_id', $languageId);
            });
        }

        $items = $query->with(['progress' => function ($query) use ($userId) {
            $query->where('user_id', $userId);
        }])
            ->orderByRaw('RAND()')
            ->limit($count)
            ->get()
            ->map->getWithExamples()
            ->toArray();

        return $this->sendResponse($items);
    }

    /**
     * Get items due for review based on spaced repetition
     */
    private function getReviewDueItems(
        int $userId,
        int $count,
        ?int $difficulty = null,
        ?int $unitId = null,
        ?int $languageId = null,
        string $reviewType = 'due'
    ): array {
        // Base query for vocabulary items
        $query = VocabularyItem::query();

        // Apply filters based on review type
        if ($reviewType === 'due' || $reviewType === 'all') {
            $query->whereHas('progress', function ($query) use ($userId, $reviewType) {
                $query->where('user_id', $userId)
                    ->where(function ($q) {
                        $q->where('status', 'in_progress')
                            ->orWhere('status', 'completed');
                    });

                // Only filter by next_review date for 'due' type
                if ($reviewType === 'due') {
                    $query->where(function ($q) {
                        $q->whereNull('meta_data->next_review')
                            ->orWhere('meta_data->next_review', '<=', now());
                    });
                }
            });
        } else if ($reviewType === 'mistakes') {
            $query->whereHas('progress', function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->where('status', 'in_progress')
                    ->whereJsonLength('meta_data->incorrect_count', '>', 0);
            });
        }

        // Filter by difficulty if provided
        if ($difficulty) {
            $query->where('difficulty_level', $difficulty);
        }

        // Filter by unit if provided
        if ($unitId) {
            $lessonIds = Lesson::where('unit_id', $unitId)->pluck('id')->toArray();
            $query->whereIn('lesson_id', $lessonIds);
        }

        // Filter by language if provided
        if ($languageId) {
            $query->whereHas('lesson.unit.learningPath', function ($q) use ($languageId) {
                $q->where('language_id', $languageId);
            });
        }

        // Include progress data and randomize
        $query->with(['progress' => function ($query) use ($userId) {
            $query->where('user_id', $userId);
        }])
            ->orderBy(DB::raw('RAND()'))
            ->limit($count);

        // Get review items
        $reviewItems = $query->get();

        // Include new words if we don't have enough review items and not specifically looking at mistakes
        if ($reviewItems->count() < $count && $reviewType !== 'mistakes') {
            $newItemsQuery = VocabularyItem::whereDoesntHave('progress', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            });

            // Apply the same filters to new items
            if ($difficulty) {
                $newItemsQuery->where('difficulty_level', $difficulty);
            }

            if ($unitId) {
                $lessonIds = Lesson::where('unit_id', $unitId)->pluck('id')->toArray();
                $newItemsQuery->whereIn('lesson_id', $lessonIds);
            }

            if ($languageId) {
                $newItemsQuery->whereHas('lesson.unit.learningPath', function ($q) use ($languageId) {
                    $q->where('language_id', $languageId);
                });
            }

            $newItems = $newItemsQuery->orderBy(DB::raw('RAND()'))
                ->limit($count - $reviewItems->count())
                ->get();

            $reviewItems = $reviewItems->concat($newItems);
        }

        return $reviewItems->map->getWithExamples()->toArray();
    }

    /**
     * Update progress using spaced repetition
     */
    private function updateProgress(VocabularyItem $item, bool $isCorrect): void
    {
        $progress = UserProgress::firstOrNew([
            'user_id'        => auth()->id(),
            'trackable_type' => VocabularyItem::class,
            'trackable_id'   => $item->id,
        ]);

        $metadata      = $progress->meta_data ?? [];
        $reviewCount   = ($metadata['review_count'] ?? 0) + 1;
        $correctStreak = $isCorrect ?
        ($metadata['correct_streak'] ?? 0) + 1 :
        0;

        // Track incorrect answers for mistake practice
        $incorrectCount = $metadata['incorrect_count'] ?? 0;
        if (! $isCorrect) {
            $incorrectCount++;
        }

        // Calculate next review date using spaced repetition
        $nextReview = $this->calculateNextReview($correctStreak);

        $progress->update([
            'status'    => $correctStreak >= 5 ? 'completed' : 'in_progress',
            'meta_data' => array_merge($metadata, [
                'review_count'    => $reviewCount,
                'correct_streak'  => $correctStreak,
                'incorrect_count' => $incorrectCount,
                'last_review'     => now(),
                'next_review'     => $nextReview,
            ]),
        ]);
    }

    /**
     * Calculate next review date using spaced repetition
     */
    private function calculateNextReview(int $correctStreak): Carbon
    {
        // Using a modified version of SuperMemo 2 algorithm
        $intervals = [
            0 => 0,  // Same day
            1 => 1,  // Next day
            2 => 3,  // 3 days
            3 => 7,  // 1 week
            4 => 14, // 2 weeks
            5 => 30, // 1 month
        ];

        $days = $intervals[min($correctStreak, 5)];
        return now()->addDays($days);
    }

    /**
     * Calculate mastery levels
     */
    private function calculateMasteryLevels($progress): array
    {
        return [
            'mastered' => $progress->where('status', 'completed')->count(),
            'familiar' => $progress->where('status', 'in_progress')
                ->filter(function ($p) {
                    return ($p->meta_data['correct_streak'] ?? 0) >= 3;
                })->count(),
            'learning' => $progress->where('status', 'in_progress')
                ->filter(function ($p) {
                    return ($p->meta_data['correct_streak'] ?? 0) < 3;
                })->count(),
        ];
    }

    /**
     * Get daily progress
     */
    private function getDailyProgress(int $userId): array
    {
        return UserProgress::where('user_id', $userId)
            ->where('trackable_type', VocabularyItem::class)
            ->where('created_at', '>=', now()->subDays(30))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(function ($record) {
                return [$record->date => [
                    'total'     => $record->total,
                    'completed' => $record->completed,
                ]];
            })
            ->toArray();
    }

    /**
     * Get recent activity
     */
    private function getRecentActivity(int $userId): array
    {
        return UserProgress::where('user_id', $userId)
            ->where('trackable_type', VocabularyItem::class)
            ->with('trackable')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->map(function ($progress) {
                return [
                    'word'           => $progress->trackable->word,
                    'translation'    => $progress->trackable->translation,
                    'status'         => $progress->status,
                    'correct_streak' => $progress->meta_data['correct_streak'] ?? 0,
                    'last_review'    => $progress->meta_data['last_review'],
                ];
            })
            ->toArray();
    }
}
