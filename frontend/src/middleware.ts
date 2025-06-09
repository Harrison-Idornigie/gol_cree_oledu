import { type NextRequest } from "next/server";
import { NextResponse } from "next/server";
import {
  extractTenantFromPath,
  isTenantPath,
  isCentralPath
} from "@/types/tenant/user";

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
  const pathname = request.nextUrl.pathname;

  // Skip middleware for API routes and static files
  if (
    pathname.startsWith("/api") ||
    pathname.startsWith("/_next") ||
    pathname.includes(".")
  ) {
    return NextResponse.next();
  }

  // Check if user has a valid token (simple check, no API call)
  const isAuthenticated = hasValidToken(request.headers.get("cookie"));

  // Extract tenant context from path
  const { tenantSlug, role } = extractTenantFromPath(pathname);
  const isValidTenantPath = isTenantPath(pathname);
  const isCentralRoute = isCentralPath(pathname);

  // Handle tenant-specific paths: /{tenant-slug}/{role}/*
  if (isValidTenantPath) {
    // Require authentication for all tenant paths
    if (!isAuthenticated) {
      return NextResponse.redirect(new URL("/login", request.url));
    }

    // TODO: Add tenant validation and user role checking here
    // For now, allow access to valid tenant paths
    return NextResponse.next();
  }

  // Handle central/landlord routes
  if (isCentralRoute) {
    // Super admin routes require authentication
    if (pathname.startsWith("/super")) {
      if (!isAuthenticated) {
        return NextResponse.redirect(new URL("/login", request.url));
      }
      // TODO: Add super admin role validation
      return NextResponse.next();
    }

    // Public auth routes (login, register, etc.)
    const publicAuthRoutes = ["/login", "/register", "/forgot-password", "/reset-password"];
    const isPublicAuthRoute = publicAuthRoutes.some(route =>
      pathname === route || pathname.startsWith(`${route}/`)
    );

    if (isPublicAuthRoute) {
      // Redirect authenticated users away from auth pages
      if (isAuthenticated) {
        // TODO: Redirect to appropriate tenant dashboard based on user context
        return NextResponse.redirect(new URL("/login", request.url));
      }
      return NextResponse.next();
    }

    return NextResponse.next();
  }

  // Handle root path and other unmatched paths
  if (pathname === "/") {
    if (isAuthenticated) {
      // TODO: Redirect to user's appropriate tenant dashboard
      // For now, redirect to login to handle role-based routing
      return NextResponse.redirect(new URL("/login", request.url));
    }
    // Redirect unauthenticated users to login
    return NextResponse.redirect(new URL("/login", request.url));
  }

  // Handle legacy routes that don't follow tenant path structure
  const legacyProtectedRoutes = ["/admin", "/team", "/student"];
  const isLegacyProtectedRoute = legacyProtectedRoutes.some(route =>
    pathname.startsWith(route)
  );

  if (isLegacyProtectedRoute) {
    if (!isAuthenticated) {
      return NextResponse.redirect(new URL("/login", request.url));
    }
    // TODO: Redirect to proper tenant-based path
    // For now, allow access to maintain backward compatibility
    return NextResponse.next();
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
