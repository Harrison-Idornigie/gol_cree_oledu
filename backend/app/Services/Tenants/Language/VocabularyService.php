<?php

namespace App\Services\Tenants\Language;

use App\Models\Tenants\VocabularyItem;
use App\Models\Tenants\User;
use App\Models\Tenants\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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
}
