<?php

namespace App\Services\Tenants\User;

use App\Models\Tenants\UserLanguage;
use App\Models\Tenants\Language;
use App\Models\Tenants\User;
use App\Models\Tenants\AuditLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

/**
 * User Language Service
 * 
 * Handles student language selection and management including:
 * - Adding/removing languages from learning list
 * - Setting primary language preferences
 * - Managing proficiency levels
 * - Progress tracking across languages
 */
class UserLanguageService
{
    public const AUDIT_AREA = 'user_languages';

    /**
     * Get all languages selected by user with progress information.
     */
    public function getUserLanguages(User $user): Collection
    {
        return UserLanguage::where('user_id', $user->id)
            ->with(['language', 'user'])
            ->get()
            ->map(function ($userLanguage) {
                $languageData = $userLanguage->toArray();
                $languageData['progress'] = $this->getLanguageProgress($userLanguage);
                $languageData['statistics'] = $this->getLanguageStatistics($userLanguage);
                return (object) $languageData;
            });
    }

    /**
     * Add a language to user's learning list.
     */
    public function addLanguage(User $user, int $languageId, array $options = []): UserLanguage
    {
        return DB::transaction(function () use ($user, $languageId, $options) {
            // Validate language exists and is available
            $language = Language::findOrFail($languageId);

            // Check if already selected
            $existing = UserLanguage::where('user_id', $user->id)
                ->where('language_id', $languageId)
                ->first();

            if ($existing) {
                throw new Exception('Language is already in your learning list.');
            }

            // Determine if this should be primary (first language or explicitly set)
            $isPrimary = $options['is_primary'] ?? false;
            $hasExistingLanguages = UserLanguage::where('user_id', $user->id)->exists();

            if (!$hasExistingLanguages) {
                $isPrimary = true; // First language is always primary
            } elseif ($isPrimary) {
                // Unset existing primary
                UserLanguage::where('user_id', $user->id)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            $userLanguage = UserLanguage::create([
                'user_id' => $user->id,
                'language_id' => $languageId,
                'is_primary' => $isPrimary,
                'proficiency_level' => $options['proficiency_level'] ?? 'beginner',
                'metadata' => [
                    'started_at' => now(),
                    'initial_goal' => $options['goal'] ?? 'general_fluency'
                ]
            ]);

            // Log the action
            $this->createAuditLog('language_added', $userLanguage->id, [
                'language_name' => $language->name,
                'language_code' => $language->code,
                'is_primary' => $isPrimary
            ]);

            return $userLanguage->load(['language']);
        });
    }

    /**
     * Remove a language from user's learning list.
     */
    public function removeLanguage(User $user, int $languageId): bool
    {
        return DB::transaction(function () use ($user, $languageId) {
            $userLanguage = UserLanguage::where('user_id', $user->id)
                ->where('language_id', $languageId)
                ->firstOrFail();

            $wasPrimary = $userLanguage->is_primary;
            $languageName = $userLanguage->language->name ?? 'Unknown';

            // Delete the language selection
            $deleted = $userLanguage->delete();

            // If this was primary, set another language as primary
            if ($wasPrimary && $deleted) {
                $newPrimary = UserLanguage::where('user_id', $user->id)->first();
                if ($newPrimary) {
                    $newPrimary->update(['is_primary' => true]);
                }
            }

            // Log the action
            $this->createAuditLog('language_removed', $languageId, [
                'language_name' => $languageName,
                'was_primary' => $wasPrimary
            ]);

            return $deleted;
        });
    }

    /**
     * Set a language as the primary learning language.
     */
    public function setPrimaryLanguage(User $user, int $languageId): UserLanguage
    {
        return DB::transaction(function () use ($user, $languageId) {
            $userLanguage = UserLanguage::where('user_id', $user->id)
                ->where('language_id', $languageId)
                ->firstOrFail();

            // Unset existing primary languages
            UserLanguage::where('user_id', $user->id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);

            // Set new primary
            $userLanguage->update(['is_primary' => true]);

            // Log the action
            $this->createAuditLog('primary_language_changed', $userLanguage->id, [
                'language_name' => $userLanguage->language->name,
                'language_code' => $userLanguage->language->code
            ]);

            return $userLanguage->load(['language']);
        });
    }

    /**
     * Update proficiency level for a user language.
     */
    public function updateProficiencyLevel(User $user, int $languageId, string $level): UserLanguage
    {
        $userLanguage = UserLanguage::where('user_id', $user->id)
            ->where('language_id', $languageId)
            ->firstOrFail();

        $oldLevel = $userLanguage->proficiency_level;
        $userLanguage->update(['proficiency_level' => $level]);

        // Log the action
        $this->createAuditLog('proficiency_updated', $userLanguage->id, [
            'language_name' => $userLanguage->language->name,
            'old_level' => $oldLevel,
            'new_level' => $level
        ]);

        return $userLanguage->load(['language']);
    }

    /**
     * Get available languages that user hasn't selected yet.
     */
    public function getAvailableLanguages(User $user): Collection
    {
        $selectedLanguageIds = UserLanguage::where('user_id', $user->id)
            ->pluck('language_id');

        return Language::whereNotIn('id', $selectedLanguageIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get language learning progress for a user language.
     */
    private function getLanguageProgress(UserLanguage $userLanguage): array
    {
        // This would integrate with actual progress tracking
        // For now, return basic structure
        return [
            'overall_progress' => 0,
            'lessons_completed' => 0,
            'exercises_completed' => 0,
            'vocabulary_learned' => 0,
            'streak_days' => 0,
            'last_activity' => $userLanguage->updated_at
        ];
    }

    /**
     * Get language learning statistics for a user language.
     */
    private function getLanguageStatistics(UserLanguage $userLanguage): array
    {
        return [
            'time_spent_minutes' => 0,
            'sessions_completed' => 0,
            'accuracy_percentage' => 0,
            'favorite_exercise_type' => null,
            'weakest_skill' => null,
            'strongest_skill' => null
        ];
    }

    /**
     * Create audit log entry.
     */
    private function createAuditLog(string $action, ?int $recordId, array $metadata = []): void
    {
        AuditLog::create([
            'area' => self::AUDIT_AREA,
            'action' => $action,
            'record_id' => $recordId,
            'user_id' => Auth::id(),
            'metadata' => $metadata
        ]);
    }
}
