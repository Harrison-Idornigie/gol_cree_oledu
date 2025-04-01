<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ClientException;

class GoogleController extends BaseAPIController
{
    /**
     * List of allowed email domains
     * @var array
     */
    protected $allowedDomains = [
        'gmail.com',
        'keyin.com'
    ];

    public function getAuthUrl()
    {
        try {
            $redirectUrl = Socialite::driver('google')
                ->stateless()
                ->redirect()
                ->getTargetUrl();

            if (empty($redirectUrl)) {
                Log::error('Google redirect URL is empty');
                return $this->sendError('Failed to generate Google login URL', [], 500);
            }

            return $this->sendResponse([
                'url' => $redirectUrl
            ]);
        } catch (ClientException $e) {
            Log::error('Google API client error: ' . $e->getMessage());
            return $this->sendError('Google API error', ['error' => 'Error connecting to Google services'], 503);
        } catch (ConnectException $e) {
            Log::error('Google API connection error: ' . $e->getMessage());
            return $this->sendError('Connection error', ['error' => 'Unable to connect to Google services'], 503);
        } catch (Exception $e) {
            Log::error('Google redirect error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to initiate Google login', ['error' => $e->getMessage()], 500);
        }
    }

    public function handleGoogleCallback()
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

            $emailDomain = $emailParts[1];
            if (!in_array($emailDomain, $this->allowedDomains)) {
                Log::warning('Unauthorized domain access attempt', [
                    'email' => $googleUser->getEmail(),
                    'domain' => $emailDomain
                ]);
                return $this->sendError(
                    'Unauthorized email domain',
                    ['email' => 'This email domain is not authorized to access the application.'],
                    403
                );
            }

            $user = User::where('google_id', $googleUser->getId())->first();

            if (!$user) {
                // Check if email already exists
                $existingUser = User::where('email', $googleUser->getEmail())->first();
                
                if ($existingUser) {
                    try {
                        // Update existing user with Google credentials
                        $existingUser->update([
                            'google_id' => $googleUser->getId(),
                            'avatar' => $googleUser->getAvatar() ?? null,
                        ]);
                        $user = $existingUser->fresh();
                    } catch (Exception $e) {
                        Log::error('Failed to update existing user with Google data', [
                            'user_id' => $existingUser->id,
                            'error' => $e->getMessage()
                        ]);
                        return $this->sendError('User update failed', ['error' => 'Failed to update user record'], 500);
                    }
                } else {
                    try {
                        // Create new user
                        $user = User::create([
                            'name' => $googleUser->getName() ?? 'Google User',
                            'email' => $googleUser->getEmail(),
                            'google_id' => $googleUser->getId(),
                            'avatar' => $googleUser->getAvatar() ?? null,
                            'password' => bcrypt(Str::random(16)), // Random password as it's not needed for OAuth
                            'email_verified_at' => now(),
                            'role' => 'user',
                        ]);
                    } catch (Exception $e) {
                        Log::error('Failed to create new user from Google data', [
                            'email' => $googleUser->getEmail(),
                            'error' => $e->getMessage()
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
                        'error' => $e->getMessage()
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
                    'error' => $e->getMessage()
                ]);
                return $this->sendError('Authentication failed', ['error' => 'Failed to create authentication token'], 500);
            }

            // Add redirect URL based on user role
            $redirectUrl = $user->role === 'admin' 
                ? 'http://localhost:3000/admin' 
                : 'http://localhost:3000/learn';

            return redirect($redirectUrl . '?token=' . $token);

        } catch (InvalidStateException $e) {
            Log::error('Invalid OAuth state: ' . $e->getMessage());
            return $this->sendError('Invalid OAuth state', ['error' => 'Please try logging in again.'], 401);
        } catch (ClientException $e) {
            Log::error('Google API client error: ' . $e->getMessage());
            return $this->sendError('Google API error', ['error' => 'Error connecting to Google services'], 503);
        } catch (Exception $e) {
            Log::error('Google login failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Google login failed', ['error' => $e->getMessage()], 500);
        }
    }
}
