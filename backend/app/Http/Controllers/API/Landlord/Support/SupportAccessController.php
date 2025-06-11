<?php

namespace App\Http\Controllers\API\Landlord\Support;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\Landlord\Support\TenantSupportAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Support Access Controller
 * 
 * Handles support access token authentication and tenant context switching
 * for central staff providing customer support.
 */
class SupportAccessController extends BaseAPIController
{
    protected TenantSupportAccessService $supportService;

    public function __construct(TenantSupportAccessService $supportService)
    {
        $this->supportService = $supportService;
    }

    /**
     * Use support access token to authenticate into tenant context
     * 
     * This endpoint allows central staff to use a support access token
     * to authenticate directly into a tenant context for support purposes.
     */
    public function useSupportAccess(Request $request, string $token)
    {
        try {
            // Use the support access token
            $result = $this->supportService->useSupportAccessToken($token);
            
            $tenant = $result['tenant'];
            $user = $result['user'];
            $tokenData = $result['token'];

            // Generate authentication token within tenant context
            $authToken = null;
            $tenant->run(function () use ($user, &$authToken) {
                $authToken = $user->createToken('support-access-token', ['support-access'])->plainTextToken;
            });

            // Prepare response data
            $responseData = [
                'token' => $authToken,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'tenant' => [
                    'id' => $tenant->id,
                    'slug' => $tenant->slug,
                    'name' => $tenant->name,
                ],
                'support_context' => [
                    'impersonator_email' => $tokenData->impersonator_email,
                    'reason' => $tokenData->reason,
                    'expires_at' => $tokenData->expires_at,
                    'permissions' => $tokenData->permissions,
                ],
                'redirect_url' => $result['redirect_url'],
            ];

            return $this->sendResponse($responseData, 'Support access authenticated successfully.');

        } catch (\Exception $e) {
            Log::warning('Support access token usage failed', [
                'token' => substr($token, 0, 8) . '...',
                'error' => $e->getMessage(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->sendError('Support access failed', ['error' => $e->getMessage()], 401);
        }
    }

    /**
     * Get current support access context
     * 
     * Returns information about the current support access session
     * if the user is authenticated via support access token.
     */
    public function getSupportContext(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return $this->sendError('Not authenticated', [], 401);
            }

            // Check if current token has support access abilities
            $currentToken = $user->currentAccessToken();
            $isSupportAccess = $currentToken && in_array('support-access', $currentToken->abilities ?? []);

            if (!$isSupportAccess) {
                return $this->sendResponse([
                    'is_support_access' => false,
                ], 'Not in support access mode.');
            }

            // Get tenant context
            $tenant = tenant();
            
            if (!$tenant) {
                return $this->sendError('No tenant context available', [], 400);
            }

            return $this->sendResponse([
                'is_support_access' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                'tenant' => [
                    'id' => $tenant->id,
                    'slug' => $tenant->slug,
                    'name' => $tenant->name,
                ],
                'token_abilities' => $currentToken->abilities ?? [],
            ], 'Support context retrieved successfully.');

        } catch (\Exception $e) {
            return $this->sendError('Failed to get support context', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * End support access session
     * 
     * Revokes the current support access token and logs the session end.
     */
    public function endSupportAccess(Request $request)
    {
        try {
            $user = $request->user();
            $currentToken = $user?->currentAccessToken();

            if (!$currentToken || !in_array('support-access', $currentToken->abilities ?? [])) {
                return $this->sendError('Not in support access mode', [], 400);
            }

            // Get tenant context for logging
            $tenant = tenant();
            
            // Revoke the token
            $currentToken->delete();

            // Log support session end
            Log::info('Support access session ended', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'tenant_id' => $tenant?->id,
                'tenant_slug' => $tenant?->slug,
                'ip_address' => $request->ip(),
                'session_duration' => $currentToken->created_at->diffInMinutes(now()) . ' minutes',
            ]);

            return $this->sendResponse([], 'Support access session ended successfully.');

        } catch (\Exception $e) {
            return $this->sendError('Failed to end support access', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Validate support access token without using it
     * 
     * Allows checking if a support access token is valid before using it.
     */
    public function validateSupportToken(Request $request, string $token)
    {
        try {
            $tokenModel = \App\Models\Landlord\ImpersonationToken::where('token', $token)->first();

            if (!$tokenModel) {
                return $this->sendError('Invalid token', [], 404);
            }

            $isValid = $tokenModel->isValid();
            $status = $isValid ? 'valid' : ($tokenModel->isExpired() ? 'expired' : 
                     ($tokenModel->isUsed() ? 'used' : 'revoked'));

            $responseData = [
                'is_valid' => $isValid,
                'status' => $status,
                'expires_at' => $tokenModel->expires_at,
                'tenant' => [
                    'slug' => $tokenModel->tenant->slug,
                    'name' => $tokenModel->tenant->name,
                ],
                'target_user_email' => $tokenModel->user_email,
                'reason' => $tokenModel->reason,
            ];

            if ($isValid) {
                return $this->sendResponse($responseData, 'Token is valid.');
            } else {
                return $this->sendResponse($responseData, "Token is {$status}.");
            }

        } catch (\Exception $e) {
            return $this->sendError('Failed to validate token', ['error' => $e->getMessage()], 500);
        }
    }
}
