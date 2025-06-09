'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card } from '@/components/ui/card';
import { AlertDialog } from '@/components/admin/AlertDialog';
import { toast } from 'sonner';
import { LessonFormData } from '@/types/lesson';

interface LessonFormProps {
  unitId: number;
  initialData?: LessonFormData;
  onSubmit: (data: FormData) => Promise<{ error?: string }>;
}

export default function LessonForm({
  unitId,
  initialData,
  onSubmit,
}: LessonFormProps) {
  const router = useRouter();
  const [loading, setLoading] = useState(false);
  const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
  const [showDiscardWarning, setShowDiscardWarning] = useState(false);

  const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setLoading(true);

    try {
      const form = new FormData(e.currentTarget);
      form.append('unit_id', unitId.toString());
      
      const isPublished = form.get('is_published') === 'on';
      form.set('is_published', isPublished.toString());

      const result = await onSubmit(form);

      if (result.error) {
        toast.error('Error', {
          description: result.error,
        });
      } else {
        toast.success('Success', {
          description: `Lesson ${initialData ? 'updated' : 'created'} successfully`,
        });
        setHasUnsavedChanges(false);
        router.refresh();
        router.push(`/admin/units/${unitId}/view`);
      }
    } catch {
      toast.error('Error', {
        description: 'An unexpected error occurred',
      });
    } finally {
      setLoading(false);
    }
  };

  const handleCancel = () => {
    if (hasUnsavedChanges) {
      setShowDiscardWarning(true);
    } else {
      router.back();
    }
  };

  const handleFieldChange = () => {
    setHasUnsavedChanges(true);
  };

  return (
    <form onSubmit={handleSubmit} onChange={handleFieldChange}>
      <Card className="p-6 space-y-4">
        <div className="space-y-2">
          <label className="text-sm font-medium" htmlFor="title">Title</label>
          <Input
            id="title"
            name="title"
            defaultValue={initialData?.title}
            required
          />
        </div>

        <div className="space-y-2">
          <label className="text-sm font-medium" htmlFor="description">Description</label>
          <textarea
            id="description"
            name="description"
            className="w-full min-h-[100px] px-3 py-2 rounded-md border border-input bg-background"
            defaultValue={initialData?.description}
            required
          />
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <label className="text-sm font-medium" htmlFor="estimated_time">Estimated Time (minutes)</label>
            <Input
              id="estimated_time"
              type="number"
              name="estimated_time"
              defaultValue={initialData?.estimated_time || 10}
              min="1"
              required
            />
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium" htmlFor="xp_reward">XP Reward</label>
            <Input
              id="xp_reward"
              type="number"
              name="xp_reward"
              defaultValue={initialData?.xp_reward || 10}
              min="0"
              required
            />
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium" htmlFor="order">Order</label>
            <Input
              id="order"
              type="number"
              name="order"
              defaultValue={initialData?.order || 0}
              min="0"
              required
            />
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium" htmlFor="difficulty_level">Difficulty Level</label>
            <select
              id="difficulty_level"
              name="difficulty_level"
              defaultValue={initialData?.difficulty_level || 'beginner'}
              className="w-full px-3 py-2 rounded-md border border-input bg-background"
              required
            >
              <option value="beginner">Beginner</option>
              <option value="intermediate">Intermediate</option>
              <option value="advanced">Advanced</option>
            </select>
          </div>
        </div>

        <div className="flex items-center space-x-2">
          <input
            type="checkbox"
            id="is_published"
            name="is_published"
            defaultChecked={initialData?.is_published}
            className="h-4 w-4 rounded border-gray-300"
          />
          <label className="text-sm font-medium" htmlFor="is_published">Published</label>
        </div>
      </Card>

      <div className="mt-6 flex justify-end space-x-2">
        <Button
          type="button"
          variant="outline"
          onClick={handleCancel}
          disabled={loading}
        >
          Cancel
        </Button>
        <Button type="submit" disabled={loading}>
          {loading
            ? 'Saving...'
            : initialData
            ? 'Update Lesson'
            : 'Create Lesson'}
        </Button>
      </div>

      {showDiscardWarning && (
        <AlertDialog
          trigger={<></>}
          title="Discard Changes"
          description="You have unsaved changes. Are you sure you want to discard them?"
          confirmText="Discard"
          cancelText="Continue Editing"
          variant="destructive"
          onConfirm={() => router.back()}
          onCancel={() => setShowDiscardWarning(false)}
        />
      )}
    </form>
  );
}