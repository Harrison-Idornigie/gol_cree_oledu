<?php

namespace App\Services\Auth;

use App\Models\Landlord\CentralUser;
use App\Models\Landlord\Tenant;
use App\Models\Landlord\UserTenantAssociation;
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
     * Check if optimized user tenant lookups are available
     */
    protected function shouldUseOptimizedLookup(): bool
    {
        // Check if the user_tenant_associations table exists and has data
        try {
            return DB::connection('landlord')
                ->table('user_tenant_associations')
                ->exists();
        } catch (\Exception $e) {
            Log::warning('Optimized user tenant lookup not available, falling back to legacy method', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    /**
     * Find all tenants a user belongs to by email (OPTIMIZED with fallback)
     *
     * Uses central mapping table when available, falls back to legacy method.
     * Performance: O(1) instead of O(n) where n = number of tenants
     *
     * @param string $email
     * @return Collection<array> [tenant, membership, permissions, last_accessed]
     */
    public function getUserTenants(string $email): Collection
    {
        // Check if optimized lookup is available
        if ($this->shouldUseOptimizedLookup()) {
            try {
                // Use the optimized central mapping approach
                $associations = UserTenantAssociation::getUserTenants($email);

                return $associations->map(function ($association) {
                    return [
                        'tenant' => $association->tenant,
                        'membership' => $association->membership,
                        'permissions' => $association->permissions ?? [],
                        'last_accessed_at' => $association->last_accessed_at,
                        'memberships' => [$association->membership], // For backward compatibility
                    ];
                });
            } catch (\Exception $e) {
                Log::warning('Optimized user tenant lookup failed, falling back to legacy method', [
                    'email' => $email,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Fallback to legacy method
        return $this->getUserTenantsLegacy($email);
    }


    /**
     * Get user's primary tenant (most recently accessed or first tenant)
     *
     * @param string $email
     * @return array|null [tenant, membership, permissions, memberships]
     */
    public function getPrimaryTenant(string $email): ?array
    {
        // Use optimized method to get most recent tenant
        $mostRecentAssociation = \App\Models\Landlord\UserTenantAssociation::getMostRecentTenant($email);

        if (!$mostRecentAssociation) {
            return null;
        }

        return [
            'tenant' => $mostRecentAssociation->tenant,
            'membership' => $mostRecentAssociation->membership,
            'permissions' => $mostRecentAssociation->permissions ?? [],
            'memberships' => [$mostRecentAssociation->membership], // For backward compatibility
        ];
    }

    /**
     * Check if user belongs to a specific tenant (OPTIMIZED)
     *
     * Uses central mapping table for fast lookup.
     *
     * @param string $email
     * @param string $tenantSlug
     * @return array|null [tenant, membership, permissions] if user belongs to tenant
     */
    public function getUserInTenant(string $email, string $tenantSlug): ?array
    {
        // Fast lookup using central mapping
        $association = \App\Models\Landlord\UserTenantAssociation::with('tenant')
            ->forEmail($email)
            ->forTenant($tenantSlug)
            ->active()
            ->whereHas('tenant', function ($query) {
                $query->where('status', 'active');
            })
            ->first();

        if (!$association) {
            return null;
        }

        // Update last accessed timestamp
        $association->updateLastAccessed();

        return [
            'tenant' => $association->tenant,
            'membership' => $association->membership,
            'permissions' => $association->permissions ?? [],
            'memberships' => [$association->membership], // For backward compatibility
        ];
    }

    /**
     * LEGACY: Check if user belongs to a specific tenant by querying tenant database
     *
     * ⚠️  WARNING: This method queries the tenant database directly.
     * Use only when you need fresh data from tenant database.
     *
     * @param string $email
     * @param string $tenantSlug
     * @return array|null [tenant, user, memberships] if user belongs to tenant
     */
    public function getUserInTenantLegacy(string $email, string $tenantSlug): ?array
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
                        'memberships' => $user->memberships()->pluck('slug')->toArray()
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
                        'memberships' => $association['memberships']
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
                'membership' => 'tenant-admin',
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

    /**
     * Legacy method to find all tenants a user belongs to (SLOW - O(n) where n = number of tenants)
     *
     * This method manually searches through all active tenants to find where a user exists.
     * It's used as a fallback when the optimized central mapping table is not available.
     *
     * @param string $email
     * @return Collection<array> [tenant, user, membership, permissions, memberships]
     */
    protected function getUserTenantsLegacy(string $email): Collection
    {
        $userTenants = collect();

        // Get all active tenants
        $tenants = \App\Models\Landlord\Tenant::where('status', 'active')->get();

        foreach ($tenants as $tenant) {
            try {
                // Initialize tenant context
                tenancy()->initialize($tenant);

                // Look for user in this tenant's database
                $user = \App\Models\Tenants\User::where('email', $email)->first();

                if ($user) {
                    $userTenants->push([
                        'tenant' => $tenant,
                        'user' => $user,
                        'membership' => $user->membership ?? 'student',
                        'permissions' => [],
                        'memberships' => [$user->membership ?? 'student'], // For backward compatibility
                        'last_accessed_at' => null,
                    ]);
                }
            } catch (\Exception $e) {
                // Log error but continue with other tenants
                Log::warning('Error checking user in tenant during legacy lookup', [
                    'email' => $email,
                    'tenant_id' => $tenant->id,
                    'tenant_slug' => $tenant->slug,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // End tenancy context
        tenancy()->end();

        return $userTenants;
    }
}
