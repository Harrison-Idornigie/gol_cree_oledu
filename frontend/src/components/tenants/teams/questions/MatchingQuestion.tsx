'use client';

import { useState } from 'react';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { MatchingQuestion as MatchingType, Media } from './types';
import MediaUploader from './MediaUploader';
import { toast } from 'sonner';

interface MatchingQuestionProps {
  isEditing: boolean;
  question: MatchingType;
  onUpdate?: (question: MatchingType) => void;
  onDelete?: () => void;
}

interface MatchedPair {
  leftIndex: number;
  rightIndex: number;
}

type PairUpdate = {
  left?: string;
  right?: string;
  media?: Media;
};

export default function MatchingQuestion({
  isEditing,
  question,
  onUpdate,
  onDelete,
}: MatchingQuestionProps) {
  const [localQuestion, setLocalQuestion] = useState(question);
  const [selectedLeft, setSelectedLeft] = useState<number | null>(null);
  const [matches, setMatches] = useState<MatchedPair[]>([]);

  // For preview mode
  const [shuffledRightItems, setShuffledRightItems] = useState(() =>
    shuffle([...Array(question.pairs.length).keys()])
  );

  const updateQuestion = (updates: Partial<MatchingType>) => {
    const updated = { ...localQuestion, ...updates };
    setLocalQuestion(updated);
    onUpdate?.(updated);
  };

  const addPair = () => {
    const newPairs = [
      ...localQuestion.pairs,
      { left: '', right: '' },
    ];
    updateQuestion({ pairs: newPairs });
  };

  const removePair = (index: number) => {
    if (localQuestion.pairs.length <= 2) {
      toast.error('Error', {
        description: 'At least 2 pairs are required',
      });
      return;
    }
    const newPairs = [...localQuestion.pairs];
    newPairs.splice(index, 1);
    updateQuestion({ pairs: newPairs });
  };

  const updatePair = (index: number, updates: PairUpdate) => {
    const newPairs = [...localQuestion.pairs];
    newPairs[index] = { ...newPairs[index], ...updates };
    updateQuestion({ pairs: newPairs });
  };

  // Helper function to shuffle array
  function shuffle<T>(array: T[]): T[] {
    const newArray = [...array];
    for (let i = newArray.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [newArray[i], newArray[j]] = [newArray[j], newArray[i]];
    }
    return newArray;
  }

  const handleItemClick = (index: number, side: 'left' | 'right') => {
    if (side === 'left') {
      if (selectedLeft === index) {
        setSelectedLeft(null);
      } else {
        setSelectedLeft(index);
      }
    } else {
      if (selectedLeft !== null) {
        // Check if either item is already matched
        const isLeftMatched = matches.some(m => m.leftIndex === selectedLeft);
        const isRightMatched = matches.some(m => m.rightIndex === shuffledRightItems[index]);
        
        if (!isLeftMatched && !isRightMatched) {
          setMatches([...matches, {
            leftIndex: selectedLeft,
            rightIndex: shuffledRightItems[index]
          }]);
        }
        setSelectedLeft(null);
      }
    }
  };

  const isMatched = (index: number, side: 'left' | 'right') => {
    if (side === 'left') {
      return matches.some(m => m.leftIndex === index);
    } else {
      return matches.some(m => m.rightIndex === shuffledRightItems[index]);
    }
  };

  if (isEditing) {
    return (
      <Card className="p-6 space-y-4">
        <div className="space-y-4">
          <div className="flex justify-between items-center">
            <h3 className="font-medium">Matching Pairs</h3>
            <Button type="button" onClick={addPair}>
              Add Pair
            </Button>
          </div>

          {localQuestion.pairs.map((pair, index) => (
            <Card key={index} className="p-4">
              <div className="space-y-4">
                <div className="flex justify-between items-start">
                  <h4 className="font-medium">Pair {index + 1}</h4>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="text-red-600"
                    onClick={() => removePair(index)}
                  >
                    Remove
                  </Button>
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <label className="text-sm font-medium">Left Item</label>
                    <Input
                      value={pair.left}
                      onChange={(e) =>
                        updatePair(index, { left: e.target.value })
                      }
                      placeholder="Enter left item"
                    />
                  </div>

                  <div className="space-y-2">
                    <label className="text-sm font-medium">Right Item</label>
                    <Input
                      value={pair.right}
                      onChange={(e) =>
                        updatePair(index, { right: e.target.value })
                      }
                      placeholder="Enter right item"
                    />
                  </div>
                </div>

                <div className="space-y-2">
                  <label className="text-sm font-medium">Media (Optional)</label>
                  <MediaUploader
                    media={pair.media ? [pair.media] : []}
                    onUpdate={(media: Media[]) =>
                      updatePair(index, { media: media[0] })
                    }
                    allowedTypes={['image', 'audio']}
                    maxFiles={1}
                  />
                </div>
              </div>
            </Card>
          ))}
        </div>
      </Card>
    );
  }

  const resetMatches = () => {
    setMatches([]);
    setSelectedLeft(null);
    setShuffledRightItems(shuffle([...Array(question.pairs.length).keys()]));
  };

  return (
    <Card className="p-6">
      <div className="space-y-6">
        <div className="grid grid-cols-2 gap-8">
          {/* Left Column */}
          <div className="space-y-4">
            <h3 className="font-medium text-center mb-4">Match these items</h3>
            {localQuestion.pairs.map((pair, index) => (
              <div
                key={`left-${index}`}
                className={`p-4 rounded-lg border-2 transition-colors cursor-pointer ${
                  selectedLeft === index
                    ? 'bg-primary/20 border-primary'
                    : isMatched(index, 'left')
                    ? 'bg-green-50 border-green-500'
                    : 'hover:bg-gray-50 border-gray-200'
                }`}
                onClick={() => handleItemClick(index, 'left')}
              >
                <div className="flex items-center gap-2">
                  {pair.media && (
                    <div className="flex-shrink-0">
                      {pair.media.type === 'image' ? (
                        <img
                          src={pair.media.url}
                          alt={pair.media.alt || ''}
                          className="w-12 h-12 object-cover rounded"
                        />
                      ) : (
                        <audio controls className="w-32">
                          <source src={pair.media.url} type="audio/mpeg" />
                        </audio>
                      )}
                    </div>
                  )}
                  <span>{pair.left}</span>
                </div>
              </div>
            ))}
          </div>

          {/* Right Column */}
          <div className="space-y-4">
            <h3 className="font-medium text-center mb-4">with these</h3>
            {shuffledRightItems.map((originalIndex, index) => (
              <div
                key={`right-${index}`}
                className={`p-4 rounded-lg border-2 transition-colors cursor-pointer ${
                  isMatched(originalIndex, 'right')
                    ? 'bg-green-50 border-green-500'
                    : 'hover:bg-gray-50 border-gray-200'
                }`}
                onClick={() => handleItemClick(index, 'right')}
              >
                {localQuestion.pairs[originalIndex].right}
              </div>
            ))}
          </div>
        </div>

        {/* Reset Button */}
        {matches.length > 0 && (
          <Button
            variant="outline"
            onClick={resetMatches}
            className="w-full mt-4"
          >
            Reset Matches
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