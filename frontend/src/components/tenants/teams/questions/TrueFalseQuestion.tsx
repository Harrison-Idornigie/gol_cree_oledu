'use client';

import { useState } from 'react';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { TrueFalseQuestion as TrueFalseQuestionType, Media } from './types';
import MediaUploader from './MediaUploader';

interface TrueFalseQuestionProps {
  isEditing: boolean;
  question: TrueFalseQuestionType;
  onUpdate?: (question: TrueFalseQuestionType) => void;
  onDelete?: () => void;
}

export default function TrueFalseQuestion({
  isEditing,
  question,
  onUpdate,
  onDelete,
}: TrueFalseQuestionProps) {
  const [localQuestion, setLocalQuestion] = useState(question);

  const updateQuestion = (updates: Partial<TrueFalseQuestionType>) => {
    const updated = { ...localQuestion, ...updates };
    setLocalQuestion(updated);
    onUpdate?.(updated);
  };

  if (isEditing) {
    return (
      <Card className="p-6 space-y-4">
        <div className="space-y-4">
          <div className="space-y-2">
            <label className="text-sm font-medium">Statement</label>
            <Input
              value={localQuestion.statement}
              onChange={(e) =>
                updateQuestion({ statement: e.target.value })
              }
              placeholder="Enter the statement"
            />
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium">Correct Answer</label>
            <div className="flex gap-4">
              <Button
                type="button"
                variant={localQuestion.is_true ? "default" : "outline"}
                onClick={() => updateQuestion({ is_true: true })}
              >
                True
              </Button>
              <Button
                type="button"
                variant={!localQuestion.is_true ? "default" : "outline"}
                onClick={() => updateQuestion({ is_true: false })}
              >
                False
              </Button>
            </div>
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium">Media (Optional)</label>
            <MediaUploader
              media={localQuestion.media || []}
              onUpdate={(media: Media[]) =>
                updateQuestion({ media })
              }
              allowedTypes={['image', 'audio']}
              maxFiles={2}
            />
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium">Explanation</label>
            <textarea
              className="w-full min-h-[100px] px-3 py-2 rounded-md border border-input bg-background"
              value={localQuestion.explanation || ''}
              onChange={(e) => updateQuestion({ explanation: e.target.value })}
              placeholder="Explain why this answer is correct..."
            />
          </div>
        </div>
      </Card>
    );
  }

  return (
    <Card className="p-6">
      <div className="space-y-6">
        {/* Media Display */}
        {localQuestion.media?.map((item, index) => (
          <div key={index} className="mb-4">
            {item.type === 'image' ? (
              <img
                src={item.url}
                alt={item.alt || ''}
                className="max-w-full h-auto rounded-lg"
              />
            ) : (
              <audio controls className="w-full">
                <source src={item.url} type="audio/mpeg" />
                Your browser does not support the audio element.
              </audio>
            )}
          </div>
        ))}

        {/* Statement */}
        <div className="text-lg font-medium">
          {localQuestion.statement}
        </div>

        {/* Answer Buttons */}
        <div className="flex gap-4">
          <Button
            variant="outline"
            size="lg"
            className={`flex-1 ${
              localQuestion.is_true
                ? 'bg-green-50 border-green-500 text-green-700'
                : ''
            }`}
            disabled={true}
          >
            True
          </Button>
          <Button
            variant="outline"
            size="lg"
            className={`flex-1 ${
              !localQuestion.is_true
                ? 'bg-green-50 border-green-500 text-green-700'
                : ''
            }`}
            disabled={true}
          >
            False
          </Button>
        </div>

        {/* Explanation */}
        {localQuestion.explanation && (
          <div className="bg-blue-50 p-4 rounded-md">
            <h4 className="text-sm font-medium text-blue-700 mb-1">
              Explanation
            </h4>
            <p className="text-blue-600 text-sm">{localQuestion.explanation}</p>
          </div>
        )}

        {/* Actions */}
        {(onUpdate || onDelete) && (
          <div className="flex justify-end gap-2 mt-4">
            {onUpdate && (
              <Button variant="outline" onClick={() => onUpdate(localQuestion)}>
                Edit
              </Button>
            )}
            {onDelete && (
              <Button
                variant="outline"
                className="text-red-600"
                onClick={onDelete}
              >
                Delete
              </Button>
            )}
          </div>
        )}
      </div>
    </Card>
  );
}