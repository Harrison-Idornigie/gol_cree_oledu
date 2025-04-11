import { NextRequest, NextResponse } from "next/server";
import { middleware } from "./middleware";
import { UserRole } from "./types/user";
import { describe, it, expect, beforeEach, vi } from "vitest";

// Mock fetch
global.fetch = vi.fn();

// Mock NextResponse
vi.mock("next/server", () => {
  const originalModule = vi.importActual("next/server");
  return {
    ...originalModule,
    NextResponse: {
      next: vi.fn().mockReturnValue({ type: "next" }),
      redirect: vi
        .fn()
        .mockImplementation((url) => ({ type: "redirect", url })),
    },
  };
});

describe("Middleware", () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it("skips middleware for API routes", async () => {
    const request = {
      nextUrl: {
        pathname: "/api/auth/login",
      },
    } as unknown as NextRequest;

    await middleware(request);
    expect(NextResponse.next).toHaveBeenCalled();
    expect(global.fetch).not.toHaveBeenCalled();
  });

  it("redirects unauthenticated users from admin routes to login", async () => {
    const request = {
      nextUrl: {
        pathname: "/admin/dashboard",
        href: "http://localhost:3000/admin/dashboard",
      },
      headers: {
        get: vi.fn().mockReturnValue(""),
      },
      url: "http://localhost:3000/admin/dashboard",
    } as unknown as NextRequest;

    // Mock failed auth response
    (global.fetch as unknown as ReturnType<typeof vi.fn>).mockResolvedValueOnce(
      {
        ok: false,
      }
    );

    await middleware(request);
    expect(NextResponse.redirect).toHaveBeenCalledWith(
      expect.objectContaining({
        pathname: "/login",
      })
    );
  });

  it("redirects authenticated users from login page to appropriate dashboard", async () => {
    const request = {
      nextUrl: {
        pathname: "/login",
        href: "http://localhost:3000/login",
      },
      headers: {
        get: vi.fn().mockReturnValue("auth_token=123"),
      },
      url: "http://localhost:3000/login",
    } as unknown as NextRequest;

    // Mock successful auth response for admin user
    (global.fetch as unknown as ReturnType<typeof vi.fn>).mockResolvedValueOnce(
      {
        ok: true,
        json: vi.fn().mockResolvedValue({
          user: {
            role: UserRole.ADMIN,
            email_verified_at: "2023-01-01",
          },
        }),
      }
    );

    await middleware(request);
    expect(NextResponse.redirect).toHaveBeenCalledWith(
      expect.objectContaining({
        pathname: "/admin",
      })
    );
  });

  it("redirects unverified users from protected routes to verification notice", async () => {
    const request = {
      nextUrl: {
        pathname: "/learn/lessons/1",
        href: "http://localhost:3000/learn/lessons/1",
      },
      headers: {
        get: vi.fn().mockReturnValue("auth_token=123"),
      },
      url: "http://localhost:3000/learn/lessons/1",
    } as unknown as NextRequest;

    // Mock successful auth response for unverified user
    (global.fetch as unknown as ReturnType<typeof vi.fn>).mockResolvedValueOnce(
      {
        ok: true,
        json: vi.fn().mockResolvedValue({
          user: {
            role: UserRole.USER,
            email_verified_at: null,
          },
        }),
      }
    );

    await middleware(request);
    expect(NextResponse.redirect).toHaveBeenCalledWith(
      expect.objectContaining({
        pathname: "/verification-notice",
      })
    );
  });

  it("allows verified users to access protected routes", async () => {
    const request = {
      nextUrl: {
        pathname: "/learn/lessons/1",
        href: "http://localhost:3000/learn/lessons/1",
      },
      headers: {
        get: vi.fn().mockReturnValue("auth_token=123"),
      },
      url: "http://localhost:3000/learn/lessons/1",
    } as unknown as NextRequest;

    // Mock successful auth response for verified user
    (global.fetch as unknown as ReturnType<typeof vi.fn>).mockResolvedValueOnce(
      {
        ok: true,
        json: vi.fn().mockResolvedValue({
          user: {
            role: UserRole.USER,
            email_verified_at: "2023-01-01",
          },
        }),
      }
    );

    await middleware(request);
    expect(NextResponse.next).toHaveBeenCalled();
  });
});
