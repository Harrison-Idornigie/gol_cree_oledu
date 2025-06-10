"use client";

import { useState, useEffect } from "react";
import { useRouter } from "next/navigation";
import { ArrowLeft, BookOpen, CheckCircle, GraduationCap, Sparkles } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import Link from "next/link";
import { LoadingSpinner } from "@/components/ui/loading-spinner";
import { getUnit, getUnitProgress } from "@/app/_actions/tenants/student/unit-actions";
import { useSequentialLearning } from "@/hooks/useSequentialLearning";
import { LockedContent } from "@/components/ui/locked-content";

export default function UnitPage({ 
  params 
}: { 
  params: { pathId: string; unitId: string } 
}) {
  const router = useRouter();
  const unitId = parseInt(params.unitId);
  const pathId = parseInt(params.pathId);
  
  const [unit, setUnit] = useState<any>(null);
  const [progress, setProgress] = useState<any>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Check if the unit is unlocked
  const { 
    isUnlocked, 
    isLoading: isCheckingAccess, 
    error: accessError 
  } = useSequentialLearning({
    type: "unit",
    id: unitId
  });

  useEffect(() => {
    async function loadUnitData() {
      try {
        setIsLoading(true);
        
        // Get unit data and progress
        const [unitData, progressData] = await Promise.all([
          getUnit(unitId),
          getUnitProgress(unitId)
        ]);
        
        if (unitData) {
          setUnit(unitData);
        } else {
          setError("Unit not found");
        }
        
        if (progressData) {
          setProgress(progressData);
        }
        
        setIsLoading(false);
      } catch (error) {
        console.error("Error loading unit data:", error);
        setError("Failed to load unit data. Please try again later.");
        setIsLoading(false);
      }
    }

    if (isUnlocked && !isCheckingAccess) {
      loadUnitData();
    }
  }, [unitId, isUnlocked, isCheckingAccess]);

  // If the unit is locked, show the locked content component
  if (!isCheckingAccess && !isUnlocked) {
    return (
      <LockedContent
        title="Unit Locked"
        message={accessError || "You need to complete previous units before accessing this unit."}
        redirectPath={`/student/paths/${pathId}`}
        redirectLabel="Back to Learning Path"
      />
    );
  }

  if (isLoading || isCheckingAccess) {
    return (
      <div className="flex items-center justify-center h-64">
        <LoadingSpinner />
      </div>
    );
  }

  if (error || !unit) {
    return (
      <div className="container px-4 py-6 md:px-6 md:py-8">
        <Card className="w-full">
          <CardContent className="p-6 text-center">
            <p className="mb-4 text-red-500">{error || "Unit not found"}</p>
            <Button asChild>
              <Link href={`/student/paths/${pathId}`}>
                Back to Learning Path
              </Link>
            </Button>
          </CardContent>
        </Card>
      </div>
    );
  }

  const completionPercentage = progress?.completion_percentage || 0;

  return (
    <div className="container px-4 py-6 md:px-6 md:py-8">
      <div className="mb-6">
        <Button variant="ghost" size="sm" className="mb-4" asChild>
          <Link href={`/student/paths/${pathId}`}>
            <ArrowLeft className="w-4 h-4 mr-2" />
            Back to Learning Path
          </Link>
        </Button>
        <h1 className="text-3xl font-bold">{unit.title}</h1>
        <p className="mt-2 text-muted-foreground">
          {unit.description}
        </p>
      </div>

      {/* Unit Progress */}
      <Card className="mb-8">
        <CardContent className="p-6">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-xl font-bold">Progress</h2>
            <div className="text-sm font-medium">
              {completionPercentage}% Complete
            </div>
          </div>
          <Progress value={completionPercentage} className="h-2 mb-4" />
          
          <div className="grid grid-cols-2 gap-4 mt-4 sm:grid-cols-4">
            <div className="p-3 text-center border rounded-md">
              <div className="text-2xl font-bold">{unit.lessons?.length || 0}</div>
              <div className="text-xs text-muted-foreground">Lessons</div>
            </div>
            <div className="p-3 text-center border rounded-md">
              <div className="text-2xl font-bold">{unit.quizzes?.length || 0}</div>
              <div className="text-xs text-muted-foreground">Quizzes</div>
            </div>
            <div className="p-3 text-center border rounded-md">
              <div className="text-2xl font-bold">{progress?.lessons_progress?.filter((l: any) => l.status === 'completed')?.length || 0}</div>
              <div className="text-xs text-muted-foreground">Completed</div>
            </div>
            <div className="p-3 text-center border rounded-md">
              <div className="text-2xl font-bold">{progress?.unlocked_lessons?.length || 0}</div>
              <div className="text-xs text-muted-foreground">Unlocked</div>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Lessons */}
      <div className="mb-8">
        <h2 className="mb-4 text-xl font-bold">Lessons</h2>
        <div className="grid gap-4 md:grid-cols-2">
          {unit.lessons?.map((lesson: any) => {
            const lessonProgress = progress?.lessons_progress?.find(
              (p: any) => p.lesson_id === lesson.id
            );
            const isLessonCompleted = lessonProgress?.status === "completed";
            const isLessonUnlocked = progress?.unlocked_lessons?.includes(lesson.id);
            
            return (
              <Card key={lesson.id} className={!isLessonUnlocked ? "opacity-70" : ""}>
                <CardHeader className="pb-2">
                  <CardTitle className="flex items-center">
                    {isLessonCompleted && (
                      <CheckCircle className="w-5 h-5 mr-2 text-green-500" />
                    )}
                    {lesson.title}
                  </CardTitle>
                  <CardDescription>
                    {lesson.description || "Learn new concepts and vocabulary"}
                  </CardDescription>
                </CardHeader>
                <CardFooter>
                  <Button 
                    className="w-full" 
                    disabled={!isLessonUnlocked}
                    asChild
                  >
                    <Link href={`/student/paths/${pathId}/units/${unitId}/lessons/${lesson.id}`}>
                      {isLessonCompleted ? "Review Lesson" : "Start Lesson"}
                    </Link>
                  </Button>
                </CardFooter>
              </Card>
            );
          })}
        </div>
      </div>

      {/* Vocabulary Review */}
      <div className="mb-8">
        <h2 className="mb-4 text-xl font-bold">Vocabulary</h2>
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center">
              <BookOpen className="w-5 h-5 mr-2 text-primary" />
              Unit Vocabulary
            </CardTitle>
            <CardDescription>
              Review and practice vocabulary from this unit
            </CardDescription>
          </CardHeader>
          <CardFooter>
            <Button className="w-full" asChild>
              <Link href={`/student/paths/${pathId}/units/${unitId}/vocabulary`}>
                Practice Vocabulary
              </Link>
            </Button>
          </CardFooter>
        </Card>
      </div>

      {/* Quizzes */}
      {unit.quizzes?.length > 0 && (
        <div>
          <h2 className="mb-4 text-xl font-bold">Quizzes</h2>
          <div className="grid gap-4 md:grid-cols-2">
            {unit.quizzes.map((quiz: any) => (
              <Card key={quiz.id}>
                <CardHeader className="pb-2">
                  <CardTitle className="flex items-center">
                    <GraduationCap className="w-5 h-5 mr-2 text-primary" />
                    {quiz.title}
                  </CardTitle>
                  <CardDescription>
                    {quiz.description || "Test your knowledge"}
                  </CardDescription>
                </CardHeader>
                <CardFooter>
                  <Button 
                    className="w-full" 
                    asChild
                  >
                    <Link href={`/student/paths/${pathId}/units/${unitId}/quizzes/${quiz.id}`}>
                      Take Quiz
                    </Link>
                  </Button>
                </CardFooter>
              </Card>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
