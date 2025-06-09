'use client';

import { useState } from 'react';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { TranslationQuestion as TranslationType, Media } from './types';
import MediaUploader from './MediaUploader';
import { toast } from 'sonner';

interface TranslationQuestionProps {
  isEditing: boolean;
  question: TranslationType;
  onUpdate?: (question: TranslationType) => void;
  onDelete?: () => void;
}

// Supported languages in the format { code: 'en', name: 'English' }
const SUPPORTED_LANGUAGES = [
  { code: 'en', name: 'English' },
  { code: 'es', name: 'Spanish' },
  { code: 'fr', name: 'French' },
  { code: 'de', name: 'German' },
  { code: 'it', name: 'Italian' },
  { code: 'pt', name: 'Portuguese' },
  { code: 'nl', name: 'Dutch' },
  { code: 'ru', name: 'Russian' },
  { code: 'ja', name: 'Japanese' },
  { code: 'zh', name: 'Chinese' },
  { code: 'ko', name: 'Korean' },
];

export default function TranslationQuestion({
  isEditing,
  question,
  onUpdate,
  onDelete,
}: TranslationQuestionProps) {
  const [localQuestion, setLocalQuestion] = useState(question);
  const [newAlternative, setNewAlternative] = useState('');

  const updateQuestion = (updates: Partial<TranslationType>) => {
    const updated = { ...localQuestion, ...updates };
    setLocalQuestion(updated);
    onUpdate?.(updated);
  };

  const addAlternative = () => {
    if (!newAlternative.trim()) return;
    if (localQuestion.alternatives?.includes(newAlternative)) {
      toast.error('Error', {
        description: 'This translation is already in the list',
      });
      return;
    }

    const alternatives = [
      ...(localQuestion.alternatives || []),
      newAlternative.trim(),
    ];
    updateQuestion({ alternatives });
    setNewAlternative('');
  };

  const removeAlternative = (index: number) => {
    const alternatives = [...(localQuestion.alternatives || [])];
    alternatives.splice(index, 1);
    updateQuestion({ alternatives });
  };

  if (isEditing) {
    return (
      <Card className="p-6 space-y-4">
        <div className="space-y-4">
          {/* Language Selection */}
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <label className="text-sm font-medium">Source Language</label>
              <select
                className="w-full px-3 py-2 rounded-md border border-input bg-background"
                value={localQuestion.source_language}
                onChange={(e) =>
                  updateQuestion({ source_language: e.target.value })
                }
              >
                {SUPPORTED_LANGUAGES.map((lang) => (
                  <option key={lang.code} value={lang.code}>
                    {lang.name}
                  </option>
                ))}
              </select>
            </div>
            <div className="space-y-2">
              <label className="text-sm font-medium">Target Language</label>
              <select
                className="w-full px-3 py-2 rounded-md border border-input bg-background"
                value={localQuestion.target_language}
                onChange={(e) =>
                  updateQuestion({ target_language: e.target.value })
                }
              >
                {SUPPORTED_LANGUAGES.map((lang) => (
                  <option key={lang.code} value={lang.code}>
                    {lang.name}
                  </option>
                ))}
              </select>
            </div>
          </div>

          {/* Text to Translate */}
          <div className="space-y-2">
            <label className="text-sm font-medium">Text to Translate</label>
            <Input
              value={localQuestion.text}
              onChange={(e) => updateQuestion({ text: e.target.value })}
              placeholder="Enter text in source language"
            />
          </div>

          {/* Media Upload */}
          <div className="space-y-2">
            <label className="text-sm font-medium">Media (Optional)</label>
            <MediaUploader
              media={localQuestion.media}
              onUpdate={(media: Media[]) => updateQuestion({ media })}
              allowedTypes={['audio']}
              maxFiles={1}
            />
          </div>

          {/* Correct Translation */}
          <div className="space-y-2">
            <label className="text-sm font-medium">Correct Translation</label>
            <Input
              value={localQuestion.correct_translation}
              onChange={(e) =>
                updateQuestion({ correct_translation: e.target.value })
              }
              placeholder="Enter correct translation"
            />
          </div>

          {/* Alternative Translations */}
          <div className="space-y-2">
            <label className="text-sm font-medium">Alternative Translations</label>
            <div className="flex gap-2">
              <Input
                value={newAlternative}
                onChange={(e) => setNewAlternative(e.target.value)}
                placeholder="Add alternative translation"
              />
              <Button type="button" onClick={addAlternative}>
                Add
              </Button>
            </div>
            <div className="space-y-2">
              {localQuestion.alternatives?.map((alt, index) => (
                <div key={index} className="flex items-center gap-2">
                  <span className="flex-1">{alt}</span>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="text-red-600"
                    onClick={() => removeAlternative(index)}
                  >
                    Remove
                  </Button>
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
        {/* Language Indicator */}
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <span>{SUPPORTED_LANGUAGES.find(l => l.code === localQuestion.source_language)?.name}</span>
          <span>→</span>
          <span>{SUPPORTED_LANGUAGES.find(l => l.code === localQuestion.target_language)?.name}</span>
        </div>

        {/* Text and Media */}
        <div>
          <h3 className="text-lg font-medium mb-2">{localQuestion.text}</h3>
          {localQuestion.media?.map((item, index) => (
            <div key={index} className="mb-4">
              {item.type === 'audio' && (
                <audio controls className="w-full">
                  <source src={item.url} type="audio/mpeg" />
                  Your browser does not support the audio element.
                </audio>
              )}
            </div>
          ))}
        </div>

        {/* Correct Answer */}
        <div className="p-4 bg-green-50 rounded-lg border border-green-200">
          <h4 className="text-sm font-medium text-green-700 mb-1">
            Correct Translation
          </h4>
          <p className="text-green-800">{localQuestion.correct_translation}</p>
        </div>

        {/* Alternative Translations */}
        {localQuestion.alternatives && localQuestion.alternatives.length > 0 && (
          <div className="space-y-2">
            <h4 className="text-sm font-medium text-muted-foreground">
              Also Accepted
            </h4>
            <div className="space-y-1">
              {localQuestion.alternatives.map((alt, index) => (
                <div
                  key={index}
                  className="p-2 bg-gray-50 rounded border border-gray-200"
                >
                  {alt}
                </div>
              ))}
            </div>
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