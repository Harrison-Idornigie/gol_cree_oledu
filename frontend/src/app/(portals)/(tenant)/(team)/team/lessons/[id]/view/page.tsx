'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { AlertDialog } from '@/components/admin/AlertDialog';
import { getLesson } from '@/app/_actions/tenants/team/lesson-actions';
import { deleteSection, updateSectionOrder, toggleSectionPublished } from '@/app/_actions/admin/section-actions';
import { SectionType } from '@/types/section';
import { Lesson, hasAssessmentQuiz } from '@/types/lesson';

interface ViewLessonPageProps {
  params: {
    id: string;
  };
}

const sectionTypeIcons: Record<SectionType, { icon: string; label: string }> = {
  theory: { icon: '📚', label: 'Theory' },
  practice: { icon: '✏️', label: 'Practice Exercise' },
  mini_quiz: { icon: '🎯', label: 'Mini Quiz' },
};

export default function ViewLessonPage({ params }: ViewLessonPageProps) {
  const router = useRouter();
  const [lesson, setLesson] = useState<Lesson | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [sectionToDelete, setSectionToDelete] = useState<number | null>(null);

  useEffect(() => {
    const loadLesson = async () => {
      const result = await getLesson(parseInt(params.id));
      if (result.error) {
        setError(result.error);
      } else if (result.data) {
        setLesson(result.data);
      }
      setLoading(false);
    };

    loadLesson();
  }, [params.id]);

  const handleSectionDelete = async (sectionId: number) => {
    const result = await deleteSection(sectionId);
    if (!result.error) {
      router.refresh();
    }
    setSectionToDelete(null);
  };

  const handleSectionOrderChange = async (sectionId: number, newOrder: number) => {
    await updateSectionOrder(sectionId, newOrder);
    router.refresh();
  };

  const handleTogglePublish = async (sectionId: number) => {
    await toggleSectionPublished(sectionId);
    router.refresh();
  };

  const getSectionLabel = (type: SectionType) => {
    const info = sectionTypeIcons[type];
    return `${info.icon} ${info.label}`;
  };

  if (loading) {
    return (
      <div className="flex h-[50vh] items-center justify-center">
        <div className="text-center space-y-4">
          <div className="animate-spin h-8 w-8 border-4 border-primary border-t-transparent rounded-full mx-auto"></div>
          <p className="text-muted-foreground">Loading lesson details...</p>
        </div>
      </div>
    );
  }

  if (error || !lesson) {
    return (
      <div className="flex h-[50vh] items-center justify-center">
        <div className="text-center space-y-4">
          <div className="text-red-500 p-4 rounded-md bg-red-50 inline-block">
            {error || 'Lesson not found'}
          </div>
          <button
            onClick={() => router.back()}
            className="text-primary hover:underline"
          >
            Go back
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">{lesson.title}</h1>
          <p className="text-muted-foreground">{lesson.description}</p>
        </div>
       
      </div>

      {/* Sections List */}
      <div className="space-y-4">
        {!lesson.sections?.length ? (
          <Card className="p-8 text-center">
            <h3 className="text-lg font-semibold mb-2">No sections yet</h3>
            <p className="text-muted-foreground mb-4">
              Start by adding theory content, practice exercises, or mini quizzes
            </p>
            <Button
              onClick={() =>
                router.push(`/admin/lessons/${params.id}/sections/new`)
              }
            >
              Add First Section
            </Button>
          </Card>
        ) : (
          <div className="grid gap-4">
            {lesson.sections.map((section, index) => (
              <Card key={section.id} className="p-4">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-4">
                    <div className="flex flex-col">
                      <span className="font-medium text-lg">
                        {section.title}
                      </span>
                      <span className="text-sm text-muted-foreground">
                        {getSectionLabel(section.type)}
                      </span>
                    </div>
                  </div>
                  
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => handleTogglePublish(section.id)}
                    >
                      {section.is_published ? '👁️ Published' : '🔒 Draft'}
                    </Button>
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() =>
                        handleSectionOrderChange(
                          section.id,
                          index > 0 ? index - 1 : 0
                        )
                      }
                      disabled={index === 0}
                    >
                      ↑
                    </Button>
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() =>
                        handleSectionOrderChange(
                          section.id,
                          index < lesson.sections.length - 1
                            ? index + 1
                            : lesson.sections.length - 1
                        )
                      }
                      disabled={index === lesson.sections.length - 1}
                    >
                      ↓
                    </Button>
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() =>
                        router.push(
                          `/admin/lessons/${params.id}/sections/${section.id}`
                        )
                      }
                    >
                      Edit
                    </Button>
                    <Button
                      variant="ghost"
                      size="sm"
                      className="text-red-500 hover:text-red-600"
                      onClick={() => setSectionToDelete(section.id)}
                    >
                      Delete
                    </Button>
                  </div>
                </div>
                <p className="mt-2 text-sm text-muted-foreground">
                  {section.description}
                </p>
                <div className="mt-2 text-sm text-muted-foreground">
                  <span className="mr-4">🎯 XP: {section.xp_reward}</span>
                  <span>⏱️ {section.estimated_time} min</span>
                </div>
              </Card>
            ))}
          </div>
        )}
      </div>

      {/* Delete Confirmation Dialog */}
      {sectionToDelete && (
        <AlertDialog
          trigger={<></>}
          title="Delete Section"
          description="Are you sure you want to delete this section? This action cannot be undone."
          confirmText="Delete"
          cancelText="Cancel"
          variant="destructive"
          onConfirm={() => handleSectionDelete(sectionToDelete)}
          onCancel={() => setSectionToDelete(null)}
        />
      )}
    </div>
  );
}