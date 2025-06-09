'use client';

import { useState } from 'react';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { MultipleChoiceQuestion as MultipleChoiceType, Media } from './types';
import MediaUploader from './MediaUploader';
import { toast } from 'sonner';

interface MultipleChoiceQuestionProps {
  isEditing: boolean;
  question: MultipleChoiceType;
  onUpdate?: (question: MultipleChoiceType) => void;
  onDelete?: () => void;
}

export default function MultipleChoiceQuestion({
  isEditing,
  question,
  onUpdate,
  onDelete,
}: MultipleChoiceQuestionProps) {
  const [localQuestion, setLocalQuestion] = useState(question);

  const updateQuestion = (updates: Partial<MultipleChoiceType>) => {
    const updated = { ...localQuestion, ...updates };
    setLocalQuestion(updated);
    onUpdate?.(updated);
  };

  const addOption = () => {
    if (localQuestion.options.length >= 4) {
      toast.error('Error', {
        description: 'Maximum 4 options allowed',
      });
      return;
    }
    updateQuestion({
      options: [...localQuestion.options, '']
    });
  };

  const removeOption = (index: number) => {
    if (localQuestion.options.length <= 2) {
      toast.error('Error', {
        description: 'Minimum 2 options required',
      });
      return;
    }
    const newOptions = [...localQuestion.options];
    newOptions.splice(index, 1);
    updateQuestion({
      options: newOptions,
      correct_answer: localQuestion.correct_answer === localQuestion.options[index]
        ? newOptions[0]
        : localQuestion.correct_answer,
    });
  };

  const updateOption = (index: number, value: string) => {
    const newOptions = [...localQuestion.options];
    newOptions[index] = value;
    updateQuestion({
      options: newOptions,
      correct_answer: localQuestion.correct_answer === localQuestion.options[index]
        ? value
        : localQuestion.correct_answer,
    });
  };

  if (isEditing) {
    return (
      <Card className="p-6 space-y-4">
        <div className="space-y-4">
          <div className="space-y-2">
            <label className="text-sm font-medium">Question Text</label>
            <Input
              value={localQuestion.question}
              onChange={(e) =>
                updateQuestion({ question: e.target.value })
              }
              placeholder="Enter your question"
            />
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium">Media (Optional)</label>
            <MediaUploader
              media={localQuestion.media}
              onUpdate={(media: Media[]) => updateQuestion({ media })}
              allowedTypes={['image', 'audio']}
              maxFiles={2}
            />
          </div>

          <div className="space-y-2">
            <div className="flex justify-between items-center">
              <label className="text-sm font-medium">Options</label>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={addOption}
                disabled={localQuestion.options.length >= 4}
              >
                Add Option
              </Button>
            </div>
            <div className="space-y-2">
              {localQuestion.options.map((option, index) => (
                <div key={index} className="flex gap-2 items-center">
                  <input
                    type="radio"
                    name="correct_answer"
                    checked={localQuestion.correct_answer === option}
                    onChange={() =>
                      updateQuestion({ correct_answer: option })
                    }
                  />
                  <Input
                    value={option}
                    onChange={(e) => updateOption(index, e.target.value)}
                    placeholder={`Option ${index + 1}`}
                  />
                  {localQuestion.options.length > 2 && (
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      className="text-red-600"
                      onClick={() => removeOption(index)}
                    >
                      Remove
                    </Button>
                  )}
                </div>
              ))}
            </div>
          </div>
        </div>
      </Card>
    );
  }

  return (
    <Card className="p-6">
      <div className="space-y-4">
        {/* Question Text */}
        <div>
          <h3 className="text-lg font-medium mb-2">{localQuestion.question}</h3>
        </div>

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

        {/* Options */}
        <div className="grid gap-2">
          {localQuestion.options.map((option, index) => (
            <div
              key={index}
              className={`p-4 rounded-lg border cursor-pointer transition-colors ${
                option === localQuestion.correct_answer
                  ? 'bg-green-50 border-green-500'
                  : 'hover:bg-gray-50 border-gray-200'
              }`}
            >
              <div className="flex items-center gap-2">
                <div className="w-4 h-4 rounded-full border-2 flex-shrink-0">
                  {option === localQuestion.correct_answer && (
                    <div className="w-2 h-2 bg-green-500 rounded-full m-0.5" />
                  )}
                </div>
                <span>{option}</span>
              </div>
            </div>
          ))}
        </div>

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