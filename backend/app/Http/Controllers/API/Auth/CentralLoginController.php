<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\Landlord\CentralUser;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Central Login Controller
 * 
 * Handles authentication for central/landlord users (super admins)
 * who need access to system-wide management features.
 * 
 * This controller operates in the central database context and creates
 * tokens in the central personal_access_tokens table.
 */
class CentralLoginController extends BaseAPIController
{
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email'    => 'required|email',
                'password' => 'required',
            ]);

            // Use central guard for authentication
            if (!Auth::guard('central')->attempt($request->only('email', 'password'))) {
                Log::warning('Failed central login attempt', ['email' => $request->email]);
                return $this->sendUnauthorizedResponse('Invalid credentials');
            }

            $user = CentralUser::where('email', $request->email)->firstOrFail();
            
            // Verify user is active
            if (!$user->is_active) {
                Log::warning('Inactive user login attempt', ['email' => $request->email]);
                return $this->sendUnauthorizedResponse('Account is inactive');
            }

            // Create token in central database
            $token = $user->createToken('central-auth-token')->plainTextToken;

            // Update last login timestamp
            $user->update(['last_login_at' => now()]);

            // Prepare response data
            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'email_verified_at' => $user->email_verified_at,
                'is_active' => $user->is_active,
                'last_login_at' => $user->last_login_at,
            ];

            // Determine redirect path based on user role
            $redirectPath = $this->getPostLoginRedirectPath($user);

            $responseData = [
                'token' => $token,
                'user'  => $userData,
                'redirect' => $redirectPath,
                'auth_context' => 'central',
            ];

            return $this->sendResponse($responseData, 'Successfully logged in');
            
        } catch (ValidationException $e) {
            return $this->sendError('Validation error', $e->errors(), 422);
        } catch (ModelNotFoundException $e) {
            Log::error('Central user not found during login', ['email' => $request->email]);
            return $this->sendError('Authentication failed', ['email' => 'User not found'], 404);
        } catch (Exception $e) {
            Log::error('Central login error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->sendError('Login failed', ['error' => 'An unexpected error occurred'], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            if (!$request->user()) {
                return $this->sendUnauthorizedResponse('User not authenticated');
            }

            $request->user()->currentAccessToken()->delete();
            return $this->sendResponse([], 'Successfully logged out');
            
        } catch (Exception $e) {
            Log::error('Central logout error: ' . $e->getMessage(), [
                'user_id' => $request->user() ? $request->user()->id : null,
                'trace'   => $e->getTraceAsString(),
            ]);
            return $this->sendError('Logout failed', ['error' => 'An unexpected error occurred'], 500);
        }
    }

    /**
     * Determine the appropriate redirect path after login based on user role
     */
    protected function getPostLoginRedirectPath(CentralUser $user): string
    {
        if ($user->isSuperAdmin()) {
            return '/super/dashboard';
        }

        // Default fallback for other central users
        return '/super/dashboard';
    }
}
