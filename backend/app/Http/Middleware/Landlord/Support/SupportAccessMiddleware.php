<?php

namespace App\Http\Middleware\Landlord\Support;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Support Access Middleware
 * 
 * Validates and tracks support access sessions, ensuring proper
 * audit logging and permission enforcement for central staff
 * accessing tenant contexts.
 */
class SupportAccessMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$permissions)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        $currentToken = $user->currentAccessToken();
        $isSupportAccess = $currentToken && in_array('support-access', $currentToken->abilities ?? []);

        if ($isSupportAccess) {
            // This is a support access session
            $this->trackSupportActivity($request, $user, $currentToken);
            
            // Check support-specific permissions if provided
            if (!empty($permissions)) {
                $hasPermission = $this->checkSupportPermissions($currentToken, $permissions);
                if (!$hasPermission) {
                    return response()->json([
                        'error' => 'Insufficient support permissions',
                        'required_permissions' => $permissions
                    ], 403);
                }
            }
        }

        return $next($request);
    }

    /**
     * Track support access activity for audit purposes
     */
    protected function trackSupportActivity(Request $request, $user, $token): void
    {
        // Only log significant actions, not every request
        $significantActions = [
            'POST', 'PUT', 'PATCH', 'DELETE'
        ];

        if (in_array($request->method(), $significantActions)) {
            Log::info('Support access activity', [
                'support_user_id' => $user->id,
                'support_user_email' => $user->email,
                'tenant_id' => tenant()?->id,
                'tenant_slug' => tenant()?->slug,
                'method' => $request->method(),
                'path' => $request->path(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'token_created_at' => $token->created_at,
            ]);
        }
    }

    /**
     * Check if support access token has required permissions
     */
    protected function checkSupportPermissions($token, array $requiredPermissions): bool
    {
        $tokenAbilities = $token->abilities ?? [];
        
        // Support access tokens always have 'support-access' ability
        if (!in_array('support-access', $tokenAbilities)) {
            return false;
        }

        // Check specific permissions if any are required
        foreach ($requiredPermissions as $permission) {
            if (!in_array($permission, $tokenAbilities)) {
                return false;
            }
        }

        return true;
    }
}
