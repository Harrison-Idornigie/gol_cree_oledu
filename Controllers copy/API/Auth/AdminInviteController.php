<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\AdminInvite;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Log;
use Exception;

class AdminInviteController extends BaseAPIController
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', function ($request, $next) {
            if ($request->user()->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            return $next($request);
        }]);
    }

    public function invite(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|unique:users,email|unique:admin_invites,email'
            ]);

            $invite = AdminInvite::create([
                'email' => $validated['email'],
                'token' => Str::random(32),
                'invited_by' => $request->user()->id,
                'expires_at' => now()->addDays(7),
            ]);

            $inviteUrl = URL::to('/register?invite=' . $invite->token);

            return $this->sendResponse([
                'invite_url' => $inviteUrl
            ], 'Invite sent successfully');
        } catch (Exception $e) {
            Log::error('Failed to create admin invite: ' . $e->getMessage(), [
                'email' => $request->email ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to send invite', ['error' => $e->getMessage()], 500);
        }
    }

    public function validateInvite(Request $request)
    {
        try {
            $validated = $request->validate([
                'token' => 'required|string'
            ]);

            $invite = AdminInvite::where('token', $validated['token'])
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->first();

            if (!$invite) {
                return $this->sendError('Invalid or expired invite token', [], 400);
            }

            return $this->sendResponse([
                'email' => $invite->email
            ], 'Valid invite token');
        } catch (Exception $e) {
            Log::error('Failed to validate invite: ' . $e->getMessage(), [
                'token' => $request->token ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to validate invite', ['error' => $e->getMessage()], 500);
        }
    }

    public function listInvites()
    {
        try {
            $invites = AdminInvite::with('inviter')
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->sendResponse($invites, 'Invites retrieved successfully');
        } catch (Exception $e) {
            Log::error('Failed to list invites: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to list invites', ['error' => $e->getMessage()], 500);
        }
    }
}
