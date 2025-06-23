<?php

namespace App\Services\Tenants\Course;

use App\Models\Tenants\Review;
use App\Models\Tenants\User;
use App\Models\Tenants\AuditLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Review Service
 * 
 * Handles CRUD operations and business logic for reviews including:
 * - Review creation, updates, and deletion
 * - Review aggregation and statistics
 * - Review moderation and validation
 * - Review analytics and reporting
 */
class ReviewService
{
    /**
     * Get paginated reviews with filters.
     */
    public function getReviews(array $filters = [], array $sorts = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Review::with(['user', 'content']);

        // Apply search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('comment', 'LIKE', "%{$search}%");
            });
        }

        // Apply filters
        if (!empty($filters['content_type'])) {
            $query->where('content_type', $filters['content_type']);
        }

        if (!empty($filters['content_id'])) {
            $query->where('content_id', $filters['content_id']);
        }

        if (!empty($filters['rating'])) {
            $query->where('rating', $filters['rating']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Apply sorting
        foreach ($sorts as $field => $direction) {
            if (in_array($field, ['rating', 'created_at', 'updated_at', 'helpful_count'])) {
                $query->orderBy($field, $direction);
            }
        }

        // Default sorting
        if (empty($sorts)) {
            $query->orderBy('created_at', 'desc');
        }

        return $query->paginate($perPage);
    }

    /**
     * Get reviews for specific content.
     */
    public function getContentReviews(string $contentType, int $contentId, int $perPage = 10): LengthAwarePaginator
    {
        return Review::where('content_type', $contentType)
            ->where('content_id', $contentId)
            ->where('status', 'approved')
            ->with(['user'])
            ->orderBy('helpful_count', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Create a new review.
     */
    public function createReview(array $data, User $user): Review
    {
        return DB::transaction(function () use ($data, $user) {
            // Validate user hasn't already reviewed this content
            $existingReview = Review::where('content_type', $data['content_type'])
                ->where('content_id', $data['content_id'])
                ->where('user_id', $user->id)
                ->first();

            if ($existingReview) {
                throw new Exception('You have already reviewed this content. Please update your existing review instead.');
            }

            // Set defaults
            $data['user_id'] = $user->id;
            $data['tenant_id'] = tenant('id');
            $data['status'] = 'pending'; // Reviews need moderation
            $data['helpful_count'] = 0;
            $data['reported_count'] = 0;

            $review = Review::create($data);

            // Log the creation for audit trail
            AuditLog::log(
                'create',
                'reviews',
                $review,
                [],
                $data,
                $user->id
            );

            Log::info('Review created via service', [
                'review_id' => $review->id,
                'content_type' => $review->content_type,
                'content_id' => $review->content_id,
                'rating' => $review->rating,
                'created_by' => $user->id,
                'tenant_id' => tenant('id')
            ]);

            return $review->load(['user']);
        });
    }

    /**
     * Update an existing review.
     */
    public function updateReview(Review $review, array $data, User $user): Review
    {
        return DB::transaction(function () use ($review, $data, $user) {
            // Verify user owns the review
            if ($review->user_id !== $user->id) {
                throw new Exception('You can only update your own reviews.');
            }

            $originalData = $review->toArray();

            // Reset status to pending if content changed
            if (isset($data['rating']) || isset($data['comment'])) {
                $data['status'] = 'pending';
            }

            $review->update($data);

            // Log the update for audit trail
            AuditLog::log(
                'update',
                'reviews',
                $review,
                $originalData,
                $data,
                $user->id
            );

            Log::info('Review updated via service', [
                'review_id' => $review->id,
                'changes' => array_keys($data),
                'updated_by' => $user->id,
                'tenant_id' => tenant('id')
            ]);

            return $review->fresh(['user']);
        });
    }

    /**
     * Delete a review.
     */
    public function deleteReview(Review $review, User $user): bool
    {
        return DB::transaction(function () use ($review, $user) {
            // Verify user owns the review or is admin
            if ($review->user_id !== $user->id && !$user->hasRole('admin')) {
                throw new Exception('You can only delete your own reviews.');
            }

            $reviewData = $review->toArray();
            $deleted = $review->delete();

            if ($deleted) {
                // Log the deletion for audit trail
                AuditLog::log(
                    'delete',
                    'reviews',
                    null,
                    $reviewData,
                    [],
                    $user->id
                );

                Log::info('Review deleted via service', [
                    'review_id' => $review->id,
                    'content_type' => $review->content_type,
                    'content_id' => $review->content_id,
                    'deleted_by' => $user->id,
                    'tenant_id' => tenant('id')
                ]);
            }

            return $deleted;
        });
    }

    /**
     * Mark review as helpful.
     */
    public function markHelpful(Review $review, User $user): Review
    {
        return DB::transaction(function () use ($review, $user) {
            // Check if user already marked this review as helpful
            // This would require a review_votes table in a full implementation
            
            $review->increment('helpful_count');

            Log::info('Review marked as helpful', [
                'review_id' => $review->id,
                'marked_by' => $user->id,
                'new_helpful_count' => $review->fresh()->helpful_count,
                'tenant_id' => tenant('id')
            ]);

            return $review->fresh();
        });
    }

    /**
     * Report a review for moderation.
     */
    public function reportReview(Review $review, User $user, string $reason): Review
    {
        return DB::transaction(function () use ($review, $user, $reason) {
            $review->increment('reported_count');
            
            // Auto-hide if too many reports
            if ($review->reported_count >= 5) {
                $review->update(['status' => 'hidden']);
            }

            Log::info('Review reported', [
                'review_id' => $review->id,
                'reported_by' => $user->id,
                'reason' => $reason,
                'new_reported_count' => $review->fresh()->reported_count,
                'tenant_id' => tenant('id')
            ]);

            return $review->fresh();
        });
    }

    /**
     * Moderate a review (approve/reject).
     */
    public function moderateReview(Review $review, string $status, User $moderator): Review
    {
        $validStatuses = ['approved', 'rejected', 'hidden'];
        
        if (!in_array($status, $validStatuses)) {
            throw new Exception("Invalid status. Must be one of: " . implode(', ', $validStatuses));
        }

        return DB::transaction(function () use ($review, $status, $moderator) {
            $originalStatus = $review->status;
            $review->update(['status' => $status]);

            Log::info('Review moderated', [
                'review_id' => $review->id,
                'old_status' => $originalStatus,
                'new_status' => $status,
                'moderated_by' => $moderator->id,
                'tenant_id' => tenant('id')
            ]);

            return $review->fresh();
        });
    }

    /**
     * Get review statistics for content.
     */
    public function getContentReviewStats(string $contentType, int $contentId): array
    {
        $reviews = Review::where('content_type', $contentType)
            ->where('content_id', $contentId)
            ->where('status', 'approved')
            ->get();

        if ($reviews->isEmpty()) {
            return [
                'total_reviews' => 0,
                'average_rating' => 0,
                'rating_distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            ];
        }

        $ratingDistribution = $reviews->groupBy('rating')->map->count()->toArray();
        
        // Fill missing ratings with 0
        for ($i = 1; $i <= 5; $i++) {
            if (!isset($ratingDistribution[$i])) {
                $ratingDistribution[$i] = 0;
            }
        }
        ksort($ratingDistribution);

        return [
            'total_reviews' => $reviews->count(),
            'average_rating' => round($reviews->avg('rating'), 2),
            'rating_distribution' => $ratingDistribution,
            'latest_review' => $reviews->sortByDesc('created_at')->first(),
        ];
    }

    /**
     * Get user's review for specific content.
     */
    public function getUserReview(string $contentType, int $contentId, User $user): ?Review
    {
        return Review::where('content_type', $contentType)
            ->where('content_id', $contentId)
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * Get reviews pending moderation.
     */
    public function getPendingReviews(int $perPage = 15): LengthAwarePaginator
    {
        return Review::where('status', 'pending')
            ->with(['user', 'content'])
            ->orderBy('created_at', 'asc')
            ->paginate($perPage);
    }
}
