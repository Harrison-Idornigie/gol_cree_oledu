import "@testing-library/react";
import { expect, afterEach, vi } from "vitest";
import { cleanup } from "@testing-library/react";
import * as matchers from "@testing-library/jest-dom/matchers";

// Extend Vitest's expect method with methods from react-testing-library
expect.extend(matchers);

// Run cleanup after each test case (e.g. clearing jsdom)
afterEach(() => {
  cleanup();
  vi.resetAllMocks();
});

// Mock next/navigation
vi.mock("next/navigation", () => ({
  useRouter: () => ({
    push: vi.fn(),
    replace: vi.fn(),
    refresh: vi.fn(),
    back: vi.fn(),
    forward: vi.fn(),
  }),
  useSearchParams: () => ({
    get: vi.fn(),
  }),
  usePathname: () => "",
}));

// Mock next/headers
vi.mock("next/headers", () => ({
  cookies: () => ({
    get: vi.fn(),
    set: vi.fn(),
    delete: vi.fn(),
  }),
}));

// Mock server actions
vi.mock("@/app/_actions/auth-actions", () => ({
  login: vi.fn(),
  register: vi.fn(),
  logout: vi.fn(),
  forgotPassword: vi.fn(),
  resetPassword: vi.fn(),
  resendVerificationEmail: vi.fn(),
  getGoogleAuthUrl: vi.fn(),
}));

// Mock toast
vi.mock("sonner", () => ({
  toast: {
    success: vi.fn(),
    error: vi.fn(),
  },
}));

// Import React for JSX
import React from "react";

// We're not mocking UI components - we'll use the real ones
// This is a more modern approach that tests the actual components

// Only mock the lucide-react icons since they might have SVG dependencies
vi.mock("lucide-react", () => ({
  AlertCircle: () => ({
    type: "div",
    props: { "data-testid": "alert-circle-icon" },
  }),
  CheckCircle2: () => ({
    type: "div",
    props: { "data-testid": "check-circle-icon" },
  }),
}));
