'use client';

import { useState, useRef, useEffect } from 'react';
import { Volume2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { WordData } from '@/types/vocabulary';

interface WordTooltipProps {
  word: WordData;
  className?: string;
}

export default function WordTooltip({ word, className = '' }: WordTooltipProps) {
  const [isPlaying, setIsPlaying] = useState(false);
  const [showTooltip, setShowTooltip] = useState(false);
  const audioRef = useRef<HTMLAudioElement | null>(null);
  const tooltipRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (word.audioUrl) {
      audioRef.current = new Audio(word.audioUrl);
      audioRef.current.onended = () => setIsPlaying(false);
    }

    return () => {
      if (audioRef.current) {
        audioRef.current.pause();
        audioRef.current = null;
      }
    };
  }, [word.audioUrl]);

  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (tooltipRef.current && !tooltipRef.current.contains(event.target as Node)) {
        setShowTooltip(false);
      }
    }

    document.addEventListener('mousedown', handleClickOutside);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, []);

  const playAudio = (e: React.MouseEvent) => {
    e.stopPropagation();
    if (!audioRef.current || isPlaying) return;
    
    setIsPlaying(true);
    audioRef.current.play().catch(() => {
      setIsPlaying(false);
    });
  };

  const toggleTooltip = () => {
    setShowTooltip(!showTooltip);
    if (!showTooltip && word.audioUrl) {
      playAudio({ stopPropagation: () => {} } as React.MouseEvent);
    }
  };

  return (
    <div className="relative inline-block">
      <span 
        className={`cursor-pointer text-primary hover:underline ${className}`}
        onClick={toggleTooltip}
      >
        {word.text}
      </span>
      
      {showTooltip && (
        <div 
          ref={tooltipRef}
          className="absolute z-50 bottom-full left-1/2 transform -translate-x-1/2 -translate-y-2 bg-white dark:bg-gray-800 rounded-md shadow-lg border border-border w-64 p-3 space-y-2"
        >
          <div className="flex items-center justify-between">
            <div>
              <span className="font-bold">{word.text}</span>
              {word.phonetic && (
                <span className="ml-2 text-xs text-muted-foreground">/{word.phonetic}/</span>
              )}
            </div>
            {word.audioUrl && (
              <Button 
                variant="ghost" 
                size="sm" 
                className="h-6 w-6 p-0" 
                onClick={playAudio}
                disabled={isPlaying}
              >
                <Volume2 className="h-4 w-4" />
              </Button>
            )}
          </div>
          
          {word.translation && (
            <div className="text-sm">
              {word.partOfSpeech && (
                <span className="text-xs text-muted-foreground italic mr-1">
                  {word.partOfSpeech}
                </span>
              )}
              {word.translation}
            </div>
          )}
          
          {word.example && (
            <div className="text-xs text-muted-foreground italic border-t pt-1">
              "{word.example}"
            </div>
          )}
          
          {/* Arrow */}
          <div className="absolute bottom-0 left-1/2 transform -translate-x-1/2 translate-y-1/2 rotate-45 w-2 h-2 bg-white dark:bg-gray-800 border-r border-b border-border"></div>
        </div>
      )}
    </div>
  );
}
