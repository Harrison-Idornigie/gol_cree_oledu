'use client';

import { useState, useEffect } from 'react';
import { WordData } from '@/types/vocabulary';
import WordTooltip from './WordTooltip';

interface ClickableTextProps {
  text: string;
  wordMapping?: Record<string, number>;
  wordData: Record<string, WordData>;
  className?: string;
}

export default function ClickableText({
  text,
  wordMapping = {},
  wordData,
  className = '',
}: ClickableTextProps) {
  const [localWordData, setLocalWordData] = useState<Record<string, WordData>>(wordData);

  useEffect(() => {
    setLocalWordData(wordData);
  }, [wordData]);

  // Function to render text with clickable words
  const renderWithClickableWords = () => {
    // Split text into words
    const words = text.split(/\s+/);

    return (
      <>
        {words.map((word, index) => {
          // Clean the word from punctuation for lookup
          const cleanWord = word.replace(/[.,!?;:'"()]/g, "").toLowerCase();
          
          // Check if this word is in our mapping
          const wordId = wordMapping[cleanWord];
          
          // If we have a mapping and word data, make it clickable
          if (wordId && localWordData[cleanWord]) {
            return (
              <span key={index}>
                <WordTooltip word={localWordData[cleanWord]} />
                {index < words.length - 1 ? " " : ""}
              </span>
            );
          }
          
          // If we don't have a mapping but have word data by text, still make it clickable
          if (localWordData[cleanWord]) {
            return (
              <span key={index}>
                <WordTooltip word={localWordData[cleanWord]} />
                {index < words.length - 1 ? " " : ""}
              </span>
            );
          }

          // Otherwise, render as plain text
          return (
            <span key={index}>
              {word}
              {index < words.length - 1 ? " " : ""}
            </span>
          );
        })}
      </>
    );
  };

  return (
    <div className={className}>
      {renderWithClickableWords()}
    </div>
  );
}
