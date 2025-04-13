<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\Language;
use App\Models\LearningPath;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\UserProgress;
use App\Models\VocabularyItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LanguageController extends BaseAPIController
{
    /**
     * Display a listing of all active languages.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Language::where('is_active', true);

        // Include learning paths count if requested
        if ($request->has('with_learning_paths_count')) {
            $query->withCount(['learningPaths' => function ($query) {
                $query->where('status', 'published');
            }]);
        }

        // Include user's progress if requested
        if ($request->has('with_user_progress') && Auth::check()) {
            $userId = Auth::id();
            $query->with(['learningPaths' => function ($query) use ($userId) {
                $query->where('status', 'published')
                    ->with(['progress' => function ($query) use ($userId) {
                        $query->where('user_id', $userId);
                    }]);
            }]);
        }

        $languages = $query->get();

        return $this->sendResponse($languages);
    }

    /**
     * Display the specified language.
     */
    public function show(Language $language): JsonResponse
    {
        if (! $language->is_active) {
            return $this->sendError('Language not found or not active.', [], 404);
        }

        return $this->sendResponse($language);
    }

    /**
     * Get all languages that have published learning paths.
     */
    public function withLearningPaths(): JsonResponse
    {
        $languages = Language::whereHas('learningPaths', function ($query) {
            $query->where('status', 'published');
        })->where('is_active', true)->get();

        return $this->sendResponse($languages);
    }

    /**
     * Get learning paths for a specific language.
     */
    public function learningPaths(Language $language, Request $request): JsonResponse
    {
        if (! $language->is_active) {
            return $this->sendError('Language not found or not active.', [], 404);
        }

        $query = $language->learningPaths()
            ->where('status', 'published');

        // Filter by target level if provided
        if ($request->has('target_level')) {
            $query->where('target_level', $request->target_level);
        }

        // Include units if requested
        if ($request->has('with_units')) {
            $query->with(['units' => function ($query) {
                $query->orderBy('order');
            }]);
        }

        // Include user progress if requested
        if ($request->has('with_progress') && Auth::check()) {
            $query->with(['progress' => function ($query) {
                $query->where('user_id', Auth::id());
            }]);
        }

        $learningPaths = $query->get();

        return $this->sendResponse($learningPaths);
    }

    /**
     * Get available proficiency levels for a specific language.
     */
    public function proficiencyLevels(Language $language): JsonResponse
    {
        if (! $language->is_active) {
            return $this->sendError('Language not found or not active.', [], 404);
        }

        $levels = $language->learningPaths()
            ->where('status', 'published')
            ->distinct()
            ->pluck('target_level')
            ->values();

        return $this->sendResponse($levels);
    }

    /**
     * Get user's progress summary for a language.
     */
    public function userProgress(Language $language): JsonResponse
    {
        if (! $language->is_active) {
            return $this->sendError('Language not found or not active.', [], 404);
        }

        $userId = Auth::id();

        // Get all learning paths for this language
        $learningPaths = $language->learningPaths()
            ->where('status', 'published')
            ->with(['progress' => function ($query) use ($userId) {
                $query->where('user_id', $userId);
            }])
            ->get();

        // Calculate progress statistics
        $totalPaths     = $learningPaths->count();
        $completedPaths = $learningPaths->filter(function ($path) {
            return $path->progress->isNotEmpty() && $path->progress->first()->status === 'completed';
        })->count();

        $inProgressPaths = $learningPaths->filter(function ($path) {
            return $path->progress->isNotEmpty() && $path->progress->first()->status === 'in_progress';
        })->count();

        $notStartedPaths = $totalPaths - $completedPaths - $inProgressPaths;

        $progressPercentage = $totalPaths > 0
        ? round((($completedPaths + ($inProgressPaths * 0.5)) / $totalPaths) * 100, 2)
        : 0;

        return $this->sendResponse([
            'language'            => $language->only(['id', 'code', 'name', 'native_name']),
            'total_paths'         => $totalPaths,
            'completed_paths'     => $completedPaths,
            'in_progress_paths'   => $inProgressPaths,
            'not_started_paths'   => $notStartedPaths,
            'progress_percentage' => $progressPercentage,
        ]);
    }

    /**
     * Get comprehensive dashboard data for a specific language.
     */
    public function dashboard(Language $language): JsonResponse
    {
        if (! $language->is_active) {
            return $this->sendError('Language not found or not active.', [], 404);
        }

        $userId = Auth::id();

        // Get progress summary
        $progressSummary = $this->getProgressSummary($language, $userId);

        // Get recent activities
        $recentActivities = $this->getRecentActivities($language, $userId);

        // Get recommendations
        $recommendations = $this->getRecommendations($language, $userId);

        // Get vocabulary statistics
        $vocabularyStats = $this->getVocabularyStats($language, $userId);

        return $this->sendResponse([
            'language'          => $language->only(['id', 'code', 'name', 'native_name']),
            'progress_summary'  => $progressSummary,
            'recent_activities' => $recentActivities,
            'recommendations'   => $recommendations,
            'vocabulary_stats'  => $vocabularyStats,
        ]);
    }

    /**
     * Get progress summary for a language.
     */
    private function getProgressSummary(Language $language, int $userId): array
    {
        // Get all learning paths for this language
        $learningPaths = $language->learningPaths()
            ->where('status', 'published')
            ->with(['progress' => function ($query) use ($userId) {
                $query->where('user_id', $userId);
            }])
            ->get();

        // Calculate progress statistics
        $totalPaths     = $learningPaths->count();
        $completedPaths = $learningPaths->filter(function ($path) {
            return $path->progress->isNotEmpty() && $path->progress->first()->status === 'completed';
        })->count();

        $inProgressPaths = $learningPaths->filter(function ($path) {
            return $path->progress->isNotEmpty() && $path->progress->first()->status === 'in_progress';
        })->count();

        $notStartedPaths = $totalPaths - $completedPaths - $inProgressPaths;

        $progressPercentage = $totalPaths > 0
        ? round((($completedPaths + ($inProgressPaths * 0.5)) / $totalPaths) * 100, 2)
        : 0;

        // Get lesson completion stats
        $lessonStats = $this->getLessonStats($language, $userId);

        // Get quiz performance
        $quizStats = $this->getQuizStats($language, $userId);

        return [
            'total_paths'         => $totalPaths,
            'completed_paths'     => $completedPaths,
            'in_progress_paths'   => $inProgressPaths,
            'not_started_paths'   => $notStartedPaths,
            'progress_percentage' => $progressPercentage,
            'lesson_stats'        => $lessonStats,
            'quiz_stats'          => $quizStats,
        ];
    }

    /**
     * Get lesson statistics for a language.
     */
    private function getLessonStats(Language $language, int $userId): array
    {
        // Get all lessons for learning paths in this language
        $lessonIds = DB::table('lessons')
            ->join('units', 'lessons.unit_id', '=', 'units.id')
            ->join('learning_paths', 'units.learning_path_id', '=', 'learning_paths.id')
            ->where('learning_paths.language_id', $language->id)
            ->where('learning_paths.status', 'published')
            ->pluck('lessons.id');

        // Get progress for these lessons
        $lessonProgress = UserProgress::where('user_id', $userId)
            ->whereIn('progressable_id', $lessonIds)
            ->where('progressable_type', 'App\\Models\\Lesson')
            ->get();

        $totalLessons      = count($lessonIds);
        $completedLessons  = $lessonProgress->where('status', 'completed')->count();
        $inProgressLessons = $lessonProgress->where('status', 'in_progress')->count();

        return [
            'total'                 => $totalLessons,
            'completed'             => $completedLessons,
            'in_progress'           => $inProgressLessons,
            'not_started'           => $totalLessons - $completedLessons - $inProgressLessons,
            'completion_percentage' => $totalLessons > 0
            ? round(($completedLessons / $totalLessons) * 100, 2)
            : 0,
        ];
    }

    /**
     * Get quiz statistics for a language.
     */
    private function getQuizStats(Language $language, int $userId): array
    {
        // Get all quizzes for learning paths in this language
        $quizIds = DB::table('quizzes')
            ->join('units', 'quizzes.unit_id', '=', 'units.id')
            ->join('learning_paths', 'units.learning_path_id', '=', 'learning_paths.id')
            ->where('learning_paths.language_id', $language->id)
            ->where('learning_paths.status', 'published')
            ->pluck('quizzes.id');

        // Get progress for these quizzes
        $quizProgress = UserProgress::where('user_id', $userId)
            ->whereIn('progressable_id', $quizIds)
            ->where('progressable_type', 'App\\Models\\Quiz')
            ->get();

        $totalQuizzes     = count($quizIds);
        $completedQuizzes = $quizProgress->where('status', 'completed')->count();

        // Calculate average score
        $averageScore = 0;
        $scoresCount  = 0;

        foreach ($quizProgress as $progress) {
            if (isset($progress->metadata['score'])) {
                $averageScore += $progress->metadata['score'];
                $scoresCount++;
            }
        }

        if ($scoresCount > 0) {
            $averageScore = round($averageScore / $scoresCount, 2);
        }

        return [
            'total'         => $totalQuizzes,
            'completed'     => $completedQuizzes,
            'average_score' => $averageScore,
        ];
    }

    /**
     * Get recent activities for a language.
     */
    private function getRecentActivities(Language $language, int $userId): array
    {
        // Get learning path IDs for this language
        $learningPathIds = $language->learningPaths()
            ->where('status', 'published')
            ->pluck('id');

        // Get unit IDs for these learning paths
        $unitIds = DB::table('units')
            ->whereIn('learning_path_id', $learningPathIds)
            ->pluck('id');

        // Get lesson and quiz IDs for these units
        $lessonIds = DB::table('lessons')
            ->whereIn('unit_id', $unitIds)
            ->pluck('id');

        $quizIds = DB::table('quizzes')
            ->whereIn('unit_id', $unitIds)
            ->pluck('id');

        // Get vocabulary item IDs for these lessons
        $vocabularyIds = DB::table('vocabulary_items')
            ->whereIn('lesson_id', $lessonIds)
            ->pluck('id');

        // Get recent progress entries
        $recentProgress = UserProgress::where('user_id', $userId)
            ->where(function ($query) use ($learningPathIds, $lessonIds, $quizIds, $vocabularyIds) {
                $query->where(function ($q) use ($learningPathIds) {
                    $q->whereIn('progressable_id', $learningPathIds)
                        ->where('progressable_type', 'App\\Models\\LearningPath');
                })->orWhere(function ($q) use ($lessonIds) {
                    $q->whereIn('progressable_id', $lessonIds)
                        ->where('progressable_type', 'App\\Models\\Lesson');
                })->orWhere(function ($q) use ($quizIds) {
                    $q->whereIn('progressable_id', $quizIds)
                        ->where('progressable_type', 'App\\Models\\Quiz');
                })->orWhere(function ($q) use ($vocabularyIds) {
                    $q->whereIn('progressable_id', $vocabularyIds)
                        ->where('progressable_type', 'App\\Models\\VocabularyItem');
                });
            })
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();

        // Format the activities
        $activities = [];

        foreach ($recentProgress as $progress) {
            $activityType = class_basename($progress->progressable_type);
            $item         = null;

            // Get the related item
            switch ($activityType) {
                case 'LearningPath':
                    $item         = LearningPath::find($progress->progressable_id);
                    $activityData = [
                        'id'         => $item->id,
                        'title'      => $item->title,
                        'type'       => 'learning_path',
                        'status'     => $progress->status,
                        'updated_at' => $progress->updated_at->toISOString(),
                    ];
                    break;

                case 'Lesson':
                    $item         = Lesson::with('unit.learningPath')->find($progress->progressable_id);
                    $activityData = [
                        'id'            => $item->id,
                        'title'         => $item->title,
                        'type'          => 'lesson',
                        'learning_path' => $item->unit->learningPath->title,
                        'status'        => $progress->status,
                        'updated_at'    => $progress->updated_at->toISOString(),
                    ];
                    break;

                case 'Quiz':
                    $item         = Quiz::with('unit.learningPath')->find($progress->progressable_id);
                    $activityData = [
                        'id'            => $item->id,
                        'title'         => $item->title,
                        'type'          => 'quiz',
                        'learning_path' => $item->unit->learningPath->title,
                        'status'        => $progress->status,
                        'score'         => $progress->metadata['score'] ?? null,
                        'updated_at'    => $progress->updated_at->toISOString(),
                    ];
                    break;

                case 'VocabularyItem':
                    $item         = VocabularyItem::with('lesson.unit.learningPath')->find($progress->progressable_id);
                    $activityData = [
                        'id'            => $item->id,
                        'word'          => $item->word,
                        'translation'   => $item->translation,
                        'type'          => 'vocabulary',
                        'lesson'        => $item->lesson->title,
                        'learning_path' => $item->lesson->unit->learningPath->title,
                        'mastery'       => $progress->progress,
                        'updated_at'    => $progress->updated_at->toISOString(),
                    ];
                    break;

                default:
                    continue 2; // Skip this iteration if type is not recognized
            }

            $activities[] = $activityData;
        }

        return $activities;
    }

    /**
     * Get recommendations for a language.
     */
    private function getRecommendations(Language $language, int $userId): array
    {
        // Get learning path IDs for this language
        $learningPathIds = $language->learningPaths()
            ->where('status', 'published')
            ->pluck('id');

        // Get unit IDs for these learning paths
        $unitIds = DB::table('units')
            ->whereIn('learning_path_id', $learningPathIds)
            ->pluck('id');

        // Get lesson IDs for these units
        $lessonIds = DB::table('lessons')
            ->whereIn('unit_id', $unitIds)
            ->pluck('id');

        // Get quiz IDs for these units
        $quizIds = DB::table('quizzes')
            ->whereIn('unit_id', $unitIds)
            ->pluck('id');

        // Get completed lesson IDs
        $completedLessonIds = UserProgress::where('user_id', $userId)
            ->where('progressable_type', 'App\\Models\\Lesson')
            ->where('status', 'completed')
            ->whereIn('progressable_id', $lessonIds)
            ->pluck('progressable_id');

        // Get completed quiz IDs
        $completedQuizIds = UserProgress::where('user_id', $userId)
            ->where('progressable_type', 'App\\Models\\Quiz')
            ->where('status', 'completed')
            ->whereIn('progressable_id', $quizIds)
            ->pluck('progressable_id');

        // Get in-progress learning path IDs
        $inProgressPathIds = UserProgress::where('user_id', $userId)
            ->where('progressable_type', 'App\\Models\\LearningPath')
            ->where('status', 'in_progress')
            ->whereIn('progressable_id', $learningPathIds)
            ->pluck('progressable_id');

        $recommendations = [];

        // Recommend next lessons in in-progress learning paths
        if ($inProgressPathIds->isNotEmpty()) {
            // Get next lessons that haven't been completed
            $nextLessons = Lesson::whereIn('unit_id', function ($query) use ($inProgressPathIds) {
                $query->select('id')
                    ->from('units')
                    ->whereIn('learning_path_id', $inProgressPathIds);
            })
                ->whereNotIn('id', $completedLessonIds)
                ->with('unit.learningPath')
                ->orderBy('order')
                ->limit(3)
                ->get()
                ->map(function ($lesson) {
                    return [
                        'id'                    => $lesson->id,
                        'title'                 => $lesson->title,
                        'type'                  => 'lesson',
                        'learning_path'         => $lesson->unit->learningPath->title,
                        'recommendation_reason' => 'Continue your learning path',
                    ];
                })
                ->toArray();

            $recommendations = array_merge($recommendations, $nextLessons);
        }

        // Recommend quizzes that are ready to be taken
        $readyQuizzes = Quiz::whereIn('unit_id', function ($query) use ($completedLessonIds) {
            $query->select('unit_id')
                ->from('lessons')
                ->whereIn('id', $completedLessonIds)
                ->groupBy('unit_id');
        })
            ->whereNotIn('id', $completedQuizIds)
            ->with('unit.learningPath')
            ->limit(2)
            ->get()
            ->map(function ($quiz) {
                return [
                    'id'                    => $quiz->id,
                    'title'                 => $quiz->title,
                    'type'                  => 'quiz',
                    'learning_path'         => $quiz->unit->learningPath->title,
                    'recommendation_reason' => 'Test your knowledge',
                ];
            })
            ->toArray();

        $recommendations = array_merge($recommendations, $readyQuizzes);

        // Recommend new learning paths if user has completed some
        if ($completedLessonIds->isNotEmpty()) {
            $newPaths = LearningPath::where('language_id', $language->id)
                ->where('status', 'published')
                ->whereNotIn('id', $inProgressPathIds)
                ->whereDoesntHave('progress', function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                })
                ->limit(2)
                ->get()
                ->map(function ($path) {
                    return [
                        'id'                    => $path->id,
                        'title'                 => $path->title,
                        'type'                  => 'learning_path',
                        'recommendation_reason' => 'New learning path for you',
                    ];
                })
                ->toArray();

            $recommendations = array_merge($recommendations, $newPaths);
        }

        return array_slice($recommendations, 0, 5); // Limit to 5 recommendations
    }

    /**
     * Get vocabulary statistics for a language.
     */
    private function getVocabularyStats(Language $language, int $userId): array
    {
        // Get learning path IDs for this language
        $learningPathIds = $language->learningPaths()
            ->where('status', 'published')
            ->pluck('id');

        // Get unit IDs for these learning paths
        $unitIds = DB::table('units')
            ->whereIn('learning_path_id', $learningPathIds)
            ->pluck('id');

        // Get lesson IDs for these units
        $lessonIds = DB::table('lessons')
            ->whereIn('unit_id', $unitIds)
            ->pluck('id');

        // Get vocabulary item IDs for these lessons
        $vocabularyItems = VocabularyItem::whereIn('lesson_id', $lessonIds)->get();
        $vocabularyIds   = $vocabularyItems->pluck('id');

        // Get progress for these vocabulary items
        $vocabularyProgress = UserProgress::where('user_id', $userId)
            ->whereIn('progressable_id', $vocabularyIds)
            ->where('progressable_type', 'App\\Models\\VocabularyItem')
            ->get();

        $totalVocabulary    = $vocabularyItems->count();
        $learnedVocabulary  = $vocabularyProgress->count();
        $masteredVocabulary = $vocabularyProgress->where('progress', '>=', 90)->count();

        // Calculate average mastery
        $averageMastery = 0;
        if ($learnedVocabulary > 0) {
            $averageMastery = round($vocabularyProgress->sum('progress') / $learnedVocabulary, 2);
        }

        // Get recently learned vocabulary
        $recentVocabulary = VocabularyItem::whereIn('id', function ($query) use ($userId, $vocabularyIds) {
            $query->select('progressable_id')
                ->from('user_progress')
                ->where('user_id', $userId)
                ->where('progressable_type', 'App\\Models\\VocabularyItem')
                ->whereIn('progressable_id', $vocabularyIds)
                ->orderBy('updated_at', 'desc')
                ->limit(5);
        })
            ->with('lesson')
            ->get()
            ->map(function ($item) use ($vocabularyProgress) {
                $progress = $vocabularyProgress->firstWhere('progressable_id', $item->id);
                return [
                    'id'          => $item->id,
                    'word'        => $item->word,
                    'translation' => $item->translation,
                    'example'     => $item->example,
                    'lesson'      => $item->lesson->title,
                    'mastery'     => $progress ? $progress->progress : 0,
                ];
            })
            ->toArray();

        return [
            'total_vocabulary'    => $totalVocabulary,
            'learned_vocabulary'  => $learnedVocabulary,
            'mastered_vocabulary' => $masteredVocabulary,
            'average_mastery'     => $averageMastery,
            'recent_vocabulary'   => $recentVocabulary,
            'to_review'           => $learnedVocabulary - $masteredVocabulary,
        ];
    }
}
