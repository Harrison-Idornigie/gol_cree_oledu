"use client";

import { useState } from "react";
import { useParams } from "next/navigation";
import { ArrowLeft, CheckCircle } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { Card, CardContent } from "@/components/ui/card";
import Link from "next/link";
import MultipleChoiceExercise from "@/components/learn/MultipleChoiceExercise";
import { useSequentialLearning } from "@/hooks/useSequentialLearning";
import { LockedContent } from "@/components/ui/locked-content";

// Mock data - in a real app, this would come from an API
const exerciseData = {
  id: 1006,
  title: "Counting Practice",
  type: "multiple_choice",
  questions: [
    {
      id: 1,
      question: "How do you say 'five' in Spanish?",
      options: ["uno", "tres", "cinco", "siete"],
      correctAnswer: "cinco",
      explanation: "Cinco is the Spanish word for 'five'.",
    },
    {
      id: 2,
      question: "Which number comes after 'ocho' (8)?",
      options: ["siete", "nueve", "diez", "seis"],
      correctAnswer: "nueve",
      explanation: "Nueve (9) comes after ocho (8).",
    },
    {
      id: 3,
      question: "What is 'dos' + 'tres'?",
      options: ["tres", "cuatro", "cinco", "seis"],
      correctAnswer: "cinco",
      explanation: "Dos (2) + tres (3) = cinco (5).",
    },
  ],
};

// Mock vocabulary data - in a real app, this would come from an API
const vocabularyData = {
  cinco: {
    text: "cinco",
    translation: "five",
    phonetic: "ˈθiŋko",
    audioUrl: "https://example.com/audio/cinco.mp3",
    partOfSpeech: "numeral",
    example: "Tengo cinco dedos en cada mano.",
  },
  nueve: {
    text: "nueve",
    translation: "nine",
    phonetic: "ˈnweβe",
    audioUrl: "https://example.com/audio/nueve.mp3",
    partOfSpeech: "numeral",
    example: "El número nueve es mi favorito.",
  },
  ocho: {
    text: "ocho",
    translation: "eight",
    phonetic: "ˈotʃo",
    audioUrl: "https://example.com/audio/ocho.mp3",
    partOfSpeech: "numeral",
    example: "Hay ocho planetas en nuestro sistema solar.",
  },
};

export default function ExercisePage() {
  const params = useParams();
  const lessonId = Number.parseInt(params.lessonId as string);
  const pathId = params.id as string;

  const [currentQuestionIndex, setCurrentQuestionIndex] = useState(0);
  const [userAnswers, setUserAnswers] = useState<Record<number, boolean>>({});
  const [isCompleted, setIsCompleted] = useState(false);

  // Check if the lesson is unlocked
  const { isUnlocked, isLoading, error } = useSequentialLearning({
    type: "lesson",
    id: lessonId,
  });

  // If the lesson is locked, show the locked content component
  if (!isLoading && !isUnlocked) {
    return (
      <LockedContent
        title="Exercise Locked"
        message={
          error ||
          "You need to complete previous lessons before accessing this exercise."
        }
        redirectPath={`/learn/path/${pathId}`}
        redirectLabel="Back to Learning Path"
      />
    );
  }

  const currentQuestion = exerciseData.questions[currentQuestionIndex];

  const handleAnswer = (isCorrect: boolean) => {
    setUserAnswers({
      ...userAnswers,
      [currentQuestionIndex]: isCorrect,
    });
  };

  const handleNext = () => {
    if (currentQuestionIndex < exerciseData.questions.length - 1) {
      setCurrentQuestionIndex(currentQuestionIndex + 1);
    } else {
      setIsCompleted(true);
      // In a real app, you would submit the results to the server here
    }
  };

  const calculateScore = () => {
    const correctAnswers = Object.values(userAnswers).filter(Boolean).length;
    return Math.round((correctAnswers / exerciseData.questions.length) * 100);
  };

  const handleRetry = () => {
    setCurrentQuestionIndex(0);
    setUserAnswers({});
    setIsCompleted(false);
  };

  return (
    <div className="min-h-screen bg-background">
      <header className="sticky z-10 border-b top-[65px] bg-background">
        <div className="container flex items-center justify-between h-16 px-4 md:px-6">
          <div className="flex items-center gap-2">
            <Link href={`/learn/path/${pathId}`}>
              <Button variant="ghost" size="icon" className="mr-2">
                <ArrowLeft className="w-5 h-5" />
                <span className="sr-only">Back to Path</span>
              </Button>
            </Link>
            <h1 className="text-xl font-bold">{exerciseData.title}</h1>
          </div>
          <div className="flex items-center gap-3">
            <span className="text-sm text-muted-foreground">
              Question {currentQuestionIndex + 1} of{" "}
              {exerciseData.questions.length}
            </span>
            <Progress
              value={
                ((currentQuestionIndex + 1) / exerciseData.questions.length) *
                100
              }
              className="w-24 h-2"
            />
          </div>
        </div>
      </header>

      <main className="container px-4 py-6 md:px-6 md:py-8">
        <div className="max-w-2xl mx-auto">
          {!isCompleted ? (
            <MultipleChoiceExercise
              question={currentQuestion.question}
              options={currentQuestion.options}
              correctAnswer={currentQuestion.correctAnswer}
              explanation={currentQuestion.explanation}
              onAnswer={handleAnswer}
              onNext={handleNext}
              wordData={vocabularyData}
            />
          ) : (
            <Card>
              <CardContent className="p-6 text-center">
                <div className="flex justify-center mb-4">
                  <div className="p-4 bg-green-100 rounded-full">
                    <CheckCircle className="w-12 h-12 text-green-600" />
                  </div>
                </div>
                <h2 className="mb-2 text-2xl font-bold">Exercise Completed!</h2>
                <p className="mb-6 text-muted-foreground">
                  You scored {calculateScore()}% on this exercise.
                </p>
                <div className="space-y-4">
                  <Link href={`/learn/path/${pathId}`}>
                    <Button className="w-full">Continue Learning</Button>
                  </Link>
                  <Button variant="outline" onClick={handleRetry}>
                    Try Again
                  </Button>
                </div>
              </CardContent>
            </Card>
          )}
        </div>
      </main>
    </div>
  );
}
