<?php

namespace App\Policies;

use App\Models\Tenants\CurriculumTemplate;
use App\Models\Tenants\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Curriculum Template Policy
 * 
 * Defines authorization rules for curriculum template operations.
 * Controls access to template browsing, instantiation, and customization.
 */
class CurriculumTemplatePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any curriculum templates.
     */
    public function viewAny(User $user): bool
    {
        // Team members and above can browse templates
        return $user->isTeam() || $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can view the curriculum template.
     */
    public function view(User $user, CurriculumTemplate $template): bool
    {
        // Team members can view all templates (official and custom)
        return $user->isTeam() || $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can create curriculum templates.
     */
    public function create(User $user): bool
    {
        // Only admins can create official templates
        // Team members can create custom templates through customization
        return $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can update the curriculum template.
     */
    public function update(User $user, CurriculumTemplate $template): bool
    {
        // Admins can update templates in their tenant
        if ($user->isTenantAdmin()) {
            return true;
        }

        // Users can only update templates they created (custom templates)
        return $template->created_by === $user->id && !$template->is_official;
    }

    /**
     * Determine whether the user can delete the curriculum template.
     */
    public function delete(User $user, CurriculumTemplate $template): bool
    {
        // Super admins can delete any template
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Admins can delete non-official templates in their tenant
        if ($user->isTenantAdmin() && !$template->is_official) {
            return true;
        }

        // Users can only delete custom templates they created with no usage
        return $template->created_by === $user->id
            && !$template->is_official
            && $template->usage_count === 0;
    }

    /**
     * Determine whether the user can instantiate the curriculum template.
     */
    public function instantiate(User $user, CurriculumTemplate $template): bool
    {
        // Team members and above can instantiate templates
        return $user->isTeam() || $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can customize the curriculum template.
     */
    public function customize(User $user, CurriculumTemplate $template): bool
    {
        // Team members and above can customize templates
        return $user->isTeam() || $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can view template analytics.
     */
    public function viewAnalytics(User $user, CurriculumTemplate $template): bool
    {
        // Admins can view analytics for templates in their tenant
        if ($user->isTenantAdmin()) {
            return true;
        }

        // Team members can view analytics for templates they created
        return $user->isTeam() && $template->created_by === $user->id;
    }

    /**
     * Determine whether the user can view template recommendations.
     */
    public function viewRecommendations(User $user): bool
    {
        // Team members and above can view recommendations
        return $user->isTeam() || $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can validate templates.
     */
    public function validate(User $user, CurriculumTemplate $template): bool
    {
        // Team members and above can validate templates before using them
        return $user->isTeam() || $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can preview template instantiation.
     */
    public function preview(User $user, CurriculumTemplate $template): bool
    {
        // Team members and above can preview templates
        return $user->isTeam() || $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can view usage statistics.
     */
    public function viewUsage(User $user, CurriculumTemplate $template): bool
    {
        // Admins can view usage stats for templates in their tenant
        if ($user->isTenantAdmin()) {
            return true;
        }

        // Template creators can view their template usage
        return $user->isTeam() && $template->created_by === $user->id;
    }

    /**
     * Determine whether the user can restore the curriculum template.
     */
    public function restore(User $user, CurriculumTemplate $template): bool
    {
        // Only admins can restore deleted templates
        return $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can permanently delete the curriculum template.
     */
    public function forceDelete(User $user, CurriculumTemplate $template): bool
    {
        // Only admins can permanently delete templates
        return $user->isTenantAdmin();
    }
}
