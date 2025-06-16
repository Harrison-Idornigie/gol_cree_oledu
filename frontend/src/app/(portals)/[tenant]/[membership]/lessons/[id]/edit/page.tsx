'use client';

import { useState, useEffect, use } from 'react';
import { useRouter } from 'next/navigation';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ArrowLeft, BookOpen, FileText, MessageSquare, Settings } from 'lucide-react';
import { useTenant } from '@/app/providers/auth-provider';
import { AdminOrTeam } from '@/components/portals/MembershipGuard';
import { toast } from 'sonner';
import { getLesson, updateLesson } from '@/app/_actions/tenants/team/lesson-actions';
import { getLanguages } from '@/app/_actions/tenants/team/language-actions';
import LessonForm from '@/components/tenants/teams/forms/LessonForm';
import SentenceQuickActions from '@/components/tenants/teams/sentences/SentenceQuickActions';
import { Lesson } from '@/types/tenant/lesson';

interface EditLessonPageProps {
  params: Promise<{
    tenant: string;
    membership: string;
    id: string;
  }>;
  searchParams: Promise<{
    return_url?: string;
  }>;
}

interface Language {
  id: number;
  name: string;
  code: string;
}

export default function EditLessonPage({ params, searchParams }: EditLessonPageProps) {
  const resolvedParams = use(params);
  const resolvedSearchParams = use(searchParams);
  const router = useRouter();
  const { tenantSlug } = useTenant();
  const [lesson, setLesson] = useState<Lesson | null>(null);
  const [languages, setLanguages] = useState<Language[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [activeTab, setActiveTab] = useState('details');

  const lessonId = parseInt(resolvedParams.id);

  // Build URL helper
  const buildUrl = (path: string) => `/${tenantSlug}/${resolvedParams.membership}${path}`;

  // Load lesson and languages
  useEffect(() => {
    loadData();
  }, [lessonId]);

  const loadData = async () => {
    setIsLoading(true);
    try {
      const [lessonResult, languagesResult] = await Promise.all([
        getLesson(lessonId),
        getLanguages()
      ]);

      if (lessonResult.error) {
        toast.error('Failed to load lesson', {
          description: lessonResult.error
        });
        router.push(buildUrl('/lessons'));
        return;
      }

      if (lessonResult.data) {
        setLesson(lessonResult.data);
      }

      if (languagesResult.data?.data?.languages) {
        setLanguages(languagesResult.data.data.languages);
      }
    } catch (error) {
      console.error('Failed to load data:', error);
      toast.error('Failed to load lesson');
      router.push(buildUrl('/lessons'));
    } finally {
      setIsLoading(false);
    }
  };

  const handleSubmit = async (formData: FormData) => {
    setIsSaving(true);
    try {
      const result = await updateLesson(lessonId, formData);
      
      if (result.error) {
        toast.error('Failed to update lesson', {
          description: result.error
        });
        return { error: result.error };
      }

      toast.success('Lesson updated successfully');
      
      // Update local state
      if (result.data) {
        setLesson(result.data);
      }
      
      return { success: true };
    } catch (error) {
      console.error('Error updating lesson:', error);
      const errorMessage = 'An unexpected error occurred. Please try again.';
      toast.error('Failed to update lesson', {
        description: errorMessage
      });
      return { error: errorMessage };
    } finally {
      setIsSaving(false);
    }
  };

  const handleCancel = () => {
    if (isSaving) return;

    const returnUrl = resolvedSearchParams.return_url || buildUrl('/lessons');
    router.push(returnUrl);
  };

  const handleSentenceCreated = (sentence: any) => {
    toast.success('Sentence added to lesson', {
      description: 'The sentence is now available for use in exercises.'
    });
  };

  if (isLoading) {
    return (
      <div className="container mx-auto py-6">
        <div className="text-center py-8">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div>
          <p className="text-muted-foreground mt-2">Loading lesson...</p>
        </div>
      </div>
    );
  }

  if (!lesson) {
    return (
      <div className="container mx-auto py-6">
        <div className="text-center py-8">
          <h2 className="text-2xl font-bold">Lesson Not Found</h2>
          <p className="text-muted-foreground">The lesson you're looking for doesn't exist.</p>
          <Button asChild className="mt-4">
            <a href={buildUrl('/lessons')}>Back to Lessons</a>
          </Button>
        </div>
      </div>
    );
  }

  return (
    <AdminOrTeam fallback={
      <div className="text-center py-12">
        <h2 className="text-2xl font-bold">Access Restricted</h2>
        <p className="text-muted-foreground">Only administrators and team members can edit lessons.</p>
      </div>
    }>
      <div className="container mx-auto py-6 space-y-6">
        {/* Header */}
        <div className="flex items-center gap-4">
          <Button
            variant="ghost"
            size="sm"
            onClick={handleCancel}
            disabled={isSaving}
            className="flex items-center gap-2"
          >
            <ArrowLeft className="h-4 w-4" />
            Back
          </Button>
          
          <div className="flex items-center gap-3 flex-1">
            <div className="p-2 bg-primary/10 rounded-lg">
              <BookOpen className="h-5 w-5 text-primary" />
            </div>
            <div>
              <h1 className="text-2xl font-bold">Edit Lesson: {lesson.title}</h1>
              <p className="text-muted-foreground">
                Manage lesson content, exercises, and settings
              </p>
            </div>
          </div>

          <SentenceQuickActions
            languages={languages}
            context={{
              type: 'lesson',
              id: lesson.id,
              languageId: lesson.unit_id // TODO: Get language from unit/lesson
            }}
            onSentenceCreated={handleSentenceCreated}
            variant="dropdown"
          />
        </div>

        {/* Main Content */}
        <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-6">
          <TabsList className="grid w-full grid-cols-4">
            <TabsTrigger value="details" className="flex items-center gap-2">
              <Settings className="h-4 w-4" />
              Details
            </TabsTrigger>
            <TabsTrigger value="content" className="flex items-center gap-2">
              <FileText className="h-4 w-4" />
              Content
            </TabsTrigger>
            <TabsTrigger value="sentences" className="flex items-center gap-2">
              <MessageSquare className="h-4 w-4" />
              Sentences
            </TabsTrigger>
            <TabsTrigger value="exercises" className="flex items-center gap-2">
              <BookOpen className="h-4 w-4" />
              Exercises
            </TabsTrigger>
          </TabsList>

          <TabsContent value="details">
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Settings className="h-5 w-5" />
                  Lesson Details
                </CardTitle>
              </CardHeader>
              <CardContent>
                <LessonForm
                  unitId={lesson.unit_id}
                  initialData={{
                    id: lesson.id,
                    unit_id: lesson.unit_id,
                    title: lesson.title,
                    description: lesson.description,
                    order: lesson.order,
                    is_published: lesson.is_published,
                    estimated_time: lesson.estimated_time,
                    xp_reward: lesson.xp_reward,
                    difficulty_level: lesson.difficulty_level
                  }}
                  onSubmit={handleSubmit}
                />
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="content">
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <FileText className="h-5 w-5" />
                  Lesson Content
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-center py-8">
                  <FileText className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                  <h3 className="text-lg font-medium mb-2">Content Editor</h3>
                  <p className="text-muted-foreground">
                    Content editing functionality will be implemented here.
                  </p>
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="sentences">
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <MessageSquare className="h-5 w-5" />
                  Lesson Sentences
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-center py-8">
                  <MessageSquare className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                  <h3 className="text-lg font-medium mb-2">Sentence Management</h3>
                  <p className="text-muted-foreground mb-4">
                    Manage sentences used in this lesson's exercises.
                  </p>
                  <SentenceQuickActions
                    languages={languages}
                    context={{
                      type: 'lesson',
                      id: lesson.id,
                      languageId: lesson.unit_id // TODO: Get language from unit/lesson
                    }}
                    onSentenceCreated={handleSentenceCreated}
                    variant="button"
                  />
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="exercises">
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <BookOpen className="h-5 w-5" />
                  Lesson Exercises
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-center py-8">
                  <BookOpen className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                  <h3 className="text-lg font-medium mb-2">Exercise Management</h3>
                  <p className="text-muted-foreground">
                    Exercise management functionality will be implemented here.
                  </p>
                </div>
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      </div>
    </AdminOrTeam>
  );
}
