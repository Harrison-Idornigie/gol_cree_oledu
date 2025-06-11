<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Impersonation Token Model
 * 
 * Manages secure tokens for central staff to access tenant contexts
 * for support purposes. Includes comprehensive audit logging and
 * time-limited access controls.
 * 
 * @property string $token
 * @property string $tenant_id
 * @property string $user_email
 * @property string $impersonator_id
 * @property string $impersonator_email
 * @property string $auth_guard
 * @property string|null $redirect_url
 * @property string|null $reason
 * @property \Carbon\Carbon $expires_at
 * @property \Carbon\Carbon|null $used_at
 * @property \Carbon\Carbon|null $revoked_at
 * @property array|null $permissions
 * @property string|null $ip_address
 * @property string|null $user_agent
 */
class ImpersonationToken extends Model
{
    use HasFactory;

    protected $table = 'tenant_user_impersonation_tokens';
    protected $primaryKey = 'token';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'token',
        'tenant_id',
        'user_email',
        'impersonator_id',
        'impersonator_email',
        'auth_guard',
        'redirect_url',
        'reason',
        'expires_at',
        'permissions',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'revoked_at' => 'datetime',
        'permissions' => 'array',
    ];

    /**
     * Get the tenant this token is for
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the central user performing the impersonation
     */
    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'impersonator_id');
    }

    /**
     * Generate a new impersonation token
     */
    public static function generate(
        Tenant $tenant,
        string $userEmail,
        CentralUser $impersonator,
        array $options = []
    ): self {
        $token = Str::random(128);
        $expiresAt = now()->addMinutes($options['duration_minutes'] ?? 60); // Default 1 hour

        return static::create([
            'token' => $token,
            'tenant_id' => $tenant->id,
            'user_email' => $userEmail,
            'impersonator_id' => $impersonator->id,
            'impersonator_email' => $impersonator->email,
            'auth_guard' => $options['auth_guard'] ?? 'tenant',
            'redirect_url' => $options['redirect_url'] ?? null,
            'reason' => $options['reason'] ?? null,
            'expires_at' => $expiresAt,
            'permissions' => $options['permissions'] ?? null,
            'ip_address' => $options['ip_address'] ?? null,
            'user_agent' => $options['user_agent'] ?? null,
        ]);
    }

    /**
     * Check if token is valid and usable
     */
    public function isValid(): bool
    {
        return $this->expires_at->isFuture() 
            && is_null($this->used_at) 
            && is_null($this->revoked_at);
    }

    /**
     * Check if token is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if token has been used
     */
    public function isUsed(): bool
    {
        return !is_null($this->used_at);
    }

    /**
     * Check if token has been revoked
     */
    public function isRevoked(): bool
    {
        return !is_null($this->revoked_at);
    }

    /**
     * Mark token as used
     */
    public function markAsUsed(): void
    {
        $this->update(['used_at' => now()]);
    }

    /**
     * Revoke the token
     */
    public function revoke(string $reason = null): void
    {
        $this->update([
            'revoked_at' => now(),
            'reason' => $this->reason . ($reason ? " | Revoked: {$reason}" : ' | Revoked')
        ]);
    }

    /**
     * Get active tokens for a user
     */
    public static function getActiveTokensForUser(string $email, string $tenantId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = static::where('user_email', $email)
                      ->whereNull('used_at')
                      ->whereNull('revoked_at')
                      ->where('expires_at', '>', now());

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get();
    }

    /**
     * Get impersonation history for audit
     */
    public static function getImpersonationHistory(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = static::with(['tenant', 'impersonator']);

        if (isset($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (isset($filters['impersonator_id'])) {
            $query->where('impersonator_id', $filters['impersonator_id']);
        }

        if (isset($filters['user_email'])) {
            $query->where('user_email', $filters['user_email']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('created_at')->get();
    }

    /**
     * Clean up expired tokens
     */
    public static function cleanupExpired(): int
    {
        return static::where('expires_at', '<', now()->subDays(7))->delete();
    }

    /**
     * Scope for valid tokens
     */
    public function scopeValid($query)
    {
        return $query->whereNull('used_at')
                    ->whereNull('revoked_at')
                    ->where('expires_at', '>', now());
    }

    /**
     * Scope for expired tokens
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }
}
