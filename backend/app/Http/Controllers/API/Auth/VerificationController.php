<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\Tenants\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Exception;

class VerificationController extends BaseAPIController
{
    /**
     * Send email verification link
     */
    public function sendVerificationEmail(Request $request)
    {
        try {
            if ($request->user()->hasVerifiedEmail()) {
                return $this->sendResponse([], 'Email already verified');
            }

            $request->user()->sendEmailVerificationNotification();

            return $this->sendResponse([], 'Verification link sent');
        } catch (Exception $e) {
            Log::error('Failed to send verification email: ' . $e->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to send verification email', ['error' => 'An unexpected error occurred'], 500);
        }
    }

    /**
     * Verify email
     */
    public function verify(Request $request)
    {
        try {
            $userId = $request->route('id');
            $user = User::findOrFail($userId);

            if (! hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
                return $this->sendError('Invalid verification link', [], 403);
            }

            if ($user->hasVerifiedEmail()) {
                return $this->sendResponse([], 'Email already verified');
            }

            if ($user->markEmailAsVerified()) {
                event(new Verified($user));
            }

            return $this->sendResponse([], 'Email verified successfully');
        } catch (Exception $e) {
            Log::error('Email verification failed: ' . $e->getMessage(), [
                'user_id' => $request->route('id') ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Email verification failed', ['error' => 'An unexpected error occurred'], 500);
        }
    }
}
