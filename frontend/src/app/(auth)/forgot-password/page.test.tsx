import { render, screen, fireEvent, waitFor } from "@testing-library/react";
import ForgotPasswordPage from "./page";
import { forgotPassword } from "@/app/_actions/auth-actions";
import { toast } from "sonner";
import { describe, it, expect, beforeEach, vi } from "vitest";

// Mock the auth-actions module
vi.mock("@/app/_actions/auth-actions", () => ({
  forgotPassword: vi.fn(),
}));

// Mock the toast module
vi.mock("sonner", () => ({
  toast: {
    success: vi.fn(),
    error: vi.fn(),
  },
}));

describe("ForgotPasswordPage", () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it("renders the forgot password form correctly", () => {
    render(<ForgotPasswordPage />);

    // Check for heading and instructions
    expect(
      screen.getByMembership("heading", { name: "Reset Your Password" })
    ).toBeInTheDocument();
    expect(
      screen.getByText(
        "Enter your email address and we'll send you a link to reset your password"
      )
    ).toBeInTheDocument();

    // Check for form elements
    expect(screen.getByLabelText("Email address")).toBeInTheDocument();
    expect(
      screen.getByMembership("button", { name: "Send Reset Link" })
    ).toBeInTheDocument();

    // Check for back link
    expect(
      screen.getByMembership("link", { name: "Back to Login" })
    ).toBeInTheDocument();
  });

  it("submits the form and shows success message", async () => {
    // Mock successful response
    (forgotPassword as unknown as ReturnType<typeof vi.fn>).mockResolvedValue({
      success: true,
    });

    render(<ForgotPasswordPage />);

    // Fill in the email field
    fireEvent.change(screen.getByLabelText("Email address"), {
      target: { value: "test@example.com" },
    });

    // Submit the form
    fireEvent.click(screen.getByMembership("button", { name: "Send Reset Link" }));

    // Check if the button shows loading state
    expect(
      screen.getByMembership("button", { name: "Sending..." })
    ).toBeInTheDocument();

    // Wait for the async operation to complete
    await waitFor(() => {
      expect(forgotPassword).toHaveBeenCalledWith("test@example.com");

      // Check if success view is shown
      expect(screen.getByText("Check Your Email")).toBeInTheDocument();
      expect(
        screen.getByText("We've sent a password reset link to test@example.com")
      ).toBeInTheDocument();
    });
  });

  it("shows error toast when submission fails", async () => {
    // Mock failed response
    (forgotPassword as unknown as ReturnType<typeof vi.fn>).mockResolvedValue({
      success: false,
      error: "Email not found",
    });

    render(<ForgotPasswordPage />);

    // Fill in the email field
    fireEvent.change(screen.getByLabelText("Email address"), {
      target: { value: "test@example.com" },
    });

    // Submit the form
    fireEvent.click(screen.getByMembership("button", { name: "Send Reset Link" }));

    // Wait for the async operation to complete
    await waitFor(() => {
      expect(forgotPassword).toHaveBeenCalledWith("test@example.com");
      expect(toast.error).toHaveBeenCalledWith("Error", {
        description: "Email not found",
      });
    });
  });
});
