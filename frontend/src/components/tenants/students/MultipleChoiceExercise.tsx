"use client";

import { useState, useEffect } from "react";
import { Card, CardContent } from "@/components/ui/card";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { CheckCircle, XCircle, AlertCircle, Volume2 } from "lucide-react";
import { getWordsForExercise } from "@/app/_actions/tenants/student/word-actions";
import ClickableText from "./ClickableText";

import { WordData } from "@/types/tenant/vocabulary";

interface MultipleChoiceExerciseProps {
  question: string;
  options: string[];
  correctAnswer: string;
  explanation?: string;
  audioUrl?: string;
  onAnswer: (isCorrect: boolean) => void;
  onNext: () => void;
  wordData?: Record<string, WordData>;
}

export default function MultipleChoiceExercise({
  question,
  options,
  correctAnswer,
  explanation,
  audioUrl,
  onAnswer,
  onNext,
  wordData = {},
}: MultipleChoiceExerciseProps) {
  const [selectedAnswer, setSelectedAnswer] = useState<string>("");
  const [isSubmitted, setIsSubmitted] = useState(false);
  const [isPlaying, setIsPlaying] = useState(false);
  const [localWordData, setLocalWordData] =
    useState<Record<string, WordData>>(wordData);

  const isCorrect = selectedAnswer === correctAnswer;

  useEffect(() => {
    // Reset state when question changes
    setSelectedAnswer("");
    setIsSubmitted(false);
  }, [question]);

  useEffect(() => {
    // Fetch word data if not provided
    if (Object.keys(wordData).length === 0) {
      const fetchWordData = async () => {
        const exerciseContent = {
          content: [
            {
              question,
              options,
              explanation,
            },
          ],
        };

        const data = await getWordsForExercise(exerciseContent);
        setLocalWordData(data);
      };

      fetchWordData();
    } else {
      setLocalWordData(wordData);
    }
  }, [question, options, explanation, wordData]);

  const handleSubmit = () => {
    setIsSubmitted(true);
    onAnswer(isCorrect);
  };

  const playAudio = () => {
    if (!audioUrl || isPlaying) return;

    setIsPlaying(true);
    const audio = new Audio(audioUrl);
    audio.play().catch(() => {
      setIsPlaying(false);
    });
    audio.onended = () => setIsPlaying(false);
  };

  // Function to render text with clickable words
  const renderWithClickableWords = (text: string) => {
    return <ClickableText text={text} wordData={localWordData} />;
  };

  return (
    <Card className="w-full">
      <CardContent className="p-6">
        <div className="space-y-6">
          {/* Question */}
          <div className="space-y-2">
            <div className="flex items-center justify-between">
              <h2 className="text-xl font-bold">
                {renderWithClickableWords(question)}
              </h2>
              {audioUrl && (
                <Button
                  variant="ghost"
                  size="sm"
                  className="w-8 h-8 p-0"
                  onClick={playAudio}
                  disabled={isPlaying}
                >
                  <Volume2 className="w-5 h-5" />
                </Button>
              )}
            </div>
          </div>

          {/* Options */}
          <RadioGroup
            value={selectedAnswer}
            onValueChange={setSelectedAnswer}
            className="space-y-3"
            disabled={isSubmitted}
          >
            {options.map((option) => (
              <div
                key={option}
                className={`flex items-center p-3 space-x-2 border rounded-md transition-colors ${
                  isSubmitted
                    ? option === correctAnswer
                      ? "bg-green-50 border-green-500"
                      : selectedAnswer === option
                      ? "bg-red-50 border-red-500"
                      : ""
                    : "hover:bg-accent"
                }`}
              >
                <RadioGroupItem value={option} id={option} />
                <Label htmlFor={option} className="flex-1 cursor-pointer">
                  {renderWithClickableWords(option)}
                </Label>
                {isSubmitted && (
                  <div className="flex-shrink-0">
                    {option === correctAnswer ? (
                      <CheckCircle className="w-5 h-5 text-green-600" />
                    ) : selectedAnswer === option ? (
                      <XCircle className="w-5 h-5 text-red-600" />
                    ) : null}
                  </div>
                )}
              </div>
            ))}
          </RadioGroup>

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
                      The correct answer is:{" "}
                      <span className="font-medium">{correctAnswer}</span>
                    </p>
                  )}
                  {explanation && (
                    <p className="mt-2 text-sm text-muted-foreground">
                      {renderWithClickableWords(explanation)}
                    </p>
                  )}
                </div>
              </div>
            </div>
          )}

          {/* Actions */}
          <div className="flex justify-end">
            {!isSubmitted ? (
              <Button onClick={handleSubmit} disabled={!selectedAnswer}>
                Check Answer
              </Button>
            ) : (
              <Button onClick={onNext}>Next Question</Button>
            )}
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
