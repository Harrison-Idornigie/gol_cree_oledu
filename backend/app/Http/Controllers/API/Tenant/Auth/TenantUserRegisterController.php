<?php
namespace App\Http\Controllers\API\Tenant\Auth;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\Tenants\AdminInvite;
use App\Models\Tenants\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TenantUserRegisterController extends BaseAPIController
{
    public function register(Request $request)
    {
        try {
            $request->validate([
                'name'         => 'required|string|max:255',
                'email'        => 'required|string|email|max:255|unique:users',
                'password'     => 'required|string|min:8|confirmed',
                'invite_token' => 'nullable|string',
            ]);

            $membership = 'user';

            // If invite token is present, validate it
            if ($request->invite_token) {
                $invite = AdminInvite::where('token', $request->invite_token)
                    ->whereNull('used_at')
                    ->where('expires_at', '>', now())
                    ->where('email', $request->email)
                    ->first();

                if (! $invite) {
                    Log::warning('Invalid invite token used', [
                        'email' => $request->email,
                        'token' => $request->invite_token,
                    ]);
                    throw ValidationException::withMessages([
                        'invite_token' => ['Invalid or expired invite token.'],
                    ]);
                }

                $membership = 'admin';
                $invite->markAsUsed();
            }

            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => bcrypt($request->password),
                'membership'     => $membership,
                'points'   => 0,
            ]);

            // Send email verification notification
            $user->sendEmailVerificationNotification();

            $token = $user->createToken('auth-token')->plainTextToken;

            return $this->sendCreatedResponse([
                'token' => $token,
                'user'  => $user,
            ], 'Successfully registered. Please check your email to verify your account.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation error', $e->errors(), 422);
        } catch (Exception $e) {
            Log::error('Registration error: ' . $e->getMessage(), [
                'data'  => $request->except(['password', 'password_confirmation']),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->sendError('Registration failed', ['error' => 'An unexpected error occurred'], 500);
        }
    }
}
