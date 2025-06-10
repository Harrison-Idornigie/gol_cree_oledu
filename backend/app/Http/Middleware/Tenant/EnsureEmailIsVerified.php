<?php

namespace App\Http\Middleware\Tenant;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() ||
            ($request->user() instanceof MustVerifyEmail &&
            ! $request->user()->hasVerifiedEmail())) {
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your email address is not verified.',
                    'verification_required' => true
                ], 403);
            }
            
            return redirect()->route('verification.notice');
        }

        return $next($request);
    }
}