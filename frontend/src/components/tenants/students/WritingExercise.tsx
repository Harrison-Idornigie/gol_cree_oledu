"use client";

import { useState, useEffect } from "react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { AlertCircle, CheckCircle, RefreshCw } from "lucide-react";
import { WordData } from "@/types/tenant/vocabulary";
import ClickableText from "./ClickableText";
import WordTooltip from "./WordTooltip";

interface WritingExerciseProps {
  exercise: {
    id: number;
    content: {
      prompt: string;
      word_ids: number[];
      word_mapping?: Record<string, number>;
    };
    answers?: {
      correct: string[];
    };
  };
  onAnswer: (isCorrect: boolean) => void;
  onNext: () => void;
  wordData: Record<string, WordData>;
}

export default function WritingExercise({
  exercise,
  onAnswer,
  onNext,
  wordData,
}: WritingExerciseProps) {
  const [selectedWords, setSelectedWords] = useState<string[]>([]);
  const [availableWords, setAvailableWords] = useState<string[]>([]);
  const [isSubmitted, setIsSubmitted] = useState(false);
  const [isCorrect, setIsCorrect] = useState(false);
  const [localWordData, setLocalWordData] =
    useState<Record<string, WordData>>(wordData);

  useEffect(() => {
    setLocalWordData(wordData);
  }, [wordData]);

  useEffect(() => {
    // Generate available words (scrambled correct answer + possible distractors)
    if (exercise.answers?.correct) {
      const correctWords = [...exercise.answers.correct];

      // Add some distractors from our word data
      const allWords = Object.keys(wordData);
      const distractors = allWords
        .filter((word) => !correctWords.includes(word))
        .slice(0, Math.min(5, correctWords.length));

      const words = [...correctWords, ...distractors];

      // Shuffle the words
      setAvailableWords(shuffle(words));
    }
  }, [exercise, wordData]);

  const handleSubmit = () => {
    if (selectedWords.length === 0) return;

    // Check if the answer is correct (words in correct order)
    const userSentence = selectedWords.join(" ");
    const correctSentence = exercise.answers?.correct.join(" ") || "";
    const correct =
      userSentence.toLowerCase() === correctSentence.toLowerCase();

    setIsCorrect(correct);
    setIsSubmitted(true);
    onAnswer(correct);
  };

  const handleReset = () => {
    setSelectedWords([]);
  };

  const handleNext = () => {
    setSelectedWords([]);
    setIsSubmitted(false);
    onNext();
  };

  // Function to shuffle array
  const shuffle = (array: string[]) => {
    const newArray = [...array];
    for (let i = newArray.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [newArray[i], newArray[j]] = [newArray[j], newArray[i]];
    }
    return newArray;
  };

  return (
    <Card className="p-6 space-y-6">
      {/* Prompt */}
      <div className="text-lg font-medium">
        <ClickableText
          text={exercise.content.prompt}
          wordMapping={exercise.content.word_mapping}
          wordData={localWordData}
        />
      </div>

      {/* Selected Words Area */}
      <div className="p-4 min-h-[100px] border rounded-md bg-muted/30">
        <div className="flex flex-wrap gap-2">
          {selectedWords.map((word, index) => (
            <Button
              key={index}
              variant="secondary"
              className="text-base"
              onClick={() => {
                if (isSubmitted) return;
                const newSelected = [...selectedWords];
                newSelected.splice(index, 1);
                setSelectedWords(newSelected);
              }}
              disabled={isSubmitted}
            >
              {localWordData[word.toLowerCase()] ? (
                <WordTooltip word={localWordData[word.toLowerCase()]} />
              ) : (
                word
              )}
            </Button>
          ))}
          {selectedWords.length === 0 && (
            <p className="text-muted-foreground">
              Select words to form a sentence
            </p>
          )}
        </div>
      </div>

      {/* Word Bank */}
      <div className="space-y-2">
        <div className="flex justify-between items-center">
          <label className="text-sm font-medium text-muted-foreground">
            Available words
          </label>
          {!isSubmitted && selectedWords.length > 0 && (
            <Button
              variant="ghost"
              size="sm"
              onClick={handleReset}
              className="h-8 px-2"
            >
              <RefreshCw className="h-4 w-4 mr-1" />
              Reset
            </Button>
          )}
        </div>
        <div className="flex flex-wrap gap-2">
          {availableWords.map((word, index) => (
            <Button
              key={index}
              variant="outline"
              className={`${
                selectedWords.includes(word)
                  ? "opacity-50 cursor-not-allowed"
                  : ""
              }`}
              onClick={() => {
                if (selectedWords.includes(word) || isSubmitted) return;
                setSelectedWords([...selectedWords, word]);
              }}
              disabled={selectedWords.includes(word) || isSubmitted}
            >
              {localWordData[word.toLowerCase()] ? (
                <WordTooltip word={localWordData[word.toLowerCase()]} />
              ) : (
                word
              )}
            </Button>
          ))}
        </div>
      </div>

      {/* Action Buttons */}
      {!isSubmitted ? (
        <Button
          onClick={handleSubmit}
          className="w-full"
          disabled={selectedWords.length === 0}
        >
          Check
        </Button>
      ) : (
        <Button onClick={handleNext} className="w-full">
          Continue
        </Button>
      )}

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
                  The correct sentence is:{" "}
                  <span className="font-medium">
                    {exercise.answers?.correct.map((word, idx) => (
                      <span key={idx}>
                        {localWordData[word.toLowerCase()] ? (
                          <WordTooltip
                            word={localWordData[word.toLowerCase()]}
                          />
                        ) : (
                          word
                        )}
                        {idx < (exercise.answers?.correct.length || 0) - 1
                          ? " "
                          : ""}
                      </span>
                    ))}
                  </span>
                </p>
              )}
            </div>
          </div>
        </div>
      )}
    </Card>
  );
}
