'use client';

import LearningPathForm from '@/components/admin/forms/LearningPathForm';
import { getLearningPath } from '@/app/_actions/admin/learning-path-actions';
import { useEffect, useState } from 'react';

interface LearningPath {
  id: number;
  title: string;
  description: string;
  difficulty_level: string;
  estimated_duration: number;
  prerequisites: string[];
  is_published: boolean;
}

export default function EditLearningPath({ params }: { params: { id: string } }) {
  const [learningPath, setLearningPath] = useState<LearningPath | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const loadLearningPath = async () => {
      const result = await getLearningPath(parseInt(params.id));
      if (result.error) {
        setError(result.error);
      } else {
        setLearningPath(result.data);
      }
      setLoading(false);
    };

    loadLearningPath();
  }, [params.id]);

  if (loading) {
    return <div className="flex justify-center items-center h-48">Loading...</div>;
  }

  if (error) {
    return (
      <div className="text-red-500 p-4 rounded-md bg-red-50 mb-4">
        Error: {error}
      </div>
    );
  }

  if (!learningPath) {
    return (
      <div className="text-red-500 p-4 rounded-md bg-red-50 mb-4">
        Learning path not found
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold tracking-tight">Edit Learning Path</h2>
        <p className="text-muted-foreground">
          Update the details of your learning path
        </p>
      </div>

      <LearningPathForm initialData={learningPath} />
    </div>
  );
}