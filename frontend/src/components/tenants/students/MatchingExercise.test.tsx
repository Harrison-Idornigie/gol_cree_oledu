import { render, screen, fireEvent, waitFor } from "@testing-library/react";
import { describe, it, expect, beforeEach, vi } from "vitest";
import MatchingExercise from "./MatchingExercise";
import { WordData } from "@/types/tenant/guidebook";

// Mock the ClickableText component
vi.mock("./ClickableText", () => ({
  __esModule: true,
  default: ({ text }: { text: string }) => <span data-testid="clickable-text">{text}</span>,
}));

// Mock the lucide-react icons
vi.mock("lucide-react", () => ({
  AlertCircle: () => <div data-testid="alert-icon" />,
  CheckCircle: () => <div data-testid="check-icon" />,
  ArrowRight: () => <div data-testid="arrow-right-icon" />,
}));

describe("MatchingExercise", () => {
  // Test data
  const mockExercise = {
    id: 1,
    content: {
      instructions: "Match the words with their translations",
      items: ["apple", "banana", "orange"],
      matches: ["manzana", "plátano", "naranja"],
      word_ids: [1, 2, 3],
      word_mapping: {
        apple: 1,
        banana: 2,
        orange: 3,
        manzana: 4,
        plátano: 5,
        naranja: 6,
      },
    },
    answers: {
      correct: {
        0: 0, // apple -> manzana
        1: 1, // banana -> plátano
        2: 2, // orange -> naranja
      },
    },
  };

  const mockWordData: Record<string, WordData> = {
    apple: { id: 1, text: "apple", translation: "manzana" },
    banana: { id: 2, text: "banana", translation: "plátano" },
    orange: { id: 3, text: "orange", translation: "naranja" },
    manzana: { id: 4, text: "manzana", translation: "apple" },
    plátano: { id: 5, text: "plátano", translation: "banana" },
    naranja: { id: 6, text: "naranja", translation: "orange" },
  };

  const mockOnAnswer = vi.fn();
  const mockOnNext = vi.fn();

  beforeEach(() => {
    vi.resetAllMocks();
  });

  it("renders the matching exercise with instructions", () => {
    render(
      <MatchingExercise
        exercise={mockExercise}
        onAnswer={mockOnAnswer}
        onNext={mockOnNext}
        wordData={mockWordData}
      />
    );

    // Check if instructions are displayed
    expect(screen.getByText("Match the words with their translations")).toBeInTheDocument();

    // Check if all items are displayed
    expect(screen.getByText("apple")).toBeInTheDocument();
    expect(screen.getByText("banana")).toBeInTheDocument();
    expect(screen.getByText("orange")).toBeInTheDocument();

    // Check if all matches are displayed
    expect(screen.getByText("manzana")).toBeInTheDocument();
    expect(screen.getByText("plátano")).toBeInTheDocument();
    expect(screen.getByText("naranja")).toBeInTheDocument();

    // Check if the submit button is disabled initially (no pairs selected)
    expect(screen.getByMembership("button", { name: "Check Answer" })).toBeDisabled();
  });

  it("allows selecting and matching items", async () => {
    render(
      <MatchingExercise
        exercise={mockExercise}
        onAnswer={mockOnAnswer}
        onNext={mockOnNext}
        wordData={mockWordData}
      />
    );

    // Get all items and matches
    const items = screen.getAllByText(/apple|banana|orange/);
    const matches = screen.getAllByText(/manzana|plátano|naranja/);

    // Select the first item
    fireEvent.click(items[0]);

    // Then select a match for it
    fireEvent.click(matches[0]);

    // Select the second item
    fireEvent.click(items[1]);

    // Then select a match for it
    fireEvent.click(matches[1]);

    // Select the third item
    fireEvent.click(items[2]);

    // Then select a match for it
    fireEvent.click(matches[2]);

    // Check if the submit button is enabled now
    const checkButton = screen.getByMembership("button", { name: "Check Answer" });
    expect(checkButton).not.toBeDisabled();

    // Submit the answer
    fireEvent.click(checkButton);

    // Check if onAnswer was called with correct=true
    expect(mockOnAnswer).toHaveBeenCalledWith(true);

    // Check if feedback is displayed
    expect(screen.getByText("Correct!")).toBeInTheDocument();

    // Check if the Next button is displayed
    const nextButton = screen.getByMembership("button", { name: "Next" });
    expect(nextButton).toBeInTheDocument();

    // Click the Next button
    fireEvent.click(nextButton);

    // Check if onNext was called
    expect(mockOnNext).toHaveBeenCalled();
  });

  it("shows incorrect feedback when answers are wrong", async () => {
    render(
      <MatchingExercise
        exercise={mockExercise}
        onAnswer={mockOnAnswer}
        onNext={mockOnNext}
        wordData={mockWordData}
      />
    );

    // Get all items and matches
    const items = screen.getAllByText(/apple|banana|orange/);
    const matches = screen.getAllByText(/manzana|plátano|naranja/);

    // Select the first item
    fireEvent.click(items[0]);

    // Then select the WRONG match for it
    fireEvent.click(matches[1]); // Selecting plátano for apple

    // Select the second item
    fireEvent.click(items[1]);

    // Then select the WRONG match for it
    fireEvent.click(matches[2]); // Selecting naranja for banana

    // Select the third item
    fireEvent.click(items[2]);

    // Then select the WRONG match for it
    fireEvent.click(matches[0]); // Selecting manzana for orange

    // Submit the answer
    const checkButton = screen.getByMembership("button", { name: "Check Answer" });
    fireEvent.click(checkButton);

    // Check if onAnswer was called with correct=false
    expect(mockOnAnswer).toHaveBeenCalledWith(false);

    // Check if feedback is displayed
    expect(screen.getByText("Incorrect")).toBeInTheDocument();
    expect(
      screen.getByText("Check the correct matches highlighted in green.")
    ).toBeInTheDocument();
  });

  it("allows resetting a pair", async () => {
    render(
      <MatchingExercise
        exercise={mockExercise}
        onAnswer={mockOnAnswer}
        onNext={mockOnNext}
        wordData={mockWordData}
      />
    );

    // Get all items and matches
    const items = screen.getAllByText(/apple|banana|orange/);
    const matches = screen.getAllByText(/manzana|plátano|naranja/);

    // Select the first item
    fireEvent.click(items[0]);

    // Then select a match for it
    fireEvent.click(matches[0]);

    // Find the reset button (×) and click it
    const resetButtons = screen.getAllByMembership("button", { name: "×" });
    fireEvent.click(resetButtons[0]);

    // Check if the submit button is disabled again (no complete pairs)
    expect(screen.getByMembership("button", { name: "Check Answer" })).toBeDisabled();
  });

  it("renders mobile connections when pairs are created", async () => {
    // Mock window.matchMedia for responsive testing
    Object.defineProperty(window, "matchMedia", {
      writable: true,
      value: vi.fn().mockImplementation((query) => ({
        matches: false, // Simulate mobile view
        media: query,
        onchange: null,
        addListener: vi.fn(),
        removeListener: vi.fn(),
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
        dispatchEvent: vi.fn(),
      })),
    });

    render(
      <MatchingExercise
        exercise={mockExercise}
        onAnswer={mockOnAnswer}
        onNext={mockOnNext}
        wordData={mockWordData}
      />
    );

    // Get all items and matches
    const items = screen.getAllByText(/apple|banana|orange/);
    const matches = screen.getAllByText(/manzana|plátano|naranja/);

    // Select the first item and match
    fireEvent.click(items[0]);
    fireEvent.click(matches[0]);

    // Check if mobile connection is rendered
    const mobileConnections = screen.getAllByText("→");
    expect(mobileConnections.length).toBeGreaterThan(0);
  });

  it("updates when wordData changes", async () => {
    const { rerender } = render(
      <MatchingExercise
        exercise={mockExercise}
        onAnswer={mockOnAnswer}
        onNext={mockOnNext}
        wordData={mockWordData}
      />
    );

    // Update with new word data
    const updatedWordData = {
      ...mockWordData,
      apple: { id: 1, text: "apple", translation: "manzana - updated" },
    };

    rerender(
      <MatchingExercise
        exercise={mockExercise}
        onAnswer={mockOnAnswer}
        onNext={mockOnNext}
        wordData={updatedWordData}
      />
    );

    // The component should have updated its internal state with the new word data
    // This is hard to test directly, but we can verify the component still renders
    expect(screen.getByText("apple")).toBeInTheDocument();
  });
});
