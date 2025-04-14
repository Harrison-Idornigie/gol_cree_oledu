import { type NextRequest } from "next/server";
import { NextResponse } from "next/server";
import { UserRole } from "./types/user";
import axios from "axios";

// Define response types for middleware
interface ApiResponse {
  success: boolean;
  data: {
    user?: User;
  };
  message: string;
}

interface User {
  id: number;
  name: string;
  email: string;
  role: string;
  email_verified_at?: string | null;
  avatar_url?: string | null;
  avatar?: string | null;
  points?: number;
  created_at?: string;
  updated_at?: string;
}

// Function to extract auth token from cookie string
function extractAuthToken(cookieHeader: string | null): string | null {
  if (!cookieHeader) {
    console.log("[Middleware] No cookie header found");
    return null;
  }

  console.log("[Middleware] Cookie header:", cookieHeader);
  const cookies = cookieHeader.split(";");
  for (const cookie of cookies) {
    const [name, value] = cookie.trim().split("=");
    if (name === "auth_token") {
      // Decode the token if it's URL encoded
      const decodedToken = decodeURIComponent(value);
      console.log(
        "[Middleware] Found auth_token:",
        decodedToken.substring(0, 10) + "..."
      );
      return decodedToken;
    }
  }
  console.log("[Middleware] No auth_token found in cookies");
  return null;
}

// Function to get the current user in middleware context
async function getCurrentUser(cookieHeader: string | null) {
  try {
    // Extract the auth token from cookies
    const token = extractAuthToken(cookieHeader);

    if (!token) {
      console.log("[Middleware] Authentication failed: No token found");
      throw new Error("No authentication token found");
    }

    console.log("[Middleware] Making API request to /auth/me with token");
    // Make the request with the Authorization header
    const apiUrl = `${process.env.NEXT_PUBLIC_API_URL}/auth/me`;
    console.log(`[Middleware] API URL: ${apiUrl}`);
    console.log(`[Middleware] Using token: ${token.substring(0, 10)}...`);

    const response = await axios.get<ApiResponse>(apiUrl, {
      headers: {
        Authorization: `Bearer ${token}`,
        Cookie: cookieHeader || "",
      },
    });

    console.log(
      "[Middleware] API response:",
      JSON.stringify(response.data, null, 2)
    );
    return response.data;
  } catch (error) {
    console.error("[Middleware] Authentication error:", error);
    throw new Error("Not authenticated");
  }
}

export async function middleware(request: NextRequest) {
  console.log("[Middleware] Processing request for:", request.nextUrl.pathname);

  // Skip middleware for API routes and static files
  if (
    request.nextUrl.pathname.startsWith("/api") ||
    request.nextUrl.pathname.startsWith("/_next") ||
    request.nextUrl.pathname.includes(".")
  ) {
    console.log(
      "[Middleware] Skipping middleware for:",
      request.nextUrl.pathname
    );
    return NextResponse.next();
  }

  try {
    // Check if user is authenticated
    let user = null;
    let isAuthenticated = false;

    try {
      console.log("[Middleware] Attempting to get current user");
      const response = await getCurrentUser(request.headers.get("cookie"));
      user = response.data?.user;
      isAuthenticated = true;
      console.log(
        "[Middleware] User authenticated:",
        user?.email,
        "Role:",
        user?.role
      );
    } catch (error) {
      // User is not authenticated
      console.log(
        "[Middleware] User not authenticated:",
        error instanceof Error ? error.message : String(error)
      );
      isAuthenticated = false;
    }

    // If not authenticated and trying to access protected routes
    if (!isAuthenticated) {
      if (request.nextUrl.pathname.startsWith("/admin")) {
        console.log(
          "[Middleware] Redirecting unauthenticated user from /admin to /login"
        );
        return NextResponse.redirect(new URL("/login", request.url));
      }
      console.log(
        "[Middleware] Allowing unauthenticated access to:",
        request.nextUrl.pathname
      );
      return NextResponse.next();
    }

    const userRole = user?.role;
    const emailVerified = user?.email_verified_at;
    console.log(
      "[Middleware] User role:",
      userRole,
      "Email verified:",
      !!emailVerified
    );

    // Protected routes that require email verification
    const requiresVerification = [
      "/learn/*",
    ];

    // Check if the current route requires email verification
    const needsVerification = requiresVerification.some((route) =>
      request.nextUrl.pathname.startsWith(route)
    );

    // Redirect to verification notice if email is not verified and route requires it
    if (needsVerification && !emailVerified) {
      console.log(
        "[Middleware] Redirecting to verification notice - email not verified"
      );
      return NextResponse.redirect(
        new URL("/verification-notice", request.url)
      );
    }

    // Protect admin routes
    if (request.nextUrl.pathname.startsWith("/admin")) {
      if (userRole !== UserRole.ADMIN && userRole !== "admin") {
        // Redirect non-admin users to home page
        console.log("[Middleware] Redirecting non-admin user from /admin to /");
        return NextResponse.redirect(new URL("/", request.url));
      }
      console.log(
        "[Middleware] Allowing admin access to:",
        request.nextUrl.pathname
      );
      return NextResponse.next();
    }

    // Define public pages that authenticated users should be redirected from
    const publicPages = [
      "/",
      "/login",
      "/register",
      "/forgot-password",
      "/reset-password",
    ];

    // Check if the current page is a public page
    const isPublicPage = publicPages.some(
      (page) =>
        request.nextUrl.pathname === page ||
        request.nextUrl.pathname.startsWith(`${page}/`)
    );

    console.log("[Middleware] Current page is public:", isPublicPage);

    // If authenticated user is trying to access a public page, redirect to their dashboard
    if (isPublicPage) {
      // Determine the appropriate dashboard based on user role
      const redirectTo =
        userRole === UserRole.ADMIN || userRole === "admin"
          ? "/admin"
          : "/learn";

      // Check if this is a post-login redirect (has a special query parameter)
      const isPostLoginRedirect =
        request.nextUrl.searchParams.has("post_login");

      console.log("[Middleware] Is post-login redirect:", isPostLoginRedirect);

      // If it's not a post-login redirect, redirect to the dashboard
      if (!isPostLoginRedirect) {
        console.log(
          `[Middleware] Redirecting authenticated user from ${request.nextUrl.pathname} to ${redirectTo}`
        );
        return NextResponse.redirect(new URL(redirectTo, request.url));
      } else {
        // Remove the post_login parameter after it's been used once
        // This prevents authenticated users from accessing public pages by keeping the parameter
        const url = new URL(request.url);
        url.searchParams.delete("post_login");
        console.log(
          "[Middleware] Removing post_login parameter, redirecting to:",
          url.toString()
        );
        return NextResponse.redirect(url);
      }
    }

    console.log(
      "[Middleware] Proceeding with request for:",
      request.nextUrl.pathname
    );
    return NextResponse.next();
  } catch (error) {
    console.error("[Middleware] Unexpected error:", error);
    return NextResponse.next();
  }
}

// Configure middleware to run on specific paths
export const config = {
  matcher: [
    /*
     * Match all request paths except:
     * 1. /api routes
     * 2. /_next (Next.js internals)
     * 3. /_static (static files)
     * 4. /images (static images)
     * 5. /favicon.ico, /sitemap.xml (static files)
     */
    "/((?!api|_next|_static|images|favicon.ico|sitemap.xml).*)",
  ],
};
