'use client';

//Placed in another folder to avoid unitid and id error

import { createLesson } from '@/app/_actions/admin/lesson-actions';
import LessonForm from '@/components/admin/forms/LessonForm';

interface NewLessonPageProps {
  params: {
    unitId: string;
  };
}

export default function NewLessonPage({ params }: NewLessonPageProps) {
  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold tracking-tight">Create New Lesson</h2>
        <p className="text-muted-foreground">
          Add a new lesson to your unit
        </p>
      </div>
      <LessonForm 
        unitId={parseInt(params.unitId)}
        onSubmit={createLesson}
      />
    </div>
  );
}