"use client";

import { useEffect, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import {
  BookOpen,
  ChevronRight,
  Clock,
  Globe,
  GraduationCap,
  LayoutDashboard,
  Medal,
  Sparkles,
  Volume2,
} from "lucide-react";
import Link from "next/link";
import { getLanguageDashboard } from "@/app/_actions/tenants/student/language-actions";
import { toast } from "@/hooks/use-toast";

interface LanguageDashboardProps {
  languageId: number;
}

export default function LanguageDashboard({
  languageId,
}: LanguageDashboardProps) {
  const [isLoading, setIsLoading] = useState(true);
  const [dashboardData, setDashboardData] = useState<any>(null);
  const router = useRouter();

  useEffect(() => {
    const fetchDashboardData = async () => {
      setIsLoading(true);
      try {
        const result = await getLanguageDashboard(languageId);
        if (result.data) {
          setDashboardData(result.data);
        } else {
          toast({
            title: "Error",
            description: result.error || "Failed to load language dashboard",
            variant: "destructive",
          });
        }
      } catch (error) {
        console.error("Error fetching language dashboard:", error);
        toast({
          title: "Error",
          description: "Failed to load language dashboard",
          variant: "destructive",
        });
      } finally {
        setIsLoading(false);
      }
    };

    if (languageId) {
      fetchDashboardData();
    }
  }, [languageId]);

  if (isLoading) {
    return <LanguageDashboardSkeleton />;
  }

  if (!dashboardData) {
    return (
      <div className="flex flex-col items-center justify-center py-12">
        <Globe className="w-16 h-16 mb-4 text-muted-foreground" />
        <h2 className="mb-2 text-xl font-bold">Dashboard not available</h2>
        <p className="mb-6 text-muted-foreground">
          We couldn't load the dashboard for this language.
        </p>
        <Button onClick={() => router.push("/student")}>
          Return to Learning Dashboard
        </Button>
      </div>
    );
  }

  const {
    language,
    progress_summary,
    recent_activities,
    recommendations,
    vocabulary_stats,
  } = dashboardData;

  return (
    <div className="container px-4 py-6 md:px-6 md:py-8">
      <div className="mb-8">
        <div className="flex items-center gap-2 mb-2">
          <Link
            href="/student"
            className="text-muted-foreground hover:text-foreground"
          >
            Learning Dashboard
          </Link>
          <ChevronRight className="w-4 h-4 text-muted-foreground" />
          <span className="font-medium">{language.name}</span>
        </div>
        <h1 className="mb-2 text-3xl font-bold">
          {language.name} Dashboard
          <Badge variant="outline" className="ml-2">
            {language.code.toUpperCase()}
          </Badge>
        </h1>
        <p className="text-muted-foreground">
          Track your progress, recent activities, and get recommendations for
          your {language.name} learning journey.
        </p>
      </div>

      {/* Progress Summary Section */}
      <div className="mb-8">
        <h2 className="mb-4 text-xl font-bold">Learning Progress</h2>
        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">
                Overall Progress
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="flex items-center justify-between mb-2">
                <span className="text-2xl font-bold">
                  {progress_summary.progress_percentage}%
                </span>
                <GraduationCap className="w-5 h-5 text-primary" />
              </div>
              <Progress
                value={progress_summary.progress_percentage}
                className="h-2 mb-2"
              />
              <div className="grid grid-cols-3 gap-2 text-xs text-muted-foreground">
                <div>
                  <div className="font-medium">
                    {progress_summary.completed_paths}
                  </div>
                  <div>Completed</div>
                </div>
                <div>
                  <div className="font-medium">
                    {progress_summary.in_progress_paths}
                  </div>
                  <div>In Progress</div>
                </div>
                <div>
                  <div className="font-medium">
                    {progress_summary.not_started_paths}
                  </div>
                  <div>Not Started</div>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">
                Lessons
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="flex items-center justify-between mb-2">
                <span className="text-2xl font-bold">
                  {progress_summary.lesson_stats.completion_percentage}%
                </span>
                <BookOpen className="w-5 h-5 text-primary" />
              </div>
              <Progress
                value={progress_summary.lesson_stats.completion_percentage}
                className="h-2 mb-2"
              />
              <div className="grid grid-cols-3 gap-2 text-xs text-muted-foreground">
                <div>
                  <div className="font-medium">
                    {progress_summary.lesson_stats.completed}
                  </div>
                  <div>Completed</div>
                </div>
                <div>
                  <div className="font-medium">
                    {progress_summary.lesson_stats.in_progress}
                  </div>
                  <div>In Progress</div>
                </div>
                <div>
                  <div className="font-medium">
                    {progress_summary.lesson_stats.not_started}
                  </div>
                  <div>Not Started</div>
                </div>
              </div>
            </CardContent>
          </Card>

     

          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">
                Vocabulary
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="flex items-center justify-between mb-2">
                <span className="text-2xl font-bold">
                  {vocabulary_stats.learned_vocabulary}/
                  {vocabulary_stats.total_vocabulary}
                </span>
                <Volume2 className="w-5 h-5 text-primary" />
              </div>
              <Progress
                value={
                  vocabulary_stats.total_vocabulary > 0
                    ? (vocabulary_stats.learned_vocabulary /
                        vocabulary_stats.total_vocabulary) *
                      100
                    : 0
                }
                className="h-2 mb-2"
              />
              <div className="text-xs text-muted-foreground">
                <div className="mb-1">
                  <span className="font-medium">Mastered: </span>
                  {vocabulary_stats.mastered_vocabulary} words
                </div>
                <div>
                  <span className="font-medium">To Review: </span>
                  {vocabulary_stats.to_review} words
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>

      <div className="grid gap-8 md:grid-cols-3">
        {/* Recent Activities Section */}
        <div className="md:col-span-2">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-xl font-bold">Recent Activities</h2>
            <Button variant="ghost" size="sm" className="text-xs">
              View All
            </Button>
          </div>

          {recent_activities.length > 0 ? (
            <div className="space-y-4">
              {recent_activities.map((activity: any, index: number) => (
                <Card key={`${activity.type}-${activity.id}-${index}`}>
                  <CardContent className="p-4">
                    <div className="flex items-start gap-3">
                      {activity.type === "learning_path" && (
                        <LayoutDashboard className="w-5 h-5 mt-1 text-primary" />
                      )}
                      {activity.type === "lesson" && (
                        <BookOpen className="w-5 h-5 mt-1 text-primary" />
                      )}
                     
                      {activity.type === "vocabulary" && (
                        <Volume2 className="w-5 h-5 mt-1 text-primary" />
                      )}

                      <div className="flex-1">
                        <div className="flex items-center justify-between">
                          <div>
                            <h3 className="font-medium">
                              {activity.title || activity.word}
                            </h3>
                            {activity.type === "vocabulary" && (
                              <p className="text-sm text-muted-foreground">
                                {activity.translation}
                              </p>
                            )}
                          </div>
                          <div className="flex items-center gap-2">
                            {activity.status && (
                              <Badge
                                variant={
                                  activity.status === "completed"
                                    ? "default"
                                    : activity.status === "in_progress"
                                    ? "secondary"
                                    : "outline"
                                }
                              >
                                {activity.status === "completed"
                                  ? "Completed"
                                  : activity.status === "in_progress"
                                  ? "In Progress"
                                  : "Started"}
                              </Badge>
                            )}
                            {activity.mastery !== undefined && (
                              <Badge
                                variant={
                                  activity.mastery >= 90
                                    ? "default"
                                    : activity.mastery >= 50
                                    ? "secondary"
                                    : "outline"
                                }
                              >
                                {activity.mastery}% Mastery
                              </Badge>
                            )}
                            {activity.score !== undefined && (
                              <Badge
                                variant={
                                  activity.score >= 80
                                    ? "default"
                                    : activity.score >= 60
                                    ? "secondary"
                                    : "outline"
                                }
                              >
                                {activity.score}% Score
                              </Badge>
                            )}
                          </div>
                        </div>

                        <div className="mt-1 text-xs text-muted-foreground">
                          {activity.learning_path && (
                            <span className="mr-2">
                              <span className="font-medium">Path:</span>{" "}
                              {activity.learning_path}
                            </span>
                          )}
                          {activity.lesson && (
                            <span className="mr-2">
                              <span className="font-medium">Lesson:</span>{" "}
                              {activity.lesson}
                            </span>
                          )}
                          <span>
                            <Clock className="inline w-3 h-3 mr-1" />
                            {new Date(activity.updated_at).toLocaleDateString()}
                          </span>
                        </div>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          ) : (
            <Card className="flex flex-col items-center justify-center p-6 text-center">
              <Clock className="w-12 h-12 mb-4 text-muted-foreground" />
              <h3 className="mb-2 text-lg font-medium">No recent activities</h3>
              <p className="mb-6 text-muted-foreground">
                You haven't completed any activities in {language.name} yet.
                Start learning to see your activities here.
              </p>
              <Button asChild>
                <Link href={`/student?language=${language.id}`}>
                  Start Learning
                </Link>
              </Button>
            </Card>
          )}
        </div>

        {/* Recommendations Section */}
        <div>
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-xl font-bold">Recommendations</h2>
          </div>

          {recommendations.length > 0 ? (
            <div className="space-y-4">
              {recommendations.map((recommendation: any, index: number) => (
                <Card
                  key={`${recommendation.type}-${recommendation.id}-${index}`}
                >
                  <CardContent className="p-4">
                    <div className="flex items-start gap-3">
                      {recommendation.type === "learning_path" && (
                        <LayoutDashboard className="w-5 h-5 mt-1 text-primary" />
                      )}
                      {recommendation.type === "lesson" && (
                        <BookOpen className="w-5 h-5 mt-1 text-primary" />
                      )}
                      

                      <div className="flex-1">
                        <h3 className="font-medium">{recommendation.title}</h3>

                        <div className="mt-1 text-xs text-muted-foreground">
                          {recommendation.learning_path && (
                            <div className="mb-1">
                              <span className="font-medium">Path:</span>{" "}
                              {recommendation.learning_path}
                            </div>
                          )}
                          <div className="flex items-center">
                            <Sparkles className="w-3 h-3 mr-1 text-primary" />
                            {recommendation.recommendation_reason}
                          </div>
                        </div>
                      </div>
                    </div>
                  </CardContent>
                  <CardFooter className="px-4 py-2 border-t">
                    <Button
                      asChild
                      variant="ghost"
                      size="sm"
                      className="w-full"
                    >
                      <Link
                        href={
                          recommendation.type === "learning_path"
                            ? `/student/paths/${recommendation.id}`
                            : recommendation.type === "lesson"
                            ? `/student/lessons/${recommendation.id}`
                            : `/student/quizzes/${recommendation.id}`
                        }
                      >
                        {recommendation.type === "learning_path"
                          ? "Start Path"
                          : recommendation.type === "lesson"
                          ? "Continue Lesson"
                          : "Take Quiz"}
                      </Link>
                    </Button>
                  </CardFooter>
                </Card>
              ))}
            </div>
          ) : (
            <Card className="flex flex-col items-center justify-center p-6 text-center">
              <Sparkles className="w-12 h-12 mb-4 text-muted-foreground" />
              <h3 className="mb-2 text-lg font-medium">
                No recommendations yet
              </h3>
              <p className="mb-6 text-muted-foreground">
                Complete more activities to get personalized recommendations.
              </p>
              <Button asChild>
                <Link href={`/student?language=${language.id}`}>
                  Browse Learning Paths
                </Link>
              </Button>
            </Card>
          )}

          {/* Recently Learned Words */}
          <div className="mt-8">
            <h2 className="mb-4 text-xl font-bold">Recently Learned Words</h2>

            {vocabulary_stats.recent_vocabulary.length > 0 ? (
              <div className="space-y-3">
                {vocabulary_stats.recent_vocabulary.map((item: any) => (
                  <Card key={item.id} className="overflow-hidden">
                    <CardContent className="p-4">
                      <div className="flex items-center justify-between mb-2">
                        <div>
                          <h4 className="font-medium">{item.word}</h4>
                          <p className="text-sm text-muted-foreground">
                            {item.translation}
                          </p>
                        </div>
                        <Button
                          variant="ghost"
                          size="sm"
                          className="w-8 h-8 p-0"
                        >
                          <Volume2 className="w-4 h-4" />
                        </Button>
                      </div>
                      <div className="flex items-center gap-2">
                        <Progress value={item.mastery} className="h-2" />
                        <span className="text-xs font-medium">
                          {item.mastery}%
                        </span>
                      </div>
                      {item.example && (
                        <p className="mt-2 text-xs italic text-muted-foreground">
                          "{item.example}"
                        </p>
                      )}
                    </CardContent>
                  </Card>
                ))}
              </div>
            ) : (
              <Card className="p-4 text-center">
                <p className="text-muted-foreground">
                  No vocabulary words learned yet.
                </p>
              </Card>
            )}

            <div className="mt-4 space-y-2">
              <Button variant="outline" className="w-full" asChild>
                <Link href={`/student/vocabulary?language=${language.id}`}>
                  Practice Vocabulary
                </Link>
              </Button>

              <div className="grid grid-cols-2 gap-2">
                <Button
                  variant="ghost"
                  size="sm"
                  className="w-full text-xs"
                  asChild
                >
                  <Link
                    href={`/student/vocabulary?language=${language.id}&type=mistakes`}
                  >
                    Practice Mistakes
                  </Link>
                </Button>

                <Button
                  variant="ghost"
                  size="sm"
                  className="w-full text-xs"
                  asChild
                >
                  <Link
                    href={`/student/vocabulary?language=${language.id}&type=all`}
                  >
                    View All Words
                  </Link>
                </Button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

function LanguageDashboardSkeleton() {
  return (
    <div className="container px-4 py-6 md:px-6 md:py-8">
      <div className="mb-8">
        <Skeleton className="w-1/4 h-8 mb-2" />
        <Skeleton className="w-3/4 h-4" />
      </div>

      <div className="mb-8">
        <Skeleton className="w-40 h-6 mb-4" />
        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
          {Array(4)
            .fill(0)
            .map((_, i) => (
              <Card key={i}>
                <CardHeader className="pb-2">
                  <Skeleton className="w-24 h-4" />
                </CardHeader>
                <CardContent>
                  <div className="flex items-center justify-between mb-2">
                    <Skeleton className="w-16 h-8" />
                    <Skeleton className="w-5 h-5" />
                  </div>
                  <Skeleton className="w-full h-2 mb-2" />
                  <div className="grid grid-cols-3 gap-2">
                    <Skeleton className="w-full h-8" />
                    <Skeleton className="w-full h-8" />
                    <Skeleton className="w-full h-8" />
                  </div>
                </CardContent>
              </Card>
            ))}
        </div>
      </div>

      <div className="grid gap-8 md:grid-cols-3">
        <div className="md:col-span-2">
          <div className="flex items-center justify-between mb-4">
            <Skeleton className="w-40 h-6" />
            <Skeleton className="w-20 h-8" />
          </div>

          <div className="space-y-4">
            {Array(5)
              .fill(0)
              .map((_, i) => (
                <Card key={i}>
                  <CardContent className="p-4">
                    <div className="flex items-start gap-3">
                      <Skeleton className="w-5 h-5 mt-1" />
                      <div className="flex-1">
                        <div className="flex items-center justify-between">
                          <Skeleton className="w-1/3 h-5" />
                          <Skeleton className="w-20 h-5" />
                        </div>
                        <Skeleton className="w-full h-4 mt-2" />
                      </div>
                    </div>
                  </CardContent>
                </Card>
              ))}
          </div>
        </div>

        <div>
          <div className="flex items-center justify-between mb-4">
            <Skeleton className="w-40 h-6" />
          </div>

          <div className="space-y-4">
            {Array(3)
              .fill(0)
              .map((_, i) => (
                <Card key={i}>
                  <CardContent className="p-4">
                    <div className="flex items-start gap-3">
                      <Skeleton className="w-5 h-5 mt-1" />
                      <div className="flex-1">
                        <Skeleton className="w-2/3 h-5 mb-2" />
                        <Skeleton className="w-full h-4" />
                      </div>
                    </div>
                  </CardContent>
                  <CardFooter className="px-4 py-2 border-t">
                    <Skeleton className="w-full h-8" />
                  </CardFooter>
                </Card>
              ))}
          </div>

          <div className="mt-8">
            <Skeleton className="w-40 h-6 mb-4" />

            <div className="space-y-3">
              {Array(3)
                .fill(0)
                .map((_, i) => (
                  <Card key={i} className="overflow-hidden">
                    <CardContent className="p-4">
                      <div className="flex items-center justify-between mb-2">
                        <div>
                          <Skeleton className="w-24 h-5 mb-1" />
                          <Skeleton className="w-32 h-4" />
                        </div>
                        <Skeleton className="w-8 h-8 rounded-full" />
                      </div>
                      <Skeleton className="w-full h-2 mb-2" />
                      <Skeleton className="w-full h-4" />
                    </CardContent>
                  </Card>
                ))}
            </div>

            <div className="mt-4">
              <Skeleton className="w-full h-10" />
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
