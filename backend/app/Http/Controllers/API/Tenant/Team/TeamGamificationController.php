<?php

namespace App\Http\Controllers\API\Tenant\Team;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\Tenants\Gamification\GamificationService;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

/**
 * Team Gamification Controller
 * 
 * Handles limited gamification features for team members.
 * Access Level: Team (Teams/Content Creators)
 * Scope: Tenant-specific
 * 
 * This controller provides read-only access to gamification features
 * and statistics for team members within their tenant scope.
 */
class TeamGamificationController extends BaseAPIController
{
    use BelongsToTenant;

    protected GamificationService $gamificationService;

    /**
     * Constructor - Apply team middleware
     */
    public function __construct(GamificationService $gamificationService)
    {
        $this->gamificationService = $gamificationService;

        // Team members can view gamification data (read-only)
        $this->middleware(function ($request, $next) {
            $this->authorize('viewAny', 'App\Models\Tenants\Achievement');
            return $next($request);
        });
    }

    /**
     * Get available achievements.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getAchievements(Request $request): JsonResponse
    {
        try {
            $filters = [
                'type' => $request->get('type'),
                'category' => $request->get('category'),
                'status' => $request->get('status', 'active'),
                'search' => $request->get('search'),
                'sort_by' => $request->get('sort_by', 'created_at'),
                'sort_order' => $request->get('sort_order', 'desc'),
            ];

            $perPage = min($request->get('per_page', 15), 50);
            $achievements = $this->gamificationService->getAchievements($filters, $perPage);

            $summary = [
                'total_achievements' => $achievements->total(),
                'active_achievements' => $achievements->where('status', 'active')->count(),
                'total_earned' => $achievements->sum('earned_count'),
                'most_popular' => $achievements->sortByDesc('earn_rate')->first()?->name ?? 'None',
            ];

            return $this->sendResponse([
                'achievements' => $achievements->items(),
                'pagination' => [
                    'current_page' => $achievements->currentPage(),
                    'last_page' => $achievements->lastPage(),
                    'per_page' => $achievements->perPage(),
                    'total' => $achievements->total(),
                ],
                'summary' => $summary,
            ], 'Achievements retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendError('Failed to retrieve achievements.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get gamification statistics.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            $filters = [
                'date_from' => $request->get('date_from'),
                'date_to' => $request->get('date_to'),
                'learning_path_id' => $request->get('learning_path_id'),
            ];

            $statistics = $this->gamificationService->getStatistics($filters);

            return $this->sendResponse($statistics, 'Gamification statistics retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendError('Failed to retrieve gamification statistics.', ['error' => $e->getMessage()]);
        }
    }
}
