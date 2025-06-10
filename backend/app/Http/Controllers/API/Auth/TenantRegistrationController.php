<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\Landlord\Tenant;
use App\Services\Landlord\TenantService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
                'tenant.slug' => 'nullable|string|max:50',
                'tenant.description' => 'nullable|string|max:1000',

                // Admin user data
                'admin.name' => 'required|string|max:255',
                'admin.email' => 'required|email|max:255|unique:users,email',
                'admin.password' => 'required|string|min:8',
                'admin.password_confirmation' => 'required|string|same:admin.password',
            ]);

            // Generate slug if not provided or clean the provided one
            if (empty($validated['tenant']['slug'])) {
                $validated['tenant']['slug'] = $this->generateSlugFromName($validated['tenant']['name']);
            } else {
                // Clean and validate the provided slug
                $validated['tenant']['slug'] = $this->cleanSlug($validated['tenant']['slug']);
                if (!$this->isValidSlug($validated['tenant']['slug'])) {
                    return $this->sendError('Invalid organization slug format', [
                        'tenant.slug' => ['Organization slug must be 3-50 characters, contain only lowercase letters, numbers, and hyphens, and start/end with alphanumeric characters']
                    ], 422);
                }
            }

            // Check slug availability (after cleaning/generation)
            if (Tenant::where('slug', $validated['tenant']['slug'])->exists()) {
                return $this->sendError('Organization slug already taken', [
                    'tenant.slug' => ['This organization slug is already taken. Please choose a different one.']
                ], 422);
            }

            // Generate progress ID for tracking
            $progressId = 'tenant_' . uniqid();

            DB::beginTransaction();

            try {
                // Create tenant and admin user with progress tracking
                $result = $this->tenantService->createTenant(
                    $validated['tenant'],
                    $validated['admin'],
                    $progressId
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
                    'progress_id' => $progressId,
                    'user' => [
                        'id' => $adminUser->id,
                        'name' => $adminUser->name,
                        'email' => $adminUser->email,
                        'role' => 'tenant-admin', // Set the correct role
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
     * Clean a provided slug
     *
     * @param string $slug
     * @return string
     */
    protected function cleanSlug(string $slug): string
    {
        // Convert to lowercase and remove invalid characters
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9-]/', '', $slug);

        // Remove leading/trailing hyphens
        $slug = preg_replace('/^-+|-+$/', '', $slug);

        // Replace multiple consecutive hyphens with single hyphen
        $slug = preg_replace('/-+/', '-', $slug);

        // Limit length
        $slug = substr($slug, 0, 50);

        return $slug;
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

        // Clean the generated slug
        $slug = $this->cleanSlug($slug);

        // Ensure it's not empty and has valid format
        if (empty($slug) || !$this->isValidSlug($slug)) {
            $slug = 'org-' . Str::random(8);
        }

        // Make unique if already exists
        $originalSlug = $slug;
        $counter = 1;
        while (Tenant::where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * Check tenant creation progress
     *
     * @param Request $request
     * @param string $progressId
     * @return JsonResponse
     */
    public function checkProgress(Request $request, string $progressId): JsonResponse
    {
        try {
            $progressData = Cache::get("tenant_creation_progress:{$progressId}");

            if (!$progressData) {
                return $this->sendError('Progress not found', [], 404);
            }

            return $this->sendResponse($progressData, 'Progress retrieved successfully');

        } catch (Exception $e) {
            Log::error('Progress check error: ' . $e->getMessage(), [
                'progress_id' => $progressId,
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->sendError('Failed to check progress', ['error' => 'An unexpected error occurred'], 500);
        }
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
        if (strlen($slug) < 3 || strlen($slug) > 50) {
            return false;
        }

        return preg_match('/^[a-z0-9][a-z0-9-]*[a-z0-9]$/', $slug) === 1;
    }
}
