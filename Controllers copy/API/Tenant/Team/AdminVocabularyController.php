<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\AuditLog;
use App\Models\VocabularyItem;
use App\Http\Requests\API\Vocabulary\StoreVocabularyRequest;
use App\Http\Requests\API\Vocabulary\UpdateVocabularyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class AdminVocabularyController extends BaseAPIController
{
    /**
     * Display a listing of all vocabulary items.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = VocabularyItem::query();

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('level')) {
                $query->where('level', $request->level);
            }

            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($query) use ($search) {
                    $query->where('word', 'like', "%{$search}%")
                        ->orWhere('definition', 'like', "%{$search}%")
                        ->orWhere('example', 'like', "%{$search}%");
                });
            }

            // Include relationships if requested
            if ($request->has('with_lessons')) {
                $query->with('lessons');
            }

            $perPage = $request->input('per_page', 15);
            $vocabulary = $query->paginate($perPage);

            return $this->sendPaginatedResponse($vocabulary);
        } catch (Exception $e) {
            Log::error('Failed to fetch vocabulary items: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to fetch vocabulary items', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created vocabulary item.
     */
    public function store(StoreVocabularyRequest $request): JsonResponse
    {
        try {
            $vocabulary = VocabularyItem::create($request->validated());

            // Log the creation for audit trail
            AuditLog::log(
                'create',
                'vocabulary',
                $vocabulary,
                [],
                $request->validated()
            );

            return $this->sendCreatedResponse($vocabulary, 'Vocabulary item created successfully.');
        } catch (Exception $e) {
            Log::error('Failed to create vocabulary item: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $request->validated()
            ]);
            return $this->sendError('Failed to create vocabulary item', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified vocabulary item.
     */
    public function show(Request $request, VocabularyItem $vocabulary): JsonResponse
    {
        try {
            // Load relationships if requested
            if ($request->has('with_lessons')) {
                $vocabulary->load('lessons');
            }

            if ($request->has('with_versions')) {
                // Load version history if requested
                $vocabulary->load('versions');
            }

            return $this->sendResponse($vocabulary);
        } catch (Exception $e) {
            Log::error('Failed to fetch vocabulary item: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'vocabulary_id' => $vocabulary->id
            ]);
            return $this->sendError('Failed to fetch vocabulary item', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified vocabulary item.
     */
    public function update(UpdateVocabularyRequest $request, VocabularyItem $vocabulary): JsonResponse
    {
        try {
            $oldData = $vocabulary->toArray();
            $vocabulary->update($request->validated());

            // Log the update for audit trail
            AuditLog::log(
                'update',
                'vocabulary',
                $vocabulary,
                $oldData,
                $request->validated()
            );

            return $this->sendResponse($vocabulary, 'Vocabulary item updated successfully.');
        } catch (Exception $e) {
            Log::error('Failed to update vocabulary item: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'vocabulary_id' => $vocabulary->id,
                'data' => $request->validated()
            ]);
            return $this->sendError('Failed to update vocabulary item', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified vocabulary item.
     */
    public function destroy(Request $request, VocabularyItem $vocabulary): JsonResponse
    {
        try {
            // Prevent deletion of published vocabulary items
            if ($vocabulary->status === 'published') {
                return $this->sendError('Cannot delete a published vocabulary item. Archive it first.',['error' => 'Cannot delete a published vocabulary item. Archive it first.'], 422);
            }

            $data = $vocabulary->toArray();
            $vocabulary->delete();

            // Log the deletion for audit trail
            AuditLog::log(
                'delete',
                'vocabulary',
                $vocabulary,
                $data,
                []
            );

            return $this->sendNoContentResponse();
        } catch (Exception $e) {
            Log::error('Failed to delete vocabulary item: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'vocabulary_id' => $vocabulary->id
            ]);
            return $this->sendError('Failed to delete vocabulary item', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update the status of a vocabulary item.
     */
    public function updateStatus(Request $request, VocabularyItem $vocabulary): JsonResponse
    {
        try {
            $request->validate([
                'status' => ['required', 'string', 'in:draft,published,archived']
            ]);

            $oldStatus = $vocabulary->status;
            $vocabulary->status = $request->status;
            $vocabulary->save();

            // Log the status change for audit trail
            AuditLog::log(
                'status_updated',
                'vocabulary',
                $vocabulary,
                [],
                [
                    'old_status' => $oldStatus,
                    'new_status' => $request->status
                ]);
            return $this->sendResponse($vocabulary, 'Vocabulary item status updated successfully.');
        } catch (Exception $e) {
            Log::error('Failed to update vocabulary item status: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'vocabulary_id' => $vocabulary->id,
                'status' => $request->status ?? null
            ]);
            return $this->sendError('Failed to update vocabulary item status', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Bulk import vocabulary items.
     */
    public function bulkImport(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'items' => ['required', 'array'],
                'items.*.word' => ['required', 'string', 'max:255'],
                'items.*.definition' => ['required', 'string'],
                'items.*.example' => ['nullable', 'string'],
                'items.*.level' => ['nullable', 'string', 'in:beginner,intermediate,advanced'],
                'items.*.category' => ['nullable', 'string', 'max:255'],
                'items.*.status' => ['nullable', 'string', 'in:draft,published,archived'],
            ]);

            $items = $request->items;
            $importedItems = [];

            foreach ($items as $item) {
                $vocabulary = VocabularyItem::create($item);
                $importedItems[] = $vocabulary;

                // Log the creation for audit trail
                AuditLog::log(
                    'bulk_imported',
                    'vocabulary',
                    $vocabulary,
                    [],
                    ['data' => $item]
                );
            }

            return $this->sendResponse($importedItems, count($importedItems) . ' vocabulary items imported successfully.');
        } catch (Exception $e) {
            Log::error('Failed to bulk import vocabulary items: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'items_count' => count($request->items ?? [])
            ]);
            return $this->sendError('Failed to bulk import vocabulary items', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Export vocabulary items.
     */
    public function export(Request $request): JsonResponse
    {
        try {
            $query = VocabularyItem::query();

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('level')) {
                $query->where('level', $request->level);
            }

            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            $vocabulary = $query->get(['id', 'word', 'definition', 'example', 'level', 'category', 'status']);

            return $this->sendResponse($vocabulary, 'Vocabulary items exported successfully.');
        } catch (Exception $e) {
            Log::error('Failed to export vocabulary items: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'filters' => $request->only(['status', 'level', 'category'])
            ]);
            return $this->sendError('Failed to export vocabulary items', ['error' => $e->getMessage()], 500);
        }
    }
}