<?php

namespace App\Policies;

use App\Models\Tenants\User;
use App\Models\Tenants\MediaFile;

class MediaFilePolicy
{
    /**
     * Determine if the user can view any media files.
     */
    public function viewAny(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam() || $user->isStudent();
    }

    /**
     * Determine if the user can view the media file.
     */
    public function view(User $user, MediaFile $mediaFile): bool
    {
        // All authenticated users can view media files in their tenant
        return true;
    }

    /**
     * Determine if the user can create media files.
     */
    public function create(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can upload media files.
     */
    public function upload(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can update the media file.
     */
    public function update(User $user, MediaFile $mediaFile): bool
    {
        // Users can update their own uploaded files
        if (isset($mediaFile->user_id) && $user->id === $mediaFile->user_id) {
            return true;
        }

        // Tenant admins can update any media file
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can delete the media file.
     */
    public function delete(User $user, MediaFile $mediaFile): bool
    {
        // Users can delete their own uploaded files
        if (isset($mediaFile->user_id) && $user->id === $mediaFile->user_id) {
            return true;
        }

        // Tenant admins can delete any media file
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can manage media library.
     */
    public function manageLibrary(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can view media analytics.
     */
    public function viewAnalytics(User $user): bool
    {
        return $user->isTenantAdmin();
    }
}
