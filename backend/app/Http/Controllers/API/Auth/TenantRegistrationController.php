<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\Landlord\Tenant;
use App\Services\Landlord\TenantService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantRegistrationController extends BaseAPIController
{
    protected TenantService $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Register a new tenant admin and provision tenant database
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function registerTenantAdmin(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                // Tenant data
                'tenant.name' => 'required|string|max:255',
                'tenant.slug' => 'nullable|string|max:50|unique:tenants,slug|regex:/^[a-z0-9][a-z0-9-]*[a-z0-9]$/',
                'tenant.description' => 'nullable|string|max:1000',
                
                // Admin user data
                'admin.name' => 'required|string|max:255',
                'admin.email' => 'required|email|max:255',
                'admin.password' => 'required|string|min:8',
                'admin.password_confirmation' => 'required|string|same:admin.password',
            ]);

            // Generate slug if not provided
            if (empty($validated['tenant']['slug'])) {
                $validated['tenant']['slug'] = $this->generateSlugFromName($validated['tenant']['name']);
            }

            // Validate generated slug
            if (!$this->isValidSlug($validated['tenant']['slug'])) {
                return $this->sendError('Invalid organization slug format', [
                    'slug' => ['Organization slug must contain only lowercase letters, numbers, and hyphens']
                ], 422);
            }

            // Check slug availability
            if (Tenant::where('slug', $validated['tenant']['slug'])->exists()) {
                return $this->sendError('Organization slug already exists', [
                    'slug' => ['This organization slug is already taken']
                ], 422);
            }

            DB::beginTransaction();

            try {
                // Create tenant and admin user
                $result = $this->tenantService->createTenant(
                    $validated['tenant'],
                    $validated['admin']
                );

                $tenant = $result['tenant'];
                $adminUser = $result['admin_user'];

                // Generate auth token for the admin user
                $token = $adminUser->createToken('auth-token')->plainTextToken;

                DB::commit();

                Log::info('Tenant registration successful', [
                    'tenant_id' => $tenant->id,
                    'tenant_slug' => $tenant->slug,
                    'admin_email' => $adminUser->email,
                ]);

                return $this->sendCreatedResponse([
                    'token' => $token,
                    'user' => [
                        'id' => $adminUser->id,
                        'name' => $adminUser->name,
                        'email' => $adminUser->email,
                        'role' => $adminUser->role,
                        'email_verified_at' => $adminUser->email_verified_at,
                        'tenant_id' => $tenant->id,
                        'tenant' => [
                            'id' => $tenant->id,
                            'name' => $tenant->name,
                            'slug' => $tenant->slug,
                            'status' => $tenant->status,
                        ]
                    ],
                ], 'Organization created successfully. Please check your email to verify your account.');

            } catch (Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ValidationException $e) {
            return $this->sendError('Validation error', $e->errors(), 422);
        } catch (Exception $e) {
            Log::error('Tenant registration error: ' . $e->getMessage(), [
                'data' => $request->except(['admin.password', 'admin.password_confirmation']),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->sendError('Registration failed', ['error' => 'An unexpected error occurred'], 500);
        }
    }

    /**
     * Validate tenant slug availability
     *
     * @param Request $request
     * @param string $slug
     * @return JsonResponse
     */
    public function validateSlug(Request $request, string $slug): JsonResponse
    {
        try {
            // Validate slug format
            if (!$this->isValidSlug($slug)) {
                return $this->sendResponse([
                    'available' => false,
                    'error' => 'Invalid slug format. Use only lowercase letters, numbers, and hyphens.'
                ]);
            }

            // Check availability
            $exists = Tenant::where('slug', $slug)->exists();

            return $this->sendResponse([
                'available' => !$exists,
                'error' => $exists ? 'This organization slug is already taken' : null
            ]);

        } catch (Exception $e) {
            Log::error('Slug validation error: ' . $e->getMessage(), [
                'slug' => $slug,
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->sendError('Unable to validate slug availability', [], 500);
        }
    }

    /**
     * Generate slug from organization name
     *
     * @param string $name
     * @return string
     */
    protected function generateSlugFromName(string $name): string
    {
        $slug = Str::slug($name, '-');
        
        // Ensure it starts and ends with alphanumeric
        $slug = preg_replace('/^-+|-+$/', '', $slug);
        
        // Limit length
        $slug = substr($slug, 0, 50);
        
        // Ensure it's not empty and has valid format
        if (empty($slug) || !$this->isValidSlug($slug)) {
            $slug = 'org-' . Str::random(8);
        }

        // Make unique if already exists
        $originalSlug = $slug;
        $counter = 1;
        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Validate slug format
     *
     * @param string $slug
     * @return bool
     */
    protected function isValidSlug(string $slug): bool
    {
        // Must be 3-50 characters, start and end with alphanumeric, contain only lowercase letters, numbers, and hyphens
        return preg_match('/^[a-z0-9][a-z0-9-]{1,48}[a-z0-9]$/', $slug) === 1;
    }
}
