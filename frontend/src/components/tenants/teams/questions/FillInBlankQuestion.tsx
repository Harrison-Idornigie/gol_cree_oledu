'use client';

import { useState } from 'react';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { FillInBlankQuestion as FillInBlankType, Media } from './types';
import MediaUploader from './MediaUploader';
import { toast } from 'sonner';

interface FillInBlankQuestionProps {
  isEditing: boolean;
  question: FillInBlankType;
  onUpdate?: (question: FillInBlankType) => void;
  onDelete?: () => void;
}

interface Blank {
  position: number;
  correct_answer: string;
  alternatives?: string[];
}

export default function FillInBlankQuestion({
  isEditing,
  question,
  onUpdate,
  onDelete,
}: FillInBlankQuestionProps) {
  const [localQuestion, setLocalQuestion] = useState(question);
  const [newAlternative, setNewAlternative] = useState('');
  const [editingBlankIndex, setEditingBlankIndex] = useState<number | null>(null);
  
  // For the word bank preview
  const [selectedWords, setSelectedWords] = useState<string[]>([]);

  const updateQuestion = (updates: Partial<FillInBlankType>) => {
    const updated = { ...localQuestion, ...updates };
    setLocalQuestion(updated);
    onUpdate?.(updated);
  };

  const addBlank = () => {
    const newBlanks = [
      ...(localQuestion.blanks || []),
      {
        position: localQuestion.blanks?.length || 0,
        correct_answer: '',
        alternatives: [],
      },
    ];
    updateQuestion({ blanks: newBlanks });
  };

  const removeBlank = (index: number) => {
    if (localQuestion.blanks.length <= 1) {
      toast.error('Error', {
        description: 'Question must have at least one blank',
      });
      return;
    }
    const newBlanks = [...localQuestion.blanks];
    newBlanks.splice(index, 1);
    // Update positions
    newBlanks.forEach((blank, idx) => {
      blank.position = idx;
    });
    updateQuestion({ blanks: newBlanks });
  };

  const updateBlank = (index: number, updates: Partial<Blank>) => {
    const newBlanks = [...localQuestion.blanks];
    newBlanks[index] = { ...newBlanks[index], ...updates };
    updateQuestion({ blanks: newBlanks });
  };

  const addAlternative = (blankIndex: number) => {
    if (!newAlternative.trim()) return;
    const blank = localQuestion.blanks[blankIndex];
    if (blank.alternatives?.includes(newAlternative)) {
      toast.error('Error', {
        description: 'This alternative is already in the list',
      });
      return;
    }

    const newBlanks = [...localQuestion.blanks];
    newBlanks[blankIndex].alternatives = [
      ...(blank.alternatives || []),
      newAlternative.trim(),
    ];
    updateQuestion({ blanks: newBlanks });
    setNewAlternative('');
  };

  const removeAlternative = (blankIndex: number, altIndex: number) => {
    const newBlanks = [...localQuestion.blanks];
    newBlanks[blankIndex].alternatives?.splice(altIndex, 1);
    updateQuestion({ blanks: newBlanks });
  };

  // Get all possible words for the word bank
  const getAllWords = () => {
    const words = new Set<string>();
    localQuestion.blanks.forEach(blank => {
      words.add(blank.correct_answer);
      blank.alternatives?.forEach(alt => words.add(alt));
    });
    // Add some distractors (words that don't fit)
    const distractors = ['apple', 'cat', 'house', 'run', 'big', 'small'];
    for (let i = 0; i < 3 && words.size < 8; i++) {
      words.add(distractors[i]);
    }
    return Array.from(words);
  };

  // Shuffle array
  const shuffle = (array: string[]) => {
    const newArray = [...array];
    for (let i = newArray.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [newArray[i], newArray[j]] = [newArray[j], newArray[i]];
    }
    return newArray;
  };

  const renderSentenceWithBlanks = () => {
    const parts = localQuestion.sentence.split(/(__+)/g);
    return parts.map((part, index) => {
      if (part.match(/^__+$/)) {
        const blankIndex = Math.floor(index / 2);
        const blank = localQuestion.blanks[blankIndex];
        if (!blank) return part;
        
        if (isEditing) {
          return (
            <span
              key={index}
              className="inline-block min-w-[100px] px-2 py-1 mx-1 bg-gray-100 rounded"
            >
              {blank.correct_answer || '_____'}
            </span>
          );
        }

        const selectedWord = selectedWords[blankIndex];
        return (
          <span
            key={index}
            className={`inline-block min-w-[100px] px-2 py-1 mx-1 rounded border-2 ${
              selectedWord
                ? 'bg-primary/10 border-primary'
                : 'bg-gray-50 border-gray-200'
            }`}
          >
            {selectedWord || '_____'}
          </span>
        );
      }
      return <span key={index}>{part}</span>;
    });
  };

  if (isEditing) {
    return (
      <Card className="p-6 space-y-4">
        <div className="space-y-4">
          {/* Sentence Input */}
          <div className="space-y-2">
            <label className="text-sm font-medium">
              Sentence (Use __ for blanks)
            </label>
            <Input
              value={localQuestion.sentence}
              onChange={(e) => updateQuestion({ sentence: e.target.value })}
              placeholder="Enter sentence with __ for blanks"
            />
          </div>

          {/* Media Upload */}
          <div className="space-y-2">
            <label className="text-sm font-medium">Media (Optional)</label>
            <MediaUploader
              media={localQuestion.media}
              onUpdate={(media: Media[]) => updateQuestion({ media })}
              allowedTypes={['image', 'audio']}
              maxFiles={2}
            />
          </div>

          {/* Blanks */}
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <label className="text-sm font-medium">Blanks</label>
              <Button type="button" onClick={addBlank}>
                Add Blank
              </Button>
            </div>
            
            {localQuestion.blanks.map((blank, blankIndex) => (
              <Card key={blankIndex} className="p-4 space-y-4">
                <div className="flex items-start justify-between">
                  <h4 className="font-medium">Blank {blankIndex + 1}</h4>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="text-red-600"
                    onClick={() => removeBlank(blankIndex)}
                  >
                    Remove
                  </Button>
                </div>

                <div className="space-y-2">
                  <label className="text-sm font-medium">Correct Answer</label>
                  <Input
                    value={blank.correct_answer}
                    onChange={(e) =>
                      updateBlank(blankIndex, {
                        correct_answer: e.target.value,
                      })
                    }
                    placeholder="Enter correct answer"
                  />
                </div>

                <div className="space-y-2">
                  <label className="text-sm font-medium">
                    Alternative Answers
                  </label>
                  <div className="flex gap-2">
                    <Input
                      value={editingBlankIndex === blankIndex ? newAlternative : ''}
                      onChange={(e) => setNewAlternative(e.target.value)}
                      onFocus={() => setEditingBlankIndex(blankIndex)}
                      placeholder="Add alternative answer"
                    />
                    <Button
                      type="button"
                      onClick={() => addAlternative(blankIndex)}
                    >
                      Add
                    </Button>
                  </div>
                  <div className="space-y-2">
                    {blank.alternatives?.map((alt, altIndex) => (
                      <div key={altIndex} className="flex items-center gap-2">
                        <span className="flex-1">{alt}</span>
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          className="text-red-600"
                          onClick={() =>
                            removeAlternative(blankIndex, altIndex)
                          }
                        >
                          Remove
                        </Button>
                      </div>
                    ))}
                  </div>
                </div>
              </Card>
            ))}
          </div>
        </div>
      </Card>
    );
  }

  const availableWords = shuffle(getAllWords());

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
                className="h-auto max-w-full rounded-lg"
              />
            ) : (
              <audio controls className="w-full">
                <source src={item.url} type="audio/mpeg" />
                Your browser does not support the audio element.
              </audio>
            )}
          </div>
        ))}

        {/* Sentence with Blanks */}
        <div className="text-lg leading-relaxed">
          {renderSentenceWithBlanks()}
        </div>

        {/* Word Bank */}
        <div className="space-y-2">
          <label className="text-sm font-medium text-muted-foreground">
            Select words to fill in the blanks
          </label>
          <div className="flex flex-wrap gap-2">
            {availableWords.map((word, index) => (
              <Button
                key={index}
                variant="outline"
                className={`${
                  selectedWords.includes(word)
                    ? 'opacity-50 cursor-not-allowed'
                    : ''
                }`}
                onClick={() => {
                  if (selectedWords.includes(word)) return;
                  if (selectedWords.length < localQuestion.blanks.length) {
                    setSelectedWords([...selectedWords, word]);
                  }
                }}
                disabled={selectedWords.includes(word)}
              >
                {word}
              </Button>
            ))}
          </div>
        </div>

        {/* Reset Button */}
        {selectedWords.length > 0 && (
          <Button
            variant="outline"
            onClick={() => setSelectedWords([])}
            className="w-full"
          >
            Reset
          </Button>
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