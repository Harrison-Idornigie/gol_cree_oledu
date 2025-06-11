<?php

namespace App\Http\Controllers\API\Landlord\Auth;

use App\Http\Controllers\API\BaseAPIController;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Central Logout Controller
 *
 * Handles logout for central/landlord users (super admins).
 * This controller operates in the central database context and deletes
 * tokens from the central personal_access_tokens table.
 */
class CentralLogoutController extends BaseAPIController
{
    public function logout(Request $request)
    {
        try {
            if (!$request->user()) {
                return $this->sendUnauthorizedResponse('User not authenticated');
            }

            // Delete the current access token from central database
            $request->user()->currentAccessToken()->delete();

            Log::info('Central user logged out successfully', [
                'user_id' => $request->user()->id,
                'email' => $request->user()->email,
            ]);

            return $this->sendResponse([], 'Successfully logged out');

        } catch (Exception $e) {
            Log::error('Central logout error: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->sendError('Logout failed', ['error' => 'An unexpected error occurred'], 500);
        }
    }
}
