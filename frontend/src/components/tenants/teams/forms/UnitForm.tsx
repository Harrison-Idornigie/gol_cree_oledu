'use client';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { createUnit, updateUnit } from '@/app/_actions/admin/unit-actions';

interface UnitFormProps {
  learningPathId?: number;
  initialData?: {
    id?: number;
    title: string;
    description: string;
    order: number;
    estimated_duration: number;
    is_published: boolean;
  };
}

export default function UnitForm({ learningPathId, initialData }: UnitFormProps) {
  const router = useRouter();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [formData, setFormData] = useState({
    title: initialData?.title || '',
    description: initialData?.description || '',
    order: initialData?.order || 0,
    estimated_duration: initialData?.estimated_duration || 0,
    is_published: initialData?.is_published || false,
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    const form = new FormData();
    form.append('title', formData.title);
    form.append('description', formData.description);
    form.append('order', formData.order.toString());
    form.append('estimated_duration', formData.estimated_duration.toString());
    form.append('is_published', formData.is_published.toString());

    try {
      if (initialData?.id) {
        const result = await updateUnit(initialData.id, form);
        if (result.error) {
          setError(result.error);
        } else {
          router.push(`/admin/learning-paths/${learningPathId}/view`);
          router.refresh();
        }
      } else if (learningPathId) {
        const result = await createUnit(learningPathId, form);
        if (result.error) {
          setError(result.error);
        } else {
          router.push(`/admin/learning-paths/${learningPathId}/view`);
          router.refresh();
        }
      }
    } catch (err) {
      setError('An unexpected error occurred');
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      <Card className="p-6 space-y-4">
        {error && (
          <div className="text-red-500 p-4 rounded-md bg-red-50 mb-4">
            {error}
          </div>
        )}

        <div className="space-y-2">
          <label className="text-sm font-medium" htmlFor="title">
            Title
          </label>
          <Input
            id="title"
            value={formData.title}
            onChange={(e) =>
              setFormData({ ...formData, title: e.target.value })
            }
            required
          />
        </div>

        <div className="space-y-2">
          <label className="text-sm font-medium" htmlFor="description">
            Description
          </label>
          <textarea
            id="description"
            className="w-full min-h-[100px] px-3 py-2 rounded-md border border-input bg-background"
            value={formData.description}
            onChange={(e) =>
              setFormData({ ...formData, description: e.target.value })
            }
            required
          />
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <label className="text-sm font-medium" htmlFor="order">
              Order
            </label>
            <Input
              id="order"
              type="number"
              min="0"
              value={formData.order}
              onChange={(e) =>
                setFormData({ ...formData, order: parseInt(e.target.value) || 0 })
              }
              required
            />
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium" htmlFor="estimated_duration">
              Estimated Duration (hours)
            </label>
            <Input
              id="estimated_duration"
              type="number"
              min="0"
              step="0.5"
              value={formData.estimated_duration}
              onChange={(e) =>
                setFormData({
                  ...formData,
                  estimated_duration: parseFloat(e.target.value) || 0,
                })
              }
              required
            />
          </div>
        </div>

        <div className="flex items-center space-x-2">
          <input
            type="checkbox"
            id="is_published"
            checked={formData.is_published}
            onChange={(e) =>
              setFormData({ ...formData, is_published: e.target.checked })
            }
            className="rounded border-gray-300"
          />
          <label className="text-sm font-medium" htmlFor="is_published">
            Publish immediately
          </label>
        </div>

        <div className="flex justify-end space-x-2">
          <Button
            type="button"
            variant="outline"
            onClick={() => router.back()}
            disabled={loading}
          >
            Cancel
          </Button>
          <Button type="submit" disabled={loading}>
            {loading
              ? 'Saving...'
              : initialData?.id
              ? 'Update Unit'
              : 'Create Unit'}
          </Button>
        </div>
      </Card>
    </form>
  );
}