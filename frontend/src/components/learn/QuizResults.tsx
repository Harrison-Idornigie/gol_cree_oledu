"use client";

import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { CheckCircle, XCircle, ArrowRight } from "lucide-react";
import Link from "next/link";
import WordTooltip from "./WordTooltip";

interface QuizQuestion {
  question: string;
  options: string[];
  correctAnswer: string;
  explanation?: string;
}

import { WordData } from "@/types/vocabulary";

interface QuizResultsProps {
  questions: QuizQuestion[];
  userAnswers: Record<number, string>;
  score: number;
  pathId: string | number;
  onRetry: () => void;
  wordData?: Record<string, WordData>;
}

export default function QuizResults({
  questions,
  userAnswers,
  score,
  pathId,
  onRetry,
  wordData = {},
}: QuizResultsProps) {
  // Function to render text with clickable words
  const renderWithClickableWords = (text: string) => {
    // Simple word splitting - in a real app, you'd want more sophisticated parsing
    const words = text.split(/\s+/);

    return (
      <>
        {words.map((word, index) => {
          // Clean the word from punctuation for lookup
          const cleanWord = word.replace(/[.,!?;:'"()]/g, "");
          const wordInfo = wordData[cleanWord.toLowerCase()];

          if (wordInfo) {
            return (
              <span key={index}>
                <WordTooltip word={wordInfo} />
                {index < words.length - 1 ? " " : ""}
              </span>
            );
          }

          return (
            <span key={index}>
              {word}
              {index < words.length - 1 ? " " : ""}
            </span>
          );
        })}
      </>
    );
  };

  return (
    <div className="space-y-8">
      {/* Summary Card */}
      <Card>
        <CardContent className="p-6 text-center">
          <div className="flex justify-center mb-4">
            <div className="p-4 bg-green-100 rounded-full">
              <CheckCircle className="w-12 h-12 text-green-600" />
            </div>
          </div>
          <h2 className="mb-2 text-2xl font-bold">Quiz Completed!</h2>
          <p className="mb-6 text-muted-foreground">
            You scored {score}% on this quiz.
          </p>
          <div className="space-y-4">
            <Link href={`/learn/path/${pathId}`}>
              <Button className="w-full">Continue Learning</Button>
            </Link>
            <Button variant="outline" onClick={onRetry}>
              Try Again
            </Button>
          </div>
        </CardContent>
      </Card>

      {/* Detailed Results */}
      <div className="space-y-4">
        <h3 className="text-xl font-bold">Detailed Results</h3>

        {questions.map((question, index) => {
          const userAnswer = userAnswers[index];
          const isCorrect = userAnswer === question.correctAnswer;

          return (
            <Card
              key={index}
              className={`border-l-4 ${
                isCorrect ? "border-l-green-500" : "border-l-red-500"
              }`}
            >
              <CardContent className="p-4">
                <div className="flex items-start gap-3">
                  <div className="flex-shrink-0 mt-0.5">
                    {isCorrect ? (
                      <CheckCircle className="h-5 w-5 text-green-600" />
                    ) : (
                      <XCircle className="h-5 w-5 text-red-600" />
                    )}
                  </div>

                  <div className="space-y-3 w-full">
                    {/* Question */}
                    <div>
                      <h4 className="font-medium">
                        {renderWithClickableWords(question.question)}
                      </h4>
                    </div>

                    {/* Options */}
                    <div className="space-y-2">
                      {question.options.map((option) => (
                        <div
                          key={option}
                          className={`p-2 rounded-md text-sm ${
                            option === question.correctAnswer
                              ? "bg-green-50 border border-green-200"
                              : option === userAnswer && !isCorrect
                              ? "bg-red-50 border border-red-200"
                              : "bg-gray-50 border border-gray-200"
                          }`}
                        >
                          <div className="flex items-center justify-between">
                            <div>{renderWithClickableWords(option)}</div>
                            {option === question.correctAnswer && (
                              <CheckCircle className="h-4 w-4 text-green-600" />
                            )}
                            {option === userAnswer && !isCorrect && (
                              <XCircle className="h-4 w-4 text-red-600" />
                            )}
                          </div>
                        </div>
                      ))}
                    </div>

                    {/* Explanation */}
                    {question.explanation && (
                      <div className="text-sm text-muted-foreground bg-muted p-2 rounded">
                        <p className="font-medium mb-1">Explanation:</p>
                        <p>{renderWithClickableWords(question.explanation)}</p>
                      </div>
                    )}
                  </div>
                </div>
              </CardContent>
            </Card>
          );
        })}
      </div>
    </div>
  );
}
