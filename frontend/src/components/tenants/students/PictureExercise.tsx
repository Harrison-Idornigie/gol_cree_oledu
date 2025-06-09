'use client';

import { useState } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { CheckCircle, XCircle, Loader2 } from 'lucide-react';
import { WordData } from '@/types/tenant/vocabulary';
import { submitPictureAnswer } from '@/app/_actions/tenants/student/picture-actions';
import Image from 'next/image';
import { cn } from '@/lib/utils';

interface PictureExerciseProps {
  exercise: {
    id: number;
    type: 'picture';
    content: {
      question: string;
      mode: 'word_to_image' | 'image_to_word';
      images?: Array<{ url: string; alt: string }>;
      words?: string[];
      target_image?: string;
      language: string;
      word_mapping?: Record<string, number>;
    };
    answers?: {
      correct: number;
    };
  };
  onAnswer: (isCorrect: boolean) => void;
  onNext: () => void;
  wordData: Record<string, WordData>;
}

export default function PictureExercise({
  exercise,
  onAnswer,
  onNext,
  wordData,
}: PictureExerciseProps) {
  const [selectedOption, setSelectedOption] = useState<number | null>(null);
  const [isSubmitted, setIsSubmitted] = useState(false);
  const [isCorrect, setIsCorrect] = useState(false);
  const [feedback, setFeedback] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  
  const handleOptionSelect = (index: number) => {
    if (isSubmitted) return;
    setSelectedOption(index);
  };
  
  const handleSubmit = async () => {
    if (selectedOption === null) return;
    
    setIsLoading(true);
    
    const response = await submitPictureAnswer(
      exercise.id,
      selectedOption
    );
    
    setIsLoading(false);
    
    if (response.success && response.data) {
      const { is_correct, feedback } = response.data;
      
      setIsCorrect(is_correct);
      setFeedback(feedback);
      setIsSubmitted(true);
      
      onAnswer(is_correct);
    } else {
      setFeedback('There was an error checking your answer. Please try again.');
    }
  };
  
  const handleNext = () => {
    setSelectedOption(null);
    setIsSubmitted(false);
    setIsCorrect(false);
    setFeedback('');
    
    onNext();
  };
  
  const renderWordToImageExercise = () => {
    if (!exercise.content.images || exercise.content.images.length === 0) {
      return <div>No images available for this exercise.</div>;
    }
    
    return (
      <div className="space-y-6">
        <div className="grid grid-cols-2 gap-4">
          {exercise.content.images.map((image, index) => (
            <div
              key={index}
              className={cn(
                "relative border-2 rounded-lg overflow-hidden cursor-pointer transition-all",
                selectedOption === index && !isSubmitted && "border-primary",
                isSubmitted && selectedOption === index && isCorrect && "border-green-500",
                isSubmitted && selectedOption === index && !isCorrect && "border-red-500",
                isSubmitted && exercise.answers?.correct === index && !isCorrect && "border-green-500"
              )}
              onClick={() => handleOptionSelect(index)}
            >
              <div className="relative aspect-square">
                <Image
                  src={image.url}
                  alt={image.alt}
                  fill
                  className="object-cover"
                />
              </div>
              {isSubmitted && selectedOption === index && (
                <div className="absolute top-2 right-2">
                  {isCorrect ? (
                    <CheckCircle className="h-6 w-6 text-green-500" />
                  ) : (
                    <XCircle className="h-6 w-6 text-red-500" />
                  )}
                </div>
              )}
              {isSubmitted && exercise.answers?.correct === index && !isCorrect && (
                <div className="absolute top-2 right-2">
                  <CheckCircle className="h-6 w-6 text-green-500" />
                </div>
              )}
            </div>
          ))}
        </div>
      </div>
    );
  };
  
  const renderImageToWordExercise = () => {
    if (!exercise.content.target_image || !exercise.content.words || exercise.content.words.length === 0) {
      return <div>No data available for this exercise.</div>;
    }
    
    return (
      <div className="space-y-6">
        <div className="relative mx-auto w-full max-w-xs aspect-square">
          <Image
            src={exercise.content.target_image}
            alt="Target image"
            fill
            className="object-cover rounded-lg"
          />
        </div>
        
        <div className="grid grid-cols-2 gap-4">
          {exercise.content.words.map((word, index) => (
            <Button
              key={index}
              variant={selectedOption === index ? "default" : "outline"}
              className={cn(
                "h-auto py-3 px-4 text-lg",
                isSubmitted && selectedOption === index && isCorrect && "bg-green-500 hover:bg-green-600",
                isSubmitted && selectedOption === index && !isCorrect && "bg-red-500 hover:bg-red-600",
                isSubmitted && exercise.answers?.correct === index && !isCorrect && "bg-green-500 hover:bg-green-600"
              )}
              onClick={() => handleOptionSelect(index)}
              disabled={isSubmitted}
            >
              {word}
            </Button>
          ))}
        </div>
      </div>
    );
  };
  
  return (
    <Card className="w-full">
      <CardContent className="p-6">
        <div className="space-y-6">
          {/* Exercise Question */}
          <div className="text-center">
            <h2 className="text-2xl font-bold">{exercise.content.question}</h2>
          </div>
          
          {/* Exercise Content */}
          {exercise.content.mode === 'word_to_image'
            ? renderWordToImageExercise()
            : renderImageToWordExercise()
          }
          
          {/* Feedback */}
          {isSubmitted && (
            <div className={`flex items-center justify-center ${isCorrect ? 'text-green-600' : 'text-red-600'}`}>
              {isCorrect ? (
                <>
                  <CheckCircle className="mr-2 h-5 w-5" />
                  <span>{feedback}</span>
                </>
              ) : (
                <>
                  <XCircle className="mr-2 h-5 w-5" />
                  <span>{feedback}</span>
                </>
              )}
            </div>
          )}
          
          {/* Action Buttons */}
          <div className="flex justify-center">
            {isSubmitted ? (
              <Button onClick={handleNext} size="lg">
                Continue
              </Button>
            ) : (
              <Button 
                onClick={handleSubmit} 
                disabled={selectedOption === null || isLoading}
                size="lg"
              >
                {isLoading ? (
                  <>
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    Checking...
                  </>
                ) : (
                  'Check'
                )}
              </Button>
            )}
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
