<?php

namespace App\Http\Controllers\API\Tenant\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\Tenants\Role;
use App\Models\Tenants\Permission;
use App\Models\Tenants\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Tenant Admin Role Controller
 * 
 * Handles role management for tenant administrators.
 * This controller provides endpoints for managing roles, permissions,
 * and user role assignments within the tenant context.
 */
class TenantAdminRoleController extends BaseAPIController
{
    /**
     * Display a listing of roles and their permissions.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Role::with(['permissions', 'users']);
            
            // Filter by role type
            if ($request->has('type')) {
                if ($request->type === 'system') {
                    $query->system();
                } elseif ($request->type === 'custom') {
                    $query->custom();
                }
            }
            
            // Search by name or slug
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('slug', 'like', "%{$search}%");
                });
            }
            
            $roles = $query->orderBy('name')->get();
            
            return $this->sendResponse([
                'roles' => $roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'slug' => $role->slug,
                        'description' => $role->description,
                        'is_system' => $role->is_system,
                        'users_count' => $role->users->count(),
                        'permissions_count' => $role->permissions->count(),
                        'permissions' => $role->permissions->map(function ($permission) {
                            return [
                                'id' => $permission->id,
                                'name' => $permission->name,
                                'slug' => $permission->slug,
                                'group' => $permission->group,
                                'is_denied' => $permission->pivot->is_denied ?? false
                            ];
                        })
                    ];
                }),
                'total' => $roles->count()
            ], 'Roles retrieved successfully');
            
        } catch (\Exception $e) {
            Log::error('Error retrieving roles', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->sendError('Failed to retrieve roles', [], 500);
        }
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:roles,slug',
            'description' => 'nullable|string|max:1000',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $role = Role::create([
                'name' => $request->name,
                'slug' => $request->slug,
                'description' => $request->description,
                'is_system' => false,
                'metadata' => $request->metadata ?? []
            ]);

            // Attach permissions if provided
            if ($request->has('permissions')) {
                foreach ($request->permissions as $permissionId) {
                    $permission = Permission::find($permissionId);
                    if ($permission) {
                        $role->grantPermission($permission);
                    }
                }
            }

            DB::commit();

            Log::info('Role created', [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'created_by' => auth('tenant')->id()
            ]);

            return $this->sendResponse([
                'role' => $role->load('permissions')
            ], 'Role created successfully', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error creating role', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);
            
            return $this->sendError('Failed to create role', [], 500);
        }
    }

    /**
     * Display the specified role.
     */
    public function show(Role $role): JsonResponse
    {
        try {
            $role->load(['permissions', 'users.activeRoles']);
            
            return $this->sendResponse([
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'description' => $role->description,
                    'is_system' => $role->is_system,
                    'metadata' => $role->metadata,
                    'permissions' => $role->permissions->map(function ($permission) {
                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'slug' => $permission->slug,
                            'group' => $permission->group,
                            'is_denied' => $permission->pivot->is_denied ?? false,
                            'conditions' => $permission->pivot->conditions
                        ];
                    }),
                    'users' => $role->users->map(function ($user) {
                        return [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'membership' => $user->membership,
                            'is_active' => $user->pivot->is_active,
                            'assigned_at' => $user->pivot->assigned_at,
                            'expires_at' => $user->pivot->expires_at
                        ];
                    })
                ]
            ], 'Role retrieved successfully');
            
        } catch (\Exception $e) {
            Log::error('Error retrieving role', [
                'role_id' => $role->id,
                'error' => $e->getMessage()
            ]);
            
            return $this->sendError('Failed to retrieve role', [], 500);
        }
    }

    /**
     * Update the specified role.
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        if ($role->is_system) {
            return $this->sendError('System roles cannot be modified', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'slug')->ignore($role->id)
            ],
            'description' => 'nullable|string|max:1000',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $role->update($request->only(['name', 'slug', 'description']));

            // Update permissions if provided
            if ($request->has('permissions')) {
                // Detach all current permissions
                $role->permissions()->detach();
                
                // Attach new permissions
                foreach ($request->permissions as $permissionId) {
                    $permission = Permission::find($permissionId);
                    if ($permission) {
                        $role->grantPermission($permission);
                    }
                }
            }

            DB::commit();

            Log::info('Role updated', [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'updated_by' => auth('tenant')->id()
            ]);

            return $this->sendResponse([
                'role' => $role->load('permissions')
            ], 'Role updated successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error updating role', [
                'role_id' => $role->id,
                'error' => $e->getMessage()
            ]);
            
            return $this->sendError('Failed to update role', [], 500);
        }
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Role $role): JsonResponse
    {
        if ($role->is_system) {
            return $this->sendError('System roles cannot be deleted', [], 403);
        }

        try {
            DB::beginTransaction();

            // Check if role is assigned to any users
            if ($role->users()->count() > 0) {
                return $this->sendError('Cannot delete role that is assigned to users', [], 400);
            }

            // Detach all permissions
            $role->permissions()->detach();
            
            // Delete the role
            $role->delete();

            DB::commit();

            Log::info('Role deleted', [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'deleted_by' => auth('tenant')->id()
            ]);

            return $this->sendResponse([], 'Role deleted successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error deleting role', [
                'role_id' => $role->id,
                'error' => $e->getMessage()
            ]);
            
            return $this->sendError('Failed to delete role', [], 500);
        }
    }

    /**
     * Update permissions for a specific role.
     */
    public function updatePermissions(Request $request, Role $role): JsonResponse
    {
        if ($role->is_system) {
            return $this->sendError('System role permissions cannot be modified', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'permissions' => 'required|array',
            'permissions.*.id' => 'required|exists:permissions,id',
            'permissions.*.is_denied' => 'boolean',
            'permissions.*.conditions' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            // Clear existing permissions
            $role->permissions()->detach();

            // Add new permissions
            foreach ($request->permissions as $permissionData) {
                $permission = Permission::find($permissionData['id']);
                if ($permission) {
                    if ($permissionData['is_denied'] ?? false) {
                        $role->denyPermission($permission, $permissionData['conditions'] ?? []);
                    } else {
                        $role->grantPermission($permission, $permissionData['conditions'] ?? []);
                    }
                }
            }

            DB::commit();

            Log::info('Role permissions updated', [
                'role_id' => $role->id,
                'permissions_count' => count($request->permissions),
                'updated_by' => auth('tenant')->id()
            ]);

            return $this->sendResponse([
                'role' => $role->load('permissions')
            ], 'Role permissions updated successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error updating role permissions', [
                'role_id' => $role->id,
                'error' => $e->getMessage()
            ]);
            
            return $this->sendError('Failed to update role permissions', [], 500);
        }
    }

    /**
     * Assign role to users.
     */
    public function assignUsers(Request $request, Role $role): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'expires_at' => 'nullable|date',
            'conditions' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $assignedUsers = [];
            foreach ($request->user_ids as $userId) {
                $user = User::find($userId);
                if ($user && !$user->hasRole($role->slug)) {
                    $expiresAt = $request->expires_at ? new \DateTime($request->expires_at) : null;
                    $role->assignTo($user, $request->conditions ?? [], $expiresAt);
                    $assignedUsers[] = $user;
                }
            }

            DB::commit();

            Log::info('Role assigned to users', [
                'role_id' => $role->id,
                'user_count' => count($assignedUsers),
                'assigned_by' => auth('tenant')->id()
            ]);

            return $this->sendResponse([
                'assigned_users_count' => count($assignedUsers),
                'role' => $role->load('users')
            ], 'Role assigned to users successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error assigning role to users', [
                'role_id' => $role->id,
                'error' => $e->getMessage()
            ]);
            
            return $this->sendError('Failed to assign role to users', [], 500);
        }
    }

    /**
     * Remove role from users.
     */
    public function removeUsers(Request $request, Role $role): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors()->toArray(), 422);
        }

        try {
            DB::beginTransaction();

            $removedUsers = [];
            foreach ($request->user_ids as $userId) {
                $user = User::find($userId);
                if ($user && $user->hasRole($role->slug)) {
                    $role->removeFrom($user);
                    $removedUsers[] = $user;
                }
            }

            DB::commit();

            Log::info('Role removed from users', [
                'role_id' => $role->id,
                'user_count' => count($removedUsers),
                'removed_by' => auth('tenant')->id()
            ]);

            return $this->sendResponse([
                'removed_users_count' => count($removedUsers),
                'role' => $role->load('users')
            ], 'Role removed from users successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error removing role from users', [
                'role_id' => $role->id,
                'error' => $e->getMessage()
            ]);
            
            return $this->sendError('Failed to remove role from users', [], 500);
        }
    }
}