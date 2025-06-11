<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Central Membership Model
 *
 * This model represents system-wide memberships in the central/landlord database.
 * These are memberships for system administrators and other users who operate
 * across the entire multi-tenant system.
 *
 * This is separate from tenant-scoped memberships which exist within
 * individual tenant databases.
 */
class Centralmembership extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     * Uses the central database, not tenant databases.
     */
    protected $table = 'central_memberships';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_system',
        'permissions',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_system' => 'boolean',
        'permissions' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Users that belong to this membership.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(CentralUser::class, 'central_memberships')
            ->withTimestamps();
    }

    /**
     * Scope for system memberships
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Check if membership has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions ?? [];
        return in_array($permission, $permissions);
    }

    /**
     * Add permission to membership
     */
    public function addPermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];
        if (!in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->permissions = $permissions;
            $this->save();
        }
    }

    /**
     * Remove permission from membership
     */
    public function removePermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];
        $permissions = array_filter($permissions, fn($p) => $p !== $permission);
        $this->permissions = array_values($permissions);
        $this->save();
    }
}
