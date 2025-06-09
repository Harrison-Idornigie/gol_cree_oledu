import React, { ReactElement } from "react";
import { render, RenderOptions } from "@testing-library/react";
import { AuthProvider } from "@/app/providers/auth-provider";
import { User, UserRole } from "@/types/tenant/user";
import { vi } from "vitest";

// Mock user data
export const mockUser: User = {
  id: 1,
  name: "Test User",
  email: "test@example.com",
  role: UserRole.USER,
  is_active: true,
  created_at: "2023-01-01T00:00:00.000Z",
  updated_at: "2023-01-01T00:00:00.000Z",
  email_verified_at: null,
};

export const mockVerifiedUser: User = {
  ...mockUser,
  email_verified_at: "2023-01-01T00:00:00.000Z",
};

// Mock auth context
export const mockAuthContext = {
  user: mockUser,
  setUser: vi.fn(),
  isLoading: false,
};

export const mockVerifiedAuthContext = {
  user: mockVerifiedUser,
  setUser: vi.fn(),
  isLoading: false,
};

// Mock auth provider
vi.mock("@/app/providers/auth-provider", () => ({
  AuthProvider: ({ children }: { children: React.ReactNode }) => (
    <>{children}</>
  ),
  useAuth: () => mockAuthContext,
}));

// Custom render with providers
const customRender = (
  ui: ReactElement,
  options?: Omit<RenderOptions, "wrapper">
) => render(ui, { ...options });

export * from "@testing-library/react";
export { customRender as render };
