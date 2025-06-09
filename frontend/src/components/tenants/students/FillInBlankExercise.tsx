'use client';

import { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { AlertCircle, CheckCircle } from 'lucide-react';
import { WordData } from '@/types/tenant/vocabulary';
import ClickableText from './ClickableText';

interface FillInBlankExerciseProps {
  exercise: {
    id: number;
    content: {
      text: string;
      blanks: number[];
      word_ids: number[];
      word_mapping?: Record<string, number>;
    };
    answers?: {
      correct: string[];
    };
  };
  onAnswer: (isCorrect: boolean) => void;
  onNext: () => void;
  wordData: Record<string, WordData>;
}

export default function FillInBlankExercise({
  exercise,
  onAnswer,
  onNext,
  wordData,
}: FillInBlankExerciseProps) {
  const [selectedWords, setSelectedWords] = useState<string[]>([]);
  const [availableWords, setAvailableWords] = useState<string[]>([]);
  const [isSubmitted, setIsSubmitted] = useState(false);
  const [isCorrect, setIsCorrect] = useState(false);
  const [localWordData, setLocalWordData] = useState<Record<string, WordData>>(wordData);

  useEffect(() => {
    setLocalWordData(wordData);
  }, [wordData]);

  useEffect(() => {
    // Generate available words for the word bank
    const words = new Set<string>();
    
    // Add correct answers
    if (exercise.answers?.correct) {
      exercise.answers.correct.forEach(answer => words.add(answer));
    }
    
    // Add some distractors from our word data
    const distractors = Object.keys(wordData).filter(word => 
      !exercise.answers?.correct.includes(word)
    ).slice(0, 5);
    
    distractors.forEach(word => words.add(word));
    
    // Shuffle the words
    setAvailableWords(shuffle(Array.from(words)));
  }, [exercise, wordData]);

  const handleSubmit = () => {
    // Check if all blanks are filled
    if (selectedWords.length < exercise.content.blanks.length) {
      return;
    }

    // Check if the answer is correct
    const correct = exercise.answers?.correct.every(
      (answer, index) => selectedWords[index].toLowerCase() === answer.toLowerCase()
    );

    setIsCorrect(!!correct);
    setIsSubmitted(true);
    onAnswer(!!correct);
  };

  const handleNext = () => {
    setSelectedWords([]);
    setIsSubmitted(false);
    onNext();
  };

  const renderSentenceWithBlanks = () => {
    const parts = exercise.content.text.split(/(__+)/g);
    
    return parts.map((part, index) => {
      if (part.match(/^__+$/)) {
        const blankIndex = Math.floor(index / 2);
        const selectedWord = selectedWords[blankIndex];
        
        return (
          <span
            key={index}
            className={`inline-block min-w-[100px] px-2 py-1 mx-1 rounded border-2 ${
              isSubmitted
                ? isCorrect
                  ? 'bg-green-50 border-green-500'
                  : 'bg-red-50 border-red-500'
                : selectedWord
                ? 'bg-primary/10 border-primary'
                : 'bg-gray-50 border-gray-200'
            }`}
          >
            {selectedWord || '_____'}
          </span>
        );
      }
      
      // Use ClickableText for regular text parts
      return (
        <ClickableText
          key={index}
          text={part}
          wordMapping={exercise.content.word_mapping}
          wordData={localWordData}
          className="inline"
        />
      );
    });
  };

  return (
    <Card className="p-6 space-y-6">
      {/* Exercise Text */}
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
                if (selectedWords.includes(word) || isSubmitted) return;
                if (selectedWords.length < exercise.content.blanks.length) {
                  setSelectedWords([...selectedWords, word]);
                }
              }}
              disabled={selectedWords.includes(word) || isSubmitted}
            >
              {word}
            </Button>
          ))}
        </div>
      </div>

      {/* Reset Button */}
      {selectedWords.length > 0 && !isSubmitted && (
        <Button
          variant="outline"
          onClick={() => setSelectedWords([])}
          className="w-full"
        >
          Reset
        </Button>
      )}

      {/* Feedback */}
      {isSubmitted && (
        <div
          className={`p-4 rounded-md ${
            isCorrect ? 'bg-green-50' : 'bg-red-50'
          }`}
        >
          <div className="flex items-start gap-3">
            {isCorrect ? (
              <CheckCircle className="h-5 w-5 text-green-600 flex-shrink-0 mt-0.5" />
            ) : (
              <AlertCircle className="h-5 w-5 text-red-600 flex-shrink-0 mt-0.5" />
            )}
            <div>
              <p className="font-medium">
                {isCorrect ? 'Correct!' : 'Incorrect'}
              </p>
              {!isCorrect && (
                <p className="mt-1 text-sm">
                  The correct answers are:{' '}
                  <span className="font-medium">
                    {exercise.answers?.correct.join(', ')}
                  </span>
                </p>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Action Buttons */}
      <div className="flex justify-end">
        {!isSubmitted ? (
          <Button
            onClick={handleSubmit}
            disabled={selectedWords.length < exercise.content.blanks.length}
          >
            Check Answer
          </Button>
        ) : (
          <Button onClick={handleNext}>Next</Button>
        )}
      </div>
    </Card>
  );
}

// Helper function to shuffle an array
function shuffle<T>(array: T[]): T[] {
  const newArray = [...array];
  for (let i = newArray.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [newArray[i], newArray[j]] = [newArray[j], newArray[i]];
  }
  return newArray;
}
