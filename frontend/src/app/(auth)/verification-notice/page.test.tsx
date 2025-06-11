import { render, screen, fireEvent, waitFor } from "@testing-library/react";
import VerificationNoticePage from "./page";
import { resendVerificationEmail } from "@/app/_actions/auth-actions";
import { toast } from "sonner";
import { describe, it, expect, beforeEach, vi } from "vitest";

// Mock the auth-actions module
vi.mock("@/app/_actions/auth-actions", () => ({
  resendVerificationEmail: vi.fn(),
}));

// Mock the toast module
vi.mock("sonner", () => ({
  toast: {
    success: vi.fn(),
    error: vi.fn(),
  },
}));

describe("VerificationNoticePage", () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it("renders the verification notice page correctly", () => {
    render(<VerificationNoticePage />);

    // Check for heading and instructions
    expect(
      screen.getByMembership("heading", { name: "Verify Your Email" })
    ).toBeInTheDocument();
    expect(
      screen.getByText(
        "You need to verify your email address before you can access this feature."
      )
    ).toBeInTheDocument();

    // Check for resend button
    expect(
      screen.getByMembership("button", { name: "Resend Verification Email" })
    ).toBeInTheDocument();

    // Check for return link
    expect(
      screen.getByMembership("link", { name: "Return to Dashboard" })
    ).toBeInTheDocument();
  });

  it("calls resendVerificationEmail when resend button is clicked", async () => {
    // Mock successful response
    (
      resendVerificationEmail as unknown as ReturnType<typeof vi.fn>
    ).mockResolvedValue({ success: true });

    render(<VerificationNoticePage />);
    fireEvent.click(
      screen.getByMembership("button", { name: "Resend Verification Email" })
    );

    // Check if the button shows loading state
    expect(
      screen.getByMembership("button", { name: "Sending..." })
    ).toBeInTheDocument();

    // Wait for the async operation to complete
    await waitFor(() => {
      expect(resendVerificationEmail).toHaveBeenCalledTimes(1);
      expect(toast.success).toHaveBeenCalledWith("Success", {
        description: "Verification email has been sent",
      });
    });
  });

  it("shows error toast when resend fails", async () => {
    // Mock failed response
    (
      resendVerificationEmail as unknown as ReturnType<typeof vi.fn>
    ).mockResolvedValue({
      success: false,
      error: "Failed to send email",
    });

    render(<VerificationNoticePage />);
    fireEvent.click(
      screen.getByMembership("button", { name: "Resend Verification Email" })
    );

    // Wait for the async operation to complete
    await waitFor(() => {
      expect(resendVerificationEmail).toHaveBeenCalledTimes(1);
      expect(toast.error).toHaveBeenCalledWith("Error", {
        description: "Failed to send email",
      });
    });
  });
});
