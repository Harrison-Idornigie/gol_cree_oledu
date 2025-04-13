"use client";

import { useState, useEffect } from "react";
import { useParams } from "next/navigation";
import { ArrowLeft } from "lucide-react";
import { Button } from "@/components/ui/button";
import Link from "next/link";
import { useSequentialLearning } from "@/hooks/useSequentialLearning";
import { LockedContent } from "@/components/ui/locked-content";
import ExerciseContainer from "@/components/learn/ExerciseContainer";
import {
  getExercisesForLesson,
  getWordsForExercises,
} from "@/app/_actions/user/exercise-actions";
import { Exercise } from "@/types/exercises";
import { WordData } from "@/types/vocabulary";
import { LoadingSpinner } from "@/components/ui/loading-spinner";

export default function ExercisePage() {
  const params = useParams();
  const lessonId = Number.parseInt(params.lessonId as string);
  const pathId = params.id as string;

  const [exercises, setExercises] = useState<Exercise[]>([]);
  const [wordData, setWordData] = useState<Record<string, WordData>>({});
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Check if the lesson is unlocked
  const {
    isUnlocked,
    isLoading: isCheckingAccess,
    error: accessError,
  } = useSequentialLearning({
    type: "lesson",
    id: lessonId,
  });

  useEffect(() => {
    async function loadExercises() {
      try {
        setIsLoading(true);

        // Fetch exercises for the lesson
        const exerciseData = await getExercisesForLesson(lessonId);
        setExercises(exerciseData);

        // Extract all word IDs from the exercises
        const wordIds = new Set<number>();
        exerciseData.forEach((exercise) => {
          // Add word IDs from content
          if (exercise.content.word_ids) {
            exercise.content.word_ids.forEach((id: number) => wordIds.add(id));
          }

          // Add word IDs from word mapping
          if (exercise.content.word_mapping) {
            Object.values(exercise.content.word_mapping).forEach((id) => {
              if (typeof id === "number") {
                wordIds.add(id);
              }
            });
          }
        });

        // Fetch word data for all word IDs
        if (wordIds.size > 0) {
          const words = await getWordsForExercises(Array.from(wordIds));
          setWordData(words);
        }

        setIsLoading(false);
      } catch (error) {
        console.error("Error loading exercises:", error);
        setError("Failed to load exercises. Please try again later.");
        setIsLoading(false);
      }
    }

    if (isUnlocked && !isCheckingAccess) {
      loadExercises();
    }
  }, [lessonId, isUnlocked, isCheckingAccess]);

  // If the lesson is locked, show the locked content component
  if (!isCheckingAccess && !isUnlocked) {
    return (
      <LockedContent
        title="Exercise Locked"
        message={
          accessError ||
          "You need to complete previous lessons before accessing this exercise."
        }
        redirectPath={`/learn/path/${pathId}`}
        redirectLabel="Back to Learning Path"
      />
    );
  }

  // Show loading state
  if (isLoading || isCheckingAccess) {
    return (
      <div className="flex items-center justify-center min-h-[60vh]">
        <LoadingSpinner size="lg" />
      </div>
    );
  }

  // Show error state
  if (error) {
    return (
      <div className="p-6 text-center">
        <p className="text-red-500">{error}</p>
        <Link href={`/learn/path/${pathId}`}>
          <Button className="mt-4">Back to Learning Path</Button>
        </Link>
      </div>
    );
  }

  // Handle completion
  const handleComplete = (results: {
    score: number;
    totalQuestions: number;
  }) => {
    // In a real app, you would submit the results to the server here
    console.log("Exercise completed with score:", results.score);
  };

  return (
    <div className="min-h-screen bg-background">
      <header className="sticky z-10 border-b top-[65px] bg-background">
        <div className="container flex items-center h-16 px-4 md:px-6">
          <Link href={`/learn/path/${pathId}/lesson/${lessonId}`}>
            <Button variant="ghost" size="icon" className="mr-2">
              <ArrowLeft className="w-5 h-5" />
              <span className="sr-only">Back to Lesson</span>
            </Button>
          </Link>
          <h1 className="text-xl font-bold">Practice Exercises</h1>
        </div>
      </header>

      <main className="container px-4 py-6 md:px-6 md:py-8">
        <div className="max-w-2xl mx-auto">
          {exercises.length > 0 ? (
            <ExerciseContainer
              exercises={exercises}
              lessonId={lessonId}
              pathId={pathId}
              wordData={wordData}
              onComplete={handleComplete}
            />
          ) : (
            <div className="p-6 text-center">
              <p>No exercises available for this lesson.</p>
              <Link href={`/learn/path/${pathId}/lesson/${lessonId}`}>
                <Button className="mt-4">Back to Lesson</Button>
              </Link>
            </div>
          )}
        </div>
      </main>
    </div>
  );
}
