<?php

namespace App\Http\Controllers\API\Landlord\Auth;

use App\Http\Controllers\API\BaseAPIController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Log;
use Exception;

class CentralResetPasswordController extends BaseAPIController
{
    /**
     * Reset the given user's password.
     */
    public function reset(Request $request)
    {
        try {
            $request->validate([
                'token' => 'required',
                'email' => 'required|email',
                'password' => 'required|min:8|confirmed',
            ]);

            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user, $password) {
                    $user->forceFill([
                        'password' => Hash::make($password)
                    ])->setRememberToken(Str::random(60));

                    $user->save();

                    event(new PasswordReset($user));
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                return $this->sendResponse([], __($status));
            }

            return $this->sendError('Failed to reset password', ['email' => __($status)], 400);
        } catch (Exception $e) {
            Log::error('Password reset failed: ' . $e->getMessage(), [
                'email' => $request->email ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to reset password', ['error' => 'An unexpected error occurred'], 500);
        }
    }
}
