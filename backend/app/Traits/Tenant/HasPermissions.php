<?php

namespace App\Traits\Tenant;

use App\Models\Tenants\Permission;
use App\Models\Tenants\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;

trait HasPermissions
{
    /**
     * Get all roles for this model.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot(['conditions', 'is_active', 'assigned_at', 'expires_at'])
            ->withTimestamps();
    }
    
    /**
     * Get only active roles for this model.
     */
    public function activeRoles(): BelongsToMany
    {
        return $this->roles()
            ->wherePivot('is_active', true)
            ->where(function ($query) {
                $query->whereNull('user_roles.expires_at')
                    ->orWhere('user_roles.expires_at', '>', now());
            });
    }
    
    /**
     * Check if the model has all of the specified permissions.
     */
    public function hasAllPermissions(array $permissions, array $context = []): bool
    {
        $cacheKey = $this->getPermissionCacheKey('has_all_permissions', $permissions, $context);
        
        return Cache::remember($cacheKey, 300, function () use ($permissions, $context) {
            foreach ($permissions as $permission) {
                if (!$this->hasPermission($permission, $context)) {
                    return false;
                }
            }
            
            return true;
        });
    }

    /**
     * Enhanced hasPermission with context support and caching
     */
    public function hasPermission($permission, array $context = []): bool
    {
        $cacheKey = $this->getPermissionCacheKey('has_permission', $permission, $context);
        
        return Cache::remember($cacheKey, 300, function () use ($permission, $context) {
            // 1. Check fixed permissions (for models that have them, like User)
            if (method_exists($this, 'getFixedPermissions') && $this->hasFixedPermission($permission)) {
                return true;
            }
            
            // 2. Check role-based permissions
            return $this->hasRolePermission($permission, $context);
        });
    }

    /**
     * Enhanced hasRole with caching
     */
    public function hasRole($role): bool
    {
        if (is_string($role)) {
            $cacheKey = $this->getPermissionCacheKey('has_role', $role);
            
            return Cache::remember($cacheKey, 600, function () use ($role) {
                return $this->activeRoles()
                    ->where('slug', $role)
                    ->exists();
            });
        }
        
        if ($role instanceof Role) {
            return $this->activeRoles()
                ->where('id', $role->id)
                ->exists();
        }
        
        return false;
    }

    /**
     * Enhanced hasAnyPermission with context support and caching
     */
    public function hasAnyPermission(array $permissions, array $context = []): bool
    {
        $cacheKey = $this->getPermissionCacheKey('has_any_permission', $permissions, $context);
        
        return Cache::remember($cacheKey, 300, function () use ($permissions, $context) {
            foreach ($permissions as $permission) {
                if ($this->hasPermission($permission, $context)) {
                    return true;
                }
            }
            
            return false;
        });
    }

    /**
     * Check if model has any of the given roles with caching
     */
    public function hasAnyRole(array $roles): bool
    {
        $cacheKey = $this->getPermissionCacheKey('has_any_role', $roles);
        
        return Cache::remember($cacheKey, 600, function () use ($roles) {
            return $this->activeRoles()
                ->whereIn('slug', $roles)
                ->exists();
        });
    }

    /**
     * Check if model has all of the given roles with caching
     */
    public function hasAllRoles(array $roles): bool
    {
        $cacheKey = $this->getPermissionCacheKey('has_all_roles', $roles);
        
        return Cache::remember($cacheKey, 600, function () use ($roles) {
            $modelRoles = $this->activeRoles()->pluck('slug')->toArray();
            return count(array_intersect($roles, $modelRoles)) === count($roles);
        });
    }

    /**
     * Assign a role to this model with cache invalidation
     */
    public function assignRole(Role $role, array $conditions = [], ?\DateTime $expiresAt = null): void
    {
        $role->assignTo($this, $conditions, $expiresAt);
        $this->clearPermissionCache();
    }

    /**
     * Remove a role from this model with cache invalidation
     */
    public function removeRole(Role $role): void
    {
        $role->removeFrom($this);
        $this->clearPermissionCache();
    }

    /**
     * Get cached role names for this model
     */
    public function getRoleNames(): array
    {
        $cacheKey = $this->getPermissionCacheKey('role_names');
        
        return Cache::remember($cacheKey, 600, function () {
            return $this->activeRoles()->pluck('name')->toArray();
        });
    }

    /**
     * Get cached role slugs for this model
     */
    public function getRoleSlugs(): array
    {
        $cacheKey = $this->getPermissionCacheKey('role_slugs');
        
        return Cache::remember($cacheKey, 600, function () {
            return $this->activeRoles()->pluck('slug')->toArray();
        });
    }

    /**
     * Get all effective permissions with caching
     */
    public function getAllPermissions(): array
    {
        $cacheKey = $this->getPermissionCacheKey('all_permissions');
        
        return Cache::remember($cacheKey, 600, function () {
            $permissions = [];
            
            // Add fixed permissions (if model supports them)
            if (method_exists($this, 'getFixedPermissions')) {
                $fixedPermissions = $this->getFixedPermissions();
                $permissions = array_merge($permissions, $fixedPermissions);
            }
            
            // Add role-based permissions
            $rolePermissions = $this->getCachedRolePermissions();
            $permissions = array_merge($permissions, $rolePermissions);
            
            return array_unique($permissions);
        });
    }

    /**
     * Get cached role permissions for this model
     */
    public function getCachedRolePermissions(): array
    {
        $cacheKey = $this->getPermissionCacheKey('role_permissions');
        
        return Cache::remember($cacheKey, 600, function () {
            $permissions = [];
            
            foreach ($this->activeRoles as $role) {
                if (method_exists($role, 'getEffectivePermissions')) {
                    $rolePermissions = $role->getEffectivePermissions();
                    $permissions = array_merge($permissions, $rolePermissions);
                }
            }
            
            return array_unique($permissions);
        });
    }

    /**
     * Clear permission cache for this model
     */
    public function clearPermissionCache(): void
    {
        // Clear cache entries for this model using pattern matching
        try {
            Cache::flush(); // Alternatively, use cache tags if available
        } catch (\Exception $e) {
            // Fallback: just let cache expire naturally
        }
    }

    /**
     * Check fixed permissions (for backward compatibility)
     */
    private function hasFixedPermission(string $permission): bool
    {
        if (!method_exists($this, 'getFixedPermissions')) {
            return false;
        }
        
        $fixedPermissions = $this->getFixedPermissions();
        return in_array($permission, $fixedPermissions);
    }

    /**
     * Check role-based permissions
     */
    private function hasRolePermission(string $permission, array $context = []): bool
    {
        foreach ($this->activeRoles as $role) {
            // First check if the role explicitly denies this permission
            if (method_exists($role, 'deniesPermission') && $role->deniesPermission($permission)) {
                continue; // Skip this role if it denies the permission
            }
            
            // Then check if the role grants this permission
            if (method_exists($role, 'hasPermission') && $role->hasPermission($permission)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Generate cache key for permission checks
     */
    private function getPermissionCacheKey(string $type, $data = null, array $context = []): string
    {
        $modelType = strtolower(class_basename($this));
        $modelId = $this->getKey() ?? 'new';
        $dataHash = $data ? md5(serialize($data)) : '';
        $contextHash = $context ? md5(serialize($context)) : '';
        
        return "{$modelType}_{$modelId}_{$type}_{$dataHash}_{$contextHash}";
    }

    /**
     * Boot method to handle cache invalidation on model events
     */
    public static function bootHasPermissions()
    {
        static::updated(function ($model) {
            if (method_exists($model, 'clearPermissionCache')) {
                $model->clearPermissionCache();
            }
        });
        
        static::deleting(function ($model) {
            if (method_exists($model, 'clearPermissionCache')) {
                $model->clearPermissionCache();
            }
        });
    }
}