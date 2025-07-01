<?php

namespace App\Policies;

use App\Models\Tenants\User;
use App\Models\Tenants\Lesson;
use App\Models\Tenants\ContentTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Content Scaffolding Policy
 * 
 * Defines authorization rules for automated content generation operations.
 * Controls access to exercise generation, lesson scaffolding, and bulk operations.
 */
class ContentScaffoldingPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can generate exercises from guidebook.
     */
    public function generateExercises(User $user): bool
    {
        // Team members and above can generate exercises
        return $user->isTeam() || $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can generate exercises from sentences.
     */
    public function generateFromSentences(User $user): bool
    {
        // Team members and above can generate exercises from sentences
        return $user->isTeam() || $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can generate lessons from templates.
     */
    public function generateLesson(User $user): bool
    {
        // Team members and above can generate lessons
        return $user->isTeam() || $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can generate lessons for a specific topic.
     */
    public function generateLessonForTopic(User $user, int $topicId): bool
    {
        // Team members can generate lessons for topics they have access to
        if (!($user->isTeam() || $user->isTenantAdmin())) {
            return false;
        }

        // Additional check: verify user has access to the topic
        // This would require loading the topic and checking permissions
        return true; // Simplified for now
    }

    /**
     * Determine whether the user can preview exercises before generation.
     */
    public function previewExercises(User $user): bool
    {
        // Team members and above can preview exercises
        return $user->isTeam() || $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can perform bulk content generation.
     */
    public function bulkGenerate(User $user): bool
    {
        // Only admins can perform bulk operations due to resource intensity
        return $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can save generated content directly.
     */
    public function saveGeneratedContent(User $user): bool
    {
        // Team members and above can save generated content
        return $user->isTeam() || $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can use a specific template for generation.
     */
    public function useTemplate(User $user, ContentTemplate $template): bool
    {
        // Team members can use any template for content generation
        return $user->isTeam() || $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can generate content with specific language pairs.
     */
    public function useLanguagePair(User $user, int $sourceLanguageId, int $targetLanguageId): bool
    {
        // Team members can use any language pair available in their tenant
        if (!($user->isTeam() || $user->isTenantAdmin())) {
            return false;
        }

        // Additional check: verify language pair exists and is active in tenant
        // This would require checking the language_pairs table
        return true; // Simplified for now
    }

    /**
     * Determine whether the user can generate exercises with specific difficulty levels.
     */
    public function setDifficultyLevel(User $user, string $difficultyLevel): bool
    {
        // Team members can set any difficulty level
        return $user->isTeam() || $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can generate exercises of specific types.
     */
    public function generateExerciseType(User $user, string $exerciseType): bool
    {
        // Team members can generate any exercise type
        if (!($user->isTeam() || $user->isTenantAdmin())) {
            return false;
        }

        // Additional validation for specific exercise types
        $allowedTypes = [
            'multiple_choice',
            'fill_blank',
            'matching',
            'writing',
            'speaking',
            'listening',
            'conversation',
            'picture'
        ];

        return in_array($exerciseType, $allowedTypes);
    }

    /**
     * Determine whether the user can specify exercise count limits.
     */
    public function setExerciseCount(User $user, int $count): bool
    {
        // Team members can generate up to 20 exercises at once
        if ($user->isTeam()) {
            return $count <= 20;
        }

        // Admins can generate up to 50 exercises at once
        if ($user->isTenantAdmin()) {
            return $count <= 50;
        }

        return false;
    }

    /**
     * Determine whether the user can perform bulk operations with specific limits.
     */
    public function setBulkLimit(User $user, int $lessonCount): bool
    {
        // Admins can bulk generate up to 10 lessons at once
        if ($user->isTenantAdmin()) {
            return $lessonCount <= 10;
        }

        // Team members cannot perform bulk operations
        return false;
    }

    /**
     * Determine whether the user can access advanced scaffolding features.
     */
    public function useAdvancedFeatures(User $user): bool
    {
        // Advanced features like AI-assisted generation are for admins
        return $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can validate content before generation.
     */
    public function validateContent(User $user): bool
    {
        // Team members and above can validate content
        return $user->isTeam() || $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can access scaffolding analytics.
     */
    public function viewAnalytics(User $user): bool
    {
        // Admins can view scaffolding usage analytics
        return $user->isTenantAdmin();
    }
}
