<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\Landlord\CentralUser;
use App\Services\Auth\UserTenantAssociationService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * User Context Controller
 * 
 * Handles user context detection for unified login experience.
 * This controller helps determine whether a user is a central user
 * or tenant user, and provides tenant associations for multi-tenant users.
 */
class UserContextController extends BaseAPIController
{
    protected UserTenantAssociationService $userTenantService;

    public function __construct(UserTenantAssociationService $userTenantService)
    {
        $this->userTenantService = $userTenantService;
    }

    /**
     * Detect user context based on email
     * 
     * This endpoint helps the frontend determine:
     * 1. Whether user is central (super admin) or tenant user
     * 2. Which tenants the user belongs to
     * 3. User's memberships in each tenant
     */
    public function detectUserContext(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
            ]);

            $email = $request->email;

            // Check if user exists in central database first
            $centralUser = CentralUser::where('email', $email)
                                    ->where('is_active', true)
                                    ->first();

            if ($centralUser) {
                return $this->sendResponse([
                    'userType' => 'central',
                    'tenants' => [],
                    'authStrategy' => 'central'
                ], 'User context detected');
            }

            // Check tenant associations
            $userTenants = $this->userTenantService->getUserTenants($email);

            if ($userTenants->isEmpty()) {
                return $this->sendError('User not found', [
                    'email' => 'No user found with this email address'
                ], 404);
            }

            // Format tenant data for frontend
            $tenantsData = $userTenants->map(function ($tenantData) {
                return [
                    'slug' => $tenantData['tenant']->slug,
                    'name' => $tenantData['tenant']->name,
                    'status' => $tenantData['tenant']->status,
                    'membership' => $tenantData['user']membership,
                    'defaultRedirect' => $this->getDefaultRedirectForMembership(
                        $tenantData['user']membership, 
                        $tenantData['tenant']->slug
                    )
                ];
            });

            return $this->sendResponse([
                'userType' => 'tenant',
                'tenants' => $tenantsData,
                'authStrategy' => $tenantsData->count() === 1 ? 'single-tenant' : 'multi-tenant'
            ], 'User context detected');

        } catch (ValidationException $e) {
            return $this->sendError('Validation error', $e->errors(), 422);
        } catch (Exception $e) {
            Log::error('User context detection error: ' . $e->getMessage(), [
                'email' => $request->email ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->sendError('Context detection failed', [
                'error' => 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Validate redirect URL and extract tenant context
     */
    public function validateRedirectUrl(Request $request)
    {
        try {
            $request->validate([
                'redirectUrl' => 'required|string',
            ]);

            $redirectUrl = $request->redirectUrl;
            $tenantContext = $this->extractTenantFromUrl($redirectUrl);

            if ($tenantContext) {
                // Validate that the tenant exists and is active
                $tenant = \App\Models\Landlord\Tenant::where('slug', $tenantContext['tenantSlug'])
                                                   ->where('status', 'active')
                                                   ->first();

                if (!$tenant) {
                    return $this->sendError('Invalid redirect URL', [
                        'redirectUrl' => 'Tenant not found or inactive'
                    ], 400);
                }

                return $this->sendResponse([
                    'isValid' => true,
                    'tenantSlug' => $tenantContext['tenantSlug'],
                    'membership' => $tenantContext['membership'],
                    'isTenantSpecific' => true
                ], 'Redirect URL validated');
            }

            // Check if it's a valid central route
            if ($this->isCentralRoute($redirectUrl)) {
                return $this->sendResponse([
                    'isValid' => true,
                    'isTenantSpecific' => false,
                    'requiresCentralAuth' => true
                ], 'Redirect URL validated');
            }

            return $this->sendError('Invalid redirect URL', [
                'redirectUrl' => 'URL does not match expected patterns'
            ], 400);

        } catch (ValidationException $e) {
            return $this->sendError('Validation error', $e->errors(), 422);
        } catch (Exception $e) {
            Log::error('Redirect URL validation error: ' . $e->getMessage(), [
                'redirectUrl' => $request->redirectUrl ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->sendError('Validation failed', [
                'error' => 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Extract tenant context from URL
     */
    protected function extractTenantFromUrl(string $url): ?array
    {
        // Remove domain and query parameters
        $path = parse_url($url, PHP_URL_PATH);
        
        // Match pattern: /{tenant-slug}/{membership}/*
        if (preg_match('/^\/([^\/]+)\/([^\/]+)/', $path, $matches)) {
            $tenantSlug = $matches[1];
            $membership = $matches[2];
            
            // Validate membership
            $validMemberships = ['admin', 'team', 'student'];
            if (in_array($membership, $validMemberships)) {
                return [
                    'tenantSlug' => $tenantSlug,
                    'membership' => $membership
                ];
            }
        }

        return null;
    }

    /**
     * Check if URL is a central route
     */
    protected function isCentralRoute(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);
        
        $centralPaths = ['/super', '/login', '/register'];
        
        foreach ($centralPaths as $centralPath) {
            if (str_starts_with($path, $centralPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get default redirect URL for user membership
     */
    protected function getDefaultRedirectForMembership(string $membership, string $tenantSlug): string
    {
        $membershipMap = [
            'tenant-admin' => 'admin',
            'admin' => 'admin',
            'team' => 'team',
            'team' => 'team',
            'student' => 'student'
        ];

        $mappedMembership = $membershipMap[$membership] ?? 'student';
        
        return "/{$tenantSlug}/{$mappedMembership}/dashboard";
    }
}
