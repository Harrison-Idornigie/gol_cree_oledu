<?php

namespace App\Http\Middleware\Tenant;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;

/**
 * Custom email verification middleware with grace period
 * 
 * Allows unverified users to access the platform for a limited time
 * after account creation, providing a better onboarding experience
 * for B2B customers while still encouraging email verification.
 */
class EnsureEmailIsVerifiedWithGracePeriod
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // If user is not authenticated, let other middleware handle it
        if (!$user) {
            return $next($request);
        }

        // If email is already verified, proceed normally
        if ($user->email_verified_at !== null) {
            return $next($request);
        }

        // Check if user is within grace period (2 days from account creation)
        $gracePeriodDays = config('auth.email_verification_grace_period_days', 2);
        $accountCreated = Carbon::parse($user->created_at);
        $gracePeriodEnd = $accountCreated->addDays($gracePeriodDays);
        $now = Carbon::now();

        // If still within grace period, allow access but add headers for frontend
        if ($now->lessThan($gracePeriodEnd)) {
            $hoursRemaining = $now->diffInHours($gracePeriodEnd);
            
            $response = $next($request);
            
            // Add headers to inform frontend about verification status
            $response->headers->set('X-Email-Verification-Required', 'true');
            $response->headers->set('X-Email-Verification-Grace-Hours-Remaining', $hoursRemaining);
            $response->headers->set('X-Email-Verification-Grace-End', $gracePeriodEnd->toISOString());
            
            return $response;
        }

        // Grace period has expired, require email verification
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Your email address must be verified to continue using the platform. Please check your email for a verification link.',
                'error_code' => 'EMAIL_VERIFICATION_REQUIRED',
                'grace_period_expired' => true,
                'account_created' => $accountCreated->toISOString(),
                'grace_period_end' => $gracePeriodEnd->toISOString(),
            ], 403);
        }

        // For non-JSON requests, redirect to verification notice
        return redirect()->route('verification.notice');
    }


}
