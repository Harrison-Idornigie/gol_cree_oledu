'use client';

import { useState, useEffect } from 'react';
import { WordData } from '@/types/tenant/guidebook';
import WordTooltip from './WordTooltip';
import InteractiveWordPopup from './InteractiveWordPopup';

interface ClickableTextProps {
  text: string;
  wordMapping?: Record<string, number>;
  wordData: Record<string, WordData>;
  className?: string;
  enablePopup?: boolean;
  showTranslations?: boolean;
  targetLanguage?: string;
}

export default function ClickableText({
  text,
  wordMapping = {},
  wordData,
  className = '',
  enablePopup = true,
  showTranslations = true,
  targetLanguage = 'en'
}: ClickableTextProps) {
  const [localWordData, setLocalWordData] = useState<Record<string, WordData>>(wordData);
  const [selectedWord, setSelectedWord] = useState<WordData | null>(null);
  const [popupPosition, setPopupPosition] = useState<{ x: number; y: number } | null>(null);

  useEffect(() => {
    setLocalWordData(wordData);
  }, [wordData]);

  // Handle word click
  const handleWordClick = (word: WordData, event: React.MouseEvent) => {
    if (!enablePopup) return;

    const rect = event.currentTarget.getBoundingClientRect();
    setPopupPosition({
      x: rect.left + rect.width / 2,
      y: rect.top
    });
    setSelectedWord(word);
  };

  // Close popup
  const closePopup = () => {
    setSelectedWord(null);
    setPopupPosition(null);
  };

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
                {enablePopup ? (
                  <span
                    className="cursor-pointer text-blue-600 hover:text-blue-800 hover:underline"
                    onClick={(e) => handleWordClick(localWordData[cleanWord], e)}
                  >
                    {word}
                  </span>
                ) : (
                  <WordTooltip word={localWordData[cleanWord]} />
                )}
                {index < words.length - 1 ? " " : ""}
              </span>
            );
          }

          // If we don't have a mapping but have word data by text, still make it clickable
          if (localWordData[cleanWord]) {
            return (
              <span key={index}>
                {enablePopup ? (
                  <span
                    className="cursor-pointer text-blue-600 hover:text-blue-800 hover:underline"
                    onClick={(e) => handleWordClick(localWordData[cleanWord], e)}
                  >
                    {word}
                  </span>
                ) : (
                  <WordTooltip word={localWordData[cleanWord]} />
                )}
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
    <>
      <div className={className}>
        {renderWithClickableWords()}
      </div>

      {/* Interactive Word Popup */}
      {selectedWord && popupPosition && enablePopup && (
        <InteractiveWordPopup
          word={selectedWord}
          position={popupPosition}
          onClose={closePopup}
          showTranslations={showTranslations}
          targetLanguage={targetLanguage}
        />
      )}
    </>
  );
}
