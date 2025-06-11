<?php

namespace App\Services\Auth;

use App\Models\Landlord\CentralUser;
use App\Models\Landlord\Tenant;
use App\Models\Tenants\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * User Tenant Association Service
 * 
 * Handles multi-tenant user identification and association logic.
 * Manages the relationship between central users and their tenant memberships.
 */
class UserTenantAssociationService
{
    /**
     * Find all tenants a user belongs to by email
     * 
     * @param string $email
     * @return Collection<Tenant>
     */
    public function getUserTenants(string $email): Collection
    {
        $tenants = collect();
        
        // Get all active tenants
        $allTenants = Tenant::where('status', 'active')->get();
        
        foreach ($allTenants as $tenant) {
            try {
                // Check if user exists in this tenant
                $tenant->run(function () use ($email, $tenant, &$tenants) {
                    $user = User::where('email', $email)->first();
                    if ($user) {
                        $tenants->push([
                            'tenant' => $tenant,
                            'user' => $user,
                            'roles' => $user->roles()->pluck('slug')->toArray()
                        ]);
                    }
                });
            } catch (\Exception $e) {
                Log::warning('Failed to check user in tenant', [
                    'email' => $email,
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $tenants;
    }
    
    /**
     * Get user's primary tenant (first tenant they belong to or most recently accessed)
     * 
     * @param string $email
     * @return array|null [tenant, user, roles]
     */
    public function getPrimaryTenant(string $email): ?array
    {
        $userTenants = $this->getUserTenants($email);
        
        if ($userTenants->isEmpty()) {
            return null;
        }
        
        // For now, return the first tenant
        // TODO: Implement logic for "most recently accessed" or user preference
        return $userTenants->first();
    }
    
    /**
     * Check if user belongs to a specific tenant
     * 
     * @param string $email
     * @param string $tenantSlug
     * @return array|null [tenant, user, roles] if user belongs to tenant
     */
    public function getUserInTenant(string $email, string $tenantSlug): ?array
    {
        $tenant = Tenant::where('slug', $tenantSlug)
                        ->where('status', 'active')
                        ->first();
        
        if (!$tenant) {
            return null;
        }
        
        try {
            $result = null;
            $tenant->run(function () use ($email, $tenant, &$result) {
                $user = User::where('email', $email)->first();
                if ($user) {
                    $result = [
                        'tenant' => $tenant,
                        'user' => $user,
                        'roles' => $user->roles()->pluck('slug')->toArray()
                    ];
                }
            });
            
            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to check user in specific tenant', [
                'email' => $email,
                'tenant_slug' => $tenantSlug,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Handle OAuth user login with tenant detection
     * 
     * @param string $email
     * @param array $oauthData
     * @param string|null $requestedTenantSlug
     * @return array [success, data, message]
     */
    public function handleOAuthLogin(string $email, array $oauthData, ?string $requestedTenantSlug = null): array
    {
        // If specific tenant requested, check that tenant first
        if ($requestedTenantSlug) {
            $tenantAssociation = $this->getUserInTenant($email, $requestedTenantSlug);
            if ($tenantAssociation) {
                return [
                    'success' => true,
                    'data' => $tenantAssociation,
                    'message' => 'User authenticated in requested tenant'
                ];
            } else {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'User not found in requested organization'
                ];
            }
        }
        
        // No specific tenant requested - find user's tenants
        $userTenants = $this->getUserTenants($email);
        
        if ($userTenants->isEmpty()) {
            return [
                'success' => false,
                'data' => null,
                'message' => 'No organization found for this email address'
            ];
        }
        
        if ($userTenants->count() === 1) {
            // Single tenant - direct login
            return [
                'success' => true,
                'data' => $userTenants->first(),
                'message' => 'User authenticated successfully'
            ];
        }
        
        // Multiple tenants - return tenant selection data
        return [
            'success' => true,
            'data' => [
                'multiple_tenants' => true,
                'tenants' => $userTenants->map(function ($association) {
                    return [
                        'tenant' => [
                            'id' => $association['tenant']->id,
                            'name' => $association['tenant']->name,
                            'slug' => $association['tenant']->slug,
                            'status' => $association['tenant']->status,
                        ],
                        'roles' => $association['roles']
                    ];
                })->toArray()
            ],
            'message' => 'Multiple organizations found. Please select one.'
        ];
    }
    
    /**
     * Create or update central user record for tenant admin
     * 
     * @param array $userData
     * @param Tenant $tenant
     * @return CentralUser
     */
    public function createOrUpdateCentralUser(array $userData, Tenant $tenant): CentralUser
    {
        return CentralUser::updateOrCreate(
            ['email' => $userData['email']],
            [
                'name' => $userData['name'],
                'role' => 'tenant-admin',
                'interface_language' => $userData['interface_language'] ?? 'en',
                'is_active' => true,
                'metadata' => [
                    'primary_tenant_id' => $tenant->id,
                    'created_via' => 'tenant_registration',
                    'last_tenant_access' => $tenant->id
                ]
            ]
        );
    }
}
