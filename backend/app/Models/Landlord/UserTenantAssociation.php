<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * User Tenant Association Model
 * 
 * Central mapping table that tracks which users belong to which tenants.
 * This eliminates the need to query every tenant database for user lookups.
 * 
 * @property int $id
 * @property string $email
 * @property string $tenant_id
 * @property string $tenant_slug
 * @property string|null $membership
 * @property array|null $permissions
 * @property \Carbon\Carbon|null $last_accessed_at
 * @property bool $is_active
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class UserTenantAssociation extends Model
{
    use HasFactory;

    /**
     * The database connection that should be used by the model.
     * Always use the central/landlord database connection.
     */
    protected $connection = 'mysql';

    protected $fillable = [
        'email',
        'tenant_id',
        'tenant_slug',
        'membership',
        'permissions',
        'last_accessed_at',
        'is_active',
    ];

    protected $casts = [
        'permissions' => 'array',
        'last_accessed_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the tenant that this association belongs to
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope to get active associations only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get associations for a specific email
     */
    public function scopeForEmail($query, string $email)
    {
        return $query->where('email', $email);
    }

    /**
     * Scope to get associations for a specific tenant
     */
    public function scopeForTenant($query, string $tenantSlug)
    {
        return $query->where('tenant_slug', $tenantSlug);
    }

    /**
     * Update last accessed timestamp
     */
    public function updateLastAccessed(): void
    {
        $this->update(['last_accessed_at' => now()]);
    }

    /**
     * Get the most recently accessed tenant for this email
     */
    public static function getMostRecentTenant(string $email): ?self
    {
        return static::forEmail($email)
                    ->active()
                    ->orderByDesc('last_accessed_at')
                    ->first();
    }

    /**
     * Get all active tenants for an email with caching
     */
    public static function getUserTenants(string $email): \Illuminate\Support\Collection
    {
        $cacheKey = "user_tenants_{$email}";
        
        return cache()->remember($cacheKey, 300, function () use ($email) {
            return static::with('tenant')
                        ->forEmail($email)
                        ->active()
                        ->whereHas('tenant', function ($query) {
                            $query->where('status', 'active');
                        })
                        ->orderByDesc('last_accessed_at')
                        ->get();
        });
    }

    /**
     * Check if user has access to specific tenant
     */
    public static function hasAccessToTenant(string $email, string $tenantSlug): bool
    {
        return static::forEmail($email)
                    ->forTenant($tenantSlug)
                    ->active()
                    ->whereHas('tenant', function ($query) {
                        $query->where('status', 'active');
                    })
                    ->exists();
    }

    /**
     * Sync user association when user is created/updated in tenant
     * Optimized for high-volume operations
     */
    public static function syncFromTenant(string $email, Tenant $tenant, array $userData): self
    {
        // Use upsert for better performance with high volume
        $attributes = [
            'email' => $email,
            'tenant_id' => $tenant->id,
            'tenant_slug' => $tenant->slug,
            'membership' => $userData['membership'] ?? null,
            'permissions' => $userData['permissions'] ?? null,
            'is_active' => $userData['is_active'] ?? true,
            'last_accessed_at' => now(),
            'updated_at' => now(),
        ];

        // Try to update first, then create if not exists
        $updated = static::where('email', $email)
                        ->where('tenant_id', $tenant->id)
                        ->update($attributes);

        if (!$updated) {
            $attributes['created_at'] = now();
            return static::create($attributes);
        }

        return static::where('email', $email)
                    ->where('tenant_id', $tenant->id)
                    ->first();
    }

    /**
     * Remove association when user is deleted from tenant
     */
    public static function removeFromTenant(string $email, string $tenantSlug): bool
    {
        return static::forEmail($email)
                    ->forTenant($tenantSlug)
                    ->delete();
    }

    /**
     * Clear cache for user's tenant associations
     */
    public static function clearUserCache(string $email): void
    {
        cache()->forget("user_tenants_{$email}");
    }
}
