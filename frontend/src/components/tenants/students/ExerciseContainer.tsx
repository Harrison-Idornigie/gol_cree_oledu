"use client";

import { useState, useEffect } from "react";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { ArrowLeft, CheckCircle } from "lucide-react";
import Link from "next/link";
import { WordData } from "@/types/vocabulary";
import FillInBlankExercise from "./FillInBlankExercise";
import MultipleChoiceExercise from "./MultipleChoiceExercise";
import MatchingExercise from "./MatchingExercise";
import WritingExercise from "./WritingExercise";
import SpeakingExercise from "./SpeakingExercise";
import ConversationExercise from "./ConversationExercise";
import ListeningExercise from "./ListeningExercise";
import PictureExercise from "./PictureExercise";

interface Exercise {
  id: number;
  type:
    | "multiple_choice"
    | "fill_blank"
    | "matching"
    | "writing"
    | "speaking"
    | "conversation"
    | "listening"
    | "picture";
  content: any;
  answers?: any;
}

interface ExerciseContainerProps {
  exercises: Exercise[];
  lessonId: number;
  pathId: string;
  wordData: Record<string, WordData>;
  onComplete?: (results: { score: number; totalQuestions: number }) => void;
}

export default function ExerciseContainer({
  exercises,
  lessonId,
  pathId,
  wordData,
  onComplete,
}: ExerciseContainerProps) {
  const [currentExerciseIndex, setCurrentExerciseIndex] = useState(0);
  const [userAnswers, setUserAnswers] = useState<Record<number, boolean>>({});
  const [isCompleted, setIsCompleted] = useState(false);
  const [localWordData, setLocalWordData] =
    useState<Record<string, WordData>>(wordData);

  useEffect(() => {
    setLocalWordData(wordData);
  }, [wordData]);

  const currentExercise = exercises[currentExerciseIndex];
  const progress = ((currentExerciseIndex + 1) / exercises.length) * 100;

  const handleAnswer = (isCorrect: boolean) => {
    setUserAnswers({
      ...userAnswers,
      [currentExerciseIndex]: isCorrect,
    });
  };

  const handleNext = () => {
    if (currentExerciseIndex < exercises.length - 1) {
      setCurrentExerciseIndex(currentExerciseIndex + 1);
    } else {
      setIsCompleted(true);

      // Calculate results
      const correctAnswers = Object.values(userAnswers).filter(Boolean).length;
      const score = Math.round((correctAnswers / exercises.length) * 100);

      // Call onComplete callback if provided
      if (onComplete) {
        onComplete({
          score,
          totalQuestions: exercises.length,
        });
      }
    }
  };

  const calculateScore = () => {
    const correctAnswers = Object.values(userAnswers).filter(Boolean).length;
    return Math.round((correctAnswers / exercises.length) * 100);
  };

  const renderExercise = () => {
    if (!currentExercise) return null;

    switch (currentExercise.type) {
      case "fill_blank":
        return (
          <FillInBlankExercise
            exercise={currentExercise}
            onAnswer={handleAnswer}
            onNext={handleNext}
            wordData={localWordData}
          />
        );
      case "multiple_choice":
        return (
          <MultipleChoiceExercise
            question={currentExercise.content.question}
            options={currentExercise.content.options}
            correctAnswer={currentExercise.answers?.correct}
            explanation={currentExercise.content.explanation}
            onAnswer={handleAnswer}
            onNext={handleNext}
            wordData={localWordData}
          />
        );
      case "matching":
        return (
          <MatchingExercise
            exercise={currentExercise}
            onAnswer={handleAnswer}
            onNext={handleNext}
            wordData={localWordData}
          />
        );
      case "writing":
        return (
          <WritingExercise
            exercise={currentExercise}
            onAnswer={handleAnswer}
            onNext={handleNext}
            wordData={localWordData}
          />
        );
      case "speaking":
        return (
          <SpeakingExercise
            exercise={currentExercise}
            onAnswer={handleAnswer}
            onNext={handleNext}
            wordData={localWordData}
          />
        );
      case "conversation":
        return (
          <ConversationExercise
            exercise={currentExercise as any}
            onAnswer={handleAnswer}
            onNext={handleNext}
            wordData={localWordData}
          />
        );
      case "listening":
        return (
          <ListeningExercise
            exercise={currentExercise as any}
            onAnswer={handleAnswer}
            onNext={handleNext}
            wordData={localWordData}
          />
        );
      case "picture":
        return (
          <PictureExercise
            exercise={currentExercise as any}
            onAnswer={handleAnswer}
            onNext={handleNext}
            wordData={localWordData}
          />
        );
      default:
        return (
          <Card className="p-6 text-center">
            <p>Exercise type {currentExercise.type} not supported yet.</p>
            <Button onClick={handleNext} className="mt-4">
              Skip
            </Button>
          </Card>
        );
    }
  };

  if (isCompleted) {
    return (
      <Card>
        <div className="p-6 text-center">
          <div className="flex justify-center mb-4">
            <div className="p-4 bg-green-100 rounded-full">
              <CheckCircle className="w-12 h-12 text-green-600" />
            </div>
          </div>
          <h2 className="mb-2 text-2xl font-bold">Exercises Completed!</h2>
          <p className="mb-6 text-muted-foreground">
            You scored {calculateScore()}% on these exercises.
          </p>
          <div className="space-y-4">
            <Link href={`/learn/path/${pathId}/lesson/${lessonId}`}>
              <Button className="w-full">Back to Lesson</Button>
            </Link>
            <Link href={`/learn/path/${pathId}`}>
              <Button variant="outline" className="w-full">
                <ArrowLeft className="w-4 h-4 mr-2" />
                Back to Learning Path
              </Button>
            </Link>
          </div>
        </div>
      </Card>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm text-muted-foreground">
            Exercise {currentExerciseIndex + 1} of {exercises.length}
          </p>
        </div>
        <Link href={`/learn/path/${pathId}/lesson/${lessonId}`}>
          <Button variant="ghost" size="sm">
            Exit
          </Button>
        </Link>
      </div>

      <Progress value={progress} className="h-2" />

      {renderExercise()}
    </div>
  );
}
