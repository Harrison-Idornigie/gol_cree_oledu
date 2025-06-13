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
        'metadata'
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'metadata' => 'array'
    ];

    /**
     * Users that have this role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')
            ->withPivot(['conditions', 'is_active', 'assigned_at', 'expires_at'])
            ->withTimestamps();
    }

    /**
     * Get only active user assignments for this role.
     */
    public function activeUsers(): BelongsToMany
    {
        return $this->users()
            ->wherePivot('is_active', true)
            ->where(function ($query) {
                $query->whereNull('user_roles.expires_at')
                    ->orWhere('user_roles.expires_at', '>', now());
            });
    }

    /**
     * Permissions that belong to this role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
            ->withPivot(['conditions', 'is_denied'])
            ->withTimestamps();
    }

    /**
     * Get only granted permissions (not denied) for this role.
     */
    public function grantedPermissions(): BelongsToMany
    {
        return $this->permissions()->wherePivot('is_denied', false);
    }

    /**
     * Get denied permissions for this role.
     */
    public function deniedPermissions(): BelongsToMany
    {
        return $this->permissions()->wherePivot('is_denied', true);
    }

    /**
     * Check if this role has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        return $this->grantedPermissions()
            ->where('slug', $permission)
            ->exists();
    }

    /**
     * Check if this role explicitly denies a specific permission.
     */
    public function deniesPermission(string $permission): bool
    {
        return $this->deniedPermissions()
            ->where('slug', $permission)
            ->exists();
    }

    /**
     * Grant a permission to this role.
     */
    public function grantPermission(Permission $permission, array $conditions = []): void
    {
        $this->permissions()->syncWithoutDetaching([
            $permission->id => [
                'tenant_id' => $this->tenant_id,
                'conditions' => empty($conditions) ? null : json_encode($conditions),
                'is_denied' => false,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }

    /**
     * Deny a permission for this role.
     */
    public function denyPermission(Permission $permission, array $conditions = []): void
    {
        $this->permissions()->syncWithoutDetaching([
            $permission->id => [
                'tenant_id' => $this->tenant_id,
                'conditions' => empty($conditions) ? null : json_encode($conditions),
                'is_denied' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }

    /**
     * Revoke a permission from this role.
     */
    public function revokePermission(Permission $permission): void
    {
        $this->permissions()->detach($permission->id);
    }

    /**
     * Get all effective permissions for this role (granted but not denied).
     */
    public function getEffectivePermissions(): array
    {
        $granted = $this->grantedPermissions()->pluck('slug')->toArray();
        $denied = $this->deniedPermissions()->pluck('slug')->toArray();
        
        return array_diff($granted, $denied);
    }

    /**
     * Assign this role to a user.
     */
    public function assignTo(User $user, array $conditions = [], ?\DateTime $expiresAt = null): void
    {
        $this->users()->syncWithoutDetaching([
            $user->id => [
                'tenant_id' => $this->tenant_id,
                'conditions' => empty($conditions) ? null : json_encode($conditions),
                'is_active' => true,
                'assigned_at' => now(),
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }

    /**
     * Remove this role from a user.
     */
    public function removeFrom(User $user): void
    {
        $this->users()->detach($user->id);
    }

    /**
     * Create a new role or update if it exists.
     */
    public static function createOrUpdate(string $name, string $slug, array $attributes = []): self
    {
        $role = static::firstOrNew([
            'slug' => $slug
        ]);

        $role->fill(array_merge([
            'name' => $name
        ], $attributes));

        $role->save();

        return $role;
    }

    /**
     * Scope a query to only system roles.
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope a query to only custom roles.
     */
    public function scopeCustom($query)
    {
        return $query->where('is_system', false);
    }

    /**
     * Check if this is a system role.
     */
    public function isSystem(): bool
    {
        return $this->is_system;
    }

    /**
     * Check if this role can be modified.
     */
    public function isModifiable(): bool
    {
        return !$this->is_system;
    }
}