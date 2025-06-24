<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class AdminInvite extends Model
{
    use BelongsToTenant, SoftDeletes;
    
    protected $fillable = [
        'email',
        'token',
        'invited_by',
        'expires_at',
        'used_at',
        'membership',
        'status',
        'metadata',
        'cancelled_by',
        'cancelled_at',
        'resent_at',
        'resent_by'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'resent_at' => 'datetime',
        'metadata' => 'array'
    ];

    /**
     * The user who sent the invitation.
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * The user who cancelled the invitation.
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * The user who resent the invitation.
     */
    public function resender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resent_by');
    }

    /**
     * Check if invitation is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at < now();
    }

    /**
     * Check if invitation is used.
     */
    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    /**
     * Check if invitation is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending' && !$this->isExpired() && !$this->isUsed();
    }

    /**
     * Check if invitation is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Mark invitation as used.
     */
    public function markAsUsed(): void
    {
        $this->update([
            'used_at' => now(),
            'status' => 'accepted'
        ]);
    }

    /**
     * Cancel the invitation.
     */
    public function cancel(User $user): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_by' => $user->id,
            'cancelled_at' => now()
        ]);
    }

    /**
     * Resend the invitation.
     */
    public function resend(User $user): void
    {
        $this->update([
            'resent_by' => $user->id,
            'resent_at' => now(),
            'expires_at' => now()->addDays(7) // Extend expiry
        ]);
    }

    /**
     * Scope for pending invitations.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending')
                    ->whereNull('used_at')
                    ->where('expires_at', '>', now());
    }

    /**
     * Scope for expired invitations.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope for used invitations.
     */
    public function scopeUsed($query)
    {
        return $query->whereNotNull('used_at');
    }

    /**
     * Scope for cancelled invitations.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }
}