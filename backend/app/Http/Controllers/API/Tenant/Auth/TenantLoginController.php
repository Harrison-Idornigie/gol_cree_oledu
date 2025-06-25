<?php

namespace App\Http\Controllers\API\Tenant\Auth;

use App\Helpers\Tenants\TenantHelper;
use App\Http\Controllers\API\BaseAPIController;
use App\Services\Auth\TenantAuthService;
use App\Services\Auth\UserTenantAssociationService;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
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
    protected TenantAuthService $tenantAuthService;
    protected UserTenantAssociationService $userTenantService;

    public function __construct(
        TenantAuthService $tenantAuthService,
        UserTenantAssociationService $userTenantService
    ) {
        $this->tenantAuthService = $tenantAuthService;
        $this->userTenantService = $userTenantService;
    }

    public function login(Request $request)
    {
        try {
            $request->validate([
                'email'    => 'required|email',
                'password' => 'required',
                'device_name' => 'required|string',
            ]);

            // Get current tenant context
            $tenant = TenantHelper::current();
            if (!$tenant) {
                return $this->sendError('Tenant context required', ['tenant' => 'No tenant context available'], 400);
            }

            // Check if user can access tenant authentication
            $this->authorize('accessTenantAuth', ['tenant-auth', $tenant]);

            // Check if login is allowed for this tenant
            $this->authorize('login', ['tenant-auth', $tenant]);

            // Use service to handle authentication
            $result = $this->tenantAuthService->authenticateUser(
                $request->email,
                $request->password,
                $request->device_name,
                $tenant
            );

            if ($result['success']) {
                return $this->sendResponse($result['data'], $result['message']);
            } else {
                if ($result['status_code'] === 401) {
                    return $this->sendUnauthorizedResponse($result['message']);
                } else {
                    return $this->sendError($result['message'], [], $result['status_code']);
                }
            }
        } catch (ValidationException $e) {
            return $this->sendError('Validation error', $e->errors(), 422);
        } catch (Exception $e) {
            Log::error('Tenant login controller error: ' . $e->getMessage(), [
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

            // Check if user can retrieve tenant list
            $this->authorize('getUserTenants', 'tenant-auth');

            // Use service to get user tenants
            $result = $this->tenantAuthService->getUserTenants($request->email);

            if ($result['success']) {
                return $this->sendResponse($result['data'], $result['message']);
            } else {
                return $this->sendError($result['message'], [], $result['status_code']);
            }
        } catch (ValidationException $e) {
            return $this->sendError('Validation error', $e->errors(), 422);
        } catch (AuthorizationException $e) {
            return $this->sendError('Forbidden', ['access' => 'You do not have permission to access this resource'], 403);
        } catch (Exception $e) {
            Log::error('Get user tenants controller error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->sendError('Failed to retrieve user tenants', ['error' => 'An unexpected error occurred'], 500);
        }
    }
}
