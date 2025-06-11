"use client";

import { useEffect } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { handleGoogleCallback } from "@/app/_actions/auth-actions";
import { getDefaultRedirectPath } from "@/types/tenant/user";
import { toast } from "sonner";

// Define response type
interface AuthResponse {
  success: boolean;
  error?: string;
  data?: {
    user: {
      id: number;
      name: string;
      email: string;
      membership: string;
      email_verified_at?: string | null;
      avatar_url?: string | null;
      avatar?: string;
      points?: number;
    };
    token: string;
    redirect_url?: string;
  };
  message?: string;
}

export default function GoogleCallbackPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const code = searchParams.get("code");
  const state = searchParams.get("state");

  useEffect(() => {
    async function handleCallback() {
      try {
        if (!code) {
          toast.error("No authorization code present");
          router.push("/login");
          return;
        }

        // Handle the response
        const response = (await handleGoogleCallback(
          code,
          state || ""
        )) as AuthResponse;
        console.log("Response:", response);

        if (response.success && response.data) {
          // Token is already stored in HTTP-only cookie by the server action

          // Use proper membership-based redirection
          const redirectPath = getDefaultRedirectPath(response.data.user);

          // Use router for navigation to maintain Next.js routing
          // Add post_login parameter to indicate this is a post-login redirect
          const redirectUrl = new URL(redirectPath, window.location.origin);
          redirectUrl.searchParams.set("post_login", "true");

          console.log(
            `Google auth successful, redirecting to: ${redirectPath}`
          );
          router.push(redirectUrl.pathname + redirectUrl.search);
        } else {
          toast.error(
            response.error || response.message || "Authentication failed"
          );
          router.push("/login");
        }
      } catch (error) {
        if (error instanceof Error) {
          toast.error(error.message);
        } else {
          toast.error("Failed to authenticate with Google");
        }
        router.push("/login");
      }
    }

    handleCallback();
  }, [code, state, router]);

  return (
    <div className="flex items-center justify-center min-h-screen">
      <div className="text-lg">Processing Google login...</div>
    </div>
  );
}
