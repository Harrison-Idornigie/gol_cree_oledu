'use client';

import UnitForm from '@/components/admin/forms/UnitForm';
import { getUnit } from '@/app/_actions/admin/unit-actions';
import { useEffect, useState } from 'react';

interface Unit {
  id: number;
  learning_path_id: number;
  title: string;
  description: string;
  order: number;
  estimated_duration: number;
  is_published: boolean;
}

export default function EditUnit({ params }: { params: { id: string } }) {
  const [unit, setUnit] = useState<Unit | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const loadUnit = async () => {
      const result = await getUnit(parseInt(params.id));
      if (result.error) {
        setError(result.error);
      } else {
        setUnit(result.data);
      }
      setLoading(false);
    };

    loadUnit();
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

  if (!unit) {
    return (
      <div className="text-red-500 p-4 rounded-md bg-red-50 mb-4">
        Unit not found
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold tracking-tight">Edit Unit</h2>
        <p className="text-muted-foreground">
          Update the details of your unit
        </p>
      </div>

      <UnitForm 
        learningPathId={unit.learning_path_id}
        initialData={{
          id: unit.id,
          title: unit.title,
          description: unit.description,
          order: unit.order,
          estimated_duration: unit.estimated_duration,
          is_published: unit.is_published,
        }}
      />
    </div>
  );
}