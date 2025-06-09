<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\AdminInvite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Support\Facades\DB;

class AdminInviteController extends BaseAPIController
{
    /**
     * Send a new admin invite
     */
    public function send(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'role' => 'required|string|in:admin,team'
            ]);

            // Check if the email already exists as a user with the specified role
            $existingUser = User::where('email', $request->email)
                ->exists();

            if ($existingUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => [
                        'email' => ['User with this email already exists']
                    ]
                ], 422);
            }

            // Check if there's an existing invite for this email
            $existingInvite = AdminInvite::where('email', $request->email)
                ->where('status', 'pending')
                ->first();

            if ($existingInvite) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => [
                        'email' => ['An invitation has already been sent to this email']
                    ]
                ], 422);
            }

            $invite = DB::transaction(function () use ($request) {
                // Create a new invite
                $invite = AdminInvite::create([
                    'email' => $request->email,
                    'role' => $request->role,
                    'token' => Str::random(32),
                    'invited_by' => $request->user()->id,
                    'expires_at' => now()->addDays(7), // Expires in 7 days
                    'status' => 'pending'
                ]);

                // Send invitation email
                Mail::to($request->email)->send(new \App\Mail\AdminInvitation($invite));
                
                return $invite;
            });

            return $this->sendCreatedResponse([
                'email' => $invite->email,
                'role' => $invite->role,
                'status' => $invite->status
            ], 'Admin invite has been created and sent');
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Let Laravel handle validation exceptions
            throw $e;
        } catch (Exception $e) {
            Log::error('Error sending admin invite: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'email' => $request->email ?? 'unknown'
            ]);
            return $this->sendError('Failed to send admin invite', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Validate an invite token
     */
    public function validate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'token' => 'required|string',
            ]);

            $invite = AdminInvite::where('token', $request->token)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->first();

            if (!$invite) {
                return $this->sendError('Invalid or expired invite token');
            }

            return $this->sendResponse([
                'email' => $invite->email,
                'expires_at' => $invite->expires_at,
            ], 'Invite is valid');
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Let Laravel handle validation exceptions
            throw $e;
        } catch (Exception $e) {
            Log::error('Error validating invite token: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'token' => $request->token ?? 'unknown'
            ]);
            return $this->sendError('Failed to validate invite token', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * List all invites for the admin dashboard
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $invites = AdminInvite::with('inviter')
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->sendResponse([
                'invites' => $invites,
            ], 'Admin invites retrieved successfully');
        } catch (Exception $e) {
            Log::error('Error retrieving admin invites: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to retrieve admin invites', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Cancel an invite
     */
    public function cancel(AdminInvite $invite): JsonResponse
    {
        try {
            if ($invite->status === 'accepted') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot cancel accepted invitation'
                ], 400);
            }

            $invite->delete();

            return response()->json([
                'success' => true,
                'message' => 'Invitation cancelled successfully'
            ]);
        } catch (Exception $e) {
            Log::error('Error cancelling admin invite: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'invite_id' => $invite->id ?? 'unknown'
            ]);
            return $this->sendError('Failed to cancel admin invite', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Resend an invite
     */
    public function resend(AdminInvite $invite): JsonResponse
    {
        try {
            if ($invite->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only resend pending invitations'
                ], 400);
            }

            DB::transaction(function () use ($invite) {
                // Update the expiration date
                $invite->update([
                    'expires_at' => now()->addDays(7),
                ]);

                // Send the invitation email
                Mail::to($invite->email)->send(new \App\Mail\AdminInvitation($invite));
            });

            return response()->json([
                'success' => true,
                'message' => 'Invitation resent successfully'
            ]);
        } catch (Exception $e) {
            Log::error('Error resending admin invite: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'invite_id' => $invite->id ?? 'unknown',
                'email' => $invite->email ?? 'unknown'
            ]);
            return $this->sendError('Failed to resend admin invite', ['error' => $e->getMessage()], 500);
        }
    }
}