import { render, screen, fireEvent, waitFor } from "@testing-library/react";
import { EmailVerificationBanner } from "./EmailVerificationBanner";
import { mockUser, mockVerifiedUser } from "@/test-utils/render";
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

describe("EmailVerificationBanner", () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it("renders nothing when user is null", () => {
    const { container } = render(<EmailVerificationBanner user={null} />);
    expect(container).toBeEmptyDOMElement();
  });

  it("renders nothing when user email is verified", () => {
    const { container } = render(
      <EmailVerificationBanner user={mockVerifiedUser} />
    );
    expect(container).toBeEmptyDOMElement();
  });

  it("renders the banner when user email is not verified", () => {
    render(<EmailVerificationBanner user={mockUser} />);
    expect(screen.getByText("Verify your email")).toBeInTheDocument();
    expect(
      screen.getByText(
        "Please verify your email address to access all features."
      )
    ).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Resend Email" })
    ).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Dismiss" })).toBeInTheDocument();
  });

  it("hides the banner when dismiss button is clicked", () => {
    render(<EmailVerificationBanner user={mockUser} />);
    fireEvent.click(screen.getByRole("button", { name: "Dismiss" }));
    expect(screen.queryByText("Verify your email")).not.toBeInTheDocument();
  });

  it("calls resendVerificationEmail when resend button is clicked", async () => {
    // Mock successful response
    (
      resendVerificationEmail as unknown as ReturnType<typeof vi.fn>
    ).mockResolvedValue({ success: true });

    render(<EmailVerificationBanner user={mockUser} />);
    fireEvent.click(screen.getByRole("button", { name: "Resend Email" }));

    // Check if the button shows loading state
    expect(
      screen.getByRole("button", { name: "Sending..." })
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

    render(<EmailVerificationBanner user={mockUser} />);
    fireEvent.click(screen.getByRole("button", { name: "Resend Email" }));

    // Wait for the async operation to complete
    await waitFor(() => {
      expect(resendVerificationEmail).toHaveBeenCalledTimes(1);
      expect(toast.error).toHaveBeenCalledWith("Error", {
        description: "Failed to send email",
      });
    });
  });
});
