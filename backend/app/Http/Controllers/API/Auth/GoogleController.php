<?php
namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\Role;
use App\Models\User;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleController extends BaseAPIController
{
    /**
     * Exchange authorization code for token without browser redirect
     * Useful for mobile apps and other clients that can't use browser redirects
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function exchangeToken(Request $request): JsonResponse
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'code'          => 'required|string',
            'client_type'   => 'required|string|in:web,ios,android,other',
            'client_id'     => 'nullable|string',
            'code_verifier' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        try {
            // Get the authorization code from the request
            $code         = $request->input('code');
            $clientType   = $request->input('client_type');
            $codeVerifier = $request->input('code_verifier');

            // Get Google OAuth configuration
            $clientId     = Config::get('services.google.client_id');
            $clientSecret = Config::get('services.google.client_secret');
            $redirectUrl  = $this->getRedirectUrlForClient($clientType);

            // Exchange the authorization code for tokens
            $googleUser = $this->getGoogleUserFromCode($code, $redirectUrl, $codeVerifier);

            if (! $googleUser) {
                return $this->sendError('Authentication failed', ['error' => 'Failed to exchange code for token'], 401);
            }

            // Validate Google user data
            if (empty($googleUser->getId())) {
                Log::error('Google user ID is missing');
                return $this->sendError('Invalid user data', ['error' => 'Google user ID is missing'], 400);
            }

            if (empty($googleUser->getEmail())) {
                Log::error('Google user email is missing');
                return $this->sendError('Invalid user data', ['error' => 'Google user email is missing'], 400);
            }

            // Validate email domain
            $emailParts = explode('@', $googleUser->getEmail());
            if (count($emailParts) !== 2) {
                Log::warning('Invalid email format from Google', ['email' => $googleUser->getEmail()]);
                return $this->sendError('Invalid email format', ['email' => 'The provided email is not valid'], 400);
            }

            // Check if domain validation is enabled
            if (Config::get('services.google.validate_domains', true)) {
                $emailDomain    = $emailParts[1];
                $allowedDomains = $this->getAllowedDomains();

                if (! in_array($emailDomain, $allowedDomains)) {
                    Log::warning('Unauthorized domain access attempt', [
                        'email'  => $googleUser->getEmail(),
                        'domain' => $emailDomain,
                    ]);
                    return $this->sendError(
                        'Unauthorized email domain',
                        ['email' => 'This email domain is not authorized to access the application.'],
                        403
                    );
                }
            }

            // Find or create user (same logic as in handleGoogleCallback)
            $user = User::where('google_id', $googleUser->getId())->first();

            if (! $user) {
                // Check if email already exists
                $existingUser = User::where('email', $googleUser->getEmail())->first();

                if ($existingUser) {
                    try {
                        // Update existing user with Google credentials
                        $existingUser->update([
                            'google_id' => $googleUser->getId(),
                            'avatar'    => $googleUser->getAvatar() ?? null,
                        ]);
                        $user = $existingUser->fresh();
                    } catch (Exception $e) {
                        Log::error('Failed to update existing user with Google data', [
                            'user_id' => $existingUser->id,
                            'error'   => $e->getMessage(),
                        ]);
                        return $this->sendError('User update failed', ['error' => 'Failed to update user record'], 500);
                    }
                } else {
                    try {
                        // Check if this is an admin email
                        $isAdminEmail = false;
                        $adminDomains = Config::get('services.google.admin_domains', []);

                        if (is_string($adminDomains)) {
                            $adminDomains = array_map('trim', explode(',', $adminDomains));
                        }

                        if (! empty($adminDomains)) {
                            $emailParts   = explode('@', $googleUser->getEmail());
                            $domain       = $emailParts[1] ?? '';
                            $isAdminEmail = in_array($domain, $adminDomains);
                        }

                        // Create new user
                        $user = User::create([
                            'name'              => $googleUser->getName() ?? 'Google User',
                            'email'             => $googleUser->getEmail(),
                            'google_id'         => $googleUser->getId(),
                            'avatar'            => $googleUser->getAvatar() ?? null,
                            'password'          => bcrypt(Str::random(16)), // Random password as it's not needed for OAuth
                            'email_verified_at' => now(),                   // Google accounts are already verified
                            'role'              => $isAdminEmail ? 'admin' : 'user',
                        ]);

                        // Assign role if using role-based permissions
                        if (class_exists('\App\Models\Role')) {
                            $roleName = $isAdminEmail ? 'admin' : 'user';
                            $role     = Role::where('slug', $roleName)->first();
                            if ($role) {
                                $user->roles()->attach($role->id);
                            }
                        }
                    } catch (Exception $e) {
                        Log::error('Failed to create new user from Google data', [
                            'email' => $googleUser->getEmail(),
                            'error' => $e->getMessage(),
                        ]);
                        return $this->sendError('User creation failed', ['error' => 'Failed to create user record'], 500);
                    }
                }
            } else {
                try {
                    // Update existing Google user's avatar if changed
                    $user->update([
                        'avatar' => $googleUser->getAvatar() ?? $user->avatar,
                    ]);
                    $user = $user->fresh();
                } catch (Exception $e) {
                    Log::warning('Failed to update user avatar', [
                        'user_id' => $user->id,
                        'error'   => $e->getMessage(),
                    ]);
                    // Continue with login despite avatar update failure
                }
            }

            // Generate token for API authentication
            try {
                $token = $user->createToken('auth-token')->plainTextToken;
            } catch (Exception $e) {
                Log::error('Failed to create authentication token', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
                return $this->sendError('Authentication failed', ['error' => 'Failed to create authentication token'], 500);
            }

            // For mobile apps, we might use a different redirect scheme
            if (in_array($clientType, ['ios', 'android'])) {
                // For mobile apps, we might use a custom URL scheme or deep link
                $appScheme   = Config::get('services.mobile.app_scheme', 'myapp');
                $redirectUrl = "{$appScheme}://auth/callback?token={$token}&user_id={$user->id}&role={$user->role}";
            } else {
                // For web clients
                $frontendUrl  = Config::get('services.frontend.url', 'http://localhost:3000');
                $redirectPath = $user->role === 'admin' ? '/admin' : '/learn';
                $redirectUrl  = "{$frontendUrl}{$redirectPath}";
            }

            // Return the user and token
            return $this->sendResponse([
                'user'         => $user,
                'token'        => $token,
                'redirect_url' => $redirectUrl,
                'client_type'  => $clientType,
            ]);

        } catch (Exception $e) {
            Log::error('Token exchange failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->sendError('Authentication failed', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get Google user from authorization code
     *
     * @param string $code
     * @param string $redirectUrl
     * @param string|null $codeVerifier
     * @return \Laravel\Socialite\Two\User|null
     */
    protected function getGoogleUserFromCode(string $code, string $redirectUrl, ?string $codeVerifier = null)
    {
        try {
            $googleProvider = Socialite::driver('google')
                ->stateless()
                ->redirectUrl($redirectUrl);

            // If code verifier is provided (for PKCE), we need to set it
            if ($codeVerifier) {
                // This is a workaround since Laravel Socialite doesn't directly support PKCE
                // In a real implementation, you might need to extend the GoogleProvider class
                // or use a different OAuth client that supports PKCE
                $googleProvider = $this->setCodeVerifier($googleProvider, $codeVerifier);
            }

            return $googleProvider->user();
        } catch (Exception $e) {
            Log::error('Failed to get Google user from code: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Handle tenant-specific Google OAuth callback
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handleTenantGoogleCallback(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'code' => 'required|string',
                'state' => 'nullable|string',
                'tenant_slug' => 'nullable|string|exists:tenants,slug',
            ]);

            $googleUser = Socialite::driver('google')
                ->stateless()
                ->user();

            // Validate Google user data
            if (empty($googleUser->getId()) || empty($googleUser->getEmail())) {
                return $this->sendError('Invalid user data from Google', [], 400);
            }

            // If tenant_slug is provided, validate user belongs to that tenant
            if (!empty($validated['tenant_slug'])) {
                $tenant = \App\Models\Landlord\Tenant::where('slug', $validated['tenant_slug'])->first();
                if (!$tenant) {
                    return $this->sendError('Organization not found', [], 404);
                }

                // Switch to tenant context to check user
                tenancy()->initialize($tenant);

                $user = \App\Models\User::where('email', $googleUser->getEmail())->first();

                if (!$user) {
                    return $this->sendError('No account found for this email in the specified organization', [], 404);
                }

                if (!$user->is_active) {
                    return $this->sendError('Your account is not authorized to access this organization', [], 403);
                }
            } else {
                // Find user in any tenant (for backward compatibility)
                $user = $this->findUserInAnyTenant($googleUser->getEmail());

                if (!$user) {
                    return $this->sendError('No account found for this email', [], 404);
                }
            }

            // Update user's Google ID if not set
            if (empty($user->google_id)) {
                $user->update(['google_id' => $googleUser->getId()]);
            }

            // Generate auth token
            $token = $user->createToken('auth-token')->plainTextToken;

            // Get tenant information
            $tenant = tenant();

            return $this->sendResponse([
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'email_verified_at' => $user->email_verified_at,
                    'tenant_id' => $tenant->id,
                    'tenant' => [
                        'id' => $tenant->id,
                        'name' => $tenant->name,
                        'slug' => $tenant->slug,
                        'status' => $tenant->status ?? 'active',
                    ]
                ],
            ], 'Authentication successful');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation error', $e->errors(), 422);
        } catch (Exception $e) {
            Log::error('Tenant Google OAuth error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->sendError('Authentication failed', [], 500);
        }
    }

    /**
     * Find user in any tenant by email
     *
     * @param string $email
     * @return \App\Models\User|null
     */
    protected function findUserInAnyTenant(string $email): ?\App\Models\User
    {
        $tenants = \App\Models\Landlord\Tenant::where('status', 'active')->get();

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);

            $user = \App\Models\User::where('email', $email)->first();
            if ($user && $user->is_active) {
                return $user;
            }
        }

        return null;
    }

    /**
     * Set code verifier for PKCE on Google provider
     * Note: This is a workaround since Laravel Socialite doesn't directly support PKCE
     *
     * @param GoogleProvider $provider
     * @param string $codeVerifier
     * @return GoogleProvider
     */
    protected function setCodeVerifier(GoogleProvider $provider, string $codeVerifier): GoogleProvider
    {
        // This is a workaround and might not work with all versions of Socialite
        // In a real implementation, you might need to extend the GoogleProvider class
        // or use a different OAuth client that supports PKCE

        // For demonstration purposes only
        return $provider;
    }
    /**
     * Get allowed email domains from config or environment
     *
     * @return array
     */
    protected function getAllowedDomains(): array
    {
        $configDomains = Config::get('services.google.allowed_domains');

        if (is_string($configDomains)) {
            return array_map('trim', explode(',', $configDomains));
        }

        return $configDomains ?: ['gmail.com'];
    }

    /**
     * Get Google auth URL with optional client parameters
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAuthUrl(Request $request)
    {
        try {
            // Get client type and ID from request
            $clientType = $request->input('client_type', 'web');
            $clientId   = $request->input('client_id', '');

            // Build state parameter to track client information
            $state = base64_encode(json_encode([
                'client_type' => $clientType,
                'client_id'   => $clientId,
                'nonce'       => Str::random(16),
            ]));

            // Get redirect URL based on client type
            $redirectUrl = $this->getRedirectUrlForClient($clientType);

            // Generate Google auth URL with state parameter
            $authUrl = Socialite::driver('google')
                ->stateless()
                ->with(['state' => $state])
                ->redirectUrl($redirectUrl)
                ->redirect()
                ->getTargetUrl();

            if (empty($authUrl)) {
                Log::error('Google redirect URL is empty');
                return $this->sendError('Failed to generate Google login URL', [], 500);
            }

            return $this->sendResponse([
                'url'   => $authUrl,
                'state' => $state,
            ]);
        } catch (ClientException $e) {
            Log::error('Google API client error: ' . $e->getMessage());
            return $this->sendError('Google API error', ['error' => 'Error connecting to Google services'], 503);
        } catch (ConnectException $e) {
            Log::error('Google API connection error: ' . $e->getMessage());
            return $this->sendError('Connection error', ['error' => 'Unable to connect to Google services'], 503);
        } catch (Exception $e) {
            Log::error('Google redirect error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->sendError('Failed to initiate Google login', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get the appropriate redirect URL based on client type
     *
     * @param string $clientType
     * @return string
     */
    protected function getRedirectUrlForClient(string $clientType): string
    {
        $baseUrl = Config::get('services.google.redirect');

        // For mobile apps, we might want to use a different callback URL
        // that doesn't involve browser redirects
        switch ($clientType) {
            case 'ios':
            case 'android':
                return Config::get('services.google.mobile_redirect', $baseUrl);
            default:
                return $baseUrl;
        }
    }

    /**
     * Handle Google OAuth callback
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->user();

            // Validate Google user data
            if (empty($googleUser->getId())) {
                Log::error('Google user ID is missing');
                return $this->sendError('Invalid user data', ['error' => 'Google user ID is missing'], 400);
            }

            if (empty($googleUser->getEmail())) {
                Log::error('Google user email is missing');
                return $this->sendError('Invalid user data', ['error' => 'Google user email is missing'], 400);
            }

            // Validate email domain
            $emailParts = explode('@', $googleUser->getEmail());
            if (count($emailParts) !== 2) {
                Log::warning('Invalid email format from Google', ['email' => $googleUser->getEmail()]);
                return $this->sendError('Invalid email format', ['email' => 'The provided email is not valid'], 400);
            }

            // Check if domain validation is enabled
            if (Config::get('services.google.validate_domains', true)) {
                $emailDomain    = $emailParts[1];
                $allowedDomains = $this->getAllowedDomains();

                if (! in_array($emailDomain, $allowedDomains)) {
                    Log::warning('Unauthorized domain access attempt', [
                        'email'  => $googleUser->getEmail(),
                        'domain' => $emailDomain,
                    ]);
                    return $this->sendError(
                        'Unauthorized email domain',
                        ['email' => 'This email domain is not authorized to access the application.'],
                        403
                    );
                }
            }

            $user = User::where('google_id', $googleUser->getId())->first();

            if (! $user) {
                // Check if email already exists
                $existingUser = User::where('email', $googleUser->getEmail())->first();

                if ($existingUser) {
                    try {
                        // Update existing user with Google credentials
                        $existingUser->update([
                            'google_id' => $googleUser->getId(),
                            'avatar'    => $googleUser->getAvatar() ?? null,
                        ]);
                        $user = $existingUser->fresh();
                    } catch (Exception $e) {
                        Log::error('Failed to update existing user with Google data', [
                            'user_id' => $existingUser->id,
                            'error'   => $e->getMessage(),
                        ]);
                        return $this->sendError('User update failed', ['error' => 'Failed to update user record'], 500);
                    }
                } else {
                    try {
                        // Check if this is an admin email
                        $isAdminEmail = false;
                        $adminDomains = Config::get('services.google.admin_domains', []);

                        if (is_string($adminDomains)) {
                            $adminDomains = array_map('trim', explode(',', $adminDomains));
                        }

                        if (! empty($adminDomains)) {
                            $emailParts   = explode('@', $googleUser->getEmail());
                            $domain       = $emailParts[1] ?? '';
                            $isAdminEmail = in_array($domain, $adminDomains);
                        }

                        // Create new user
                        $user = User::create([
                            'name'              => $googleUser->getName() ?? 'Google User',
                            'email'             => $googleUser->getEmail(),
                            'google_id'         => $googleUser->getId(),
                            'avatar'            => $googleUser->getAvatar() ?? null,
                            'password'          => bcrypt(Str::random(16)), // Random password as it's not needed for OAuth
                            'email_verified_at' => now(),                   // Google accounts are already verified
                            'role'              => $isAdminEmail ? 'admin' : 'user',
                        ]);

                        // Assign role if using role-based permissions
                        if (class_exists('\App\Models\Role')) {
                            $roleName = $isAdminEmail ? 'admin' : 'user';
                            $role     = Role::where('slug', $roleName)->first();
                            if ($role) {
                                $user->roles()->attach($role->id);
                            }
                        }
                    } catch (Exception $e) {
                        Log::error('Failed to create new user from Google data', [
                            'email' => $googleUser->getEmail(),
                            'error' => $e->getMessage(),
                        ]);
                        return $this->sendError('User creation failed', ['error' => 'Failed to create user record'], 500);
                    }
                }
            } else {
                try {
                    // Update existing Google user's avatar if changed
                    $user->update([
                        'avatar' => $googleUser->getAvatar() ?? $user->avatar,
                    ]);
                    $user = $user->fresh();
                } catch (Exception $e) {
                    Log::warning('Failed to update user avatar', [
                        'user_id' => $user->id,
                        'error'   => $e->getMessage(),
                    ]);
                    // Continue with login despite avatar update failure
                }
            }

            // Generate token for API authentication
            try {
                $token = $user->createToken('auth-token')->plainTextToken;
            } catch (Exception $e) {
                Log::error('Failed to create authentication token', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
                return $this->sendError('Authentication failed', ['error' => 'Failed to create authentication token'], 500);
            }

            // Prepare response data for the client

            // Extract client information from state parameter if available
            $clientType = 'web';
            $clientId   = '';

            if ($request->has('state')) {
                try {
                    $stateData  = json_decode(base64_decode($request->state), true);
                    $clientType = $stateData['client_type'] ?? 'web';
                    $clientId   = $stateData['client_id'] ?? '';
                } catch (\Exception $e) {
                    Log::warning('Failed to decode state parameter: ' . $e->getMessage());
                }
            }

            // Get frontend URL from config
            $frontendUrl = Config::get('services.frontend.url', 'http://localhost:3000');

            // Add redirect URL based on user role and client type
            $redirectPath = $user->role === 'admin' ? '/admin' : '/learn';

            // For mobile apps, we might use a different redirect scheme
            if (in_array($clientType, ['ios', 'android'])) {
                // For mobile apps, we might use a custom URL scheme or deep link
                $appScheme   = Config::get('services.mobile.app_scheme', 'myapp');
                $redirectUrl = "{$appScheme}://auth/callback?token={$token}&user_id={$user->id}&role={$user->role}";
            } else {
                // For web clients
                $redirectUrl = $frontendUrl . $redirectPath;
            }

            // For API requests or mobile clients, return JSON
            if (request()->expectsJson() || request()->ajax() || request()->wantsJson() || in_array($clientType, ['ios', 'android'])) {
                return $this->sendResponse([
                    'user'         => $user,
                    'token'        => $token,
                    'redirect_url' => $redirectUrl,
                    'client_type'  => $clientType,
                    'client_id'    => $clientId,
                ]);
            }

            // For web requests, redirect with token
            return redirect($redirectUrl . '?token=' . $token);

        } catch (InvalidStateException $e) {
            Log::error('Invalid OAuth state: ' . $e->getMessage());
            return $this->sendError('Invalid OAuth state', ['error' => 'Please try logging in again.'], 401);
        } catch (ClientException $e) {
            Log::error('Google API client error: ' . $e->getMessage());
            return $this->sendError('Google API error', ['error' => 'Error connecting to Google services'], 503);
        } catch (Exception $e) {
            Log::error('Google login failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->sendError('Google login failed', ['error' => $e->getMessage()], 500);
        }
    }
}