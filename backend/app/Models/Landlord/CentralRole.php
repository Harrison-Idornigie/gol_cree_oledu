<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Central Role Model
 *
 * This model represents system-wide roles in the central/landlord database.
 * These are roles for system administrators and other users who operate
 * across the entire multi-tenant system.
 *
 * This is separate from tenant-scoped roles which exist within
 * individual tenant databases.
 */
class CentralRole extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     * Uses the central database, not tenant databases.
     */
    protected $table = 'central_roles';

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
     * Users that belong to this role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(CentralUser::class, 'central_user_roles')
            ->withTimestamps();
    }

    /**
     * Scope for system roles
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Check if role has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions ?? [];
        return in_array($permission, $permissions);
    }

    /**
     * Add permission to role
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
     * Remove permission from role
     */
    public function removePermission(string $permission): void
    {
        $permissions = $this->permissions ?? [];
        $permissions = array_filter($permissions, fn($p) => $p !== $permission);
        $this->permissions = array_values($permissions);
        $this->save();
    }
}
