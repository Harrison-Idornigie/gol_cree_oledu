<?php

namespace App\Policies;

use App\Models\Tenants\ContentTemplate;
use App\Models\Tenants\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Content Template Policy
 * 
 * Defines authorization rules for content template operations.
 * Controls access to template CRUD operations and usage tracking.
 */
class ContentTemplatePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any content templates.
     */
    public function viewAny(User $user): bool
    {
        // Team members and above can browse content templates
        return $user->isTeam() || $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can view the content template.
     */
    public function view(User $user, ContentTemplate $template): bool
    {
        // Team members and above can view templates
        return $user->isTeam() || $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can create content templates.
     */
    public function create(User $user): bool
    {
        // Team members and above can create content templates
        return $user->isTeam() || $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can update the content template.
     */
    public function update(User $user, ContentTemplate $template): bool
    {
        // Admins can update any template in their tenant
        if ($user->isTenantAdmin()) {
            return true;
        }

        // Team members can only update templates they created
        return $user->isTeam() && $template->created_by === $user->id;
    }

    /**
     * Determine whether the user can delete the content template.
     */
    public function delete(User $user, ContentTemplate $template): bool
    {
        // Admins can delete templates in their tenant
        if ($user->isTenantAdmin()) {
            return true;
        }

        // Team members can only delete templates they created with no usage
        return $user->isTeam() && $template->created_by === $user->id && $template->usage_count === 0;
    }

    /**
     * Determine whether the user can duplicate the content template.
     */
    public function duplicate(User $user, ContentTemplate $template): bool
    {
        // Team members and above can duplicate templates
        return $user->isTeam() || $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can validate template structure.
     */
    public function validate(User $user, ContentTemplate $template): bool
    {
        // Team members and above can validate templates
        return $user->isTeam() || $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can view template usage statistics.
     */
    public function viewStats(User $user, ContentTemplate $template): bool
    {
        // Admins can view stats for templates in their tenant
        if ($user->isTenantAdmin()) {
            return true;
        }

        // Team members can view stats for templates they created
        return $user->isTeam() && $template->created_by === $user->id;
    }

    /**
     * Determine whether the user can create templates of specific types.
     */
    public function createType(User $user, string $templateType): bool
    {
        // Validate template type
        $allowedTypes = [
            ContentTemplate::TYPE_UNIT,
            ContentTemplate::TYPE_TOPIC,
            ContentTemplate::TYPE_LESSON,
            ContentTemplate::TYPE_EXERCISE,
        ];

        if (!in_array($templateType, $allowedTypes)) {
            return false;
        }

        // Team members can create any template type
        return $user->isTeam() || $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can set specific difficulty levels.
     */
    public function setDifficultyLevel(User $user, int $difficultyLevel): bool
    {
        // Validate difficulty level range
        if ($difficultyLevel < 1 || $difficultyLevel > 10) {
            return false;
        }

        // Team members can set any difficulty level
        return $user->isTeam() || $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can specify skill focus areas.
     */
    public function setSkillFocus(User $user, string $skillFocus): bool
    {
        // Validate skill focus options
        $allowedSkills = [
            'guidebook',
            'grammar',
            'listening',
            'speaking',
            'reading',
            'writing',
            'conversation',
            'pronunciation'
        ];

        if (!in_array($skillFocus, $allowedSkills)) {
            return false;
        }

        // Team members can set any skill focus
        return $user->isTeam() || $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can set guidebook requirements.
     */
    public function setGuidebookRequirements(User $user, array $requirements): bool
    {
        // Team members can set guidebook requirements
        return $user->isTeam() || $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can specify exercise types for templates.
     */
    public function setExerciseTypes(User $user, array $exerciseTypes): bool
    {
        // Validate exercise types
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

        foreach ($exerciseTypes as $type) {
            if (!in_array($type, $allowedTypes)) {
                return false;
            }
        }

        // Team members can set any exercise types
        return $user->isTeam() || $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can restore the content template.
     */
    public function restore(User $user, ContentTemplate $template): bool
    {
        // Admins and above can restore deleted templates
        return $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can permanently delete the content template.
     */
    public function forceDelete(User $user, ContentTemplate $template): bool
    {
        // Only admins can permanently delete templates (no super admin in tenant context)
        return $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can increment template usage.
     */
    public function incrementUsage(User $user, ContentTemplate $template): bool
    {
        // This happens automatically when templates are used
        // Team members and above can use templates (which increments usage)
        return $user->isTeam() || $user->isTenantAdmin();
    }
}
