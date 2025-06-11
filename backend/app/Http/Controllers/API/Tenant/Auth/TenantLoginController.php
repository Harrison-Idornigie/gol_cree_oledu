<?php

namespace App\Http\Controllers\API\Tenant\Auth;

use App\Helpers\Tenants\TenantHelper;
use App\Http\Controllers\API\BaseAPIController;
use App\Models\Tenants\User;
use App\Services\Auth\UserTenantAssociationService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Tenant Login Controller
 * 
 * Handles authentication for tenant users within specific tenant contexts.
 * This controller operates within tenant database context and creates
 * tokens in the tenant's personal_access_tokens table.
 * 
 * Supports multi-tenant user scenarios where a user might belong to
 * multiple tenants.
 */
class TenantLoginController extends BaseAPIController
{
    protected UserTenantAssociationService $userTenantService;

    public function __construct(UserTenantAssociationService $userTenantService)
    {
        $this->userTenantService = $userTenantService;
    }

    public function login(Request $request)
    {
        try {
            $request->validate([
                'email'    => 'required|email',
                'password' => 'required',
            ]);

            // Get current tenant context
            $tenant = TenantHelper::current();
            if (!$tenant) {
                return $this->sendError('Tenant context required', ['tenant' => 'No tenant context available'], 400);
            }

            // Use tenant guard for authentication
            if (!Auth::guard('tenant')->attempt($request->only('email', 'password'))) {
                Log::warning('Failed tenant login attempt', [
                    'email' => $request->email,
                    'tenant_slug' => $tenant->slug
                ]);
                return $this->sendUnauthorizedResponse('Invalid credentials');
            }

            $user = User::where('email', $request->email)->firstOrFail();
            
            // Create token in tenant database
            $token = $user->createToken('tenant-auth-token')->plainTextToken;

            // Prepare response data
            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'membership' => $user->membership,
                'email_verified_at' => $user->email_verified_at,
                'total_points' => $user->total_points,
                'interface_language' => $user->interface_language,
            ];

            // Add tenant context to user data
            $userData = TenantHelper::addTenantContextToUser($userData, $tenant);

            // Determine redirect path based on user membership and tenant
            $redirectPath = $this->getPostLoginRedirectPath($user, $tenant);

            $responseData = [
                'token' => $token,
                'user'  => $userData,
                'redirect' => $redirectPath,
                'auth_context' => 'tenant',
                'tenant_slug' => $tenant->slug,
            ];

            return $this->sendResponse($responseData, 'Successfully logged in');
            
        } catch (ValidationException $e) {
            return $this->sendError('Validation error', $e->errors(), 422);
        } catch (ModelNotFoundException $e) {
            Log::error('Tenant user not found during login', [
                'email' => $request->email,
                'tenant_slug' => $tenant->slug ?? 'unknown'
            ]);
            return $this->sendError('Authentication failed', ['email' => 'User not found in this organization'], 404);
        } catch (Exception $e) {
            Log::error('Tenant login error: ' . $e->getMessage(), [
                'tenant_slug' => $tenant->slug ?? 'unknown',
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->sendError('Login failed', ['error' => 'An unexpected error occurred'], 500);
        }
    }



    /**
     * Get user's available tenants for multi-tenant scenarios
     */
    public function getUserTenants(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
            ]);

            $userTenants = $this->userTenantService->getUserTenants($request->email);

            $tenantsData = $userTenants->map(function ($tenantData) {
                return [
                    'tenant' => [
                        'id' => $tenantData['tenant']->id,
                        'name' => $tenantData['tenant']->name,
                        'slug' => $tenantData['tenant']->slug,
                        'status' => $tenantData['tenant']->status,
                    ],
                    'user' => [
                        'id' => $tenantData['user']->id,
                        'membership' => $tenantData['user']->membership,
                    ],
                    'memberships' => $tenantData['memberships'],
                ];
            });

            return $this->sendResponse([
                'tenants' => $tenantsData,
                'count' => $tenantsData->count(),
            ], 'User tenants retrieved successfully');
            
        } catch (ValidationException $e) {
            return $this->sendError('Validation error', $e->errors(), 422);
        } catch (Exception $e) {
            Log::error('Get user tenants error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->sendError('Failed to retrieve user tenants', ['error' => 'An unexpected error occurred'], 500);
        }
    }

    /**
     * Determine the appropriate redirect path after login based on user membership and tenant
     */
    protected function getPostLoginRedirectPath(User $user, $tenant): string
    {
        $tenantSlug = $tenant->slug;

        if ($user->hasMembership('tenant-admin') || $user->hasMembership('admin')) {
            return "/{$tenantSlug}/admin/dashboard";
        }

        if ($user->hasMembership('team') || $user->hasMembership('team')) {
            return "/{$tenantSlug}/team/dashboard";
        }

        // Default to student dashboard
        return "/{$tenantSlug}/student/dashboard";
    }
}
