<?php

namespace App\Http\Controllers\API\Tenant\Admin;

use App\Http\Controllers\API\BaseAPIController;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use App\Models\Tenants\User;
use App\Models\Tenants\AdminInvite;
use App\Services\Tenants\Admin\UserManagementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

/**
 * Tenant Admin User Controller
 * 
 * Handles user management operations within tenant scope.
 * Access Level: Tenant Admin
 * Scope: Tenant-specific
 * 
 * This controller manages users within a specific tenant, including
 * invitations, membership management, and user activity monitoring.
 */
class TenantAdminUserController extends BaseAPIController
{
    use BelongsToTenant;

    protected UserManagementService $userManagementService;

    /**
     * Constructor - Apply tenant admin middleware and inject services
     */
    public function __construct(UserManagementService $userManagementService)
    {
        $this->middleware(['auth:sanctum', 'verified', 'tenant']);
        $this->userManagementService = $userManagementService;
    }

    /**
     * Get all users in the tenant.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getUsers(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        
        try {
            $filters = $request->only(['role', 'status', 'search', 'created_from', 'created_to']);
            $perPage = $request->get('per_page', 15);
            
            $users = $this->userManagementService->getUsers($filters, $perPage);
            
            return $this->sendResponse($users, 'Users retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve users', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get specific user details.
     * 
     * @param Request $request
     * @param User $user
     * @return JsonResponse
     */
    public function getUser(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);
        
        try {
            return $this->sendResponse($user->load(['roles', 'permissions']), 'User retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve user', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create a new user.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function createUser(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|string|in:student,team,admin',
            'password' => 'nullable|string|min:8|confirmed'
        ]);

        try {
            $user = $this->userManagementService->createUser($request->all(), $request->user());
            
            return $this->sendResponse($user, 'User created successfully.', 201);
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to create user', ['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Update user information.
     * 
     * @param Request $request
     * @param User $user
     * @return JsonResponse
     */
    public function updateUser(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'role' => 'sometimes|string|in:student,team,admin',
            'status' => 'sometimes|string|in:active,inactive,suspended,banned'
        ]);

        try {
            $updatedUser = $this->userManagementService->updateUser($user, $request->all(), $request->user());
            
            return $this->sendResponse($updatedUser, 'User updated successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to update user', ['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Send invitation to new user.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function sendInvite(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $request->validate([
            'email' => 'required|email',
            'membership' => 'required|string|in:student,team,admin',
            'metadata' => 'nullable|array'
        ]);

        try {
            $invitation = $this->userManagementService->sendInvitation($request->all(), $request->user());
            
            return $this->sendResponse($invitation, 'Invitation sent successfully.', 201);
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to send invitation', ['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Cancel an invitation.
     * 
     * @param Request $request
     * @param AdminInvite $invitation
     * @return JsonResponse
     */
    public function cancelInvite(Request $request, AdminInvite $invitation): JsonResponse
    {
        $this->authorize('delete', $invitation);

        try {
            $this->userManagementService->cancelInvitation($invitation, $request->user());
            
            return $this->sendResponse([], 'Invitation cancelled successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to cancel invitation', ['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Resend an invitation.
     * 
     * @param Request $request
     * @param AdminInvite $invitation
     * @return JsonResponse
     */
    public function resendInvite(Request $request, AdminInvite $invitation): JsonResponse
    {
        $this->authorize('update', $invitation);

        try {
            $this->userManagementService->resendInvitation($invitation, $request->user());
            
            return $this->sendResponse([], 'Invitation resent successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to resend invitation', ['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Get all invitations.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getInvitations(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AdminInvite::class);

        try {
            $filters = $request->only(['status', 'email', 'membership']);
            $perPage = $request->get('per_page', 15);
            
            $invitations = $this->userManagementService->getInvitations($filters, $perPage);
            
            return $this->sendResponse($invitations, 'Invitations retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve invitations', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update user membership.
     * 
     * @param Request $request
     * @param User $user
     * @return JsonResponse
     */
    public function updateMembership(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $request->validate([
            'role' => 'required|string|in:student,team,admin'
        ]);

        try {
            $updatedUser = $this->userManagementService->updateUser(
                $user, 
                ['role' => $request->role], 
                $request->user()
            );
            
            return $this->sendResponse($updatedUser, 'User membership updated successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to update membership', ['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Update user status.
     * 
     * @param Request $request
     * @param User $user
     * @return JsonResponse
     */
    public function updateStatus(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $request->validate([
            'status' => 'required|string|in:active,inactive,suspended,banned'
        ]);

        try {
            $this->userManagementService->updateUserStatus($user, $request->status, $request->user());
            
            return $this->sendResponse([], 'User status updated successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to update status', ['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Delete user.
     * 
     * @param Request $request
     * @param User $user
     * @return JsonResponse
     */
    public function deleteUser(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        try {
            $this->userManagementService->deleteUser($user, $request->user());
            
            return $this->sendResponse([], 'User deleted successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to delete user', ['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Get user activity logs.
     * 
     * @param Request $request
     * @param User $user
     * @return JsonResponse
     */
    public function getUserActivity(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);

        try {
            $filters = $request->only(['date_from', 'date_to']);
            $activity = $this->userManagementService->getUserActivity($user, $filters);
            
            return $this->sendResponse($activity, 'User activity retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve user activity', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get user statistics.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getUserStatistics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        try {
            $filters = $request->only(['date_from', 'date_to']);
            $statistics = $this->userManagementService->getUserStatistics($filters);
            
            return $this->sendResponse($statistics, 'User statistics retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendErrorResponse('Failed to retrieve statistics', ['error' => $e->getMessage()], 500);
        }
    }
}
