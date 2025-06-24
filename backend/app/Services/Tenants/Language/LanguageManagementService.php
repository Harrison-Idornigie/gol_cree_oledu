<?php

namespace App\Services\Tenants\Language;

use App\Models\Tenants\Language;
use App\Models\Tenants\LanguagePair;
use App\Models\Tenants\User;
use App\Models\Tenants\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Language Management Service
 * 
 * Handles Language and LanguagePair operations for team members.
 * Focuses on language administration without duplicating word management functionality.
 * 
 * Responsibilities:
 * - Language CRUD operations
 * - Language pair management
 * - Language activation/deactivation
 * - Tenant-specific language filtering
 * - Language statistics and reporting
 */
class LanguageManagementService
{
    /**
     * Get a single language with relationships.
     */
    public function getLanguage(int $languageId, string $membership = 'student', array $with = []): ?Language
    {
        $query = Language::query();

        // Apply membership-based filtering
        if (!in_array($membership, ['super-admin', 'tenant-admin', 'team'])) {
            // Students can only see active languages
            $query->where('is_active', true);
        }

        // Default relationships
        $defaultWith = [];
        $with = array_merge($defaultWith, $with);

        if (!empty($with)) {
            $query->with($with);
        }

        return $query->find($languageId);
    }

    /**
     * Get filtered languages with membership-based access.
     */
    public function getFilteredLanguages(Request $request, string $membership = 'student'): LengthAwarePaginator
    {
        $query = Language::query();

        // Apply membership-based filtering
        if (!in_array($membership, ['super-admin', 'tenant-admin', 'team'])) {
            // Students can only see active languages
            $query->where('is_active', true);
        }

        // Apply search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('native_name', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%");
            });
        }

        // Apply filters
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('code')) {
            $query->where('code', $request->code);
        }

        // Include related data for team/admin views
        if (in_array($membership, ['super-admin', 'tenant-admin', 'team'])) {
            $query->withCount(['words', 'sentences', 'learningPaths']);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->input('per_page', 15);
        return $query->paginate($perPage);
    }

    /**
     * Create a new language with audit logging.
     */
    public function createLanguage(array $data, User $user): Language
    {
        return DB::transaction(function () use ($data, $user) {
            // Validate language code uniqueness within tenant
            $existingLanguage = Language::where('code', $data['code'])->first();
            if ($existingLanguage) {
                throw new \Exception("Language with code '{$data['code']}' already exists in this tenant.");
            }

            $language = Language::create([
                'code' => $data['code'],
                'name' => $data['name'],
                'native_name' => $data['native_name'] ?? $data['name'],
                'is_active' => $data['is_active'] ?? true,
                'tenant_id' => tenant('id')
            ]);

            // Log the creation for audit trail
            AuditLog::log(
                'create',
                'languages',
                $language,
                [],
                $data,
                ['user_id' => $user->id]
            );

            return $language;
        });
    }

    /**
     * Update a language with audit logging.
     */
    public function updateLanguage(Language $language, array $data, User $user): Language
    {
        return DB::transaction(function () use ($language, $data, $user) {
            $oldData = $language->toArray();

            // Validate language code uniqueness if changing
            if (isset($data['code']) && $data['code'] !== $language->code) {
                $existingLanguage = Language::where('code', $data['code'])
                    ->where('id', '!=', $language->id)
                    ->first();
                if ($existingLanguage) {
                    throw new \Exception("Language with code '{$data['code']}' already exists in this tenant.");
                }
            }

            $language->update(array_filter([
                'code' => $data['code'] ?? null,
                'name' => $data['name'] ?? null,
                'native_name' => $data['native_name'] ?? null,
                'is_active' => $data['is_active'] ?? null,
            ], function ($value) {
                return $value !== null;
            }));

            // Log the update for audit trail
            AuditLog::log(
                'update',
                'languages',
                $language,
                $oldData,
                $data,
                ['user_id' => $user->id]
            );

            return $language;
        });
    }

    /**
     * Delete a language with validation and audit logging.
     */
    public function deleteLanguage(Language $language, User $user): bool
    {
        return DB::transaction(function () use ($language, $user) {
            // Check if language is being used
            $usageCheck = $this->checkLanguageUsage($language);
            if (!$usageCheck['can_delete']) {
                throw new \Exception(
                    "Cannot delete language '{$language->name}'. It is being used in: " .
                        implode(', ', $usageCheck['usage_locations'])
                );
            }

            $data = $language->toArray();

            // Remove all language pairs
            $language->sourceLanguagePairs()->delete();
            $language->targetLanguagePairs()->delete();

            $language->delete();

            // Log the deletion for audit trail
            AuditLog::log(
                'delete',
                'languages',
                $language,
                $data,
                [],
                ['user_id' => $user->id]
            );

            return true;
        });
    }

    /**
     * Create a language pair with validation.
     */
    public function createLanguagePair(int $sourceLanguageId, int $targetLanguageId, User $user): LanguagePair
    {
        return DB::transaction(function () use ($sourceLanguageId, $targetLanguageId, $user) {
            // Validate languages exist and are active
            $sourceLanguage = Language::where('id', $sourceLanguageId)->where('is_active', true)->first();
            $targetLanguage = Language::where('id', $targetLanguageId)->where('is_active', true)->first();

            if (!$sourceLanguage || !$targetLanguage) {
                throw new \Exception('Both source and target languages must exist and be active.');
            }

            if ($sourceLanguageId === $targetLanguageId) {
                throw new \Exception('Source and target languages cannot be the same.');
            }

            // Check if pair already exists
            $existingPair = LanguagePair::where('source_language_id', $sourceLanguageId)
                ->where('target_language_id', $targetLanguageId)
                ->first();

            if ($existingPair) {
                throw new \Exception('This language pair already exists.');
            }

            $languagePair = LanguagePair::create([
                'source_language_id' => $sourceLanguageId,
                'target_language_id' => $targetLanguageId,
                'is_active' => true,
                'tenant_id' => tenant('id')
            ]);

            // Log the creation
            AuditLog::log(
                'create',
                'language_pairs',
                $languagePair,
                [],
                [
                    'source_language_id' => $sourceLanguageId,
                    'target_language_id' => $targetLanguageId
                ],
                ['user_id' => $user->id]
            );

            return $languagePair->load(['sourceLanguage', 'targetLanguage']);
        });
    }

    /**
     * Update language pair status.
     */
    public function updateLanguagePairStatus(int $sourceLanguageId, int $targetLanguageId, bool $isActive, User $user): LanguagePair
    {
        return DB::transaction(function () use ($sourceLanguageId, $targetLanguageId, $isActive, $user) {
            $languagePair = LanguagePair::where('source_language_id', $sourceLanguageId)
                ->where('target_language_id', $targetLanguageId)
                ->firstOrFail();

            $oldData = $languagePair->toArray();
            $languagePair->update(['is_active' => $isActive]);

            // Log the update
            AuditLog::log(
                'update',
                'language_pairs',
                $languagePair,
                $oldData,
                ['is_active' => $isActive],
                ['user_id' => $user->id]
            );

            return $languagePair->load(['sourceLanguage', 'targetLanguage']);
        });
    }

    /**
     * Delete a language pair.
     */
    public function deleteLanguagePair(int $sourceLanguageId, int $targetLanguageId, User $user): bool
    {
        return DB::transaction(function () use ($sourceLanguageId, $targetLanguageId, $user) {
            $languagePair = LanguagePair::where('source_language_id', $sourceLanguageId)
                ->where('target_language_id', $targetLanguageId)
                ->firstOrFail();

            $data = $languagePair->toArray();
            $languagePair->delete();

            // Log the deletion
            AuditLog::log(
                'delete',
                'language_pairs',
                $languagePair,
                $data,
                [],
                ['user_id' => $user->id]
            );

            return true;
        });
    }

    /**
     * Get available language pairs for content creation.
     */
    public function getAvailableLanguagePairs(): Collection
    {
        return LanguagePair::with(['sourceLanguage', 'targetLanguage'])
            ->where('is_active', true)
            ->whereHas('sourceLanguage', function ($q) {
                $q->where('is_active', true);
            })
            ->whereHas('targetLanguage', function ($q) {
                $q->where('is_active', true);
            })
            ->get();
    }

    /**
     * Get language statistics for dashboard.
     */
    public function getLanguageStatistics(): array
    {
        $languages = Language::withCount(['words', 'sentences', 'learningPaths'])->get();
        $languagePairs = LanguagePair::where('is_active', true)->count();

        return [
            'total_languages' => $languages->count(),
            'active_languages' => $languages->where('is_active', true)->count(),
            'inactive_languages' => $languages->where('is_active', false)->count(),
            'total_language_pairs' => $languagePairs,
            'languages_with_content' => $languages->filter(function ($lang) {
                return $lang->words_count > 0 || $lang->sentences_count > 0;
            })->count(),
            'languages_with_learning_paths' => $languages->where('learning_paths_count', '>', 0)->count(),
        ];
    }

    /**
     * Check if a language can be safely deleted.
     */
    private function checkLanguageUsage(Language $language): array
    {
        $usageLocations = [];
        $canDelete = true;

        // Check for words
        if ($language->words()->exists()) {
            $usageLocations[] = 'words';
            $canDelete = false;
        }

        // Check for sentences
        if ($language->sentences()->exists()) {
            $usageLocations[] = 'sentences';
            $canDelete = false;
        }

        // Check for learning paths
        if ($language->learningPaths()->exists()) {
            $usageLocations[] = 'learning paths';
            $canDelete = false;
        }

        // Check for language pairs
        if ($language->sourceLanguagePairs()->exists() || $language->targetLanguagePairs()->exists()) {
            $usageLocations[] = 'language pairs';
            // Language pairs can be deleted automatically, so this doesn't prevent deletion
        }

        return [
            'can_delete' => $canDelete,
            'usage_locations' => $usageLocations,
            'usage_count' => [
                'words' => $language->words()->count(),
                'sentences' => $language->sentences()->count(),
                'learning_paths' => $language->learningPaths()->count(),
                'source_pairs' => $language->sourceLanguagePairs()->count(),
                'target_pairs' => $language->targetLanguagePairs()->count(),
            ]
        ];
    }

    /**
     * Get proficiency levels available for a language.
     */
    public function getLanguageProficiencyLevels(Language $language): array
    {
        // Common proficiency levels with content availability
        $levels = [
            'beginner' => [
                'level' => 'beginner',
                'name' => 'Beginner (A1)',
                'description' => 'Basic understanding and simple phrases',
                'content_count' => 0
            ],
            'elementary' => [
                'level' => 'elementary',
                'name' => 'Elementary (A2)', 
                'description' => 'Simple conversations and everyday topics',
                'content_count' => 0
            ],
            'intermediate' => [
                'level' => 'intermediate',
                'name' => 'Intermediate (B1/B2)',
                'description' => 'Complex conversations and detailed texts',
                'content_count' => 0
            ],
            'advanced' => [
                'level' => 'advanced',
                'name' => 'Advanced (C1/C2)',
                'description' => 'Fluent communication and sophisticated content',
                'content_count' => 0
            ]
        ];

        // Count content available per level (this would be expanded based on actual content)
        foreach ($levels as $level => &$data) {
            $data['content_count'] = $this->getContentCountForLevel($language, $level);
        }

        return array_values($levels);
    }

    /**
     * Get language content overview for students.
     */
    public function getLanguageContentOverview(Language $language): array
    {
        return [
            'learning_paths' => $language->learningPaths()
                ->where('status', 'published')
                ->count(),
            'total_lessons' => $language->lessons()
                ->where('status', 'published')
                ->count(),
            'vocabulary_words' => $language->vocabularyWords()
                ->where('status', 'published')
                ->count(),
            'exercises' => $language->exercises()
                ->where('status', 'published')
                ->count(),
            'topics_covered' => $language->topics()
                ->where('status', 'published')
                ->count(),
            'estimated_hours' => $this->calculateEstimatedLearningHours($language),
            'difficulty_range' => $this->getLanguageDifficultyRange($language)
        ];
    }

    /**
     * Get user's progress summary for a language.
     */
    public function getUserLanguageProgress(Language $language, User $user): array
    {
        // This would integrate with actual progress tracking models
        return [
            'overall_progress' => 0,
            'proficiency_level' => 'beginner',
            'lessons_completed' => 0,
            'exercises_completed' => 0,
            'vocabulary_learned' => 0,
            'current_streak' => 0,
            'total_time_minutes' => 0,
            'achievements_earned' => 0,
            'last_activity' => null,
            'next_recommended_content' => null,
            'weakest_skills' => [],
            'strongest_skills' => []
        ];
    }

    /**
     * Get language dashboard data for a student.
     */
    public function getLanguageDashboard(Language $language, User $user): array
    {
        $progress = $this->getUserLanguageProgress($language, $user);
        $overview = $this->getLanguageContentOverview($language);

        return [
            'language' => $language,
            'progress' => $progress,
            'content_overview' => $overview,
            'recent_activity' => $this->getRecentLanguageActivity($language, $user),
            'upcoming_content' => $this->getUpcomingContent($language, $user),
            'recommended_actions' => $this->getRecommendedActions($language, $user),
            'study_streak' => $this->getStudyStreak($language, $user)
        ];
    }

    /**
     * Helper: Get content count for a proficiency level.
     */
    private function getContentCountForLevel(Language $language, string $level): int
    {
        // This would be expanded based on actual content categorization
        return 0;
    }

    /**
     * Helper: Calculate estimated learning hours for a language.
     */
    private function calculateEstimatedLearningHours(Language $language): int
    {
        // Basic calculation based on content volume
        $lessonCount = $language->lessons()->where('status', 'published')->count();
        $exerciseCount = $language->exercises()->where('status', 'published')->count();
        
        // Rough estimate: 30 minutes per lesson, 15 minutes per exercise
        return ($lessonCount * 0.5) + ($exerciseCount * 0.25);
    }

    /**
     * Helper: Get difficulty range for a language.
     */
    private function getLanguageDifficultyRange(Language $language): array
    {
        return [
            'min_level' => 'beginner',
            'max_level' => 'advanced',
            'primary_level' => 'intermediate'
        ];
    }

    /**
     * Helper: Get recent activity for language.
     */
    private function getRecentLanguageActivity(Language $language, User $user): array
    {
        return [
            'last_lesson' => null,
            'last_exercise' => null,
            'recent_achievements' => [],
            'activity_count_7_days' => 0
        ];
    }

    /**
     * Helper: Get upcoming content recommendations.
     */
    private function getUpcomingContent(Language $language, User $user): array
    {
        return [
            'next_lesson' => null,
            'recommended_exercises' => [],
            'suggested_vocabulary' => []
        ];
    }

    /**
     * Helper: Get recommended actions for user.
     */
    private function getRecommendedActions(Language $language, User $user): array
    {
        return [
            'priority_action' => 'start_learning_path',
            'suggested_goals' => ['practice_daily', 'complete_beginner_path'],
            'skill_gaps' => []
        ];
    }

    /**
     * Helper: Get study streak information.
     */
    private function getStudyStreak(Language $language, User $user): array
    {
        return [
            'current_streak' => 0,
            'longest_streak' => 0,
            'streak_goal' => 7,
            'last_study_date' => null
        ];
    }
}
