<?php

namespace App\Services\Tenants\Roles;

use App\Models\Tenants\Role;
use App\Models\Tenants\Permission;
use App\Models\Tenants\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Role Service
 * 
 * Handles complex role operations, permission management, and caching
 * for the role-based permission system.
 */
class RoleService
{
    /**
     * Create a new role with permissions
     */
    public function createRole(array $data, array $permissionIds = []): Role
    {
        DB::beginTransaction();
        
        try {
            $role = Role::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
                'is_system' => $data['is_system'] ?? false,
                'metadata' => $data['metadata'] ?? []
            ]);

            if (!empty($permissionIds)) {
                $this->syncRolePermissions($role, $permissionIds);
            }

            DB::commit();

            Log::info('Role created via service', [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'permissions_count' => count($permissionIds)
            ]);

            return $role;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update role with permissions
     */
    public function updateRole(Role $role, array $data, array $permissionIds = null): Role
    {
        if ($role->is_system && isset($data['is_system']) && !$data['is_system']) {
            throw new \InvalidArgumentException('Cannot change system role to custom role');
        }

        DB::beginTransaction();
        
        try {
            $role->update($data);

            if ($permissionIds !== null) {
                $this->syncRolePermissions($role, $permissionIds);
            }

            DB::commit();

            // Clear cache for all users with this role
            $this->clearRoleCache($role);

            Log::info('Role updated via service', [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'permissions_synced' => $permissionIds !== null
            ]);

            return $role;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Sync role permissions with support for denied permissions
     */
    public function syncRolePermissions(Role $role, array $permissions): void
    {
        if ($role->is_system) {
            throw new \InvalidArgumentException('Cannot modify system role permissions');
        }

        $role->permissions()->detach();

        foreach ($permissions as $permission) {
            if (is_array($permission)) {
                // Format: ['id' => 1, 'is_denied' => false, 'conditions' => []]
                $permissionModel = Permission::find($permission['id']);
                if ($permissionModel) {
                    if ($permission['is_denied'] ?? false) {
                        $role->denyPermission($permissionModel, $permission['conditions'] ?? []);
                    } else {
                        $role->grantPermission($permissionModel, $permission['conditions'] ?? []);
                    }
                }
            } else {
                // Simple permission ID
                $permissionModel = Permission::find($permission);
                if ($permissionModel) {
                    $role->grantPermission($permissionModel);
                }
            }
        }
    }

    /**
     * Bulk assign role to multiple users
     */
    public function bulkAssignRole(Role $role, array $userIds, array $options = []): array
    {
        $assigned = [];
        $skipped = [];
        $errors = [];

        DB::beginTransaction();
        
        try {
            foreach ($userIds as $userId) {
                try {
                    $user = User::findOrFail($userId);
                    
                    if ($user->hasRole($role->slug)) {
                        $skipped[] = [
                            'user_id' => $userId,
                            'reason' => 'User already has this role'
                        ];
                        continue;
                    }

                    $expiresAt = isset($options['expires_at']) ? new \DateTime($options['expires_at']) : null;
                    $role->assignTo($user, $options['conditions'] ?? [], $expiresAt);
                    
                    $assigned[] = $userId;
                    
                } catch (\Exception $e) {
                    $errors[] = [
                        'user_id' => $userId,
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            // Clear cache for affected users
            $this->clearUsersCache($assigned);

            Log::info('Bulk role assignment completed', [
                'role_id' => $role->id,
                'assigned_count' => count($assigned),
                'skipped_count' => count($skipped),
                'error_count' => count($errors)
            ]);

            return [
                'assigned' => $assigned,
                'skipped' => $skipped,
                'errors' => $errors
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Bulk remove role from multiple users
     */
    public function bulkRemoveRole(Role $role, array $userIds): array
    {
        $removed = [];
        $skipped = [];
        $errors = [];

        DB::beginTransaction();
        
        try {
            foreach ($userIds as $userId) {
                try {
                    $user = User::findOrFail($userId);
                    
                    if (!$user->hasRole($role->slug)) {
                        $skipped[] = [
                            'user_id' => $userId,
                            'reason' => 'User does not have this role'
                        ];
                        continue;
                    }

                    $role->removeFrom($user);
                    $removed[] = $userId;
                    
                } catch (\Exception $e) {
                    $errors[] = [
                        'user_id' => $userId,
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            // Clear cache for affected users
            $this->clearUsersCache($removed);

            Log::info('Bulk role removal completed', [
                'role_id' => $role->id,
                'removed_count' => count($removed),
                'skipped_count' => count($skipped),
                'error_count' => count($errors)
            ]);

            return [
                'removed' => $removed,
                'skipped' => $skipped,
                'errors' => $errors
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get role hierarchy and dependencies
     */
    public function getRoleHierarchy(): array
    {
        $cacheKey = 'role_hierarchy';
        
        return Cache::remember($cacheKey, 3600, function () {
            $roles = Role::with(['permissions', 'users'])->get();
            
            return $roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'is_system' => $role->is_system,
                    'users_count' => $role->users->count(),
                    'permissions' => $role->permissions->map(function ($permission) {
                        return [
                            'id' => $permission->id,
                            'slug' => $permission->slug,
                            'group' => $permission->group,
                            'is_denied' => $permission->pivot->is_denied ?? false
                        ];
                    }),
                    'effective_permissions' => $role->getEffectivePermissions()
                ];
            })->toArray();
        });
    }

    /**
     * Validate role assignment conflicts
     */
    public function validateRoleAssignment(User $user, Role $role): array
    {
        $conflicts = [];
        $warnings = [];

        // Check if user already has the role
        if ($user->hasRole($role->slug)) {
            $conflicts[] = 'User already has this role';
        }

        // Check for permission conflicts with existing roles
        $existingRoles = $user->activeRoles;
        $newPermissions = $role->getEffectivePermissions();

        foreach ($existingRoles as $existingRole) {
            $existingPermissions = $existingRole->getEffectivePermissions();
            $overlap = array_intersect($existingPermissions, $newPermissions);
            
            if (!empty($overlap)) {
                $warnings[] = "Role '{$existingRole->name}' has overlapping permissions: " . implode(', ', $overlap);
            }
        }

        // Check for membership compatibility
        $membershipPermissions = $user->getFixedPermissions();
        $permissionOverlap = array_intersect($membershipPermissions, $newPermissions);
        
        if (!empty($permissionOverlap)) {
            $warnings[] = "User's membership already provides permissions: " . implode(', ', $permissionOverlap);
        }

        return [
            'conflicts' => $conflicts,
            'warnings' => $warnings,
            'can_assign' => empty($conflicts)
        ];
    }

    /**
     * Get permission usage statistics
     */
    public function getPermissionStatistics(): array
    {
        $cacheKey = 'permission_statistics';
        
        return Cache::remember($cacheKey, 1800, function () {
            $stats = [];
            
            $permissions = Permission::withCount(['roles', 'users'])->get();
            
            foreach ($permissions as $permission) {
                $stats[] = [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'slug' => $permission->slug,
                    'group' => $permission->group,
                    'roles_count' => $permission->roles_count,
                    'users_count' => $permission->users_count,
                    'is_system' => $permission->is_system
                ];
            }

            return $stats;
        });
    }

    /**
     * Clean up orphaned role assignments
     */
    public function cleanupOrphanedAssignments(): array
    {
        $cleaned = [];
        
        DB::beginTransaction();
        
        try {
            // Remove expired role assignments
            $expiredCount = DB::table('user_roles')
                ->where('expires_at', '<', now())
                ->where('expires_at', '!=', null)
                ->delete();
            
            if ($expiredCount > 0) {
                $cleaned['expired_assignments'] = $expiredCount;
            }

            // Remove assignments to non-existent users
            $orphanedUsers = DB::table('user_roles')
                ->leftJoin('users', 'user_roles.user_id', '=', 'users.id')
                ->whereNull('users.id')
                ->delete();
            
            if ($orphanedUsers > 0) {
                $cleaned['orphaned_user_assignments'] = $orphanedUsers;
            }

            // Remove assignments to non-existent roles
            $orphanedRoles = DB::table('user_roles')
                ->leftJoin('roles', 'user_roles.role_id', '=', 'roles.id')
                ->whereNull('roles.id')
                ->delete();
            
            if ($orphanedRoles > 0) {
                $cleaned['orphaned_role_assignments'] = $orphanedRoles;
            }

            DB::commit();

            Log::info('Role cleanup completed', $cleaned);
            
            return $cleaned;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Clear cache for a specific role
     */
    protected function clearRoleCache(Role $role): void
    {
        $users = $role->users;
        foreach ($users as $user) {
            $user->clearPermissionCache();
        }
    }

    /**
     * Clear cache for multiple users
     */
    protected function clearUsersCache(array $userIds): void
    {
        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if ($user) {
                $user->clearPermissionCache();
            }
        }
    }

    /**
     * Export role configuration
     */
    public function exportRoleConfiguration(): array
    {
        $roles = Role::with(['permissions'])->get();
        
        return $roles->map(function ($role) {
            return [
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'metadata' => $role->metadata,
                'permissions' => $role->permissions->map(function ($permission) {
                    return [
                        'slug' => $permission->slug,
                        'is_denied' => $permission->pivot->is_denied ?? false,
                        'conditions' => $permission->pivot->conditions
                    ];
                })
            ];
        })->toArray();
    }

    /**
     * Import role configuration
     */
    public function importRoleConfiguration(array $rolesData): array
    {
        $imported = [];
        $skipped = [];
        $errors = [];

        DB::beginTransaction();
        
        try {
            foreach ($rolesData as $roleData) {
                try {
                    // Check if role already exists
                    $existingRole = Role::where('slug', $roleData['slug'])->first();
                    
                    if ($existingRole && $existingRole->is_system) {
                        $skipped[] = [
                            'slug' => $roleData['slug'],
                            'reason' => 'System role cannot be modified'
                        ];
                        continue;
                    }

                    // Create or update role
                    $role = Role::updateOrCreate(
                        ['slug' => $roleData['slug']],
                        [
                            'name' => $roleData['name'],
                            'description' => $roleData['description'] ?? null,
                            'is_system' => $roleData['is_system'] ?? false,
                            'metadata' => $roleData['metadata'] ?? []
                        ]
                    );

                    // Sync permissions if provided
                    if (isset($roleData['permissions'])) {
                        $permissionIds = [];
                        
                        foreach ($roleData['permissions'] as $permissionData) {
                            $permission = Permission::where('slug', $permissionData['slug'])->first();
                            if ($permission) {
                                $permissionIds[] = [
                                    'id' => $permission->id,
                                    'is_denied' => $permissionData['is_denied'] ?? false,
                                    'conditions' => $permissionData['conditions'] ?? []
                                ];
                            }
                        }
                        
                        $this->syncRolePermissions($role, $permissionIds);
                    }

                    $imported[] = $role->slug;
                    
                } catch (\Exception $e) {
                    $errors[] = [
                        'slug' => $roleData['slug'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            Log::info('Role import completed', [
                'imported_count' => count($imported),
                'skipped_count' => count($skipped),
                'error_count' => count($errors)
            ]);

            return [
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}