"use client";

import { useState, useEffect } from "react";
import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import {
  ArrowLeft,
  BookOpen,
  Check,
  CheckCircle,
  ChevronLeft,
  ChevronRight,
  Volume2,
} from "lucide-react";
import { useSequentialLearning } from "@/hooks/useSequentialLearning";
import { LockedContent } from "@/components/ui/locked-content";

import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Label } from "@/components/ui/label";

// Mock lesson data
const lessonData: Record<
  number,
  {
    id: number;
    name: string;
    type: string;
    levelId: number;
    unitId: number;
    content: any;
  }
> = {
  // Level 1, Unit 1, Lesson 1 - Greetings
  1001: {
    id: 1001,
    name: "Greetings",
    type: "lesson",
    levelId: 1,
    unitId: 101,
    content: {
      introduction:
        "Learning how to greet people is the first step in any language. Let's learn some common English greetings.",
      sections: [
        {
          title: "Formal Greetings",
          content:
            "Use these in professional settings or with people you don't know well:",
          items: [
            { text: "Hello", translation: "A universal greeting" },
            { text: "Good morning", translation: "Used before noon" },
            {
              text: "Good afternoon",
              translation: "Used from noon until evening",
            },
            { text: "Good evening", translation: "Used in the evening" },
            {
              text: "How do you do?",
              translation: "Very formal greeting (mainly UK)",
            },
          ],
        },
        {
          title: "Informal Greetings",
          content: "Use these with friends, family, or in casual settings:",
          items: [
            { text: "Hi", translation: "Casual hello" },
            { text: "Hey", translation: "Very casual greeting" },
            {
              text: "What's up?",
              translation: "Asking how someone is (casual)",
            },
            {
              text: "How's it going?",
              translation: "Asking how someone is (casual)",
            },
            { text: "Morning!", translation: "Casual way to say Good morning" },
          ],
        },
      ],
      tips: "Practice these greetings regularly. The greeting you choose depends on your relationship with the person and the setting you're in.",
    },
  },
  // Level 4, Unit 2, Lesson 1 - Physical Descriptions
  4004: {
    id: 4004,
    name: "Physical Descriptions",
    type: "lesson",
    levelId: 4,
    unitId: 402,
    content: {
      introduction:
        "Learning to describe people's physical appearance is important when talking about family members. Let's learn some common physical description terms.",
      sections: [
        {
          title: "Height and Build",
          content:
            "These words describe how tall or short someone is, and their body type:",
          items: [
            { text: "Tall", translation: "Above average height" },
            { text: "Short", translation: "Below average height" },
            { text: "Average height", translation: "Neither tall nor short" },
            { text: "Slim/Thin", translation: "Not heavy, small body frame" },
            { text: "Athletic", translation: "Strong, physically fit body" },
          ],
        },
        {
          title: "Hair",
          content: "Words to describe hair color, length and style:",
          items: [
            { text: "Blonde", translation: "Yellow or golden hair" },
            { text: "Brunette", translation: "Brown or dark brown hair" },
            { text: "Redhead", translation: "Red or orange hair" },
            { text: "Gray/White", translation: "Hair that has lost pigment" },
            { text: "Long/Short/Medium", translation: "Hair length" },
          ],
        },
      ],
      tips: "When describing family members, start with the most noticeable features first. Remember that it's polite to focus on neutral or positive attributes.",
    },
  },
  // Quiz example
  4007: {
    id: 4007,
    name: "Description Quiz",
    type: "quiz",
    levelId: 4,
    unitId: 402,
    content: {
      introduction: "Test your knowledge of family description vocabulary.",
      questions: [
        {
          question: "Which word describes someone with a lot of muscle?",
          options: ["Slim", "Athletic", "Tall", "Elegant"],
          correctAnswer: "Athletic",
        },
        {
          question: "What would you call someone with yellow or golden hair?",
          options: ["Brunette", "Redhead", "Blonde", "Gray-haired"],
          correctAnswer: "Blonde",
        },
        {
          question: "Which is NOT a hair length description?",
          options: ["Long", "Medium", "Short", "Attractive"],
          correctAnswer: "Attractive",
        },
        {
          question:
            "How would you describe someone who is above average height?",
          options: ["Tall", "Short", "Medium", "Average"],
          correctAnswer: "Tall",
        },
      ],
    },
  },
};

export default function LessonPage() {
  const params = useParams();
  const router = useRouter();
  const lessonId = Number.parseInt(params.lessonId as string);
  const pathId = params.id as string;

  const [quizAnswers, setQuizAnswers] = useState<Record<number, string>>({});
  const [quizSubmitted, setQuizSubmitted] = useState(false);
  const [currentPage, setCurrentPage] = useState(0);

  // Check if the lesson is unlocked
  const { isUnlocked, isLoading, error } = useSequentialLearning({
    type: "lesson",
    id: lessonId,
  });

  // Get the lesson data or redirect if not found
  const lesson = lessonData[lessonId];
  if (!lesson) {
    router.push("/");
    return null;
  }

  // If the lesson is locked, show the locked content component
  if (!isLoading && !isUnlocked) {
    return (
      <LockedContent
        title="Lesson Locked"
        message={
          error ||
          "You need to complete previous lessons before accessing this one."
        }
        redirectPath={`/learn/path/${pathId}`}
        redirectLabel="Back to Learning Path"
      />
    );
  }

  const isQuiz = lesson.type === "quiz";

  const handleQuizSubmit = () => {
    setQuizSubmitted(true);
  };

  // Calculate quiz score if submitted
  const calculateScore = () => {
    if (!isQuiz || !quizSubmitted) return 0;

    let correctCount = 0;
    lesson.content.questions.forEach((question, index) => {
      if (quizAnswers[index] === question.correctAnswer) {
        correctCount++;
      }
    });

    return Math.round((correctCount / lesson.content.questions.length) * 100);
  };

  const handleNextPage = () => {
    if (isQuiz && currentPage >= lesson.content.questions.length - 1) {
      handleQuizSubmit();
    } else {
      setCurrentPage((prev) => prev + 1);
    }
  };

  const handlePrevPage = () => {
    setCurrentPage((prev) => Math.max(0, prev - 1));
  };

  return (
    <div className="min-h-screen bg-background">
      <header className="sticky z-10 border-b top-[65px] bg-background">
        <div className="container flex items-center justify-between h-16 px-4 md:px-6">
          <div className="flex items-center gap-2">
            <Link href={`/learn/path/${lesson.levelId}`}>
              <Button variant="ghost" size="icon" className="mr-2">
                <ArrowLeft className="w-5 h-5" />
                <span className="sr-only">Back to Level</span>
              </Button>
            </Link>
            <h1 className="text-xl font-bold">{lesson.name}</h1>
          </div>
          <div className="flex items-center gap-3">
            {isQuiz ? (
              quizSubmitted ? (
                <div className="flex items-center gap-1 text-green-600">
                  <span className="font-medium">
                    Score: {calculateScore()}%
                  </span>
                </div>
              ) : (
                <span className="text-sm text-muted-foreground">
                  Question {currentPage + 1} of{" "}
                  {lesson.content.questions.length}
                </span>
              )
            ) : (
              <Progress
                value={
                  ((currentPage + 1) / (lesson.content.sections.length + 1)) *
                  100
                }
                className="w-24 h-2"
              />
            )}
          </div>
        </div>
      </header>

      <main className="container px-4 py-6 md:px-6 md:py-8">
        <div className="max-w-2xl mx-auto">
          {isQuiz ? (
            // Quiz content
            <div className="space-y-8">
              {!quizSubmitted ? (
                // Quiz questions
                <Card>
                  <CardContent className="p-6">
                    <div className="space-y-6">
                      <h2 className="text-xl font-bold">
                        {lesson.content.questions[currentPage].question}
                      </h2>
                      <RadioGroup
                        value={quizAnswers[currentPage]}
                        onValueChange={(value) =>
                          setQuizAnswers({
                            ...quizAnswers,
                            [currentPage]: value,
                          })
                        }
                        className="space-y-3"
                      >
                        {lesson.content.questions[currentPage].options.map(
                          (option) => (
                            <div
                              key={option}
                              className="flex items-center p-3 space-x-2 border rounded-md"
                            >
                              <RadioGroupItem value={option} id={option} />
                              <Label
                                htmlFor={option}
                                className="flex-1 cursor-pointer"
                              >
                                {option}
                              </Label>
                            </div>
                          )
                        )}
                      </RadioGroup>
                    </div>
                  </CardContent>
                </Card>
              ) : (
                // Quiz results
                <Card>
                  <CardContent className="p-6 text-center">
                    <div className="flex justify-center mb-4">
                      <div className="p-4 bg-green-100 rounded-full">
                        <CheckCircle className="w-12 h-12 text-green-600" />
                      </div>
                    </div>
                    <h2 className="mb-2 text-2xl font-bold">Quiz Completed!</h2>
                    <p className="mb-6 text-muted-foreground">
                      You scored {calculateScore()}% on this quiz.
                    </p>
                    <div className="space-y-4">
                      <Link href={`/learn/path/${lesson.levelId}`}>
                        <Button className="w-full">Continue Learning</Button>
                      </Link>
                      <Button
                        variant="outline"
                        onClick={() => {
                          setQuizAnswers({});
                          setQuizSubmitted(false);
                          setCurrentPage(0);
                        }}
                      >
                        Try Again
                      </Button>
                    </div>
                  </CardContent>
                </Card>
              )}

              {!quizSubmitted && (
                <div className="flex justify-between">
                  <Button
                    variant="outline"
                    onClick={handlePrevPage}
                    disabled={currentPage === 0}
                  >
                    <ChevronLeft className="w-4 h-4 mr-2" />
                    Previous
                  </Button>

                  <Button
                    onClick={handleNextPage}
                    disabled={!quizAnswers[currentPage]}
                  >
                    {currentPage >= lesson.content.questions.length - 1
                      ? "Submit"
                      : "Next"}
                    {currentPage >= lesson.content.questions.length - 1 ? (
                      <Check className="w-4 h-4 ml-2" />
                    ) : (
                      <ChevronRight className="w-4 h-4 ml-2" />
                    )}
                  </Button>
                </div>
              )}
            </div>
          ) : (
            // Regular lesson content
            <div className="space-y-8">
              {currentPage === 0 ? (
                // Introduction page
                <Card>
                  <CardContent className="p-6">
                    <div className="flex justify-center mb-6">
                      <div className="p-4 rounded-full bg-primary/10">
                        <BookOpen className="w-12 h-12 text-primary" />
                      </div>
                    </div>
                    <h2 className="mb-4 text-2xl font-bold text-center">
                      {lesson.name}
                    </h2>
                    <p className="mb-6 text-center text-muted-foreground">
                      {lesson.content.introduction}
                    </p>
                  </CardContent>
                </Card>
              ) : (
                // Section content
                <Card>
                  <CardContent className="p-6">
                    <h2 className="mb-4 text-xl font-bold">
                      {lesson.content.sections[currentPage - 1].title}
                    </h2>
                    <p className="mb-6 text-muted-foreground">
                      {lesson.content.sections[currentPage - 1].content}
                    </p>
                    <div className="space-y-4">
                      {lesson.content.sections[currentPage - 1].items.map(
                        (item, index) => (
                          <div
                            key={index}
                            className="flex justify-between p-3 border rounded-md hover:bg-accent"
                          >
                            <div className="flex items-center gap-3">
                              <div className="flex items-center justify-center w-8 h-8 rounded-full bg-primary/10">
                                <Volume2 className="w-4 h-4 cursor-pointer text-primary" />
                              </div>
                              <span className="font-medium">{item.text}</span>
                            </div>
                            <span className="text-muted-foreground">
                              {item.translation}
                            </span>
                          </div>
                        )
                      )}
                    </div>

                    {currentPage === lesson.content.sections.length && (
                      <div className="p-4 mt-6 rounded-md bg-muted">
                        <h3 className="mb-2 font-semibold">Tips:</h3>
                        <p className="text-muted-foreground">
                          {lesson.content.tips}
                        </p>
                      </div>
                    )}
                  </CardContent>
                </Card>
              )}

              <div className="flex justify-between">
                <Button
                  variant="outline"
                  onClick={handlePrevPage}
                  disabled={currentPage === 0}
                >
                  <ChevronLeft className="w-4 h-4 mr-2" />
                  Previous
                </Button>

                {currentPage === lesson.content.sections.length ? (
                  <Link href={`/learn/path/${lesson.levelId}`}>
                    <Button>
                      Complete Lesson
                      <Check className="w-4 h-4 ml-2" />
                    </Button>
                  </Link>
                ) : (
                  <Button onClick={handleNextPage}>
                    Next
                    <ChevronRight className="w-4 h-4 ml-2" />
                  </Button>
                )}
              </div>
            </div>
          )}
        </div>
      </main>
    </div>
  );
}
