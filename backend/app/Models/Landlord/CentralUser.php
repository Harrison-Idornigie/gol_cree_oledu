<?php

namespace App\Models\Landlord;

use App\Models\Tenants\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Central User Model
 *
 * This model represents users in the central/landlord database.
 * These are system-wide users like super admins who can manage
 * the entire multi-tenant system.
 *
 * This is separate from tenant-scoped users who exist within
 * individual tenant databases.
 */
class CentralUser extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The table associated with the model.
     * Uses the central database, not tenant databases.
     */
    protected $table = 'central_users';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'membership',
        'interface_language',
        'avatar',
        'is_active',
        'last_login_at',
        'metadata',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the memberships that belong to the user.
     * These are system-wide memberships, not tenant-specific memberships.
     */
    public function memberships(): BelongsToMany
    {
        return $this->belongsToMany(CentralRole::class, 'central_memberships')
            ->withTimestamps();
    }

    /**
     * Check if user has a specific membership.
     */
    public function hasRole(string $membership): bool
    {
        return $thismemberships()->where('slug', $membership)->exists() || $thismembership === $membership;
    }

    /**
     * Super Admin Role Check
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin') || $thismembership === 'admin';
    }

    /**
     * Check if user can manage tenants
     */
    public function canManageTenants(): bool
    {
        return $this->isSuperAdmin();
    }

    /**
     * Check if user can access tenant
     */
    public function canAccessTenant(Tenant $tenant): bool
    {
        return $this->isSuperAdmin();
    }

    /**
     * Scope for active users
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for super admins
     */
    public function scopeSuperAdmins($query)
    {
        return $query->where('membership', 'admin')
                    ->orWhereHas('memberships', function ($q) {
                        $q->where('slug', 'super-admin');
                    });
    }
}
