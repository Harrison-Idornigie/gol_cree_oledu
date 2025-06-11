<?php

namespace App\Services\Landlord\Support;

use App\Models\Landlord\CentralUser;
use App\Models\Landlord\ImpersonationToken;
use App\Models\Landlord\Tenant;
use App\Models\Landlord\UserTenantAssociation;
use App\Models\Tenants\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Tenant Support Access Service
 * 
 * Provides secure mechanisms for central staff to access tenant contexts
 * for customer support purposes. Includes comprehensive audit logging,
 * time-limited access, and permission controls.
 */
class TenantSupportAccessService
{
    /**
     * Generate support access token for tenant user impersonation
     */
    public function generateSupportAccess(
        CentralUser $centralUser,
        Tenant $tenant,
        string $userEmail,
        array $options = []
    ): array {
        // Validate central user permissions
        if (!$centralUser->isSuperAdmin()) {
            throw new \Exception('Only super administrators can generate support access tokens');
        }

        // Validate tenant is active
        if (!$tenant->isActive()) {
            throw new \Exception('Cannot generate access token for inactive tenant');
        }

        // Check if user exists in tenant
        $userExists = $this->verifyUserExistsInTenant($tenant, $userEmail);
        if (!$userExists) {
            throw new \Exception("User {$userEmail} not found in tenant {$tenant->slug}");
        }

        // Generate secure token
        $token = ImpersonationToken::generate($tenant, $userEmail, $centralUser, [
            'duration_minutes' => $options['duration_minutes'] ?? 60,
            'reason' => $options['reason'] ?? 'Customer support access',
            'redirect_url' => $options['redirect_url'] ?? "/{$tenant->slug}/admin/dashboard",
            'permissions' => $options['permissions'] ?? $this->getDefaultSupportPermissions(),
            'ip_address' => $options['ip_address'] ?? request()->ip(),
            'user_agent' => $options['user_agent'] ?? request()->userAgent(),
        ]);

        // Log support access generation
        Log::info('Support access token generated', [
            'token_id' => $token->token,
            'impersonator_id' => $centralUser->id,
            'impersonator_email' => $centralUser->email,
            'tenant_id' => $tenant->id,
            'tenant_slug' => $tenant->slug,
            'target_user_email' => $userEmail,
            'reason' => $options['reason'] ?? 'Customer support access',
            'expires_at' => $token->expires_at,
            'ip_address' => request()->ip(),
        ]);

        return [
            'token' => $token->token,
            'access_url' => $this->generateAccessUrl($token),
            'expires_at' => $token->expires_at,
            'tenant' => $tenant,
            'user_email' => $userEmail,
        ];
    }

    /**
     * Use support access token to authenticate into tenant
     */
    public function useSupportAccessToken(string $tokenString): array
    {
        $token = ImpersonationToken::where('token', $tokenString)->first();

        if (!$token) {
            throw new \Exception('Invalid support access token');
        }

        if (!$token->isValid()) {
            $reason = $token->isExpired() ? 'expired' : ($token->isUsed() ? 'already used' : 'revoked');
            throw new \Exception("Support access token is {$reason}");
        }

        // Mark token as used
        $token->markAsUsed();

        // Get tenant and user
        $tenant = $token->tenant;
        $userEmail = $token->user_email;

        // Get user from tenant database
        $tenantUser = null;
        $tenant->run(function () use ($userEmail, &$tenantUser) {
            $tenantUser = User::where('email', $userEmail)->first();
        });

        if (!$tenantUser) {
            throw new \Exception('Target user no longer exists in tenant database');
        }

        // Log support access usage
        Log::info('Support access token used', [
            'token_id' => $token->token,
            'impersonator_id' => $token->impersonator_id,
            'impersonator_email' => $token->impersonator_email,
            'tenant_id' => $tenant->id,
            'tenant_slug' => $tenant->slug,
            'target_user_id' => $tenantUser->id,
            'target_user_email' => $userEmail,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return [
            'tenant' => $tenant,
            'user' => $tenantUser,
            'token' => $token,
            'redirect_url' => $token->redirect_url,
            'permissions' => $token->permissions,
        ];
    }

    /**
     * Create temporary tenant association for support access
     */
    public function createTemporarySupportAssociation(
        CentralUser $centralUser,
        Tenant $tenant,
        array $options = []
    ): UserTenantAssociation {
        // Validate permissions
        if (!$centralUser->isSuperAdmin()) {
            throw new \Exception('Only super administrators can create temporary tenant associations');
        }

        // Create temporary association
        $association = UserTenantAssociation::create([
            'email' => $centralUser->email,
            'tenant_id' => $tenant->id,
            'tenant_slug' => $tenant->slug,
            'user_role' => 'support-admin',
            'permissions' => $options['permissions'] ?? $this->getDefaultSupportPermissions(),
            'is_active' => true,
            'last_accessed_at' => now(),
        ]);

        // Log temporary association creation
        Log::info('Temporary support association created', [
            'association_id' => $association->id,
            'central_user_id' => $centralUser->id,
            'central_user_email' => $centralUser->email,
            'tenant_id' => $tenant->id,
            'tenant_slug' => $tenant->slug,
            'reason' => $options['reason'] ?? 'Support access',
            'ip_address' => request()->ip(),
        ]);

        return $association;
    }

    /**
     * Remove temporary support association
     */
    public function removeTemporarySupportAssociation(
        CentralUser $centralUser,
        Tenant $tenant
    ): bool {
        $removed = UserTenantAssociation::where('email', $centralUser->email)
            ->where('tenant_id', $tenant->id)
            ->where('user_role', 'support-admin')
            ->delete();

        if ($removed) {
            Log::info('Temporary support association removed', [
                'central_user_id' => $centralUser->id,
                'central_user_email' => $centralUser->email,
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
                'ip_address' => request()->ip(),
            ]);
        }

        return $removed > 0;
    }

    /**
     * Get support access audit log
     */
    public function getSupportAccessAudit(array $filters = []): array
    {
        $tokens = ImpersonationToken::getImpersonationHistory($filters);
        
        return [
            'total' => $tokens->count(),
            'active_sessions' => $tokens->where('used_at', '!=', null)
                                     ->where('expires_at', '>', now())
                                     ->count(),
            'tokens' => $tokens->map(function ($token) {
                return [
                    'id' => $token->token,
                    'impersonator' => [
                        'id' => $token->impersonator_id,
                        'email' => $token->impersonator_email,
                        'name' => $token->impersonator->name ?? 'Unknown',
                    ],
                    'tenant' => [
                        'id' => $token->tenant_id,
                        'slug' => $token->tenant->slug,
                        'name' => $token->tenant->name,
                    ],
                    'target_user_email' => $token->user_email,
                    'reason' => $token->reason,
                    'created_at' => $token->created_at,
                    'expires_at' => $token->expires_at,
                    'used_at' => $token->used_at,
                    'revoked_at' => $token->revoked_at,
                    'status' => $this->getTokenStatus($token),
                    'ip_address' => $token->ip_address,
                ];
            }),
        ];
    }

    /**
     * Revoke support access token
     */
    public function revokeSupportAccess(string $tokenString, string $reason = null): bool
    {
        $token = ImpersonationToken::where('token', $tokenString)->first();
        
        if (!$token) {
            return false;
        }

        $token->revoke($reason);

        Log::info('Support access token revoked', [
            'token_id' => $token->token,
            'impersonator_id' => $token->impersonator_id,
            'tenant_id' => $token->tenant_id,
            'reason' => $reason,
            'revoked_by' => auth()->user()?->email ?? 'system',
        ]);

        return true;
    }

    /**
     * Verify user exists in tenant database
     */
    protected function verifyUserExistsInTenant(Tenant $tenant, string $email): bool
    {
        $exists = false;
        
        $tenant->run(function () use ($email, &$exists) {
            $exists = User::where('email', $email)->exists();
        });

        return $exists;
    }

    /**
     * Generate access URL for support token
     */
    protected function generateAccessUrl(ImpersonationToken $token): string
    {
        return url("/api/support/access/{$token->token}");
    }

    /**
     * Get default support permissions
     */
    protected function getDefaultSupportPermissions(): array
    {
        return [
            'read_only' => true,
            'can_view_users' => true,
            'can_view_content' => true,
            'can_view_settings' => true,
            'can_modify_users' => false,
            'can_modify_content' => false,
            'can_modify_settings' => false,
            'can_delete' => false,
        ];
    }

    /**
     * Get token status for display
     */
    protected function getTokenStatus(ImpersonationToken $token): string
    {
        if ($token->isRevoked()) return 'revoked';
        if ($token->isExpired()) return 'expired';
        if ($token->isUsed()) return 'used';
        return 'active';
    }
}
