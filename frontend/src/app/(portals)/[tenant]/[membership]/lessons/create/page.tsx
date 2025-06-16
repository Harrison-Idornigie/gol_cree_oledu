'use client';

import { useState, use } from 'react';
import { useRouter } from 'next/navigation';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { ArrowLeft, BookOpen } from 'lucide-react';
import { useTenant } from '@/app/providers/auth-provider';
import { AdminOrTeam } from '@/components/portals/MembershipGuard';
import { toast } from 'sonner';
import { createLesson } from '@/app/_actions/tenants/team/lesson-actions';
import LessonForm from '@/components/tenants/teams/forms/LessonForm';
import { DEFAULT_LESSON_VALUES } from '@/types/tenant/lesson';

interface CreateLessonPageProps {
  params: Promise<{
    tenant: string;
    membership: string;
  }>;
  searchParams: Promise<{
    unit_id?: string;
    return_url?: string;
  }>;
}

export default function CreateLessonPage({ params, searchParams }: CreateLessonPageProps) {
  const resolvedParams = use(params);
  const resolvedSearchParams = use(searchParams);
  const router = useRouter();
  const { tenantSlug } = useTenant();
  const [isLoading, setIsLoading] = useState(false);

  // Build URL helper
  const buildUrl = (path: string) => `/${tenantSlug}/${resolvedParams.membership}${path}`;

  const handleSubmit = async (formData: FormData) => {
    setIsLoading(true);
    try {
      // Add unit_id if provided in search params
      if (resolvedSearchParams.unit_id) {
        formData.append('unit_id', resolvedSearchParams.unit_id);
      }

      const result = await createLesson(formData);
      
      if (result.error) {
        toast.error('Failed to create lesson', {
          description: result.error
        });
        return { error: result.error };
      }

      toast.success('Lesson created successfully', {
        description: 'The lesson has been created and is ready for content.',
        action: {
          label: 'View Lesson',
          onClick: () => router.push(buildUrl(`/lessons/${result.data?.id}/edit`))
        }
      });

      // Redirect to the return URL or lessons list
      const returnUrl = resolvedSearchParams.return_url || buildUrl('/lessons');
      router.push(returnUrl);

      return { success: true };
    } catch (error) {
      console.error('Error creating lesson:', error);
      const errorMessage = 'An unexpected error occurred. Please try again.';
      toast.error('Failed to create lesson', {
        description: errorMessage
      });
      return { error: errorMessage };
    } finally {
      setIsLoading(false);
    }
  };

  const handleCancel = () => {
    if (isLoading) return;

    const returnUrl = resolvedSearchParams.return_url || buildUrl('/lessons');
    router.push(returnUrl);
  };

  // Prepare initial data
  const initialData = {
    ...DEFAULT_LESSON_VALUES,
    unit_id: resolvedSearchParams.unit_id ? parseInt(resolvedSearchParams.unit_id) : 0
  };

  return (
    <AdminOrTeam fallback={
      <div className="text-center py-12">
        <h2 className="text-2xl font-bold">Access Restricted</h2>
        <p className="text-muted-foreground">Only administrators and team members can create lessons.</p>
      </div>
    }>
      <div className="container mx-auto py-6 space-y-6">
        {/* Header */}
        <div className="flex items-center gap-4">
          <Button
            variant="ghost"
            size="sm"
            onClick={handleCancel}
            disabled={isLoading}
            className="flex items-center gap-2"
          >
            <ArrowLeft className="h-4 w-4" />
            Back
          </Button>
          
          <div className="flex items-center gap-3">
            <div className="p-2 bg-primary/10 rounded-lg">
              <BookOpen className="h-5 w-5 text-primary" />
            </div>
            <div>
              <h1 className="text-2xl font-bold">Create New Lesson</h1>
              <p className="text-muted-foreground">
                Create a new lesson with exercises and content
              </p>
            </div>
          </div>
        </div>

        {/* Main Content */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <BookOpen className="h-5 w-5" />
              Lesson Details
            </CardTitle>
          </CardHeader>
          <CardContent>
            <LessonForm
              unitId={initialData.unit_id}
              initialData={initialData}
              onSubmit={handleSubmit}
            />
          </CardContent>
        </Card>

        {/* Help Section */}
        <Card>
          <CardHeader>
            <CardTitle className="text-lg">Tips for Creating Lessons</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="grid md:grid-cols-2 gap-4 text-sm">
              <div>
                <h4 className="font-medium mb-2">Lesson Structure</h4>
                <ul className="space-y-1 text-muted-foreground">
                  <li>• Choose a clear, descriptive title</li>
                  <li>• Set realistic time estimates</li>
                  <li>• Assign appropriate difficulty levels</li>
                  <li>• Order lessons logically within units</li>
                </ul>
              </div>
              <div>
                <h4 className="font-medium mb-2">Content Planning</h4>
                <ul className="space-y-1 text-muted-foreground">
                  <li>• Plan exercises and activities</li>
                  <li>• Consider XP rewards for engagement</li>
                  <li>• Add sentences and vocabulary</li>
                  <li>• Test the lesson flow</li>
                </ul>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </AdminOrTeam>
  );
}
