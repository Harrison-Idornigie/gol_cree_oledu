<?php

namespace App\Services\Auth;

use App\Models\Tenants\User;
use App\Models\Landlord\Tenant;
use App\Helpers\Tenants\TenantHelper;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Tenant Authentication Service
 * 
 * Handles all authentication logic for tenant users including:
 * - User credential validation
 * - Token creation and management
 * - User status checks
 * - Tenant-specific access control
 * - Login/logout operations
 */
class TenantAuthService
{
    protected UserTenantAssociationService $userTenantService;

    public function __construct(UserTenantAssociationService $userTenantService)
    {
        $this->userTenantService = $userTenantService;
    }

    /**
     * Authenticate user with email and password in tenant context
     * 
     * @param string $email
     * @param string $password
     * @param string $deviceName
     * @param Tenant $tenant
     * @return array [success, data, message, status_code]
     */
    public function authenticateUser(string $email, string $password, string $deviceName, Tenant $tenant): array
    {
        try {
            // Find user in tenant database
            $user = $this->findUserByEmail($email);

            if (!$user) {
                Log::warning('User not found in tenant database', [
                    'email' => $email,
                    'tenant_slug' => $tenant->slug
                ]);
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Invalid credentials',
                    'status_code' => 401
                ];
            }

            // Validate user status
            $statusCheck = $this->validateUserStatus($user, $tenant);
            if (!$statusCheck['valid']) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => $statusCheck['message'],
                    'status_code' => $statusCheck['status_code']
                ];
            }

            // Verify password
            if (!$this->verifyPassword($password, $user->password)) {
                Log::warning('Password verification failed', [
                    'email' => $email,
                    'tenant_slug' => $tenant->slug,
                    'user_id' => $user->id
                ]);
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Invalid credentials',
                    'status_code' => 401
                ];
            }

            // Check if user is allowed to login to this tenant
            if (!$this->isUserAllowedToLogin($user, $tenant)) {
                Log::warning('User not allowed to login to tenant', [
                    'email' => $email,
                    'tenant_slug' => $tenant->slug,
                    'user_id' => $user->id,
                    'user_membership' => $user->membership
                ]);
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'You do not have permission to access this organization.',
                    'status_code' => 403
                ];
            }

            // Create authentication token
            $token = $this->createUserToken($user, $deviceName);

            // Prepare user data for response
            $userData = $this->prepareUserData($user, $tenant);

            // Determine redirect path
            $redirectPath = $this->getPostLoginRedirectPath($user, $tenant);

            Log::info('User authenticated successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'tenant_slug' => $tenant->slug,
                'membership' => $user->membership
            ]);

            return [
                'success' => true,
                'data' => [
                    'token' => $token,
                    'user' => $userData,
                    'redirect' => $redirectPath,
                    'auth_context' => 'tenant',
                    'tenant_slug' => $tenant->slug,
                ],
                'message' => 'Successfully logged in',
                'status_code' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Tenant authentication error: ' . $e->getMessage(), [
                'email' => $email,
                'tenant_slug' => $tenant->slug,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'An unexpected error occurred during authentication',
                'status_code' => 500
            ];
        }
    }

    /**
     * Get user's available tenants
     * 
     * @param string $email
     * @return array [success, data, message, status_code]
     */
    public function getUserTenants(string $email): array
    {
        try {
            $userTenants = $this->userTenantService->getUserTenants($email);

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

            return [
                'success' => true,
                'data' => [
                    'tenants' => $tenantsData,
                    'count' => $tenantsData->count(),
                ],
                'message' => 'User tenants retrieved successfully',
                'status_code' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Get user tenants error: ' . $e->getMessage(), [
                'email' => $email,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve user tenants',
                'status_code' => 500
            ];
        }
    }

    /**
     * Logout user by deleting current token
     * 
     * @param User $user
     * @return array [success, data, message, status_code]
     */
    public function logoutUser(User $user): array
    {
        try {
            $tenant = TenantHelper::current();

            // Delete the current access token from tenant database
            $user->currentAccessToken()->delete();

            Log::info('Tenant user logged out successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'tenant_slug' => $tenant?->slug,
            ]);

            return [
                'success' => true,
                'data' => [],
                'message' => 'Logged out successfully',
                'status_code' => 200
            ];
        } catch (\Exception $e) {
            $tenant = TenantHelper::current();
            Log::error('Tenant logout error: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'tenant_slug' => $tenant?->slug,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Logout failed',
                'status_code' => 500
            ];
        }
    }

    /**
     * Find user by email in current tenant context
     */
    protected function findUserByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /**
     * Verify password against user's hashed password
     */
    protected function verifyPassword(string $password, string $hashedPassword): bool
    {
        return Hash::check($password, $hashedPassword);
    }

    /**
     * Create authentication token for user
     */
    protected function createUserToken(User $user, string $deviceName): string
    {
        return $user->createToken($deviceName)->plainTextToken;
    }

    /**
     * Validate user status and account conditions
     */
    protected function validateUserStatus(User $user, Tenant $tenant): array
    {
        // Check if user account is suspended
        if (isset($user->status) && $user->status === 'suspended') {
            Log::warning('Suspended user attempted login', [
                'email' => $user->email,
                'tenant_slug' => $tenant->slug,
                'user_id' => $user->id
            ]);
            return [
                'valid' => false,
                'message' => 'Your account has been suspended. Please contact support.',
                'status_code' => 403
            ];
        }

        // Add other status checks here (e.g., account expiration, etc.)

        return ['valid' => true];
    }

    /**
     * Check if user is allowed to login to the specified tenant
     */
    protected function isUserAllowedToLogin(User $user, Tenant $tenant): bool
    {
        // Default membership to 'student' if not set
        $userMembership = $user->membership ?? 'student';

        // Check if tenant allows the user's membership type
        $tenantSettings = $tenant->settings ?? [];
        $allowedMemberships = $tenantSettings['allowed_memberships'] ?? ['student', 'team', 'tenant-admin', 'admin'];

        if (!in_array($userMembership, $allowedMemberships)) {
            return false;
        }

        // Check if user has valid membership
        $validMemberships = ['student', 'team', 'tenant-admin', 'admin', 'super-admin'];
        if (!in_array($userMembership, $validMemberships)) {
            return false;
        }

        return true;
    }

    /**
     * Prepare user data for API response
     */
    protected function prepareUserData(User $user, Tenant $tenant): array
    {
        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'membership' => $user->membership ?? 'student', // Default to student if null
            'email_verified_at' => $user->email_verified_at,
            'total_points' => $user->total_points,
            'interface_language' => $user->interface_language,
        ];

        // Add tenant context to user data
        return TenantHelper::addTenantContextToUser($userData, $tenant);
    }

    /**
     * Determine the appropriate redirect path after login based on user membership and tenant
     */
    protected function getPostLoginRedirectPath(User $user, Tenant $tenant): string
    {
        $tenantSlug = $tenant->slug;

        if ($user->isTenantAdmin() || $user->isAdmin()) {
            return "/{$tenantSlug}/admin/dashboard";
        }

        if ($user->isTeam()) {
            return "/{$tenantSlug}/team/dashboard";
        }

        // Default to student dashboard
        return "/{$tenantSlug}/student/dashboard";
    }
}
