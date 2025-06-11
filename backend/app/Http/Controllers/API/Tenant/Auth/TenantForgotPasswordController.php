<?php

namespace App\Http\Controllers\API\Tenant\Auth;

use App\Http\Controllers\API\BaseAPIController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
use Exception;

class TenantForgotPasswordController extends BaseAPIController
{
    /**
     * Send a reset link to the given user.
     */
    public function sendResetLinkEmail(Request $request)
    {
        try {
            $request->validate(['email' => 'required|email']);

            $status = Password::sendResetLink(
                $request->only('email')
            );

            if ($status === Password::RESET_LINK_SENT) {
                return $this->sendResponse([], __($status));
            }

            return $this->sendError('Failed to send reset link', ['email' => __($status)], 400);
        } catch (Exception $e) {
            Log::error('Failed to send password reset link: ' . $e->getMessage(), [
                'email' => $request->email ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to send reset link', ['error' => 'An unexpected error occurred'], 500);
        }
    }
}
