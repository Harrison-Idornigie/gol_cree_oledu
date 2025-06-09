'use client';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { BaseQuestion } from './types';
import { Input } from '@/components/ui/input';

interface BaseQuestionProps {
  isEditing: boolean;
  question: BaseQuestion;
  onUpdate?: (updatedQuestion: BaseQuestion) => void;
  onDelete?: () => void;
}

interface BaseQuestionEditProps<T extends BaseQuestion> {
  question: T;
  onUpdate: (question: T) => void;
  onCancel: () => void;
  children?: React.ReactNode;
}

interface BaseQuestionPreviewProps<T extends BaseQuestion> {
  question: T;
  onEdit: () => void;
  onDelete: () => void;
  children?: React.ReactNode;
}

export function BaseQuestionEdit<T extends BaseQuestion>({
  question,
  onUpdate,
  onCancel,
  children
}: BaseQuestionEditProps<T>) {
  const handleOrderChange = (newOrder: number) => {
    onUpdate({
      ...question,
      order: newOrder
    });
  };

  const handleDifficultyChange = (newDifficulty: string) => {
    onUpdate({
      ...question,
      difficulty_level: newDifficulty
    });
  };

  const handleExplanationChange = (newExplanation: string) => {
    onUpdate({
      ...question,
      explanation: newExplanation
    });
  };

  return (
    <Card className="p-6">
      <div className="space-y-4">
        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <label className="text-sm font-medium">Order</label>
            <Input
              type="number"
              min={0}
              value={question.order}
              onChange={(e) => handleOrderChange(parseInt(e.target.value) || 0)}
            />
          </div>
          <div className="space-y-2">
            <label className="text-sm font-medium">Difficulty</label>
            <select
              className="w-full px-3 py-2 rounded-md border border-input bg-background"
              value={question.difficulty_level || 'normal'}
              onChange={(e) => handleDifficultyChange(e.target.value)}
            >
              <option value="easy">Easy</option>
              <option value="normal">Normal</option>
              <option value="hard">Hard</option>
            </select>
          </div>
        </div>

        {children}

        <div className="space-y-2">
          <label className="text-sm font-medium">Explanation</label>
          <textarea
            className="w-full min-h-[100px] px-3 py-2 rounded-md border border-input bg-background"
            value={question.explanation || ''}
            onChange={(e) => handleExplanationChange(e.target.value)}
            placeholder="Explain why this answer is correct..."
          />
        </div>

        <div className="flex justify-end gap-2">
          <Button variant="outline" onClick={onCancel}>
            Cancel
          </Button>
          <Button type="button" onClick={() => onUpdate(question)}>
            Save
          </Button>
        </div>
      </div>
    </Card>
  );
}

export function BaseQuestionPreview<T extends BaseQuestion>({
  question,
  onEdit,
  onDelete,
  children
}: BaseQuestionPreviewProps<T>) {
  return (
    <Card className="p-6">
      <div className="space-y-4">
        <div className="flex justify-between items-start">
          <div className="flex gap-2 items-center">
            <span className="text-sm text-muted-foreground">
              Question {question.order + 1}
            </span>
            <span
              className={`inline-block px-2 py-1 text-xs rounded-full ${
                question.difficulty_level === 'easy'
                  ? 'bg-green-100 text-green-700'
                  : question.difficulty_level === 'hard'
                  ? 'bg-red-100 text-red-700'
                  : 'bg-blue-100 text-blue-700'
              }`}
            >
              {question.difficulty_level || 'Normal'}
            </span>
          </div>
          <div className="flex gap-2">
            <Button variant="outline" size="sm" onClick={onEdit}>
              Edit
            </Button>
            <Button
              variant="outline"
              size="sm"
              className="text-red-600"
              onClick={onDelete}
            >
              Delete
            </Button>
          </div>
        </div>

        {children}

        {question.explanation && (
          <div className="bg-blue-50 p-4 rounded-md mt-4">
            <h4 className="text-sm font-medium text-blue-700 mb-1">
              Explanation
            </h4>
            <p className="text-blue-600 text-sm">{question.explanation}</p>
          </div>
        )}
      </div>
    </Card>
  );
}