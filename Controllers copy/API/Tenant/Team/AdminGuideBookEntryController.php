<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\GuideBookEntry;
use App\Http\Requests\API\GuideBookEntry\StoreGuideBookEntryRequest;
use App\Http\Requests\API\GuideBookEntry\UpdateGuideBookEntryRequest;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AdminGuideBookEntryController extends BaseAPIController
{
    /**
     * Display a listing of all guide book entries.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = GuideBookEntry::query();

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            if ($request->has('level')) {
                $query->where('level', $request->level);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            }

            // Include relationships if requested
            if ($request->has('with_related')) {
                $query->with('relatedEntries');
            }

            $perPage = $request->input('per_page', 15);
            $guideBookEntries = $query->paginate($perPage);

            return $this->sendPaginatedResponse($guideBookEntries);
        } catch (Exception $e) {
            Log::error('Error retrieving guide book entries: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to retrieve guide book entries', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created guide book entry.
     */
    public function store(StoreGuideBookEntryRequest $request): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request) {
                $guideBookEntry = GuideBookEntry::create($request->validated());

                // Handle related entries if provided
                if ($request->has('related_entry_ids')) {
                    $guideBookEntry->relatedEntries()->sync($request->related_entry_ids);
                }

                // Log the creation for audit trail
                AuditLog::log(
                    'create',
                    'guide_book_entries',
                    $guideBookEntry,
                    [],
                    $request->validated()
                );

                return $this->sendCreatedResponse($guideBookEntry, 'Guide book entry created successfully.');
            });
        } catch (Exception $e) {
            Log::error('Error creating guide book entry: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $request->validated()
            ]);
            return $this->sendError('Failed to create guide book entry', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified guide book entry.
     */
    public function show(Request $request, GuideBookEntry $guideBookEntry): JsonResponse
    {
        try {
            // Load relationships if requested
            if ($request->has('with_related')) {
                $guideBookEntry->load('relatedEntries');
            }

            if ($request->has('with_versions')) {
                // Load version history if requested
                $guideBookEntry->load('versions');
            }

            return $this->sendResponse($guideBookEntry);
        } catch (Exception $e) {
            Log::error('Error retrieving guide book entry: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'entry_id' => $guideBookEntry->id
            ]);
            return $this->sendError('Failed to retrieve guide book entry', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified guide book entry.
     */
    public function update(UpdateGuideBookEntryRequest $request, GuideBookEntry $guideBookEntry): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request, $guideBookEntry) {
                $oldData = $guideBookEntry->toArray();
                $guideBookEntry->update($request->validated());

                // Handle related entries if provided
                if ($request->has('related_entry_ids')) {
                    $guideBookEntry->relatedEntries()->sync($request->related_entry_ids);
                }

                // Log the update for audit trail
                AuditLog::logChange(
                    $guideBookEntry,
                    'update',
                    $oldData,
                    $guideBookEntry->toArray()
                );

                return $this->sendResponse($guideBookEntry, 'Guide book entry updated successfully.');
            });
        } catch (Exception $e) {
            Log::error('Error updating guide book entry: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'entry_id' => $guideBookEntry->id,
                'data' => $request->validated()
            ]);
            return $this->sendError('Failed to update guide book entry', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified guide book entry.
     */
    public function destroy(Request $request, GuideBookEntry $guideBookEntry): JsonResponse
    {
        try {
            // Prevent deletion of published guide book entries
            if ($guideBookEntry->status === 'published') {
                return $this->sendError('Cannot delete a published guide book entry. Archive it first.', ['status' => 422]);
            }

            return DB::transaction(function () use ($guideBookEntry) {
                $data = $guideBookEntry->toArray();
                
                // Detach related entries
                $guideBookEntry->relatedEntries()->detach();
                
                $guideBookEntry->delete();

                // Log the deletion for audit trail
                AuditLog::log(
                    'delete',
                    'guide_book_entries',
                    $guideBookEntry,
                    $data,
                    []
                );

                return $this->sendNoContentResponse();
            });
        } catch (Exception $e) {
            Log::error('Error deleting guide book entry: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'entry_id' => $guideBookEntry->id
            ]);
            return $this->sendError('Failed to delete guide book entry', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update the status of a guide book entry.
     */
    public function updateStatus(Request $request, GuideBookEntry $guideBookEntry): JsonResponse
    {
        try {
            $request->validate([
                'status' => ['required', 'string', 'in:draft,published,archived']
            ]);

            $oldStatus = $guideBookEntry->status;
            $guideBookEntry->status = $request->status;
            $guideBookEntry->save();

            // Log the status change for audit trail
            AuditLog::log(
                'status_update',
                'guide_book_entries',
                $guideBookEntry,
                ['status' => $oldStatus],
                ['status' => $request->status]
            );

            return $this->sendResponse($guideBookEntry, 'Guide book entry status updated successfully.');
        } catch (Exception $e) {
            Log::error('Error updating guide book entry status: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'entry_id' => $guideBookEntry->id,
                'status' => $request->status ?? 'unknown'
            ]);
            return $this->sendError('Failed to update guide book entry status', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get categories for guide book entries.
     */
    public function getCategories(): JsonResponse
    {
        try {
            $categories = GuideBookEntry::select('category')
                ->distinct()
                ->whereNotNull('category')
                ->pluck('category');

            return $this->sendResponse($categories);
        } catch (Exception $e) {
            Log::error('Error retrieving guide book categories: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to retrieve guide book categories', ['error' => $e->getMessage()], 500);
        }
    }
}
