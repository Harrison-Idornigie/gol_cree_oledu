'use client';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { getLearningPath } from '@/app/_actions/admin/learning-path-actions';
import { getUnits } from '@/app/_actions/admin/unit-actions';
import { useEffect, useState } from 'react';
import Link from 'next/link';

interface LearningPath {
  id: number;
  title: string;
  description: string;
  difficulty_level: string;
  estimated_duration: number;
  prerequisites: string[];
  is_published: boolean;
  created_at: string;
  updated_at: string;
}

interface Unit {
  id: number;
  title: string;
  description: string;
  order: number;
  is_published: boolean;
}

export default function ViewLearningPath({ params }: { params: { id: string } }) {
  const [learningPath, setLearningPath] = useState<LearningPath | null>(null);
  const [units, setUnits] = useState<Unit[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const loadData = async () => {
      try {
        const [pathResult, unitsResult] = await Promise.all([
          getLearningPath(parseInt(params.id)),
          getUnits(parseInt(params.id))
        ]);

        if (pathResult.error) {
          setError(pathResult.error);
        } else {
          setLearningPath(pathResult.data);
        }

        if (unitsResult.error) {
          setError(prev => prev ? `${prev}, ${unitsResult.error}` : unitsResult.error);
        } else {
          setUnits(unitsResult.data || []);
        }
      } catch (err) {
        setError('Failed to load learning path data');
      } finally {
        setLoading(false);
      }
    };

    loadData();
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
      {/* Header */}
      <div className="flex justify-between items-start">
        <div>
          <h2 className="text-2xl font-bold tracking-tight">
            {learningPath.title}
          </h2>
          <p className="text-muted-foreground">{learningPath.description}</p>
        </div>
        <div className="flex gap-2">
          <Link href={`/admin/learning-paths/${learningPath.id}`}>
            <Button variant="outline">Edit</Button>
          </Link>
          <Link href={`/admin/learning-paths/${learningPath.id}/units/new`}>
            <Button>Add Unit</Button>
          </Link>
        </div>
      </div>

      {/* Details */}
      <Card className="p-6">
        <div className="grid grid-cols-2 gap-4">
          <div>
            <h3 className="font-semibold mb-2">Details</h3>
            <dl className="space-y-2">
              <div>
                <dt className="text-sm text-muted-foreground">Difficulty Level</dt>
                <dd className="capitalize">{learningPath.difficulty_level}</dd>
              </div>
              <div>
                <dt className="text-sm text-muted-foreground">
                  Estimated Duration
                </dt>
                <dd>{learningPath.estimated_duration} hours</dd>
              </div>
              <div>
                <dt className="text-sm text-muted-foreground">Status</dt>
                <dd>
                  <span
                    className={`inline-block px-2 py-1 text-xs rounded-full ${
                      learningPath.is_published
                        ? 'bg-green-100 text-green-700'
                        : 'bg-yellow-100 text-yellow-700'
                    }`}
                  >
                    {learningPath.is_published ? 'Published' : 'Draft'}
                  </span>
                </dd>
              </div>
            </dl>
          </div>
          <div>
            <h3 className="font-semibold mb-2">Prerequisites</h3>
            {learningPath.prerequisites.length > 0 ? (
              <ul className="list-disc list-inside space-y-1">
                {learningPath.prerequisites.map((prereq, index) => (
                  <li key={index} className="text-muted-foreground">
                    {prereq}
                  </li>
                ))}
              </ul>
            ) : (
              <p className="text-muted-foreground">No prerequisites</p>
            )}
          </div>
        </div>
      </Card>

      {/* Units */}
      <div>
        <h3 className="text-lg font-semibold mb-4">Units</h3>
        <div className="space-y-4">
          {units.map((unit) => (
            <Card key={unit.id} className="p-4">
              <div className="flex items-center justify-between">
                <div>
                  <h4 className="font-medium">{unit.title}</h4>
                  <p className="text-sm text-muted-foreground">
                    {unit.description}
                  </p>
                </div>
                <div className="flex gap-2">
                  <Link href={`/admin/units/${unit.id}`}>
                    <Button variant="outline" size="sm">
                      Edit
                    </Button>
                  </Link>
                  <Link href={`/admin/units/${unit.id}/view`}>
                    <Button variant="outline" size="sm">
                      View
                    </Button>
                  </Link>
                </div>
              </div>
            </Card>
          ))}

          {units.length === 0 && (
            <Card className="p-6">
              <div className="text-center text-muted-foreground">
                No units found. Add your first unit to get started!
              </div>
            </Card>
          )}
        </div>
      </div>
    </div>
  );
}