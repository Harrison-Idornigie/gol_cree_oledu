<?php

namespace App\Policies;

use App\Models\Tenants\User;
use App\Models\Tenants\Achievement;

class AchievementPolicy
{
    /**
     * Determine if the user can view any achievements.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view achievements
    }

    /**
     * Determine if the user can view the achievement.
     */
    public function view(User $user, Achievement $achievement): bool
    {
        return true; // All authenticated users can view individual achievements
    }

    /**
     * Determine if the user can create achievements.
     */
    public function create(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can update the achievement.
     */
    public function update(User $user, Achievement $achievement): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can delete the achievement.
     */
    public function delete(User $user, Achievement $achievement): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can award achievements.
     */
    public function award(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can revoke achievements.
     */
    public function revoke(User $user): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can view achievement analytics.
     */
    public function viewAnalytics(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can configure achievement settings.
     */
    public function configureSettings(User $user): bool
    {
        return $user->isTenantAdmin();
    }
}
