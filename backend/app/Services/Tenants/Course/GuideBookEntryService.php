<?php

namespace App\Services\Tenants\Course;

use App\Models\Tenants\GuideBookEntry;
use App\Models\Tenants\User;
use App\Models\Tenants\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * Guide Book Entry Service
 * 
 * Handles CRUD operations and business logic for guide book entries including:
 * - Guide entry creation, updates, and deletion
 * - Content management and versioning
 * - Filtering and search functionality
 * - Category and topic organization
 */
class GuideBookEntryService
{
    /**
     * Get a single guide book entry with relationships.
     */
    public function getGuideBookEntry(int $entryId, string $membership = 'student', array $with = []): ?GuideBookEntry
    {
        $query = GuideBookEntry::query();

        // Apply membership-based filtering
        if (!in_array($membership, ['super-admin', 'tenant-admin', 'team'])) {
            // Students can only see published content
            $query->whereHas('unit.learningPath', function ($q) {
                $q->where('status', 'published');
            });
        }

        // Default relationships
        $defaultWith = ['unit', 'unit.learningPath', 'unit.topics'];
        $with = array_merge($defaultWith, $with);

        return $query->with($with)->find($entryId);
    }

    /**
     * Get filtered guide book entries with membership-based access.
     */
    public function getFilteredGuideBookEntries(Request $request, string $membership = 'student'): LengthAwarePaginator
    {
        $query = GuideBookEntry::query();

        // Apply membership-based filtering
        if (!in_array($membership, ['super-admin', 'tenant-admin', 'team'])) {
            // Students can only see published content
            $query->whereHas('unit.learningPath', function ($q) {
                $q->where('status', 'published');
            });
        }

        // Apply filters
        if ($request->has('unit_id')) {
            $query->where('unit_id', $request->unit_id);
        }

        if ($request->has('difficulty_level')) {
            $query->where('difficulty_level', $request->difficulty_level);
        }

        if ($request->has('language_id')) {
            $query->whereHas('unit.learningPath', function ($q) use ($request) {
                $q->where('language_id', $request->language_id);
            });
        }

        if ($request->has('tags')) {
            $tags = is_array($request->tags) ? $request->tags : explode(',', $request->tags);
            $query->where(function ($q) use ($tags) {
                foreach ($tags as $tag) {
                    $q->orWhereJsonContains('tags', trim($tag));
                }
            });
        }

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('topic', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        // Include relationships if requested
        if ($request->has('with_unit')) {
            $query->with('unit');
        }

        if ($request->has('with_media')) {
            $query->with('media');
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'order');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        return $query->paginate($perPage);
    }

    /**
     * Create a new guide book entry.
     */
    public function createGuideBookEntry(array $data, User $user): GuideBookEntry
    {
        return DB::transaction(function () use ($data, $user) {
            // Set default order if not provided
            if (!isset($data['order'])) {
                $maxOrder = GuideBookEntry::where('unit_id', $data['unit_id'])->max('order') ?? 0;
                $data['order'] = $maxOrder + 1;
            }

            $guideEntry = GuideBookEntry::create($data);

            // Log the creation
            AuditLog::log(
                'create',
                'guide_book',
                $guideEntry,
                [],
                $data
            );

            return $guideEntry->load('unit');
        });
    }

    /**
     * Update an existing guide book entry.
     */
    public function updateGuideBookEntry(GuideBookEntry $guideEntry, array $data, User $user): GuideBookEntry
    {
        return DB::transaction(function () use ($guideEntry, $data, $user) {
            $originalData = $guideEntry->toArray();
            $guideEntry->update($data);

            // Log the update
            AuditLog::log(
                'update',
                'guide_book',
                $guideEntry,
                $originalData,
                $data
            );

            return $guideEntry->load('unit');
        });
    }

    /**
     * Delete a guide book entry.
     */
    public function deleteGuideBookEntry(GuideBookEntry $guideEntry, User $user): bool
    {
        return DB::transaction(function () use ($guideEntry, $user) {
            // Store data before deletion for audit
            $entryData = $guideEntry->toArray();

            // Delete associated media
            $guideEntry->clearMediaCollection();

            // Delete the guide entry
            $deleted = $guideEntry->delete();

            if ($deleted) {
                // Reorder remaining entries
                $this->reorderEntriesAfterDeletion($guideEntry->unit_id, $guideEntry->order);

                // Log the deletion
                AuditLog::log(
                    'delete',
                    'guide_book',
                    null,
                    $entryData,
                    []
                );
            }

            return $deleted;
        });
    }

    /**
     * Get guide book entries by unit.
     */
    public function getGuideBookEntriesByUnit(int $unitId, string $membership = 'student'): \Illuminate\Database\Eloquent\Collection
    {
        $query = GuideBookEntry::where('unit_id', $unitId);

        // Apply membership-based filtering
        if (!in_array($membership, ['super-admin', 'tenant-admin', 'team'])) {
            $query->whereHas('unit.learningPath', function ($q) {
                $q->where('status', 'published');
            });
        }

        return $query->orderBy('order')->get();
    }

    /**
     * Reorder guide book entries within a unit.
     */
    public function reorderGuideBookEntries(int $unitId, array $entryOrders, User $user): bool
    {
        return DB::transaction(function () use ($unitId, $entryOrders, $user) {
            foreach ($entryOrders as $orderData) {
                GuideBookEntry::where('id', $orderData['id'])
                    ->where('unit_id', $unitId)
                    ->update(['order' => $orderData['order']]);
            }

            // Log the reordering
            AuditLog::log(
                'reorder',
                'guide_book',
                null,
                [],
                ['unit_id' => $unitId, 'entry_orders' => $entryOrders]
            );

            return true;
        });
    }

    /**
     * Reorder entries after deletion.
     */
    protected function reorderEntriesAfterDeletion(int $unitId, int $deletedOrder): void
    {
        GuideBookEntry::where('unit_id', $unitId)
            ->where('order', '>', $deletedOrder)
            ->decrement('order');
    }

    /**
     * Get guide book entry statistics.
     */
    public function getGuideBookStatistics(array $filters = []): array
    {
        $query = GuideBookEntry::query();

        // Apply filters
        if (!empty($filters['unit_id'])) {
            $query->where('unit_id', $filters['unit_id']);
        }

        if (!empty($filters['language_id'])) {
            $query->whereHas('unit.learningPath', function ($q) use ($filters) {
                $q->where('language_id', $filters['language_id']);
            });
        }

        $total = $query->count();
        $byDifficulty = $query->groupBy('difficulty_level')->selectRaw('difficulty_level, count(*) as count')->pluck('count', 'difficulty_level');

        return [
            'total_entries' => $total,
            'by_difficulty_level' => $byDifficulty,
        ];
    }

    /**
     * Bulk create guide book entries.
     */
    public function bulkCreateGuideBookEntries(array $entriesData, User $user): array
    {
        $createdEntries = [];
        $errors = [];

        DB::transaction(function () use ($entriesData, $user, &$createdEntries, &$errors) {
            foreach ($entriesData as $index => $data) {
                try {
                    $entry = $this->createGuideBookEntry($data, $user);
                    $createdEntries[] = $entry;
                } catch (Exception $e) {
                    $errors[$index] = $e->getMessage();
                }
            }
        });

        return [
            'created' => $createdEntries,
            'errors' => $errors,
            'total_processed' => count($entriesData),
            'successful' => count($createdEntries),
            'failed' => count($errors)
        ];
    }
}
