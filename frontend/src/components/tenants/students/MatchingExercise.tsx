"use client";

import { useState, useEffect } from "react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { AlertCircle, CheckCircle, ArrowRight } from "lucide-react";
import { WordData } from "@/types/tenant/guidebook";
import ClickableText from "./ClickableText";

interface MatchingExerciseProps {
  exercise: {
    id: number;
    content: {
      instructions?: string;
      items: string[];
      matches: string[];
      word_ids: number[];
      word_mapping?: Record<string, number>;
    };
    answers?: {
      correct: Record<number, number>;
    };
  };
  onAnswer: (isCorrect: boolean) => void;
  onNext: () => void;
  wordData: Record<string, WordData>;
}

export default function MatchingExercise({
  exercise,
  onAnswer,
  onNext,
  wordData,
}: MatchingExerciseProps) {
  const [selectedPairs, setSelectedPairs] = useState<Record<number, number>>(
    {}
  );
  const [selectedItem, setSelectedItem] = useState<number | null>(null);
  const [isSubmitted, setIsSubmitted] = useState(false);
  const [isCorrect, setIsCorrect] = useState(false);
  const [localWordData, setLocalWordData] =
    useState<Record<string, WordData>>(wordData);

  useEffect(() => {
    setLocalWordData(wordData);
  }, [wordData]);

  const handleItemClick = (index: number) => {
    if (isSubmitted) return;

    // If an item is already selected, deselect it if clicked again
    if (selectedItem === index) {
      setSelectedItem(null);
      return;
    }

    // If no item is selected, select this one
    if (selectedItem === null) {
      setSelectedItem(index);
      return;
    }

    // If a match is clicked after an item is selected, create a pair
    const itemIndex = selectedItem;
    setSelectedPairs({
      ...selectedPairs,
      [itemIndex]: index,
    });
    setSelectedItem(null);
  };

  const handleMatchClick = (index: number) => {
    if (isSubmitted) return;

    // If no item is selected, do nothing
    if (selectedItem === null) {
      return;
    }

    // Create a pair
    setSelectedPairs({
      ...selectedPairs,
      [selectedItem]: index,
    });
    setSelectedItem(null);
  };

  const resetPair = (itemIndex: number) => {
    if (isSubmitted) return;

    const newPairs = { ...selectedPairs };
    delete newPairs[itemIndex];
    setSelectedPairs(newPairs);
  };

  const handleSubmit = () => {
    // Check if all items are matched
    if (Object.keys(selectedPairs).length < exercise.content.items.length) {
      return;
    }

    // Check if the answer is correct
    const correct = Object.entries(exercise.answers?.correct || {}).every(
      ([itemIndex, matchIndex]) =>
        selectedPairs[Number(itemIndex)] === matchIndex
    );

    setIsCorrect(correct);
    setIsSubmitted(true);
    onAnswer(correct);
  };

  const handleNext = () => {
    setSelectedPairs({});
    setSelectedItem(null);
    setIsSubmitted(false);
    onNext();
  };

  return (
    <Card className="p-6 space-y-6">
      {/* Instructions */}
      {exercise.content.instructions && (
        <div className="text-lg font-medium">
          <ClickableText
            text={exercise.content.instructions}
            wordMapping={exercise.content.word_mapping}
            wordData={localWordData}
          />
        </div>
      )}

      {/* Matching Exercise */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {/* Left Column - Items */}
        <div className="space-y-3">
          {exercise.content.items.map((item, index) => (
            <div
              key={`item-${index}`}
              onClick={() => handleItemClick(index)}
              className={`p-3 border rounded-md cursor-pointer transition-colors ${
                selectedItem === index
                  ? "bg-primary/20 border-primary"
                  : index in selectedPairs
                  ? isSubmitted
                    ? selectedPairs[index] === exercise.answers?.correct[index]
                      ? "bg-green-50 border-green-500"
                      : "bg-red-50 border-red-500"
                    : "bg-primary/10 border-primary"
                  : "hover:bg-accent"
              }`}
            >
              <div className="flex items-center justify-between">
                <ClickableText
                  text={item}
                  wordMapping={exercise.content.word_mapping}
                  wordData={localWordData}
                />
                {index in selectedPairs && !isSubmitted && (
                  <Button
                    variant="ghost"
                    size="sm"
                    className="h-6 w-6 p-0 ml-2"
                    onClick={(e) => {
                      e.stopPropagation();
                      resetPair(index);
                    }}
                  >
                    ×
                  </Button>
                )}
              </div>
            </div>
          ))}
        </div>

        {/* Right Column - Matches */}
        <div className="space-y-3">
          {exercise.content.matches.map((match, index) => (
            <div
              key={`match-${index}`}
              onClick={() => handleMatchClick(index)}
              className={`p-3 border rounded-md cursor-pointer transition-colors ${
                Object.values(selectedPairs).includes(index)
                  ? isSubmitted
                    ? Object.entries(exercise.answers?.correct || {}).some(
                        ([itemIndex, matchIndex]) =>
                          selectedPairs[Number(itemIndex)] === index &&
                          matchIndex === index
                      )
                      ? "bg-green-50 border-green-500"
                      : "bg-red-50 border-red-500"
                    : "bg-primary/10 border-primary"
                  : "hover:bg-accent"
              }`}
            >
              <ClickableText
                text={match}
                wordMapping={exercise.content.word_mapping}
                wordData={localWordData}
              />
            </div>
          ))}
        </div>
      </div>

      {/* Connections - Only visible on desktop */}
      <div className="relative hidden md:block">
        {Object.entries(selectedPairs).map(([itemIndex, matchIndex]) => (
          <div
            key={`connection-${itemIndex}-${matchIndex}`}
            className="absolute inset-0 pointer-events-none"
          >
            <svg className="absolute inset-0 w-full h-full">
              <line
                x1="50%"
                y1={`${
                  (Number(itemIndex) * 100) / exercise.content.items.length +
                  50 / exercise.content.items.length
                }%`}
                x2="50%"
                y2={`${
                  (matchIndex * 100) / exercise.content.matches.length +
                  50 / exercise.content.matches.length
                }%`}
                stroke={
                  isSubmitted
                    ? exercise.answers?.correct[Number(itemIndex)] ===
                      matchIndex
                      ? "#22c55e" // green-500
                      : "#ef4444" // red-500
                    : "#6366f1" // indigo-500
                }
                strokeWidth="2"
              />
            </svg>
          </div>
        ))}
      </div>

      {/* Mobile Connections - Visual indicators for mobile */}
      <div className="md:hidden space-y-4">
        {Object.entries(selectedPairs).map(([itemIndex, matchIndex]) => {
          const item = exercise.content.items[Number(itemIndex)];
          const match = exercise.content.matches[matchIndex];
          const isCorrectPair =
            isSubmitted &&
            exercise.answers?.correct[Number(itemIndex)] === matchIndex;
          const isIncorrectPair =
            isSubmitted &&
            exercise.answers?.correct[Number(itemIndex)] !== matchIndex;

          return (
            <div
              key={`mobile-connection-${itemIndex}-${matchIndex}`}
              className={`p-2 rounded-md flex items-center justify-between text-sm ${
                isSubmitted
                  ? isCorrectPair
                    ? "bg-green-50 border border-green-200"
                    : "bg-red-50 border border-red-200"
                  : "bg-primary/5 border border-primary/10"
              }`}
            >
              <div className="font-medium">{item}</div>
              <div className="mx-2">→</div>
              <div>{match}</div>
            </div>
          );
        })}
      </div>

      {/* Feedback */}
      {isSubmitted && (
        <div
          className={`p-4 rounded-md ${
            isCorrect ? "bg-green-50" : "bg-red-50"
          }`}
        >
          <div className="flex items-start gap-3">
            {isCorrect ? (
              <CheckCircle className="h-5 w-5 text-green-600 flex-shrink-0 mt-0.5" />
            ) : (
              <AlertCircle className="h-5 w-5 text-red-600 flex-shrink-0 mt-0.5" />
            )}
            <div>
              <p className="font-medium">
                {isCorrect ? "Correct!" : "Incorrect"}
              </p>
              {!isCorrect && (
                <p className="mt-1 text-sm">
                  Check the correct matches highlighted in green.
                </p>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Action Buttons */}
      <div className="flex justify-center md:justify-end">
        {!isSubmitted ? (
          <Button
            onClick={handleSubmit}
            disabled={
              Object.keys(selectedPairs).length < exercise.content.items.length
            }
            className="w-full md:w-auto"
          >
            Check Answer
          </Button>
        ) : (
          <Button onClick={handleNext} className="w-full md:w-auto">
            Next
          </Button>
        )}
      </div>
    </Card>
  );
}
