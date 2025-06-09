import { render, screen, fireEvent } from "@testing-library/react";
import { vi, describe, it, expect, beforeEach } from "vitest";
import WritingExercise from "../WritingExercise";
import { WordData } from "@/types/vocabulary";

// Mock the lucide-react icons
vi.mock("lucide-react", () => ({
  AlertCircle: () => <div data-testid="alert-circle-icon" />,
  CheckCircle: () => <div data-testid="check-circle-icon" />,
  RefreshCw: () => <div data-testid="refresh-icon" />,
}));

// Mock the WordTooltip component
vi.mock("../WordTooltip", () => ({
  default: ({ word }: { word: WordData }) => <span>{word.text}</span>,
}));

describe("WritingExercise", () => {
  const mockExercise = {
    id: 1,
    content: {
      prompt: "Translate: Hello, how are you?",
      word_ids: [1, 2, 3, 4],
      word_mapping: { Hello: 1, how: 2, are: 3, you: 4 },
    },
    answers: {
      correct: ["Hola", "cómo", "estás"],
    },
  };

  const mockWordData: Record<string, WordData> = {
    hola: {
      id: 1,
      text: "Hola",
      phonetic: "OH-lah",
      partOfSpeech: "interjection",
      translation: "Hello",
      audioUrl: "/audio/hola.mp3",
      example: "¡Hola! ¿Cómo estás?",
    },
    cómo: {
      id: 2,
      text: "cómo",
      phonetic: "KOH-moh",
      partOfSpeech: "adverb",
      translation: "how",
      audioUrl: "/audio/como.mp3",
      example: "¿Cómo te llamas?",
    },
    estás: {
      id: 3,
      text: "estás",
      phonetic: "eh-STAHS",
      partOfSpeech: "verb",
      translation: "are",
      audioUrl: "/audio/estas.mp3",
      example: "¿Cómo estás hoy?",
    },
    tú: {
      id: 4,
      text: "tú",
      phonetic: "too",
      partOfSpeech: "pronoun",
      translation: "you",
      audioUrl: "/audio/tu.mp3",
      example: "Tú eres mi amigo.",
    },
  };

  const mockOnAnswer = vi.fn();
  const mockOnNext = vi.fn();

  beforeEach(() => {
    mockOnAnswer.mockClear();
    mockOnNext.mockClear();
  });

  it("renders the exercise prompt", () => {
    render(
      <WritingExercise
        exercise={mockExercise}
        onAnswer={mockOnAnswer}
        onNext={mockOnNext}
        wordData={mockWordData}
      />
    );

    // The prompt is now broken up into spans by ClickableText
    expect(screen.getByText("Translate:")).toBeInTheDocument();
    expect(screen.getByText("Hello,")).toBeInTheDocument();
    expect(screen.getByText("how")).toBeInTheDocument();
    expect(screen.getByText("are")).toBeInTheDocument();
    expect(screen.getByText("you?")).toBeInTheDocument();
  });

  it("displays available words to select", () => {
    render(
      <WritingExercise
        exercise={mockExercise}
        onAnswer={mockOnAnswer}
        onNext={mockOnNext}
        wordData={mockWordData}
      />
    );

    // Use getAllByText and check that at least one instance of each word exists
    expect(screen.getAllByText("Hola").length).toBeGreaterThan(0);
    expect(screen.getAllByText("cómo").length).toBeGreaterThan(0);
    expect(screen.getAllByText("estás").length).toBeGreaterThan(0);
  });

  it("allows selecting words to form a sentence", () => {
    render(
      <WritingExercise
        exercise={mockExercise}
        onAnswer={mockOnAnswer}
        onNext={mockOnNext}
        wordData={mockWordData}
      />
    );

    // Get all buttons containing the words and click the first one of each
    const holaButtons = screen.getAllByText("Hola");
    const comoButtons = screen.getAllByText("cómo");
    const estasButtons = screen.getAllByText("estás");

    fireEvent.click(holaButtons[0]);
    fireEvent.click(comoButtons[0]);
    fireEvent.click(estasButtons[0]);

    // Check that the words appear in the selected area
    const selectedButtons = screen.getAllByRole("button", {
      name: /Hola|cómo|estás/,
    });
    expect(selectedButtons.length).toBeGreaterThanOrEqual(3);
  });

  it("calls onAnswer with correct=true when the answer is correct", () => {
    render(
      <WritingExercise
        exercise={mockExercise}
        onAnswer={mockOnAnswer}
        onNext={mockOnNext}
        wordData={mockWordData}
      />
    );

    // Get all buttons containing the words and click the first one of each
    const holaButtons = screen.getAllByText("Hola");
    const comoButtons = screen.getAllByText("cómo");
    const estasButtons = screen.getAllByText("estás");

    // Select words in correct order
    fireEvent.click(holaButtons[0]);
    fireEvent.click(comoButtons[0]);
    fireEvent.click(estasButtons[0]);

    // Submit answer
    fireEvent.click(screen.getByText("Check"));

    expect(mockOnAnswer).toHaveBeenCalledWith(true);
    expect(screen.getByText("Correct!")).toBeInTheDocument();
  });

  it("calls onAnswer with correct=false when the answer is incorrect", () => {
    render(
      <WritingExercise
        exercise={mockExercise}
        onAnswer={mockOnAnswer}
        onNext={mockOnNext}
        wordData={mockWordData}
      />
    );

    // Get all buttons containing the words and click the first one of each
    const holaButtons = screen.getAllByText("Hola");
    const comoButtons = screen.getAllByText("cómo");
    const estasButtons = screen.getAllByText("estás");

    // Select words in wrong order
    fireEvent.click(estasButtons[0]);
    fireEvent.click(holaButtons[0]);
    fireEvent.click(comoButtons[0]);

    // Submit answer
    fireEvent.click(screen.getByText("Check"));

    expect(mockOnAnswer).toHaveBeenCalledWith(false);
    expect(screen.getByText("Incorrect")).toBeInTheDocument();
    expect(screen.getByText(/The correct sentence is/)).toBeInTheDocument();
  });

  it("calls onNext when continuing after submission", () => {
    render(
      <WritingExercise
        exercise={mockExercise}
        onAnswer={mockOnAnswer}
        onNext={mockOnNext}
        wordData={mockWordData}
      />
    );

    // Get all buttons containing the word Hola and click the first one
    const holaButtons = screen.getAllByText("Hola");

    // Select words and submit
    fireEvent.click(holaButtons[0]);
    fireEvent.click(screen.getByText("Check"));

    // Continue to next exercise
    fireEvent.click(screen.getByText("Continue"));

    expect(mockOnNext).toHaveBeenCalled();
  });

  it("allows resetting selected words", () => {
    render(
      <WritingExercise
        exercise={mockExercise}
        onAnswer={mockOnAnswer}
        onNext={mockOnNext}
        wordData={mockWordData}
      />
    );

    // Get all buttons containing the word Hola and click the first one
    const holaButtons = screen.getAllByText("Hola");

    // Select a word
    fireEvent.click(holaButtons[0]);

    // Reset
    fireEvent.click(screen.getByText("Reset"));

    // Check that the selection area shows the empty state message
    expect(
      screen.getByText("Select words to form a sentence")
    ).toBeInTheDocument();
  });
});
