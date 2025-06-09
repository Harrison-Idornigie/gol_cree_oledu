'use client';

import { useState, useEffect } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { VocabularyItem } from '@/types/vocabulary';
import Flashcard from './Flashcard';
import { checkVocabularyTranslation } from '@/app/_actions/tenants/student/vocabulary-actions';
import { LoadingSpinner } from '@/components/ui/loading-spinner';
import { CheckCircle, XCircle, RefreshCw, Trophy } from 'lucide-react';

interface VocabularyReviewProps {
  vocabularyItems: VocabularyItem[];
  unitId?: number;
  languageId?: number;
}

export default function VocabularyReview({
  vocabularyItems,
  unitId,
  languageId
}: VocabularyReviewProps) {
  const [currentIndex, setCurrentIndex] = useState(0);
  const [reviewMode, setReviewMode] = useState<'translation' | 'word'>('translation');
  const [results, setResults] = useState<{ correct: boolean; itemId: number }[]>([]);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isCompleted, setIsCompleted] = useState(false);

  // Reset state when vocabulary items change
  useEffect(() => {
    setCurrentIndex(0);
    setResults([]);
    setIsCompleted(false);
  }, [vocabularyItems]);

  const currentItem = vocabularyItems[currentIndex];
  const progress = Math.round(((currentIndex) / vocabularyItems.length) * 100);
  
  const handleAnswer = async (isCorrect: boolean) => {
    setIsSubmitting(true);
    
    try {
      // If using the real API, submit the answer
      if (!isCorrect) {
        await checkVocabularyTranslation(
          currentItem.id,
          reviewMode === 'translation' ? currentItem.translation : currentItem.word
        );
      }
      
      // Record the result
      setResults([...results, { correct: isCorrect, itemId: currentItem.id }]);
    } catch (error) {
      console.error('Error submitting answer:', error);
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleNext = () => {
    if (currentIndex < vocabularyItems.length - 1) {
      setCurrentIndex(currentIndex + 1);
    } else {
      setIsCompleted(true);
    }
  };

  const handleRestart = () => {
    setCurrentIndex(0);
    setResults([]);
    setIsCompleted(false);
  };

  const correctCount = results.filter(r => r.correct).length;
  const accuracy = results.length > 0 ? Math.round((correctCount / results.length) * 100) : 0;

  if (vocabularyItems.length === 0) {
    return (
      <Card className="w-full max-w-md mx-auto">
        <CardContent className="p-6 text-center">
          <p className="mb-4">No vocabulary items available for review.</p>
          <Button>Return to Dashboard</Button>
        </CardContent>
      </Card>
    );
  }

  if (isCompleted) {
    return (
      <Card className="w-full max-w-md mx-auto">
        <CardContent className="p-6">
          <div className="text-center mb-6">
            <div className="flex justify-center mb-4">
              <div className="w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center">
                <Trophy className="w-8 h-8 text-primary" />
              </div>
            </div>
            <h2 className="text-2xl font-bold mb-2">Review Completed!</h2>
            <p className="text-muted-foreground">
              You've reviewed {vocabularyItems.length} words
            </p>
          </div>

          <div className="space-y-4 mb-6">
            <div className="flex justify-between items-center">
              <span>Accuracy</span>
              <span className="font-medium">{accuracy}%</span>
            </div>
            <Progress value={accuracy} className="h-2" />

            <div className="flex justify-between text-sm">
              <div className="flex items-center">
                <CheckCircle className="w-4 h-4 text-green-500 mr-1" />
                <span>{correctCount} correct</span>
              </div>
              <div className="flex items-center">
                <XCircle className="w-4 h-4 text-red-500 mr-1" />
                <span>{results.length - correctCount} incorrect</span>
              </div>
            </div>
          </div>

          <div className="flex gap-2">
            <Button variant="outline" className="flex-1" onClick={handleRestart}>
              <RefreshCw className="w-4 h-4 mr-2" />
              Practice Again
            </Button>
            <Button className="flex-1">
              Continue Learning
            </Button>
          </div>
        </CardContent>
      </Card>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div className="text-sm text-muted-foreground">
          {currentIndex + 1} of {vocabularyItems.length}
        </div>
        <Tabs
          value={reviewMode}
          onValueChange={(value) => setReviewMode(value as 'translation' | 'word')}
          className="w-auto"
        >
          <TabsList className="grid w-[200px] grid-cols-2">
            <TabsTrigger value="translation">Word → Translation</TabsTrigger>
            <TabsTrigger value="word">Translation → Word</TabsTrigger>
          </TabsList>
        </Tabs>
      </div>

      <Progress value={progress} className="h-2" />

      {isSubmitting ? (
        <div className="flex justify-center items-center h-64">
          <LoadingSpinner />
        </div>
      ) : (
        <Flashcard
          item={currentItem}
          onAnswer={handleAnswer}
          onNext={handleNext}
          mode={reviewMode}
        />
      )}

      <div className="flex justify-between text-sm">
        <div className="flex items-center">
          <CheckCircle className="w-4 h-4 text-green-500 mr-1" />
          <span>{results.filter(r => r.correct).length} correct</span>
        </div>
        <div className="flex items-center">
          <XCircle className="w-4 h-4 text-red-500 mr-1" />
          <span>{results.filter(r => !r.correct).length} incorrect</span>
        </div>
      </div>
    </div>
  );
}
