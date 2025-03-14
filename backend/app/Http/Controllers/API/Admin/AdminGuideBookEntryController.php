<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\GuideBookEntry;
use App\Http\Requests\API\GuideBookEntry\StoreGuideBookEntryRequest;
use App\Http\Requests\API\GuideBookEntry\UpdateGuideBookEntryRequest;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminGuideBookEntryController extends BaseAPIController
{
    /**
     * Display a listing of all guide book entries.
     */
    public function index(Request $request): JsonResponse
    {
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
    }

    /**
     * Store a newly created guide book entry.
     */
    public function store(StoreGuideBookEntryRequest $request): JsonResponse
    {
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
    }

    /**
     * Display the specified guide book entry.
     */
    public function show(Request $request, GuideBookEntry $guideBookEntry): JsonResponse
    {
        // Load relationships if requested
        if ($request->has('with_related')) {
            $guideBookEntry->load('relatedEntries');
        }

        if ($request->has('with_versions')) {
            // Load version history if requested
            $guideBookEntry->load('versions');
        }

        return $this->sendResponse($guideBookEntry);
    }

    /**
     * Update the specified guide book entry.
     */
    public function update(UpdateGuideBookEntryRequest $request, GuideBookEntry $guideBookEntry): JsonResponse
    {
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
    }

    /**
     * Remove the specified guide book entry.
     */
    public function destroy(Request $request, GuideBookEntry $guideBookEntry): JsonResponse
    {
        // Prevent deletion of published guide book entries
        if ($guideBookEntry->status === 'published') {
            return $this->sendError('Cannot delete a published guide book entry. Archive it first.', ['status' => 422]);
        }

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
    }

    /**
     * Update the status of a guide book entry.
     */
    public function updateStatus(Request $request, GuideBookEntry $guideBookEntry): JsonResponse
    {
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
    }

    /**
     * Get categories for guide book entries.
     */
    public function getCategories(): JsonResponse
    {
        $categories = GuideBookEntry::select('category')
            ->distinct()
            ->whereNotNull('category')
            ->pluck('category');

        return $this->sendResponse($categories);
    }
}
