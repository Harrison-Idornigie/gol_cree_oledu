'use client';

import { useState, useRef, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Play, Pause, Volume2 } from 'lucide-react';

interface AudioPlayerProps {
  src: string;
  isPlaying: boolean;
  onPlayPause: () => void;
  className?: string;
}

export function AudioPlayer({ src, isPlaying, onPlayPause, className }: AudioPlayerProps) {
  const audioRef = useRef<HTMLAudioElement>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [hasError, setHasError] = useState(false);

  useEffect(() => {
    const audio = audioRef.current;
    if (!audio) return;

    if (isPlaying) {
      setIsLoading(true);
      audio.play().catch(() => {
        setHasError(true);
        setIsLoading(false);
      });
    } else {
      audio.pause();
      setIsLoading(false);
    }
  }, [isPlaying]);

  useEffect(() => {
    const audio = audioRef.current;
    if (!audio) return;

    const handleCanPlay = () => {
      setIsLoading(false);
      setHasError(false);
    };

    const handleEnded = () => {
      setIsLoading(false);
      onPlayPause(); // This will set isPlaying to false
    };

    const handleError = () => {
      setIsLoading(false);
      setHasError(true);
    };

    audio.addEventListener('canplay', handleCanPlay);
    audio.addEventListener('ended', handleEnded);
    audio.addEventListener('error', handleError);

    return () => {
      audio.removeEventListener('canplay', handleCanPlay);
      audio.removeEventListener('ended', handleEnded);
      audio.removeEventListener('error', handleError);
    };
  }, [onPlayPause]);

  if (hasError) {
    return (
      <div className={`flex items-center gap-1 text-muted-foreground ${className}`}>
        <Volume2 className="h-3 w-3" />
        <span className="text-xs">Error</span>
      </div>
    );
  }

  return (
    <div className={`flex items-center gap-1 ${className}`}>
      <audio ref={audioRef} src={src} preload="metadata" />
      <Button
        variant="ghost"
        size="sm"
        onClick={onPlayPause}
        disabled={isLoading}
        className="h-6 w-6 p-0"
      >
        {isLoading ? (
          <div className="h-3 w-3 animate-spin rounded-full border-2 border-current border-t-transparent" />
        ) : isPlaying ? (
          <Pause className="h-3 w-3" />
        ) : (
          <Play className="h-3 w-3" />
        )}
      </Button>
    </div>
  );
}