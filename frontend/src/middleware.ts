import { type NextRequest } from "next/server";
import { NextResponse } from "next/server";
import { UserRole } from "./types/user";

export async function middleware(request: NextRequest) {
  // Skip middleware for API routes and static files
  if (
    request.nextUrl.pathname.startsWith("/api") ||
    request.nextUrl.pathname.startsWith("/_next") ||
    request.nextUrl.pathname.includes(".")
  ) {
    return NextResponse.next();
  }

  try {
    // Check if user is authenticated
    const response = await fetch(
      `${process.env.NEXT_PUBLIC_API_URL}/api/auth/me`,
      {
        headers: {
          Cookie: request.headers.get("cookie") || "",
        },
      }
    );

    if (!response.ok) {
      // Redirect to login if not authenticated
      if (request.nextUrl.pathname.startsWith("/admin")) {
        return NextResponse.redirect(new URL("/login", request.url));
      }
      return NextResponse.next();
    }

    const data = await response.json();
    const userRole = data.user?.role;
    const emailVerified = data.user?.email_verified_at;

    // Protected routes that require email verification
    const requiresVerification = [
      "/learn/lessons",
      "/learn/quizzes",
      "/learn/exercises",
    ];

    // Check if the current route requires email verification
    const needsVerification = requiresVerification.some((route) =>
      request.nextUrl.pathname.startsWith(route)
    );

    // Redirect to verification notice if email is not verified and route requires it
    if (needsVerification && !emailVerified) {
      return NextResponse.redirect(
        new URL("/verification-notice", request.url)
      );
    }

    // Protect admin routes
    if (request.nextUrl.pathname.startsWith("/admin")) {
      if (userRole !== UserRole.ADMIN) {
        // Redirect non-admin users to home page
        return NextResponse.redirect(new URL("/", request.url));
      }
      return NextResponse.next();
    }

    // Handle authenticated user routes
    if (
      request.nextUrl.pathname === "/login" ||
      request.nextUrl.pathname === "/register"
    ) {
      // Redirect authenticated users to their appropriate dashboard
      const redirectTo = userRole === UserRole.ADMIN ? "/admin" : "/learn";
      return NextResponse.redirect(new URL(redirectTo, request.url));
    }

    return NextResponse.next();
  } catch (error) {
    console.error("Middleware Error:", error);
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
