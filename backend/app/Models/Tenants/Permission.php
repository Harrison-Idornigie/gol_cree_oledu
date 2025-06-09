<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'group',
        'description',
        'is_system',
        'metadata',
        'conditions'
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'metadata' => 'array',
        'conditions' => 'array'
    ];

    /**
     * Roles that have this permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withPivot(['conditions', 'is_denied'])
            ->withTimestamps();
    }

    /**
     * Get all users that have this permission through their roles.
     */
    public function users()
    {
        return User::whereHas('roles', function ($query) {
            $query->whereHas('permissions', function ($query) {
                $query->where('permissions.id', $this->id)
                    ->where('permission_role.is_denied', false);
            });
        });
    }

    /**
     * Check if the permission is granted to a specific role.
     */
    public function isGrantedTo(Role $role): bool
    {
        return $this->roles()
            ->where('roles.id', $role->id)
            ->where('is_denied', false)
            ->exists();
    }

    /**
     * Check if the permission matches given conditions.
     */
    public function matchesConditions(array $conditions): bool
    {
        if (empty($this->conditions)) {
            return true;
        }

        foreach ($this->conditions as $key => $value) {
            if (!isset($conditions[$key]) || $conditions[$key] !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create a new permission or update if it exists.
     */
    public static function createOrUpdate(string $name, string $slug, array $attributes = []): self
    {
        $permission = static::firstOrNew([
            'slug' => $slug
        ]);

        $permission->fill(array_merge([
            'name' => $name
        ], $attributes));

        $permission->save();

        return $permission;
    }

    /**
     * Scope a query to permissions in a specific group.
     */
    public function scopeInGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    /**
     * Scope a query to only system permissions.
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope a query to only custom permissions.
     */
    public function scopeCustom($query)
    {
        return $query->where('is_system', false);
    }

    /**
     * Get all permission groups with their permissions.
     */
    public static function getGroupedPermissions()
    {
        return static::all()
            ->groupBy('group')
            ->map(function ($permissions) {
                return $permissions->mapWithKeys(function ($permission) {
                    return [$permission->slug => $permission];
                });
            });
    }

    /**
     * Check if this is a system permission.
     */
    public function isSystem(): bool
    {
        return $this->is_system;
    }

    /**
     * Check if this permission can be modified.
     */
    public function isModifiable(): bool
    {
        return !$this->is_system;
    }
}