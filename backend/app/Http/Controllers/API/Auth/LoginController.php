<?php
namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LoginController extends BaseAPIController
{
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email'    => 'required|email',
                'password' => 'required',
            ]);

            if (! Auth::attempt($request->only('email', 'password'))) {
                Log::warning('Failed login attempt', ['email' => $request->email]);
                return $this->sendUnauthorizedResponse('Invalid credentials');
            }

            $user  = User::where('email', $request->email)->firstOrFail();
            $token = $user->createToken('auth-token')->plainTextToken;

            return $this->sendResponse([
                'token' => $token,
                'user'  => $user,
            ], 'Successfully logged in');
        } catch (ValidationException $e) {
            return $this->sendError('Validation error', $e->errors(), 422);
        } catch (ModelNotFoundException $e) {
            Log::error('User not found during login', ['email' => $request->email]);
            return $this->sendError('Authentication failed', ['email' => 'User not found'], 404);
        } catch (Exception $e) {
            Log::error('Login error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->sendError('Login failed', ['error' => 'An unexpected error occurred'], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            if (! $request->user()) {
                return $this->sendUnauthorizedResponse('User not authenticated');
            }

            $request->user()->currentAccessToken()->delete();
            return $this->sendResponse([], 'Successfully logged out');
        } catch (Exception $e) {
            Log::error('Logout error: ' . $e->getMessage(), [
                'user_id' => $request->user() ? $request->user()->id : null,
                'trace'   => $e->getTraceAsString(),
            ]);
            return $this->sendError('Logout failed', ['error' => 'An unexpected error occurred'], 500);
        }
    }
}
