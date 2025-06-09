'use client';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { useState, useEffect } from 'react';
import { getLearningPaths, toggleLearningPathStatus, deleteLearningPath } from '@/app/_actions/admin/learning-path-actions';
import Link from 'next/link';
import { AlertDialog } from '@/components/admin/AlertDialog';
import { useRouter } from 'next/navigation';

interface LearningPath {
  id: number;
  title: string;
  description: string;
  difficulty_level: string;
  estimated_duration: number;
  is_published: boolean;
  created_at: string;
}

export default function LearningPathsPage() {
  const router = useRouter();
  const [paths, setPaths] = useState<LearningPath[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    loadLearningPaths();
  }, []);

  const loadLearningPaths = async () => {
    const result = await getLearningPaths();
    if (result.error) {
      
      setError(result.error);
    } else {
      setPaths(result.data || []);
    }
    setLoading(false);
  };

  const handleToggleStatus = async (id: number) => {
    const result = await toggleLearningPathStatus(id);
    if (result.error) {
      setError(result.error);
    } else {
      // Update the path status in the local state
      setPaths(paths.map(path => 
        path.id === id ? { ...path, is_published: !path.is_published } : path
      ));
    }
  };

  const handleDelete = async (id: number) => {
    const result = await deleteLearningPath(id);
    if (result.error) {
      setError(result.error);
    } else {
      setPaths(paths.filter(path => path.id !== id));
      router.refresh();
    }
  };

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

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-2xl font-bold tracking-tight">Learning Paths</h2>
          <p className="text-muted-foreground">
            Manage and organize your learning content
          </p>
        </div>
        <Link href="/admin/learning-paths/new">
          <Button>Create Learning Path</Button>
        </Link>
      </div>

      <div className="grid gap-4">
        {paths.map((path) => (
          <Card key={path.id} className="p-6">
            <div className="flex items-start justify-between">
              <div className="space-y-1">
                <div className="flex items-center gap-2">
                  <Link href={`/admin/learning-paths/${path.id}/view`}>
                    <h3 className="font-semibold hover:underline">
                      {path.title}
                    </h3>
                  </Link>
                  <span
                    className={`inline-block px-2 py-1 text-xs rounded-full ${
                      path.is_published
                        ? 'bg-green-100 text-green-700'
                        : 'bg-yellow-100 text-yellow-700'
                    }`}
                  >
                    {path.is_published ? 'Published' : 'Draft'}
                  </span>
                </div>
                <p className="text-sm text-muted-foreground">{path.description}</p>
                <div className="flex gap-2 text-sm text-muted-foreground">
                  <span>Level: {path.difficulty_level}</span>
                  <span>•</span>
                  <span>Duration: {path.estimated_duration} hours</span>
                </div>
              </div>
              <div className="flex gap-2">
                <Button
                  variant={path.is_published ? "outline" : "default"}
                  onClick={() => handleToggleStatus(path.id)}
                >
                  {path.is_published ? "Unpublish" : "Publish"}
                </Button>
                <Link href={`/admin/learning-paths/${path.id}/view`}>
                  <Button variant="outline">View</Button>
                </Link>
                <Link href={`/admin/learning-paths/${path.id}`}>
                  <Button variant="outline">Edit</Button>
                </Link>
                <AlertDialog
                  trigger={
                    <Button variant="outline" className="text-red-600 hover:text-red-700">
                      Delete
                    </Button>
                  }
                  title="Delete Learning Path"
                  description="Are you sure you want to delete this learning path? This action cannot be undone and will remove all associated units and lessons."
                  confirmText="Delete"
                  cancelText="Cancel"
                  variant="destructive"
                  onConfirm={() => handleDelete(path.id)}
                />
              </div>
            </div>
          </Card>
        ))}

        {paths.length === 0 && (
          <Card className="p-6">
            <div className="text-center text-muted-foreground">
              No learning paths found. Create your first one!
            </div>
          </Card>
        )}
      </div>
    </div>
  );
}