<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\Role;
use App\Models\Permission;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AdminRoleController extends BaseAPIController
{
    /**
     * Display a listing of roles.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Role::query();

            // Filter system/custom roles
            if ($request->has('system')) {
                $query->where('is_system', $request->boolean('system'));
            }

            // Search by name
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $roles = $query->with('permissions')
                ->orderBy('name')
                ->paginate($request->input('per_page', 15));

            return $this->sendPaginatedResponse($roles);
        } catch (Exception $e) {
            Log::error('Failed to fetch roles: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to fetch roles', [], 500);
        }
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255|unique:roles,name',
                'description' => 'nullable|string',
                'permissions' => 'array',
                'permissions.*' => 'exists:permissions,id'
            ]);

            DB::beginTransaction();
            
            $role = Role::create([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'description' => $request->description,
                'is_system' => false
            ]);

            if ($request->has('permissions')) {
                $role->permissions()->attach($request->permissions);
            }

            $role->load('permissions');
            
            DB::commit();
            return $this->sendCreatedResponse($role);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to create role: ' . $e->getMessage(), [
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to create role: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Display the specified role.
     */
    public function show(Role $role): JsonResponse
    {
        try {
            $role->load(['permissions', 'users']);
            return $this->sendResponse($role);
        } catch (Exception $e) {
            Log::error('Failed to show role: ' . $e->getMessage(), [
                'role_id' => $role->id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to show role', [], 500);
        }
    }

    /**
     * Update the specified role.
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        try {
            if ($role->is_system) {
                return $this->sendError('System roles cannot be modified.',['error' => 'System roles cannot be modified.'], 403);
            }

            $request->validate([
                'name' => "required|string|max:255|unique:roles,name,{$role->id}",
                'description' => 'nullable|string'
            ]);

            DB::beginTransaction();
            
            $role->update([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'description' => $request->description
            ]);
            
            DB::commit();
            return $this->sendResponse($role);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to update role: ' . $e->getMessage(), [
                'role_id' => $role->id,
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to update role: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Role $role): JsonResponse
    {
        try {
            if ($role->is_system) {
                return $this->sendError('System roles cannot be deleted.', ['error' => 'System roles cannot be deleted.'], 403);
            }

            if ($role->users()->exists()) {
                return $this->sendError('Cannot delete role with assigned users.', ['error' => 'Cannot delete role with assigned users.'], 409);
            }

            DB::beginTransaction();
            $role->delete();
            DB::commit();
            
            return $this->sendNoContentResponse();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete role: ' . $e->getMessage(), [
                'role_id' => $role->id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to delete role: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Update role permissions.
     */
    public function updatePermissions(Request $request, Role $role): JsonResponse
    {
        try {
            if ($role->is_system) {
                return $this->sendError('System role permissions cannot be modified.', ['error' => 'System role permissions cannot be modified.'], 403);
            }

            $request->validate([
                'permissions' => 'required|array',
                'permissions.*.id' => 'required|exists:permissions,id',
                'permissions.*.granted' => 'required|boolean',
                'permissions.*.conditions' => 'nullable|array'
            ]);

            DB::beginTransaction();
            
            // Clear existing permissions
            $role->permissions()->detach();

            // Attach new permissions with conditions
            foreach ($request->permissions as $permission) {
                if ($permission['granted']) {
                    $role->permissions()->attach($permission['id'], [
                        'conditions' => $permission['conditions'] ?? null,
                        'is_denied' => false
                    ]);
                }
            }

            $role->load('permissions');
            
            DB::commit();
            return $this->sendResponse($role);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to update role permissions: ' . $e->getMessage(), [
                'role_id' => $role->id,
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to update role permissions: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get all available permissions grouped by area.
     */
    public function getAvailablePermissions(): JsonResponse
    {
        try {
            $permissions = Permission::all()
                ->groupBy('group')
                ->map(function ($group) {
                    return $group->map(function ($permission) {
                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'slug' => $permission->slug,
                            'description' => $permission->description,
                            'is_system' => $permission->is_system,
                            'conditions' => $permission->conditions
                        ];
                    });
                });

            return $this->sendResponse($permissions);
        } catch (Exception $e) {
            Log::error('Failed to fetch available permissions: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to fetch available permissions', [], 500);
        }
    }

    /**
     * Get role statistics.
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_roles' => Role::count(),
                'system_roles' => Role::where('is_system', true)->count(),
                'custom_roles' => Role::where('is_system', false)->count(),
                'roles_with_users' => Role::whereHas('users')->count(),
                'permissions_per_role' => DB::table('permission_role')
                    ->selectRaw('role_id, COUNT(*) as count')
                    ->groupBy('role_id')
                    ->get()
                    ->avg('count') ?? 0,
                'most_used_permissions' => DB::table('permission_role')
                    ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                    ->selectRaw('permissions.name, COUNT(*) as usage_count')
                    ->groupBy('permissions.id', 'permissions.name')
                    ->orderByDesc('usage_count')
                    ->limit(5)
                    ->get()
            ];

            return $this->sendResponse($stats);
        } catch (Exception $e) {
            Log::error('Failed to fetch role statistics: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to fetch role statistics', [], 500);
        }
    }
}