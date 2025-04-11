import { render, screen, fireEvent, waitFor } from "@testing-library/react";
import ResetPasswordPage from "./page";
import { resetPassword } from "@/app/_actions/auth-actions";
import { toast } from "sonner";
import { useRouter, useSearchParams } from "next/navigation";
import { describe, it, expect, beforeEach, vi } from "vitest";

// Mock the auth-actions module
vi.mock("@/app/_actions/auth-actions", () => ({
  resetPassword: vi.fn(),
}));

// Mock the toast module
vi.mock("sonner", () => ({
  toast: {
    success: vi.fn(),
    error: vi.fn(),
  },
}));

// Mock the next/navigation module
vi.mock("next/navigation", () => ({
  useRouter: vi.fn(),
  useSearchParams: vi.fn(),
}));

describe("ResetPasswordPage", () => {
  beforeEach(() => {
    vi.resetAllMocks();

    // Setup default mocks
    (useRouter as unknown as ReturnType<typeof vi.fn>).mockReturnValue({
      push: vi.fn(),
    });
  });

  it("shows invalid link message when token or email is missing", () => {
    // Mock search params with no token and email
    (useSearchParams as unknown as ReturnType<typeof vi.fn>).mockReturnValue({
      get: vi.fn().mockReturnValue(null),
    });

    render(<ResetPasswordPage />);

    expect(screen.getByText("Invalid Reset Link")).toBeInTheDocument();
    expect(
      screen.getByText("The password reset link is invalid or has expired.")
    ).toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: "Request a new password reset link" })
    ).toBeInTheDocument();
  });

  it("renders the reset password form when token and email are present", () => {
    // Mock search params with token and email
    (useSearchParams as unknown as ReturnType<typeof vi.fn>).mockReturnValue({
      get: (param: string) =>
        param === "token" ? "valid-token" : "test@example.com",
    });

    render(<ResetPasswordPage />);

    expect(
      screen.getByRole("heading", { name: "Reset Your Password" })
    ).toBeInTheDocument();
    expect(screen.getByLabelText("New Password")).toBeInTheDocument();
    expect(screen.getByLabelText("Confirm New Password")).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Reset Password" })
    ).toBeInTheDocument();
  });

  it("shows error when passwords do not match", async () => {
    // Mock search params with token and email
    (useSearchParams as unknown as ReturnType<typeof vi.fn>).mockReturnValue({
      get: (param: string) =>
        param === "token" ? "valid-token" : "test@example.com",
    });

    render(<ResetPasswordPage />);

    // Fill in the password fields with different values
    fireEvent.change(screen.getByLabelText("New Password"), {
      target: { value: "password123" },
    });

    fireEvent.change(screen.getByLabelText("Confirm New Password"), {
      target: { value: "different123" },
    });

    // Submit the form
    fireEvent.click(screen.getByRole("button", { name: "Reset Password" }));

    // Check for error message
    expect(screen.getByText("Passwords do not match")).toBeInTheDocument();
  });

  it("shows error when password is too short", async () => {
    // Mock search params with token and email
    (useSearchParams as unknown as ReturnType<typeof vi.fn>).mockReturnValue({
      get: (param: string) =>
        param === "token" ? "valid-token" : "test@example.com",
    });

    render(<ResetPasswordPage />);

    // Fill in the password fields with short password
    fireEvent.change(screen.getByLabelText("New Password"), {
      target: { value: "short" },
    });

    fireEvent.change(screen.getByLabelText("Confirm New Password"), {
      target: { value: "short" },
    });

    // Submit the form
    fireEvent.click(screen.getByRole("button", { name: "Reset Password" }));

    // Check for error message
    expect(
      screen.getByText("Password must be at least 8 characters long")
    ).toBeInTheDocument();
  });

  it("submits the form and redirects on success", async () => {
    // Mock search params with token and email
    (useSearchParams as unknown as ReturnType<typeof vi.fn>).mockReturnValue({
      get: (param: string) =>
        param === "token" ? "valid-token" : "test@example.com",
    });

    // Mock successful response
    (resetPassword as unknown as ReturnType<typeof vi.fn>).mockResolvedValue({
      success: true,
    });

    const mockPush = vi.fn();
    (useRouter as unknown as ReturnType<typeof vi.fn>).mockReturnValue({
      push: mockPush,
    });

    render(<ResetPasswordPage />);

    // Fill in the password fields
    fireEvent.change(screen.getByLabelText("New Password"), {
      target: { value: "newpassword123" },
    });

    fireEvent.change(screen.getByLabelText("Confirm New Password"), {
      target: { value: "newpassword123" },
    });

    // Submit the form
    fireEvent.click(screen.getByRole("button", { name: "Reset Password" }));

    // Check if the button shows loading state
    expect(
      screen.getByRole("button", { name: "Resetting Password..." })
    ).toBeInTheDocument();

    // Wait for the async operation to complete
    await waitFor(() => {
      expect(resetPassword).toHaveBeenCalledWith({
        token: "valid-token",
        email: "test@example.com",
        password: "newpassword123",
        password_confirmation: "newpassword123",
      });

      expect(toast.success).toHaveBeenCalledWith("Success", {
        description: "Your password has been reset successfully",
      });

      expect(mockPush).toHaveBeenCalledWith("/login");
    });
  });

  it("shows error when reset fails", async () => {
    // Mock search params with token and email
    (useSearchParams as unknown as ReturnType<typeof vi.fn>).mockReturnValue({
      get: (param: string) =>
        param === "token" ? "valid-token" : "test@example.com",
    });

    // Mock failed response
    (resetPassword as unknown as ReturnType<typeof vi.fn>).mockResolvedValue({
      success: false,
      error: "Invalid token",
    });

    render(<ResetPasswordPage />);

    // Fill in the password fields
    fireEvent.change(screen.getByLabelText("New Password"), {
      target: { value: "newpassword123" },
    });

    fireEvent.change(screen.getByLabelText("Confirm New Password"), {
      target: { value: "newpassword123" },
    });

    // Submit the form
    fireEvent.click(screen.getByRole("button", { name: "Reset Password" }));

    // Wait for the async operation to complete
    await waitFor(() => {
      expect(resetPassword).toHaveBeenCalled();
      expect(screen.getByText("Invalid token")).toBeInTheDocument();
    });
  });
});
