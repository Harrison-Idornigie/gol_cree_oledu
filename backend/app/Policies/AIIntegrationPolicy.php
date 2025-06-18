<?php

namespace App\Policies;

use App\Models\Tenants\User;

class AIIntegrationPolicy
{
    /**
     * Determine if the user can use AI pronunciation scoring.
     */
    public function usePronunciationScoring(User $user): bool
    {
        // All authenticated users can use pronunciation scoring
        return true;
    }

    /**
     * Determine if the user can use AI grammar checking.
     */
    public function useGrammarChecking(User $user): bool
    {
        // All authenticated users can use grammar checking
        return true;
    }

    /**
     * Determine if the user can access adaptive learning recommendations.
     */
    public function useAdaptiveLearning(User $user): bool
    {
        // All authenticated users can access adaptive learning
        return true;
    }

    /**
     * Determine if the user can use AI content generation.
     */
    public function useContentGeneration(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can configure AI settings.
     */
    public function configureAISettings(User $user): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can view AI usage analytics.
     */
    public function viewAIAnalytics(User $user): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can use AI translation suggestions.
     */
    public function useTranslationSuggestions(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can access AI-powered insights.
     */
    public function viewAIInsights(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can use AI for automatic difficulty adjustment.
     */
    public function useDifficultyAdjustment(User $user): bool
    {
        // All authenticated users benefit from difficulty adjustment
        return true;
    }

    /**
     * Determine if the user can use AI for personalized learning paths.
     */
    public function usePersonalizedPaths(User $user): bool
    {
        // All authenticated users can use personalized paths
        return true;
    }
}
