"use client";
import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import {
  ArrowLeft,
  BookOpen,
  Check,
  ChevronRight,
  FlameIcon as Fire,
  Lock,
  Star,
} from "lucide-react";
import { useEffect, useState } from "react";

import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "@/components/ui/tooltip";
import { useSequentialLearning } from "@/hooks/useSequentialLearning";

// Define the types for our data structure
type Lesson = {
  id: number;
  name: string;
  type: string;
  completed: boolean;
};

type Unit = {
  id: number;
  name: string;
  description: string;
  progress: number;
  lessons: Lesson[];
};

type Path = {
  id: number;
  name: string;
  description: string;
  units: Unit[];
};

// Mock data for units and lessons
const pathData: Record<number, Path> = {
  1: {
    id: 1,
    name: "Beginner I",
    description: "Start your language journey with basic expressions",
    units: [
      {
        id: 101,
        name: "Introductions",
        description: "Learn to introduce yourself and greet others",
        progress: 100,
        lessons: [
          { id: 1001, name: "Greetings", type: "lesson", completed: true },
          {
            id: 1002,
            name: "Self Introduction",
            type: "lesson",
            completed: true,
          },
          { id: 1003, name: "Basic Questions", type: "quiz", completed: true },
        ],
      },
      {
        id: 102,
        name: "Numbers & Counting",
        description: "Master numbers and basic counting",
        progress: 100,
        lessons: [
          { id: 1004, name: "Numbers 1-10", type: "lesson", completed: true },
          { id: 1005, name: "Numbers 11-100", type: "lesson", completed: true },
          {
            id: 1006,
            name: "Counting Practice",
            type: "exercise",
            completed: true,
          },
          { id: 1007, name: "Numbers Quiz", type: "quiz", completed: true },
        ],
      },
      {
        id: 103,
        name: "Basic Conversation",
        description: "Practice everyday conversations",
        progress: 100,
        lessons: [
          { id: 1008, name: "Common Phrases", type: "lesson", completed: true },
          {
            id: 1009,
            name: "Asking Questions",
            type: "lesson",
            completed: true,
          },
          {
            id: 1010,
            name: "Conversation Practice",
            type: "exercise",
            completed: true,
          },
        ],
      },
    ],
  },
  4: {
    id: 4,
    name: "Elementary II",
    description: "Learn to discuss family, hobbies, and daily life",
    units: [
      {
        id: 401,
        name: "Family Members",
        description: "Learn vocabulary for different family members",
        progress: 100,
        lessons: [
          {
            id: 4001,
            name: "Immediate Family",
            type: "lesson",
            completed: true,
          },
          {
            id: 4002,
            name: "Extended Family",
            type: "lesson",
            completed: true,
          },
          {
            id: 4003,
            name: "Family Vocabulary Quiz",
            type: "quiz",
            completed: true,
          },
        ],
      },
      {
        id: 402,
        name: "Describing Your Family",
        description: "Learn to talk about your family members",
        progress: 75,
        lessons: [
          {
            id: 4004,
            name: "Physical Descriptions",
            type: "lesson",
            completed: true,
          },
          {
            id: 4005,
            name: "Personality Traits",
            type: "lesson",
            completed: true,
          },
          {
            id: 4006,
            name: "Family Descriptions",
            type: "exercise",
            completed: false,
          },
          {
            id: 4007,
            name: "Description Quiz",
            type: "quiz",
            completed: false,
          },
        ],
      },
      {
        id: 403,
        name: "Family Events",
        description: "Vocabulary for family celebrations and events",
        progress: 0,
        lessons: [
          { id: 4008, name: "Celebrations", type: "lesson", completed: false },
          {
            id: 4009,
            name: "Family Traditions",
            type: "lesson",
            completed: false,
          },
          {
            id: 4010,
            name: "Events Practice",
            type: "exercise",
            completed: false,
          },
        ],
      },
      {
        id: 404,
        name: "Family History",
        description: "Talking about your family background",
        progress: 0,
        lessons: [
          {
            id: 4011,
            name: "Origins & Ancestry",
            type: "lesson",
            completed: false,
          },
          {
            id: 4012,
            name: "Family Stories",
            type: "lesson",
            completed: false,
          },
          {
            id: 4013,
            name: "History Practice",
            type: "exercise",
            completed: false,
          },
          {
            id: 4014,
            name: "Family History Quiz",
            type: "quiz",
            completed: false,
          },
        ],
      },
    ],
  },
};

export default function LearnPathOverview() {
  const params = useParams();
  const router = useRouter();
  const pathId = Number.parseInt(params.id as string);

  // Get the path data or redirect if not found
  const path = pathData[pathId];
  if (!path) {
    router.push("/learn");
    return null;
  }

  return (
    <div className="min-h-screen bg-background">
      <header className="sticky z-10 bg-green-400 border-b top-[65px]">
        <div className="container flex items-center justify-between h-16 px-4 md:px-6">
          <div className="flex items-center gap-2">
            <Link href="/learn">
              <Button variant="ghost" size="icon" className="mr-2">
                <ArrowLeft className="w-5 h-5" />
                <span className="sr-only">Back to Dashboard</span>
              </Button>
            </Link>
            <h1 className="text-xl font-bold">
              Path {path.id}: {path.name}
            </h1>
          </div>
        </div>
      </header>

      <main className="container px-4 py-6 md:px-6 md:py-8">
        <div className="max-w-3xl mx-auto">
          <div className="mb-8">
            <p className="text-muted-foreground">{path.description}</p>
          </div>

          <div className="space-y-6">
            {path.units.map((unit: Unit) => (
              <Card key={unit.id} className="overflow-hidden">
                <CardHeader className="pb-3 bg-muted/50">
                  <div className="flex items-center justify-between">
                    <div>
                      <CardTitle>{unit.name}</CardTitle>
                      <CardDescription>{unit.description}</CardDescription>
                    </div>
                    {unit.progress === 100 && (
                      <div className="p-1 bg-green-100 rounded-full">
                        <Check className="w-5 h-5 text-green-600" />
                      </div>
                    )}
                  </div>
                </CardHeader>
                <CardContent className="p-4">
                  {/* Progress bar for unit */}
                  <div className="mb-4">
                    <div className="flex items-center justify-between mb-1 text-sm">
                      <span>Progress</span>
                      <span>{unit.progress}%</span>
                    </div>
                    <Progress value={unit.progress} className="h-2" />
                  </div>

                  {/* Lessons list */}
                  <div className="space-y-2">
                    {unit.lessons.map((lesson: Lesson, index: number) => {
                      // A lesson is locked if any previous lesson is not completed
                      const isLocked = unit.lessons.some(
                        (l, i) => i < index && !l.completed
                      );

                      return (
                        <Link
                          key={lesson.id}
                          href={`/learn/path/${pathId}/lesson/${lesson.id}`}
                          className={`flex items-center justify-between rounded-md border p-3 transition-colors ${
                            lesson.completed
                              ? "bg-muted/30"
                              : isLocked
                              ? "bg-gray-50 opacity-70 cursor-not-allowed"
                              : "hover:bg-accent"
                          }`}
                          onClick={(e) => {
                            if (isLocked) {
                              e.preventDefault();
                            }
                          }}
                        >
                          <div className="flex items-center gap-3">
                            <LessonTypeIcon
                              type={lesson.type}
                              completed={lesson.completed}
                              isLocked={isLocked}
                            />
                            <div>
                              <p className="font-medium">{lesson.name}</p>
                              <p className="text-xs capitalize text-muted-foreground">
                                {lesson.type}
                              </p>
                            </div>
                          </div>

                          <div className="flex items-center">
                            {lesson.completed ? (
                              <span className="mr-2 text-sm text-muted-foreground">
                                Completed
                              </span>
                            ) : isLocked ? (
                              <Tooltip content="Complete previous lessons to unlock">
                                <span className="flex items-center mr-2 text-sm text-amber-600">
                                  <Lock className="w-4 h-4 mr-1" /> Locked
                                </span>
                              </Tooltip>
                            ) : null}
                            <ChevronRight className="w-5 h-5 text-muted-foreground" />
                          </div>
                        </Link>
                      );
                    })}
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        </div>
      </main>
    </div>
  );
}

function LessonTypeIcon({
  type,
  completed,
  isLocked,
}: {
  type: string;
  completed: boolean;
  isLocked?: boolean;
}) {
  const getIconColor = () => {
    if (completed) return "text-green-600 bg-green-100";
    if (isLocked) return "text-gray-400 bg-gray-100";

    switch (type) {
      case "lesson":
        return "text-blue-600 bg-blue-100";
      case "exercise":
        return "text-amber-600 bg-amber-100";
      case "quiz":
        return "text-purple-600 bg-purple-100";
      default:
        return "text-gray-600 bg-gray-100";
    }
  };

  return (
    <div className={`rounded-full p-2 ${getIconColor()}`}>
      {type === "lesson" && <BookOpen className="w-4 h-4" />}
      {type === "exercise" && <Fire className="w-4 h-4" />}
      {type === "quiz" && <Star className="w-4 h-4" />}
    </div>
  );
}
