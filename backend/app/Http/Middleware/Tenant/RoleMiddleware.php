<?php

namespace App\Http\Middleware\Tenant;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$memberships)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.'
            ], 401);
        }

        foreach ($memberships as $membership) {
            if ($user->hasRole($membership)) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'Unauthorized. Requires membership: ' . implode(', ', $memberships)
        ], 403);
    }
}
