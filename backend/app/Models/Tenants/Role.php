<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Role extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_system',
        'metadata',
        'tenant_id'
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'metadata' => 'array'
    ];

    /**
     * Users that belong to this role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('metadata')
            ->withTimestamps();
    }

    /**
     * Permissions that belong to this role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)
            ->withPivot(['conditions', 'is_denied'])
            ->withTimestamps();
    }

    /**
     * Check if the role has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        return $this->permissions()
            ->where('slug', $permission)
            ->where('is_denied', false)
            ->exists();
    }

    /**
     * Check if the role has any of the given permissions.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        return $this->permissions()
            ->whereIn('slug', $permissions)
            ->where('is_denied', false)
            ->exists();
    }

    /**
     * Check if the role has all of the given permissions.
     */
    public function hasAllPermissions(array $permissions): bool
    {
        $grantedPermissions = $this->permissions()
            ->whereIn('slug', $permissions)
            ->where('is_denied', false)
            ->pluck('slug');

        return count($permissions) === $grantedPermissions->count();
    }

    /**
     * Grant one or more permissions to the role.
     */
    public function grantPermissions(array $permissions, array $conditions = null): void
    {
        $this->permissions()->syncWithoutDetaching(
            collect($permissions)->mapWithKeys(function ($permission) use ($conditions) {
                return [
                    $permission => [
                        'conditions' => $conditions,
                        'is_denied' => false
                    ]
                ];
            })->all()
        );
    }

    /**
     * Revoke one or more permissions from the role.
     */
    public function revokePermissions(array $permissions): void
    {
        $this->permissions()->detach($permissions);
    }

    /**
     * Explicitly deny one or more permissions for the role.
     */
    public function denyPermissions(array $permissions): void
    {
        $this->permissions()->syncWithoutDetaching(
            collect($permissions)->mapWithKeys(function ($permission) {
                return [
                    $permission => [
                        'is_denied' => true
                    ]
                ];
            })->all()
        );
    }

    /**
     * Sync role permissions, maintaining deny status where applicable.
     */
    public function syncPermissions(array $permissions): void
    {
        $this->permissions()->sync(
            collect($permissions)->mapWithKeys(function ($permission) {
                return [
                    $permission => [
                        'is_denied' => false
                    ]
                ];
            })->all()
        );
    }

    /**
     * Get all effective permissions (including inherited).
     */
    public function getAllPermissions(): array
    {
        return $this->permissions()
            ->where('is_denied', false)
            ->get()
            ->pluck('slug')
            ->toArray();
    }
}