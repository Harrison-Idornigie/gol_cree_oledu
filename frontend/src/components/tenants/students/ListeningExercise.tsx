'use client';

import { useState, useEffect, useRef } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { CheckCircle, XCircle, Volume2, Loader2 } from 'lucide-react';
import { WordData } from '@/types/tenant/guidebook';
import { submitListeningTranscript } from '@/app/_actions/tenants/student/listening-actions';

interface ListeningExerciseProps {
  exercise: {
    id: number;
    type: 'listening';
    content: {
      audio_url: string;
      transcript: string;
      prompt: string;
      language: string;
      difficulty: string;
      word_mapping?: Record<string, number>;
    };
    answers?: {
      correct: string[];
      alternatives?: string[];
    };
  };
  onAnswer: (isCorrect: boolean) => void;
  onNext: () => void;
  wordData: Record<string, WordData>;
}

export default function ListeningExercise({
  exercise,
  onAnswer,
  onNext,
  wordData,
}: ListeningExerciseProps) {
  const [userTranscript, setUserTranscript] = useState('');
  const [isSubmitted, setIsSubmitted] = useState(false);
  const [isCorrect, setIsCorrect] = useState(false);
  const [feedback, setFeedback] = useState('');
  const [hint, setHint] = useState<string | null>(null);
  const [isPlaying, setIsPlaying] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [playbackSpeed, setPlaybackSpeed] = useState(1);
  const [attemptNumber, setAttemptNumber] = useState(0);
  
  const audioRef = useRef<HTMLAudioElement | null>(null);
  
  useEffect(() => {
    // Create audio element
    audioRef.current = new Audio(exercise.content.audio_url);
    
    // Cleanup on unmount
    return () => {
      if (audioRef.current) {
        audioRef.current.pause();
        audioRef.current = null;
      }
    };
  }, [exercise.content.audio_url]);
  
  const playAudio = () => {
    if (isPlaying || !audioRef.current) return;
    
    setIsPlaying(true);
    
    if (audioRef.current) {
      audioRef.current.playbackRate = playbackSpeed;
      audioRef.current.onended = () => setIsPlaying(false);
      audioRef.current.play().catch((error) => {
        console.error('Error playing audio:', error);
        setIsPlaying(false);
      });
    }
  };
  
  const handleSubmit = async () => {
    if (!userTranscript.trim()) return;
    
    setIsLoading(true);
    
    const response = await submitListeningTranscript(
      exercise.id,
      userTranscript
    );
    
    setIsLoading(false);
    
    if (response.success && response.data) {
      const { is_correct, feedback, hint, attempt_number } = response.data;
      
      setIsCorrect(is_correct);
      setFeedback(feedback);
      setHint(hint);
      setAttemptNumber(attempt_number);
      setIsSubmitted(true);
      
      onAnswer(is_correct);
    } else {
      setFeedback('There was an error checking your answer. Please try again.');
    }
  };
  
  const handleNext = () => {
    setUserTranscript('');
    setIsSubmitted(false);
    setIsCorrect(false);
    setFeedback('');
    setHint(null);
    
    onNext();
  };
  
  const handleTryAgain = () => {
    setIsSubmitted(false);
    // Keep the user's transcript for them to edit
  };
  
  const changePlaybackSpeed = () => {
    // Cycle through speeds: 1 -> 0.75 -> 0.5 -> 1
    const newSpeed = playbackSpeed === 1 ? 0.75 : playbackSpeed === 0.75 ? 0.5 : 1;
    setPlaybackSpeed(newSpeed);
    
    if (audioRef.current) {
      audioRef.current.playbackRate = newSpeed;
    }
  };
  
  return (
    <Card className="w-full">
      <CardContent className="p-6">
        <div className="space-y-6">
          {/* Exercise Prompt */}
          <div className="text-center">
            <h2 className="text-2xl font-bold">{exercise.content.prompt}</h2>
            <p className="text-muted-foreground">
              Difficulty: {exercise.content.difficulty.charAt(0).toUpperCase() + exercise.content.difficulty.slice(1)}
            </p>
          </div>
          
          {/* Audio Controls */}
          <div className="flex justify-center space-x-4">
            <Button
              onClick={playAudio}
              disabled={isPlaying}
              size="lg"
              className="flex items-center space-x-2"
            >
              {isPlaying ? (
                <Loader2 className="w-5 h-5 animate-spin" />
              ) : (
                <Volume2 className="w-5 h-5" />
              )}
              <span>Listen</span>
            </Button>
            
            <Button
              onClick={changePlaybackSpeed}
              variant="outline"
              size="lg"
              className="flex items-center space-x-2"
            >
              <span>Speed: {playbackSpeed}x</span>
            </Button>
          </div>
          
          {/* User Input */}
          <div className="space-y-4">
            <Input
              value={userTranscript}
              onChange={(e) => setUserTranscript(e.target.value)}
              placeholder="Type what you hear..."
              disabled={isSubmitted || isLoading}
              className="w-full p-4 text-lg"
              onKeyDown={(e) => {
                if (e.key === 'Enter' && !isSubmitted && !isLoading) {
                  handleSubmit();
                }
              }}
            />
            
            {hint && (
              <div className="text-sm text-muted-foreground">
                <span className="font-medium">Hint:</span> {hint}
              </div>
            )}
            
            {isSubmitted && (
              <div className={`flex items-center ${isCorrect ? 'text-green-600' : 'text-red-600'}`}>
                {isCorrect ? (
                  <>
                    <CheckCircle className="w-5 h-5 mr-2" />
                    <span>{feedback}</span>
                  </>
                ) : (
                  <>
                    <XCircle className="w-5 h-5 mr-2" />
                    <span>{feedback}</span>
                  </>
                )}
              </div>
            )}
          </div>
          
          {/* Action Buttons */}
          <div className="flex justify-between">
            {isSubmitted ? (
              <>
                {!isCorrect && attemptNumber < 3 ? (
                  <Button variant="outline" onClick={handleTryAgain}>
                    Try Again
                  </Button>
                ) : (
                  <div></div> // Empty div for spacing
                )}
                <Button onClick={handleNext}>
                  {isCorrect ? 'Continue' : 'Skip'}
                </Button>
              </>
            ) : (
              <>
                <Button
                  variant="outline"
                  onClick={playAudio}
                  disabled={isPlaying}
                >
                  Replay
                </Button>
                <Button onClick={handleSubmit} disabled={isLoading}>
                  {isLoading ? (
                    <>
                      <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                      Checking...
                    </>
                  ) : (
                    'Check'
                  )}
                </Button>
              </>
            )}
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
