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
        return $user->hasAnyRole(['admin', 'team', 'content_creator']);
    }

    /**
     * Determine whether the user can view the topic.
     */
    public function view(User $user, Topic $topic): bool
    {
        return $user->hasAnyRole(['admin', 'team', 'content_creator']);
    }

    /**
     * Determine whether the user can create topics.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'team', 'content_creator']);
    }

    /**
     * Determine whether the user can update the topic.
     */
    public function update(User $user, Topic $topic): bool
    {
        return $user->hasAnyRole(['admin', 'team', 'content_creator']);
    }

    /**
     * Determine whether the user can delete the topic.
     */
    public function delete(User $user, Topic $topic): bool
    {
        return $user->hasAnyRole(['admin', 'team', 'content_creator']);
    }

    /**
     * Determine whether the user can restore the topic.
     */
    public function restore(User $user, Topic $topic): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the topic.
     */
    public function forceDelete(User $user, Topic $topic): bool
    {
        return $user->hasRole('admin');
    }
}
