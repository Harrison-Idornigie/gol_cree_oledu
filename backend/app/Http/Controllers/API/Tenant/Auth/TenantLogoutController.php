<?php

namespace App\Http\Controllers\API\Tenant\Auth;

use App\Helpers\Tenants\TenantHelper;
use App\Http\Controllers\API\BaseAPIController;
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
    public function logout(Request $request)
    {
        try {
            if (!$request->user()) {
                return $this->sendUnauthorizedResponse('User not authenticated');
            }

            // Get current tenant context for logging
            $tenant = TenantHelper::current();

            // Delete the current access token from tenant database
            $request->user()->currentAccessToken()->delete();

            Log::info('Tenant user logged out successfully', [
                'user_id' => $request->user()->id,
                'email' => $request->user()->email,
                'tenant_slug' => $tenant?->slug,
            ]);

            return $this->sendResponse([], 'Successfully logged out');

        } catch (Exception $e) {
            $tenant = TenantHelper::current();
            Log::error('Tenant logout error: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'tenant_slug' => $tenant?->slug,
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->sendError('Logout failed', ['error' => 'An unexpected error occurred'], 500);
        }
    }
}
