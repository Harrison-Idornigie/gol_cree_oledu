<?php

namespace App\Policies;

use App\Models\Tenants\User;
use App\Models\Tenants\Review;

class ReviewPolicy
{
    /**
     * Determine if the user can view any reviews.
     */
    public function viewAny(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can view the review.
     */
    public function view(User $user, Review $review): bool
    {
        // Users can view reviews they submitted
        if ($user->id === $review->submitted_by) {
            return true;
        }

        // Users can view reviews assigned to them
        if ($user->id === $review->reviewed_by) {
            return true;
        }

        // Tenant admins can view all reviews
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can create reviews.
     */
    public function create(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can submit content for review.
     */
    public function submitForReview(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can update the review.
     */
    public function update(User $user, Review $review): bool
    {
        // Users can update reviews they submitted (if still pending)
        if ($user->id === $review->submitted_by && $review->status === 'pending') {
            return true;
        }

        // Reviewers can update reviews assigned to them
        if ($user->id === $review->reviewed_by) {
            return true;
        }

        // Tenant admins can update any review
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can approve reviews.
     */
    public function approve(User $user, Review $review): bool
    {
        // Only assigned reviewers can approve
        if ($user->id === $review->reviewed_by) {
            return true;
        }

        // Tenant admins can approve any review
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can reject reviews.
     */
    public function reject(User $user, Review $review): bool
    {
        // Only assigned reviewers can reject
        if ($user->id === $review->reviewed_by) {
            return true;
        }

        // Tenant admins can reject any review
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can delete the review.
     */
    public function delete(User $user, Review $review): bool
    {
        // Users can delete their own submitted reviews (if still pending)
        if ($user->id === $review->submitted_by && $review->status === 'pending') {
            return true;
        }

        // Tenant admins can delete any review
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can assign reviewers.
     */
    public function assignReviewer(User $user): bool
    {
        return $user->isTenantAdmin();
    }

    /**
     * Determine if the user can view pending reviews.
     */
    public function viewPendingReviews(User $user): bool
    {
        return $user->isTenantAdmin() || $user->isTeam();
    }

    /**
     * Determine if the user can view review analytics.
     */
    public function viewAnalytics(User $user): bool
    {
        return $user->isTenantAdmin();
    }
}
