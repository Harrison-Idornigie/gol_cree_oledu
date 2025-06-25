<?php

namespace App\Services\Tenants\Language;

use App\Models\Tenants\VocabularyItem;
use App\Models\Tenants\User;
use App\Models\Tenants\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * Vocabulary Service
 * 
 * Handles CRUD operations and business logic for vocabulary items including:
 * - Vocabulary item creation, updates, and deletion
 * - Filtering and search functionality
 * - Audio processing and media management
 * - Bulk operations and imports
 */
class VocabularyService
{
    /**
     * Get a single vocabulary item with relationships.
     */
    public function getVocabularyItem(int $vocabularyId, string $membership = 'student', array $with = []): ?VocabularyItem
    {
        $query = VocabularyItem::query();

        // Apply membership-based filtering
        if (!in_array($membership, ['super-admin', 'tenant-admin', 'team'])) {
            // Students can only see published content
            $query->whereHas('lesson', function ($q) {
                $q->where('status', 'published');
            });
        }

        // Default relationships
        $defaultWith = ['lesson', 'lesson.unit', 'lesson.unit.learningPath'];
        $with = array_merge($defaultWith, $with);

        return $query->with($with)->find($vocabularyId);
    }

    /**
     * Get filtered vocabulary items with membership-based access.
     */
    public function getFilteredVocabularyItems(Request $request, string $membership = 'student'): LengthAwarePaginator
    {
        $query = VocabularyItem::query();

        // Apply membership-based filtering
        if (!in_array($membership, ['super-admin', 'tenant-admin', 'team'])) {
            // Students can only see published content
            $query->whereHas('lesson', function ($q) {
                $q->where('status', 'published');
            });
        }

        // Apply filters
        if ($request->has('lesson_id')) {
            $query->where('lesson_id', $request->lesson_id);
        }

        if ($request->has('part_of_speech')) {
            $query->where('part_of_speech', $request->part_of_speech);
        }

        if ($request->has('difficulty_level')) {
            $query->where('difficulty_level', $request->difficulty_level);
        }

        if ($request->has('language_id')) {
            $query->whereHas('lesson.unit.learningPath', function ($q) use ($request) {
                $q->where('language_id', $request->language_id);
            });
        }

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('word', 'like', "%{$search}%")
                    ->orWhere('translation', 'like', "%{$search}%")
                    ->orWhere('example', 'like', "%{$search}%");
            });
        }

        // Include relationships if requested
        if ($request->has('with_lesson')) {
            $query->with('lesson');
        }

        if ($request->has('with_media')) {
            $query->with('media');
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'word');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        return $query->paginate($perPage);
    }

    /**
     * Create a new vocabulary item.
     */
    public function createVocabularyItem(array $data, User $user): VocabularyItem
    {
        return DB::transaction(function () use ($data, $user) {
            $vocabularyItem = VocabularyItem::create($data);

            // Log the creation
            AuditLog::log(
                'create',
                'vocabulary',
                $vocabularyItem,
                [],
                $data
            );

            return $vocabularyItem->load('lesson');
        });
    }

    /**
     * Update an existing vocabulary item.
     */
    public function updateVocabularyItem(VocabularyItem $vocabularyItem, array $data, User $user): VocabularyItem
    {
        return DB::transaction(function () use ($vocabularyItem, $data, $user) {
            $originalData = $vocabularyItem->toArray();
            $vocabularyItem->update($data);

            // Log the update
            AuditLog::log(
                'update',
                'vocabulary',
                $vocabularyItem,
                $originalData,
                $data
            );

            return $vocabularyItem->load('lesson');
        });
    }

    /**
     * Delete a vocabulary item.
     */
    public function deleteVocabularyItem(VocabularyItem $vocabularyItem, User $user): bool
    {
        return DB::transaction(function () use ($vocabularyItem, $user) {
            // Store data before deletion for audit
            $vocabularyData = $vocabularyItem->toArray();

            // Delete associated media
            $vocabularyItem->clearMediaCollection();

            // Delete the vocabulary item
            $deleted = $vocabularyItem->delete();

            if ($deleted) {
                // Log the deletion
                AuditLog::log(
                    'delete',
                    'vocabulary',
                    null,
                    $vocabularyData,
                    []
                );
            }

            return $deleted;
        });
    }

    /**
     * Get vocabulary items by lesson.
     */
    public function getVocabularyByLesson(int $lessonId, string $membership = 'student'): \Illuminate\Database\Eloquent\Collection
    {
        $query = VocabularyItem::where('lesson_id', $lessonId);

        // Apply membership-based filtering
        if (!in_array($membership, ['super-admin', 'tenant-admin', 'team'])) {
            $query->whereHas('lesson', function ($q) {
                $q->where('status', 'published');
            });
        }

        return $query->orderBy('word')->get();
    }

    /**
     * Bulk create vocabulary items.
     */
    public function bulkCreateVocabularyItems(array $vocabularyData, User $user): array
    {
        $createdItems = [];
        $errors = [];

        DB::transaction(function () use ($vocabularyData, $user, &$createdItems, &$errors) {
            foreach ($vocabularyData as $index => $data) {
                try {
                    $vocabularyItem = $this->createVocabularyItem($data, $user);
                    $createdItems[] = $vocabularyItem;
                } catch (Exception $e) {
                    $errors[$index] = $e->getMessage();
                }
            }
        });

        return [
            'created' => $createdItems,
            'errors' => $errors,
            'total_processed' => count($vocabularyData),
            'successful' => count($createdItems),
            'failed' => count($errors)
        ];
    }

    /**
     * Get vocabulary statistics.
     */
    public function getVocabularyStatistics(array $filters = []): array
    {
        $query = VocabularyItem::query();

        // Apply filters
        if (!empty($filters['lesson_id'])) {
            $query->where('lesson_id', $filters['lesson_id']);
        }

        if (!empty($filters['language_id'])) {
            $query->whereHas('lesson.unit.learningPath', function ($q) use ($filters) {
                $q->where('language_id', $filters['language_id']);
            });
        }

        $total = $query->count();
        $byPartOfSpeech = $query->groupBy('part_of_speech')->selectRaw('part_of_speech, count(*) as count')->pluck('count', 'part_of_speech');
        $byDifficulty = $query->groupBy('difficulty_level')->selectRaw('difficulty_level, count(*) as count')->pluck('count', 'difficulty_level');

        return [
            'total_items' => $total,
            'by_part_of_speech' => $byPartOfSpeech,
            'by_difficulty_level' => $byDifficulty,
        ];
    }

    /**
     * Get vocabulary items for review based on spaced repetition.
     */
    public function getVocabularyForReview(User $user, array $filters = []): Collection
    {
        $query = VocabularyItem::where('status', 'published');

        // Apply language filter if provided
        if (isset($filters['language_id'])) {
            $query->where('language_id', $filters['language_id']);
        }

        // This would integrate with actual user progress tracking
        // For now, return a basic selection
        return $query->with(['language', 'lesson', 'unit'])
            ->inRandomOrder()
            ->limit($filters['limit'] ?? 20)
            ->get()
            ->map(function ($item) use ($user) {
                $itemArray = $item->toArray();
                $itemArray['user_progress'] = $this->getUserVocabularyProgress($item, $user);
                return (object) $itemArray;
            });
    }

    /**
     * Get vocabulary items that user has answered incorrectly.
     */
    public function getMistakeVocabulary(User $user, array $filters = []): Collection
    {
        // This would integrate with actual mistake tracking
        $query = VocabularyItem::where('status', 'published');

        // Apply language filter if provided
        if (isset($filters['language_id'])) {
            $query->where('language_id', $filters['language_id']);
        }

        // For now, return a placeholder collection
        return $query->with(['language', 'lesson', 'unit'])
            ->limit($filters['limit'] ?? 10)
            ->get()
            ->map(function ($item) use ($user) {
                $itemArray = $item->toArray();
                $itemArray['mistake_count'] = 0;
                $itemArray['last_mistake_date'] = null;
                $itemArray['common_errors'] = [];
                return (object) $itemArray;
            });
    }

    /**
     * Get vocabulary items for a specific unit.
     */
    public function getVocabularyByUnit(int $unitId, User $user): Collection
    {
        return VocabularyItem::where('unit_id', $unitId)
            ->where('status', 'published')
            ->with(['language', 'lesson'])
            ->orderBy('order')
            ->get()
            ->map(function ($item) use ($user) {
                $itemArray = $item->toArray();
                $itemArray['user_progress'] = $this->getUserVocabularyProgress($item, $user);
                return (object) $itemArray;
            });
    }

    /**
     * Check user's translation answer for a vocabulary item.
     */
    public function checkTranslation(VocabularyItem $vocabularyItem, string $userAnswer, User $user): array
    {
        $acceptedAnswers = $this->getAcceptedAnswers($vocabularyItem);
        $isCorrect = $this->isAnswerCorrect($userAnswer, $acceptedAnswers);

        // Record the attempt (this would be saved to database)
        $this->recordVocabularyAttempt($vocabularyItem, $userAnswer, $isCorrect, $user);

        return [
            'is_correct' => $isCorrect,
            'user_answer' => $userAnswer,
            'accepted_answers' => $acceptedAnswers,
            'feedback' => $this->generateFeedback($vocabularyItem, $userAnswer, $isCorrect),
            'progress_update' => $this->updateUserProgress($vocabularyItem, $isCorrect, $user)
        ];
    }

    /**
     * Get vocabulary learning statistics for a user.
     */
    public function getUserVocabularyStatistics(User $user, array $filters = []): array
    {
        // This would integrate with actual progress tracking
        return [
            'total_vocabulary_learned' => 0,
            'mastery_levels' => [
                'beginner' => 0,
                'intermediate' => 0,
                'advanced' => 0,
                'mastered' => 0
            ],
            'accuracy_percentage' => 0,
            'current_streak' => 0,
            'longest_streak' => 0,
            'total_practice_time_minutes' => 0,
            'last_practice_date' => null,
            'favorite_vocabulary_types' => [],
            'challenging_areas' => [],
            'recent_achievements' => []
        ];
    }

    /**
     * Get user's progress for a specific vocabulary item.
     */
    private function getUserVocabularyProgress(VocabularyItem $vocabularyItem, User $user): array
    {
        return [
            'mastery_level' => 'learning',
            'correct_attempts' => 0,
            'total_attempts' => 0,
            'last_practiced' => null,
            'next_review_date' => now()->addDays(1),
            'difficulty_rating' => 1,
            'retention_score' => 0
        ];
    }

    /**
     * Get accepted answers for a vocabulary item.
     */
    private function getAcceptedAnswers(VocabularyItem $vocabularyItem): array
    {
        $answers = [$vocabularyItem->target_word];

        // Add alternative translations if they exist
        if ($vocabularyItem->alternative_translations) {
            $alternatives = is_array($vocabularyItem->alternative_translations)
                ? $vocabularyItem->alternative_translations
                : json_decode($vocabularyItem->alternative_translations, true) ?? [];
            $answers = array_merge($answers, $alternatives);
        }

        return array_map('strtolower', array_map('trim', $answers));
    }

    /**
     * Check if user's answer is correct.
     */
    private function isAnswerCorrect(string $userAnswer, array $acceptedAnswers): bool
    {
        $normalizedAnswer = strtolower(trim($userAnswer));
        return in_array($normalizedAnswer, $acceptedAnswers);
    }

    /**
     * Record vocabulary practice attempt.
     */
    private function recordVocabularyAttempt(VocabularyItem $vocabularyItem, string $userAnswer, bool $isCorrect, User $user): void
    {
        // This would save to a VocabularyAttempt model
        // For now, we just track it conceptually
    }

    /**
     * Generate feedback for vocabulary practice.
     */
    private function generateFeedback(VocabularyItem $vocabularyItem, string $userAnswer, bool $isCorrect): string
    {
        if ($isCorrect) {
            return 'Correct! Well done.';
        }

        return "Not quite right. The correct answer is: {$vocabularyItem->target_word}";
    }

    /**
     * Update user's progress for vocabulary item.
     */
    private function updateUserProgress(VocabularyItem $vocabularyItem, bool $isCorrect, User $user): array
    {
        // This would update actual progress tracking
        return [
            'points_earned' => $isCorrect ? 10 : 0,
            'streak_updated' => $isCorrect,
            'mastery_level_changed' => false,
            'next_review_date' => now()->addDays($isCorrect ? 2 : 1)
        ];
    }
}
