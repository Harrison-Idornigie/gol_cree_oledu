"use server";

import axiosInstance from "@/lib/axios";
import { cookies } from "next/headers";
import { UserRole } from "@/types/tenant/user";

interface TokenExchangeResponse {
  success: boolean;
  data?: {
    user: {
      id: number;
      name: string;
      email: string;
      role: UserRole;
    };
    token: string;
    redirect_url: string;
    client_type: string;
    client_id?: string;
  };
  message?: string;
  error?: string;
}

/**
 * Exchange an authorization code for a token without browser redirects
 * This is useful for mobile apps and other clients that can't use browser redirects
 * 
 * @param code The authorization code from Google
 * @param clientType The type of client (web, ios, android, other)
 * @param clientId Optional client identifier
 * @param codeVerifier Optional PKCE code verifier
 * @returns TokenExchangeResponse
 */
export async function exchangeCodeForToken(
  code: string,
  clientType: "web" | "ios" | "android" | "other",
  clientId?: string,
  codeVerifier?: string
): Promise<TokenExchangeResponse> {
  try {
    const response = await axiosInstance.post<TokenExchangeResponse>(
      "/auth/google/token",
      {
        code,
        client_type: clientType,
        client_id: clientId,
        code_verifier: codeVerifier,
      }
    );

    if (response.data.data?.token && clientType === "web") {
      // For web clients, store the token in a cookie
      const cookieStore = await cookies();
      cookieStore.set("auth_token", response.data.data.token, {
        httpOnly: true,
        secure: process.env.NODE_ENV === "production",
        sameSite: "lax",
        path: "/",
      });
    }

    return response.data;
  } catch (error) {
    console.error("Token exchange error:", error);
    return {
      success: false,
      error: getErrorMessage(error),
    };
  }
}

/**
 * Get error message from various error types
 */
function getErrorMessage(error: unknown): string {
  if (error instanceof Error) return error.message;
  if (typeof error === "object" && error && "message" in error) {
    return String(error.message);
  }
  return "An unexpected error occurred";
}
