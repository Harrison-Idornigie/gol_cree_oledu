<?php

namespace App\Services\Landlord;

use App\Models\Landlord\Tenant;
use App\Models\Tenants\User as TenantUser;
use App\Models\Landlord\CentralUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * System User Service
 * 
 * Handles creation and management of system users for automated operations
 * like seeding, migrations, and other background tasks that require user context.
 * 
 * This service provides:
 * - System user creation in both central and tenant contexts
 * - Consistent authentication for seeding operations
 * - Proper cleanup and management of system users
 */
class SystemUserService
{
    const SYSTEM_USER_EMAIL = 'system@gol.internal';
    const SYSTEM_USER_NAME = 'System User';
    
    /**
     * Get or create a system user in the central database
     */
    public function getCentralSystemUser(): CentralUser
    {
        $systemUser = CentralUser::where('email', self::SYSTEM_USER_EMAIL)->first();
        
        if (!$systemUser) {
            $systemUser = CentralUser::create([
                'name' => self::SYSTEM_USER_NAME,
                'email' => self::SYSTEM_USER_EMAIL,
                'password' => Hash::make('system-' . config('app.key')),
                'email_verified_at' => now(),
                'is_system_user' => true,
            ]);
            
            Log::info('Central system user created', [
                'user_id' => $systemUser->id,
                'email' => $systemUser->email
            ]);
        }
        
        return $systemUser;
    }
    
    /**
     * Get or create a system user in a tenant context
     */
    public function getTenantSystemUser(Tenant $tenant): TenantUser
    {
        return $tenant->run(function () use ($tenant) {
            $systemUser = TenantUser::where('email', self::SYSTEM_USER_EMAIL)->first();
            
            if (!$systemUser) {
                $systemUser = TenantUser::create([
                    'name' => self::SYSTEM_USER_NAME,
                    'email' => self::SYSTEM_USER_EMAIL,
                    'password' => Hash::make('system-' . config('app.key')),
                    'email_verified_at' => now(),
                    'membership' => 'system',
                    'interface_language' => 'en',
                    'is_active' => true,
                    'tenant_id' => $tenant->id,
                ]);
                
                Log::info('Tenant system user created', [
                    'tenant_id' => $tenant->id,
                    'user_id' => $systemUser->id,
                    'email' => $systemUser->email
                ]);
            }
            
            return $systemUser;
        });
    }
    
    /**
     * Authenticate as system user for seeding operations
     * 
     * @param Tenant|null $tenant If provided, creates/uses tenant system user
     * @return CentralUser|TenantUser The authenticated system user
     */
    public function authenticateSystemUser(?Tenant $tenant = null)
    {
        if ($tenant) {
            $systemUser = $this->getTenantSystemUser($tenant);
            
            // Switch to tenant context and authenticate
            $tenant->run(function () use ($systemUser) {
                Auth::login($systemUser);
            });
            
            return $systemUser;
        } else {
            $systemUser = $this->getCentralSystemUser();
            Auth::login($systemUser);
            return $systemUser;
        }
    }
    
    /**
     * Execute a callback with system user authentication
     * 
     * @param callable $callback The operation to execute
     * @param Tenant|null $tenant Optional tenant context
     * @return mixed The result of the callback
     */
    public function executeAsSystemUser(callable $callback, ?Tenant $tenant = null)
    {
        $originalUser = Auth::user();
        
        try {
            $systemUser = $this->authenticateSystemUser($tenant);
            
            Log::debug('Executing operation as system user', [
                'system_user_id' => $systemUser->id,
                'tenant_id' => $tenant?->id,
                'original_user_id' => $originalUser?->id
            ]);
            
            if ($tenant) {
                return $tenant->run($callback);
            } else {
                return $callback();
            }
            
        } finally {
            // Restore original authentication state
            if ($originalUser) {
                Auth::login($originalUser);
            } else {
                Auth::logout();
            }
        }
    }
    
    /**
     * Clean up system users (for testing or maintenance)
     */
    public function cleanupSystemUsers(?Tenant $tenant = null): void
    {
        if ($tenant) {
            $tenant->run(function () {
                $deleted = TenantUser::where('email', self::SYSTEM_USER_EMAIL)->delete();
                if ($deleted > 0) {
                    Log::info('Tenant system user cleaned up', [
                        'tenant_id' => tenant()->id,
                        'deleted_count' => $deleted
                    ]);
                }
            });
        } else {
            $deleted = CentralUser::where('email', self::SYSTEM_USER_EMAIL)->delete();
            if ($deleted > 0) {
                Log::info('Central system user cleaned up', [
                    'deleted_count' => $deleted
                ]);
            }
        }
    }
    
    /**
     * Check if current user is a system user
     */
    public function isSystemUser(): bool
    {
        $user = Auth::user();
        return $user && $user->email === self::SYSTEM_USER_EMAIL;
    }
    
    /**
     * Get system user info for debugging
     */
    public function getSystemUserInfo(?Tenant $tenant = null): array
    {
        if ($tenant) {
            return $tenant->run(function () use ($tenant) {
                $systemUser = TenantUser::where('email', self::SYSTEM_USER_EMAIL)->first();
                return [
                    'exists' => !!$systemUser,
                    'user_id' => $systemUser?->id,
                    'tenant_id' => $tenant->id,
                    'context' => 'tenant'
                ];
            });
        } else {
            $systemUser = CentralUser::where('email', self::SYSTEM_USER_EMAIL)->first();
            return [
                'exists' => !!$systemUser,
                'user_id' => $systemUser?->id,
                'context' => 'central'
            ];
        }
    }
}
