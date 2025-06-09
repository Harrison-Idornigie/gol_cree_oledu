'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { getLesson, updateLesson } from '@/app/_actions/admin/lesson-actions';
import LessonForm from '@/components/admin/forms/LessonForm';
import { LessonFormData } from '@/types/lesson';

interface EditLessonPageProps {
  params: {
    id: string;
  };
}

export default function EditLessonPage({ params }: EditLessonPageProps) {
  const router = useRouter();
  const [lesson, setLesson] = useState<LessonFormData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const loadLesson = async () => {
      const result = await getLesson(parseInt(params.id));
      if (result.error) {
        setError(result.error);
      } else if (result.data) {
        const formData: LessonFormData = {
          id: result.data.id,
          unit_id: result.data.unit_id,
          title: result.data.title,
          description: result.data.description,
          order: result.data.order,
          is_published: result.data.is_published,
          estimated_time: result.data.estimated_time,
          xp_reward: result.data.xp_reward,
          difficulty_level: result.data.difficulty_level
        };
        setLesson(formData);
      }
      setLoading(false);
    };

    loadLesson();
  }, [params.id]);

  if (loading) {
    return (
      <div className="flex h-[50vh] items-center justify-center">
        <div className="text-center space-y-4">
          <div className="animate-spin h-8 w-8 border-4 border-primary border-t-transparent rounded-full mx-auto"></div>
          <p className="text-muted-foreground">Loading lesson...</p>
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
      <div>
        <h2 className="text-2xl font-bold tracking-tight">Edit Lesson</h2>
        <p className="text-muted-foreground">
          Make changes to your lesson
        </p>
      </div>

      <LessonForm
        unitId={lesson.unit_id}
        initialData={lesson}
        onSubmit={async (formData) => {
          const result = await updateLesson(lesson.id!, formData);
          if (result.error) {
            return { error: result.error };
          }
          return {};
        }}
      />
    </div>
  );
}