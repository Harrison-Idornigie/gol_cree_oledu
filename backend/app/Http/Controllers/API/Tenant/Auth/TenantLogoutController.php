<?php

namespace App\Http\Controllers\API\Tenant\Auth;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\Auth\TenantAuthService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Tenant Logout Controller
 *
 * Handles logout for tenant users within specific tenant contexts.
 * This controller operates within tenant database context and deletes
 * tokens from the tenant's personal_access_tokens table.
 */
class TenantLogoutController extends BaseAPIController
{
    protected TenantAuthService $tenantAuthService;

    public function __construct(TenantAuthService $tenantAuthService)
    {
        $this->tenantAuthService = $tenantAuthService;
    }

    public function logout(Request $request)
    {
        try {
            if (!$request->user()) {
                return $this->sendUnauthorizedResponse('User not authenticated');
            }

            // Check authorization
            $this->authorize('logout', 'tenant-auth');

            // Use service to handle logout
            $result = $this->tenantAuthService->logoutUser($request->user());

            if ($result['success']) {
                return $this->sendResponse($result['data'], $result['message']);
            } else {
                return $this->sendError($result['message'], [], $result['status_code']);
            }
        } catch (Exception $e) {
            Log::error('Tenant logout controller error: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->sendError('Logout failed', ['error' => 'An unexpected error occurred'], 500);
        }
    }
}
