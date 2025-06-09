'use client';

import { useState } from 'react';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { ListenTypeQuestion as ListenTypeQuestionType, Media } from './types';
import MediaUploader from './MediaUploader';
import { toast } from 'sonner';

interface ListenTypeQuestionProps {
  isEditing: boolean;
  question: ListenTypeQuestionType;
  onUpdate?: (question: ListenTypeQuestionType) => void;
  onDelete?: () => void;
}

interface AnswerToken {
  text: string;
  isSelected: boolean;
}

export default function ListenTypeQuestion({
  isEditing,
  question,
  onUpdate,
  onDelete,
}: ListenTypeQuestionProps) {
  const [localQuestion, setLocalQuestion] = useState(question);
  const [selectedTokens, setSelectedTokens] = useState<number[]>([]);
  const [isPlaying, setIsPlaying] = useState(false);
  const [availableTokens, setAvailableTokens] = useState<AnswerToken[]>(() => {
    // Create a pool of available tokens based on the correct answer and some distractors
    const correctTokens = question.correct_text.split(' ');
    const distractors = ['the', 'a', 'an', 'is', 'are', 'was', 'were'];
    const allTokens = [...correctTokens];
    
    // Add some distractors if it's a word-based question
    if (correctTokens.length > 1) {
      for (let i = 0; i < 3 && allTokens.length < 8; i++) {
        if (!correctTokens.includes(distractors[i])) {
          allTokens.push(distractors[i]);
        }
      }
    }
    
    return shuffle(allTokens.map(token => ({
      text: token,
      isSelected: false
    })));
  });

  const updateQuestion = (updates: Partial<ListenTypeQuestionType>) => {
    const updated = { ...localQuestion, ...updates };
    setLocalQuestion(updated);
    onUpdate?.(updated);
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

  const handleAudioPlay = async (isSlowVersion: boolean = false) => {
    const audio = isSlowVersion
      ? localQuestion.slow_audio
      : localQuestion.audio;

    if (!audio) return;

    try {
      setIsPlaying(true);
      const audioElement = new Audio(audio.url);
      await audioElement.play();
      audioElement.onended = () => setIsPlaying(false);
    } catch {
      toast.error('Error playing audio');
      setIsPlaying(false);
    }
  };

  const handleTokenClick = (index: number) => {
    if (availableTokens[index].isSelected) return;

    setSelectedTokens([...selectedTokens, index]);
    setAvailableTokens(tokens => tokens.map((token, i) => 
      i === index ? { ...token, isSelected: true } : token
    ));
  };

  const resetSelection = () => {
    setSelectedTokens([]);
    setAvailableTokens(tokens => tokens.map(token => ({ ...token, isSelected: false })));
  };

  const checkAnswer = () => {
    const userAnswer = selectedTokens
      .map(index => availableTokens[index].text)
      .join(' ');
    
    const correctAnswers = [
      localQuestion.correct_text.toLowerCase(),
      ...(localQuestion.alternatives || []).map(alt => alt.toLowerCase())
    ];

    return correctAnswers.includes(userAnswer.toLowerCase());
  };

  if (isEditing) {
    return (
      <Card className="p-6 space-y-4">
        <div className="space-y-4">
          <div className="space-y-2">
            <label className="text-sm font-medium">Correct Text</label>
            <Input
              value={localQuestion.correct_text}
              onChange={(e) =>
                updateQuestion({ correct_text: e.target.value })
              }
              placeholder="Enter the correct text"
            />
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium">Language</label>
            <select
              className="w-full px-3 py-2 rounded-md border border-input bg-background"
              value={localQuestion.language}
              onChange={(e) =>
                updateQuestion({ language: e.target.value })
              }
            >
              <option value="en">English</option>
              <option value="es">Spanish</option>
              <option value="fr">French</option>
              <option value="de">German</option>
              {/* Add more languages as needed */}
            </select>
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium">Audio</label>
            <MediaUploader
              media={[localQuestion.audio]}
              onUpdate={(media: Media[]) =>
                updateQuestion({ audio: media[0] })
              }
              allowedTypes={['audio']}
              maxFiles={1}
            />
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium">
              Slow Version Audio (Optional)
            </label>
            <MediaUploader
              media={localQuestion.slow_audio ? [localQuestion.slow_audio] : []}
              onUpdate={(media: Media[]) =>
                updateQuestion({ slow_audio: media[0] })
              }
              allowedTypes={['audio']}
              maxFiles={1}
            />
          </div>

          <div className="space-y-2">
            <label className="text-sm font-medium">
              Alternative Answers
            </label>
            <div className="flex gap-2">
              <Input
                placeholder="Add alternative answer"
                onKeyPress={(e) => {
                  if (e.key === 'Enter' && (e.target as HTMLInputElement).value) {
                    const alternatives = [
                      ...(localQuestion.alternatives || []),
                      (e.target as HTMLInputElement).value,
                    ];
                    updateQuestion({ alternatives });
                    (e.target as HTMLInputElement).value = '';
                  }
                }}
              />
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
                    onClick={() => {
                      const alternatives = [...(localQuestion.alternatives || [])];
                      alternatives.splice(index, 1);
                      updateQuestion({ alternatives });
                    }}
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
      <div className="space-y-6">
        {/* Audio Controls */}
        <div className="flex justify-center gap-4">
          <Button
            variant="outline"
            size="lg"
            className="w-32"
            onClick={() => handleAudioPlay(false)}
            disabled={isPlaying}
          >
            {isPlaying ? (
              <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-primary" />
            ) : (
              <>
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  className="w-6 h-6 mr-2"
                >
                  <polygon points="5 3 19 12 5 21 5 3" />
                </svg>
                Play
              </>
            )}
          </Button>
          {localQuestion.slow_audio && (
            <Button
              variant="outline"
              size="lg"
              className="w-32"
              onClick={() => handleAudioPlay(true)}
              disabled={isPlaying}
            >
              {isPlaying ? (
                <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-primary" />
              ) : (
                <>
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    className="w-6 h-6 mr-2"
                  >
                    <circle cx="12" cy="12" r="10" />
                    <polyline points="12 6 12 12 16 14" />
                  </svg>
                  Slow
                </>
              )}
            </Button>
          )}
        </div>

        {/* Answer Display */}
        <div className="min-h-[60px] p-4 border-2 border-dashed rounded-lg flex items-center justify-center">
          {selectedTokens.length > 0 ? (
            <div className="flex flex-wrap gap-2">
              {selectedTokens.map((tokenIndex, index) => (
                <span
                  key={index}
                  className="px-3 py-1 bg-primary/10 rounded-full"
                >
                  {availableTokens[tokenIndex].text}
                </span>
              ))}
            </div>
          ) : (
            <span className="text-muted-foreground">
              Select words to form the sentence
            </span>
          )}
        </div>

        {/* Available Tokens */}
        <div className="flex flex-wrap gap-2 justify-center">
          {availableTokens.map((token, index) => (
            <Button
              key={index}
              variant="outline"
              size="sm"
              disabled={token.isSelected}
              onClick={() => handleTokenClick(index)}
              className={token.isSelected ? 'opacity-50' : ''}
            >
              {token.text}
            </Button>
          ))}
        </div>

        {/* Action Buttons */}
        <div className="flex justify-center gap-4">
          <Button variant="outline" onClick={resetSelection}>
            Reset
          </Button>
          <Button
            onClick={() => {
              if (checkAnswer()) {
                toast.success('Correct!');
              } else {
                toast.error('Try again');
              }
            }}
          >
            Check
          </Button>
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