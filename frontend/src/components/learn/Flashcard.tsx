'use client';

import { useState, useEffect } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import { Volume2, ArrowRight, CheckCircle, XCircle, RefreshCw } from 'lucide-react';
import { VocabularyItem } from '@/types/vocabulary';
import { audioService } from '@/lib/services/audioService';

interface FlashcardProps {
  item: VocabularyItem;
  onAnswer: (isCorrect: boolean) => void;
  onNext: () => void;
  showAnswer?: boolean;
  mode?: 'translation' | 'word';
}

export default function Flashcard({
  item,
  onAnswer,
  onNext,
  showAnswer = false,
  mode = 'translation'
}: FlashcardProps) {
  const [flipped, setFlipped] = useState(showAnswer);
  const [userInput, setUserInput] = useState('');
  const [isCorrect, setIsCorrect] = useState<boolean | null>(null);
  const [isPlaying, setIsPlaying] = useState(false);

  // Reset state when item changes
  useEffect(() => {
    setFlipped(showAnswer);
    setUserInput('');
    setIsCorrect(null);
  }, [item, showAnswer]);

  const handleFlip = () => {
    if (!flipped && !isCorrect) {
      setFlipped(true);
      setIsCorrect(false);
      onAnswer(false);
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    
    if (isCorrect !== null) {
      // Already answered, go to next
      onNext();
      return;
    }

    // Check if the answer is correct
    const correctAnswer = mode === 'translation' ? item.translation : item.word;
    const isAnswerCorrect = userInput.trim().toLowerCase() === correctAnswer.trim().toLowerCase();
    
    setIsCorrect(isAnswerCorrect);
    setFlipped(true);
    onAnswer(isAnswerCorrect);
  };

  const playAudio = async () => {
    if (isPlaying || !item.pronunciation_url) return;
    
    setIsPlaying(true);
    try {
      await audioService.play(item.pronunciation_url);
    } catch (error) {
      console.error('Error playing audio:', error);
    } finally {
      setIsPlaying(false);
    }
  };

  return (
    <div className="w-full max-w-md mx-auto">
      <Card className={`w-full transition-all duration-300 ${flipped ? 'bg-background' : 'bg-background'}`}>
        <CardContent className="p-6">
          <div className="flex justify-between items-center mb-4">
            <div className="text-sm text-muted-foreground">
              {item.part_of_speech && (
                <span className="px-2 py-1 rounded-full bg-primary/10 text-primary text-xs">
                  {item.part_of_speech}
                </span>
              )}
            </div>
            {item.pronunciation_url && (
              <Button
                variant="ghost"
                size="sm"
                className="w-8 h-8 p-0"
                onClick={playAudio}
                disabled={isPlaying}
              >
                <Volume2 className="w-4 h-4" />
              </Button>
            )}
          </div>

          <div className="text-center mb-6">
            <h2 className="text-2xl font-bold mb-2">
              {mode === 'translation' ? item.word : item.translation}
            </h2>
            {item.phonetic && <p className="text-muted-foreground">{item.phonetic}</p>}
          </div>

          {flipped ? (
            <div className="space-y-4">
              <div className={`p-4 rounded-md ${isCorrect ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'}`}>
                <div className="flex items-center gap-2 mb-2">
                  {isCorrect ? (
                    <CheckCircle className="w-5 h-5 text-green-500" />
                  ) : (
                    <XCircle className="w-5 h-5 text-red-500" />
                  )}
                  <span className={`font-medium ${isCorrect ? 'text-green-700' : 'text-red-700'}`}>
                    {isCorrect ? 'Correct!' : 'Incorrect'}
                  </span>
                </div>
                <p className="text-lg font-medium">
                  {mode === 'translation' ? item.translation : item.word}
                </p>
              </div>

              {item.example && (
                <div className="p-4 rounded-md bg-muted">
                  <p className="text-sm italic">"{item.example}"</p>
                </div>
              )}

              <Button className="w-full" onClick={onNext}>
                Next <ArrowRight className="ml-2 w-4 h-4" />
              </Button>
            </div>
          ) : (
            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="space-y-2">
                <Input
                  type="text"
                  placeholder={`Enter the ${mode === 'translation' ? 'translation' : 'word'}`}
                  value={userInput}
                  onChange={(e) => setUserInput(e.target.value)}
                  className="w-full"
                  autoFocus
                />
              </div>

              <div className="flex gap-2">
                <Button type="submit" className="flex-1">
                  Check
                </Button>
                <Button type="button" variant="outline" onClick={handleFlip}>
                  Show Answer
                </Button>
              </div>
            </form>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
