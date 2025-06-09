<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\GuideBookEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GuideController extends BaseAPIController
{
    /**
     * Get guide entries for a unit
     */
    public function index(Request $request, int $unitId): JsonResponse
    {
        $entries = GuideBookEntry::where('unit_id', $unitId)
            ->orderBy('order')
            ->with(['media'])
            ->when($request->has('topic'), function($query) use ($request) {
                $query->where('topic', 'like', "%{$request->topic}%");
            })
            ->when($request->has('difficulty_level'), function($query) use ($request) {
                $query->where('difficulty_level', $request->difficulty_level);
            })
            ->paginate($request->input('per_page', 10));

        return $this->sendPaginatedResponse($entries);
    }

    /**
     * Get a specific guide entry with related content
     */
    public function show(GuideBookEntry $entry): JsonResponse
    {
        $entry->load(['media']);
        
        return $this->sendResponse([
            'entry' => $entry->getFullContent(),
            'related_entries' => $entry->getRelatedEntries()
        ]);
    }

    /**
     * Search guide entries
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2',
            'filters' => 'nullable|array',
            'filters.difficulty_level' => 'nullable|integer|min:1|max:5',
            'filters.tags' => 'nullable|array',
            'filters.unit_id' => 'nullable|exists:units,id'
        ]);

        $results = GuideBookEntry::search(
            $request->input('query'),
            $request->input('filters', [])
        );

        return $this->sendResponse($results);
    }

    /**
     * Record guide entry view
     */
    public function recordView(GuideBookEntry $entry): JsonResponse
    {
        // Record that user has viewed this entry
        $progress = $entry->progress()->updateOrCreate(
            [
                'user_id' => auth()->id(),
                'trackable_type' => GuideBookEntry::class,
                'trackable_id' => $entry->id
            ],
            [
                'status' => 'viewed',
                'meta_data' => [
                    'last_viewed_at' => now(),
                    'view_count' => DB::raw('COALESCE(meta_data->>"$.view_count", 0) + 1')
                ]
            ]
        );

        return $this->sendResponse([
            'status' => 'success',
            'view_count' => $progress->meta_data['view_count'] ?? 1
        ]);
    }

    /**
     * Get recommended guides based on user's progress
     */
    public function getRecommendations(): JsonResponse
    {
        $userId = auth()->id();
        
        // Get user's current learning level and topics
        $userLevel = $this->getUserLevel($userId);
        $recentTopics = $this->getRecentTopics($userId);

        // Get relevant guides
        $recommendations = GuideBookEntry::where('difficulty_level', '<=', $userLevel + 1)
            ->whereNotIn('id', function($query) use ($userId) {
                $query->select('trackable_id')
                    ->from('user_progress')
                    ->where('user_id', $userId)
                    ->where('trackable_type', GuideBookEntry::class)
                    ->where('status', 'viewed');
            })
            ->when($recentTopics, function($query) use ($recentTopics) {
                $query->whereJsonContains('tags', $recentTopics);
            })
            ->orderBy('difficulty_level')
            ->limit(5)
            ->get();

        return $this->sendResponse($recommendations);
    }

    /**
     * Get user's current learning level
     */
    private function getUserLevel(int $userId): int
    {
        $avgLevel = GuideBookEntry::whereHas('progress', function($query) use ($userId) {
            $query->where('user_id', $userId)
                ->where('status', 'viewed');
        })->avg('difficulty_level');

        return (int) ($avgLevel ?: 1);
    }

    /**
     * Get user's recent learning topics
     */
    private function getRecentTopics(int $userId): array
    {
        return GuideBookEntry::whereHas('progress', function($query) use ($userId) {
            $query->where('user_id', $userId)
                ->where('status', 'viewed')
                ->where('created_at', '>=', now()->subDays(7));
        })
        ->pluck('tags')
        ->flatten()
        ->unique()
        ->take(5)
        ->values()
        ->toArray();
    }
}