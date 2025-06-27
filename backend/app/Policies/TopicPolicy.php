<?php

namespace App\Policies;

use App\Models\Tenants\Topic;
use App\Models\Tenants\User;

class TopicPolicy
{
    /**
     * Determine whether the user can view any topics.
     */
    public function viewAny(User $user): bool
    {
        // Students can view topics, but only published ones
        // Admins and team members can view all topics
        return true;
    }

    /**
     * Determine whether the user can view the topic.
     */
    public function view(User $user, Topic $topic): bool
    {
        // Students can only view published topics
        if ($user->isStudent()) {
            return $topic->status === 'published';
        }

        // Admins and team members can view all topics
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine whether the user can create topics.
     */
    public function create(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine whether the user can update the topic.
     */
    public function update(User $user, Topic $topic): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine whether the user can delete the topic.
     */
    public function delete(User $user, Topic $topic): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine whether the user can restore the topic.
     */
    public function restore(User $user, Topic $topic): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine whether the user can permanently delete the topic.
     */
    public function forceDelete(User $user, Topic $topic): bool
    {
        return $user->isTenantAdmin();
    }
}
