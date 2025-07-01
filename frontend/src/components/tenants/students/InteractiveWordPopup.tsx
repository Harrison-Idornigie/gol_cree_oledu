'use client';

import { useState, useEffect, useRef } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Volume2, X, BookOpen, MessageCircle } from 'lucide-react';
import { WordData } from '@/types/tenant/guidebook';

interface InteractiveWordPopupProps {
  word: WordData;
  position: { x: number; y: number };
  onClose: () => void;
  showTranslations?: boolean;
  targetLanguage?: string;
}

export default function InteractiveWordPopup({
  word,
  position,
  onClose,
  showTranslations = true,
  targetLanguage = 'en'
}: InteractiveWordPopupProps) {
  const [isPlaying, setIsPlaying] = useState(false);
  const popupRef = useRef<HTMLDivElement>(null);
  const audioRef = useRef<HTMLAudioElement | null>(null);

  // Position the popup
  useEffect(() => {
    if (popupRef.current) {
      const popup = popupRef.current;
      const rect = popup.getBoundingClientRect();
      const viewportWidth = window.innerWidth;
      const viewportHeight = window.innerHeight;

      // Calculate optimal position
      let left = position.x - rect.width / 2;
      let top = position.y - rect.height - 10; // 10px above the word

      // Adjust if popup goes off screen
      if (left < 10) left = 10;
      if (left + rect.width > viewportWidth - 10) {
        left = viewportWidth - rect.width - 10;
      }
      
      if (top < 10) {
        top = position.y + 30; // Show below the word instead
      }

      popup.style.left = `${left}px`;
      popup.style.top = `${top}px`;
    }
  }, [position]);

  // Close popup when clicking outside
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (popupRef.current && !popupRef.current.contains(event.target as Node)) {
        onClose();
      }
    };

    const handleEscape = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        onClose();
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    document.addEventListener('keydown', handleEscape);

    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
      document.removeEventListener('keydown', handleEscape);
    };
  }, [onClose]);

  // Play audio pronunciation
  const playAudio = async () => {
    if (!word.audio_url || isPlaying) return;

    setIsPlaying(true);
    try {
      if (audioRef.current) {
        audioRef.current.pause();
        audioRef.current.currentTime = 0;
      }

      audioRef.current = new Audio(word.audio_url);
      audioRef.current.onended = () => setIsPlaying(false);
      audioRef.current.onerror = () => setIsPlaying(false);
      
      await audioRef.current.play();
    } catch (error) {
      console.error('Failed to play audio:', error);
      setIsPlaying(false);
    }
  };

  // Get primary translation for target language
  const getPrimaryTranslation = () => {
    if (!word.translations || !showTranslations) return null;
    
    return word.translations.find(t => 
      t.language_code === targetLanguage || 
      t.language_code === 'en'
    ) || word.translations[0];
  };

  const primaryTranslation = getPrimaryTranslation();

  return (
    <div
      ref={popupRef}
      className="fixed z-50 w-80 max-w-sm"
      style={{ position: 'fixed' }}
    >
      <Card className="shadow-lg border-2 border-primary/20">
        <CardHeader className="pb-3">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <CardTitle className="text-lg font-bold text-primary">
                {word.text}
              </CardTitle>
              {word.part_of_speech && (
                <Badge variant="outline" className="text-xs">
                  {word.part_of_speech}
                </Badge>
              )}
            </div>
            <Button
              variant="ghost"
              size="sm"
              onClick={onClose}
              className="h-6 w-6 p-0"
            >
              <X className="h-4 w-4" />
            </Button>
          </div>
          
          {/* Pronunciation */}
          {word.pronunciation_key && (
            <div className="text-sm text-muted-foreground">
              /{word.pronunciation_key}/
            </div>
          )}
        </CardHeader>

        <CardContent className="space-y-4">
          {/* Primary Translation */}
          {primaryTranslation && (
            <div className="p-3 bg-primary/5 rounded-md">
              <div className="flex items-center gap-2 mb-1">
                <MessageCircle className="h-4 w-4 text-primary" />
                <span className="font-medium text-primary">Translation</span>
              </div>
              <p className="text-lg font-semibold">{primaryTranslation.text}</p>
              {primaryTranslation.context_notes && (
                <p className="text-sm text-muted-foreground mt-1">
                  {primaryTranslation.context_notes}
                </p>
              )}
            </div>
          )}

          {/* Audio Controls */}
          {word.audio_url && (
            <div className="flex items-center gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={playAudio}
                disabled={isPlaying}
                className="flex items-center gap-2"
              >
                <Volume2 className="h-4 w-4" />
                {isPlaying ? 'Playing...' : 'Pronunciation'}
              </Button>
            </div>
          )}

          {/* Additional Translations */}
          {showTranslations && word.translations && word.translations.length > 1 && (
            <div>
              <div className="flex items-center gap-2 mb-2">
                <BookOpen className="h-4 w-4 text-muted-foreground" />
                <span className="text-sm font-medium text-muted-foreground">
                  Other Meanings
                </span>
              </div>
              <div className="space-y-2">
                {word.translations
                  .filter(t => t.id !== primaryTranslation?.id)
                  .slice(0, 3) // Limit to 3 additional translations
                  .map((translation, index) => (
                    <div key={translation.id || index} className="text-sm">
                      <span className="font-medium">{translation.text}</span>
                      {translation.context_notes && (
                        <span className="text-muted-foreground ml-2">
                          ({translation.context_notes})
                        </span>
                      )}
                    </div>
                  ))}
              </div>
            </div>
          )}

          {/* Usage Examples */}
          {word.usage_examples && word.usage_examples.length > 0 && (
            <div>
              <div className="text-sm font-medium text-muted-foreground mb-2">
                Example Usage
              </div>
              <div className="space-y-1">
                {word.usage_examples.slice(0, 2).map((example, index) => (
                  <div key={index} className="text-sm italic text-muted-foreground">
                    "{example}"
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Difficulty Level */}
          {word.difficulty_level && (
            <div className="flex items-center gap-2">
              <span className="text-sm text-muted-foreground">Difficulty:</span>
              <Badge 
                variant={
                  word.difficulty_level === 'beginner' ? 'default' :
                  word.difficulty_level === 'intermediate' ? 'secondary' : 'destructive'
                }
                className="text-xs"
              >
                {word.difficulty_level}
              </Badge>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
