import { type NextRequest } from "next/server";
import { NextResponse } from "next/server";

// Function to extract auth token from cookie string
function extractAuthToken(cookieHeader: string | null): string | null {
  if (!cookieHeader) {
    return null;
  }

  const cookies = cookieHeader.split(";");
  for (const cookie of cookies) {
    const [name, value] = cookie.trim().split("=");
    if (name === "auth_token") {
      return decodeURIComponent(value);
    }
  }
  return null;
}

// Simple function to check if user has a valid token (without API call)
function hasValidToken(cookieHeader: string | null): boolean {
  const token = extractAuthToken(cookieHeader);
  return !!token && token.length > 0;
}

export async function middleware(request: NextRequest) {
  // Skip middleware for API routes and static files
  if (
    request.nextUrl.pathname.startsWith("/api") ||
    request.nextUrl.pathname.startsWith("/_next") ||
    request.nextUrl.pathname.includes(".")
  ) {
    return NextResponse.next();
  }

  // Check if user has a valid token (simple check, no API call)
  const isAuthenticated = hasValidToken(request.headers.get("cookie"));

  // Define protected routes that require authentication
  const protectedRoutes = ["/admin", "/super", "/student", "/team"];
  const isProtectedRoute = protectedRoutes.some((route) =>
    request.nextUrl.pathname.startsWith(route)
  );

  // If not authenticated and trying to access protected routes
  if (!isAuthenticated && isProtectedRoute) {
    return NextResponse.redirect(new URL("/login", request.url));
  }

  // Define public pages that authenticated users should be redirected from
  const publicPages = [
    "/",
    "/login",
    "/register",
    "/forgot-password",
    "/reset-password",
  ];
  const isPublicPage = publicPages.some(
    (page) =>
      request.nextUrl.pathname === page ||
      request.nextUrl.pathname.startsWith(`${page}/`)
  );

  // If authenticated user is trying to access a public page, redirect to dashboard
  // Note: Role-based routing will be handled by the actual pages/components
  if (isAuthenticated && isPublicPage) {
    // Check if this is a post-login redirect
    const isPostLoginRedirect = request.nextUrl.searchParams.has("post_login");

    if (!isPostLoginRedirect) {
      // Default redirect to login page for authenticated users
      // Note: Proper role-based routing is handled by the login action
      return NextResponse.redirect(new URL("/login", request.url));
    } else {
      // Remove the post_login parameter
      const url = new URL(request.url);
      url.searchParams.delete("post_login");
      return NextResponse.redirect(url);
    }
  }

  return NextResponse.next();
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
