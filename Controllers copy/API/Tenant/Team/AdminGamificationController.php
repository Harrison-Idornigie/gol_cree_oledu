<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\League;
use App\Models\XpRule;
use App\Models\StreakRule;
use App\Models\DailyGoal;
use App\Models\BonusEvent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Http\Requests\Admin\Gamification\{
    CreateAchievementRequest,
    UpdateAchievementRequest,
    CreateLeagueRequest,
    UpdateStreakRulesRequest,
    CreateDailyGoalRequest,
    CreateBonusEventRequest
};

class AdminGamificationController extends Controller
{
    /**
     * Create a new achievement.
     */
    public function createAchievement(CreateAchievementRequest $request): JsonResponse
    {
        try {
            $achievement = DB::transaction(function () use ($request) {
                $achievement = Achievement::create($request->validated());

                if ($request->hasFile('icon')) {
                    $achievement->addMedia($request->file('icon'))
                        ->toMediaCollection('icon');
                }

                return $achievement;
            });

            return response()->json([
                'success' => true,
                'data' => $achievement->getPreviewData()
            ], 201);
        } catch (Exception $e) {
            Log::error('Error creating achievement: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create achievement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an achievement.
     */
    public function updateAchievement(UpdateAchievementRequest $request, Achievement $achievement): JsonResponse
    {
        try {
            $achievement = DB::transaction(function () use ($request, $achievement) {
                $achievement->update($request->validated());

                if ($request->hasFile('icon')) {
                    $achievement->clearMediaCollection('icon');
                    $achievement->addMedia($request->file('icon'))
                        ->toMediaCollection('icon');
                }

                return $achievement;
            });

            return response()->json([
                'success' => true,
                'data' => $achievement->getPreviewData()
            ]);
        } catch (Exception $e) {
            Log::error('Error updating achievement: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'achievement_id' => $achievement->id
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update achievement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Configure XP rules.
     */
    public function configureXpRules(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'rules' => 'required|array',
                'rules.*.action' => 'required|string',
                'rules.*.base_xp' => 'required|integer|min:0',
                'rules.*.multipliers' => 'array'
            ]);

            DB::transaction(function () use ($request) {
                foreach ($request->rules as $rule) {
                    XpRule::updateOrCreate(
                        ['action' => $rule['action']],
                        [
                            'base_xp' => $rule['base_xp'],
                            'multipliers' => $rule['multipliers'] ?? null
                        ]
                    );
                }
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'rules_count' => count($request->rules)
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error configuring XP rules: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to configure XP rules',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Configure leagues.
     */
    public function configureLeagues(CreateLeagueRequest $request): JsonResponse
    {
        try {
            $leagues = DB::transaction(function () use ($request) {
                $leagues = collect($request->leagues)->map(function ($leagueData) {
                    return League::create([
                        'name' => $leagueData['name'],
                        'tier' => $leagueData['tier'],
                        'requirements' => $leagueData['requirements'],
                        'rewards' => $leagueData['rewards']
                    ]);
                });

                return $leagues;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'leagues_count' => $leagues->count(),
                    'leagues' => $leagues
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error configuring leagues: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to configure leagues',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Configure streak rules.
     */
    public function configureStreakRules(UpdateStreakRulesRequest $request): JsonResponse
    {
        try {
            $rules = StreakRule::updateOrCreate(
                ['id' => 1], // Single configuration record
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'data' => $rules
            ]);
        } catch (Exception $e) {
            Log::error('Error configuring streak rules: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to configure streak rules',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Configure daily goals.
     */
    public function configureDailyGoals(CreateDailyGoalRequest $request): JsonResponse
    {
        try {
            DB::transaction(function () use ($request) {
                // Clear existing goals if we're replacing them
                if ($request->boolean('replace_existing', false)) {
                    DailyGoal::query()->delete();
                }

                foreach ($request->options as $option) {
                    DailyGoal::create([
                        'name' => $option['name'],
                        'xp_target' => $option['xp_target'],
                        'rewards' => $option['rewards']
                    ]);
                }
            });

            return response()->json([
                'success' => true,
                'data' => DailyGoal::all()
            ]);
        } catch (Exception $e) {
            Log::error('Error configuring daily goals: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to configure daily goals',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a bonus event.
     */
    public function createBonusEvent(CreateBonusEventRequest $request): JsonResponse
    {
        try {
            $event = BonusEvent::create($request->validated());

            return response()->json([
                'success' => true,
                'data' => $event
            ], 201);
        } catch (Exception $e) {
            Log::error('Error creating bonus event: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create bonus event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get gamification statistics.
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $stats = [
                'achievements' => [
                    'total' => Achievement::count(),
                    'most_earned' => Achievement::withCount('users')
                        ->orderBy('users_count', 'desc')
                        ->first()
                ],
                'leagues' => [
                    'active_users' => DB::table('league_memberships')
                        ->where('joined_at', '>=', now()->subWeek())
                        ->count(),
                    'distribution' => League::withCount('users')->get()
                ],
                'streaks' => [
                    'active_streaks' => DB::table('user_streaks')
                        ->where('current_streak', '>', 0)
                        ->count(),
                    'longest_current' => DB::table('user_streaks')
                        ->max('current_streak')
                ],
                'daily_goals' => [
                    'completion_rate' => DB::table('user_daily_goals')
                        ->where('date', '>=', now()->subDays(30))
                        ->avg('completed')
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching gamification statistics: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch gamification statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
