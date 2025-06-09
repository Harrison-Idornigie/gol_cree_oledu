'use client';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { getUnit } from '@/app/_actions/admin/unit-actions';
import { getLessons } from '@/app/_actions/admin/lesson-actions';
import { useEffect, useState } from 'react';
import Link from 'next/link';
import { AlertDialog } from '@/components/admin/AlertDialog';
import { useRouter } from 'next/navigation';
import { deleteUnit } from '@/app/_actions/admin/unit-actions';

interface Unit {
  id: number;
  learning_path_id: number;
  title: string;
  description: string;
  order: number;
  estimated_duration: number;
  is_published: boolean;
  created_at: string;
  updated_at: string;
}

interface Lesson {
  id: number;
  title: string;
  description: string;
  order: number;
  is_published: boolean;
}

export default function ViewUnit({ params }: { params: { id: string } }) {
  const router = useRouter();
  const [unit, setUnit] = useState<Unit | null>(null);
  const [lessons, setLessons] = useState<Lesson[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const loadData = async () => {
      try {
        const [unitResult, lessonsResult] = await Promise.all([
          getUnit(parseInt(params.id)),
          getLessons(parseInt(params.id))
        ]);

        if (unitResult.error) {
          setError(unitResult.error);
        } else {
          setUnit(unitResult.data);
        }

        if (lessonsResult.error) {
          setError(prev => prev ? `${prev}, ${lessonsResult.error}` : lessonsResult.error);
        } else {
          setLessons(lessonsResult.data || []);
        }
      } catch (err) {
        setError('Failed to load unit data');
      } finally {
        setLoading(false);
      }
    };

    loadData();
  }, [params.id]);

  const handleDelete = async () => {
    const result = await deleteUnit(parseInt(params.id));
    if (result.error) {
      setError(result.error);
    } else {
      router.push(`/admin/learning-paths/${unit?.learning_path_id}/view`);
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

  if (!unit) {
    return (
      <div className="text-red-500 p-4 rounded-md bg-red-50 mb-4">
        Unit not found
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex justify-between items-start">
        <div>
          <h2 className="text-2xl font-bold tracking-tight">{unit.title}</h2>
          <p className="text-muted-foreground">{unit.description}</p>
        </div>
        <div className="flex gap-2">
          <Link href={`/admin/units/${unit.id}`}>
            <Button variant="outline">Edit</Button>
          </Link>
          <Link href={`/admin/units/${unit.id}/lessons/new`}>
            <Button>Add Lesson</Button>
          </Link>
          <AlertDialog
            trigger={
              <Button variant="outline" className="text-red-600 hover:text-red-700">
                Delete
              </Button>
            }
            title="Delete Unit"
            description="Are you sure you want to delete this unit? This action cannot be undone and will remove all associated lessons."
            confirmText="Delete"
            cancelText="Cancel"
            variant="destructive"
            onConfirm={handleDelete}
          />
        </div>
      </div>

      {/* Details */}
      <Card className="p-6">
        <div className="grid grid-cols-2 gap-4">
          <div>
            <h3 className="font-semibold mb-2">Details</h3>
            <dl className="space-y-2">
              <div>
                <dt className="text-sm text-muted-foreground">Order</dt>
                <dd>{unit.order}</dd>
              </div>
              <div>
                <dt className="text-sm text-muted-foreground">
                  Estimated Duration
                </dt>
                <dd>{unit.estimated_duration} hours</dd>
              </div>
              <div>
                <dt className="text-sm text-muted-foreground">Status</dt>
                <dd>
                  <span
                    className={`inline-block px-2 py-1 text-xs rounded-full ${
                      unit.is_published
                        ? 'bg-green-100 text-green-700'
                        : 'bg-yellow-100 text-yellow-700'
                    }`}
                  >
                    {unit.is_published ? 'Published' : 'Draft'}
                  </span>
                </dd>
              </div>
            </dl>
          </div>
          <div>
            <h3 className="font-semibold mb-2">Last Updated</h3>
            <p className="text-sm text-muted-foreground">
              {new Date(unit.updated_at).toLocaleDateString()}
            </p>
          </div>
        </div>
      </Card>

      {/* Lessons */}
      <div>
        <h3 className="text-lg font-semibold mb-4">Lessons</h3>
        <div className="space-y-4">
          {lessons.map((lesson) => (
            <Card key={lesson.id} className="p-4">
              <div className="flex items-center justify-between">
                <div>
                  <h4 className="font-medium">{lesson.title}</h4>
                  <p className="text-sm text-muted-foreground">
                    {lesson.description}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  <span
                    className={`inline-block px-2 py-1 text-xs rounded-full ${
                      lesson.is_published
                        ? 'bg-green-100 text-green-700'
                        : 'bg-yellow-100 text-yellow-700'
                    }`}
                  >
                    {lesson.is_published ? 'Published' : 'Draft'}
                  </span>
                  <Link href={`/admin/lessons/${lesson.id}`}>
                    <Button variant="outline" size="sm">
                      Edit
                    </Button>
                  </Link>
                  <Link href={`/admin/lessons/${lesson.id}/view`}>
                    <Button variant="outline" size="sm">
                      View
                    </Button>
                  </Link>
                </div>
              </div>
            </Card>
          ))}

          {lessons.length === 0 && (
            <Card className="p-6">
              <div className="text-center text-muted-foreground">
                No lessons found. Add your first lesson to get started!
              </div>
            </Card>
          )}
        </div>
      </div>
    </div>
  );
}