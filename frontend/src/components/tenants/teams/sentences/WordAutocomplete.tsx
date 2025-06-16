'use client';

import { useState, useEffect, useRef } from 'react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { Search, Volume2, AlertTriangle, CheckCircle } from 'lucide-react';
import { cn } from '@/lib/utils';

interface Word {
  id: string | number;
  text: string;
  type: 'word' | 'exception';
  exception_type?: string;
  description?: string;
  part_of_speech?: string;
  translations?: Array<{
    id: number;
    text: string;
    language_code: string;
  }>;
}

interface WordAutocompleteProps {
  languageId: string | number;
  onWordSelect: (word: Word) => void;
  placeholder?: string;
  className?: string;
  disabled?: boolean;
  showValidationStatus?: boolean;
}

export default function WordAutocomplete({
  languageId,
  onWordSelect,
  placeholder = "Search for words...",
  className,
  disabled = false,
  showValidationStatus = true
}: WordAutocompleteProps) {
  const [search, setSearch] = useState('');
  const [words, setWords] = useState<Word[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [isOpen, setIsOpen] = useState(false);
  const [selectedIndex, setSelectedIndex] = useState(-1);
  const [error, setError] = useState<string | null>(null);
  
  const inputRef = useRef<HTMLInputElement>(null);
  const dropdownRef = useRef<HTMLDivElement>(null);
  const searchTimeoutRef = useRef<NodeJS.Timeout>();

  // Fetch words based on search
  useEffect(() => {
    if (!languageId || search.length < 1) {
      setWords([]);
      setIsOpen(false);
      return;
    }

    // Clear previous timeout
    if (searchTimeoutRef.current) {
      clearTimeout(searchTimeoutRef.current);
    }

    // Debounce search
    searchTimeoutRef.current = setTimeout(async () => {
      setIsLoading(true);
      setError(null);
      
      try {
        const response = await fetch(
          `/api/team/sentences/available-words?language_id=${languageId}&search=${encodeURIComponent(search)}&limit=20`
        );
        
        if (!response.ok) {
          throw new Error('Failed to fetch words');
        }
        
        const data = await response.json();
        
        if (data.success) {
          setWords(data.data.words || []);
          setIsOpen(true);
          setSelectedIndex(-1);
        } else {
          setError(data.message || 'Failed to fetch words');
        }
      } catch (err) {
        setError('Failed to fetch words');
        console.error('Word fetch error:', err);
      } finally {
        setIsLoading(false);
      }
    }, 300);

    return () => {
      if (searchTimeoutRef.current) {
        clearTimeout(searchTimeoutRef.current);
      }
    };
  }, [search, languageId]);

  // Handle keyboard navigation
  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (!isOpen || words.length === 0) return;

    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        setSelectedIndex(prev => 
          prev < words.length - 1 ? prev + 1 : 0
        );
        break;
      case 'ArrowUp':
        e.preventDefault();
        setSelectedIndex(prev => 
          prev > 0 ? prev - 1 : words.length - 1
        );
        break;
      case 'Enter':
        e.preventDefault();
        if (selectedIndex >= 0 && selectedIndex < words.length) {
          handleWordSelect(words[selectedIndex]);
        }
        break;
      case 'Escape':
        setIsOpen(false);
        setSelectedIndex(-1);
        break;
    }
  };

  // Handle word selection
  const handleWordSelect = (word: Word) => {
    onWordSelect(word);
    setSearch('');
    setIsOpen(false);
    setSelectedIndex(-1);
    inputRef.current?.focus();
  };

  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (
        dropdownRef.current &&
        !dropdownRef.current.contains(event.target as Node) &&
        !inputRef.current?.contains(event.target as Node)
      ) {
        setIsOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Get word validation status
  const getWordValidationIcon = (word: Word) => {
    if (word.type === 'exception') {
      return <AlertTriangle className="h-3 w-3 text-amber-500" />;
    }
    return <CheckCircle className="h-3 w-3 text-green-500" />;
  };

  // Get word type badge
  const getWordTypeBadge = (word: Word) => {
    if (word.type === 'exception') {
      return (
        <Badge variant="secondary" className="text-xs">
          {word.exception_type || 'Exception'}
        </Badge>
      );
    }
    
    if (word.part_of_speech) {
      return (
        <Badge variant="outline" className="text-xs">
          {word.part_of_speech}
        </Badge>
      );
    }
    
    return null;
  };

  return (
    <div className={cn("relative", className)}>
      {/* Search Input */}
      <div className="relative">
        <Search className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
        <Input
          ref={inputRef}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          onKeyDown={handleKeyDown}
          placeholder={placeholder}
          disabled={disabled}
          className="pl-10"
        />
        {isLoading && (
          <div className="absolute right-3 top-3">
            <div className="h-4 w-4 animate-spin rounded-full border-2 border-primary border-t-transparent" />
          </div>
        )}
      </div>

      {/* Error Message */}
      {error && (
        <div className="mt-1 text-sm text-red-600">
          {error}
        </div>
      )}

      {/* Dropdown */}
      {isOpen && (
        <Card 
          ref={dropdownRef}
          className="absolute z-50 mt-1 w-full max-h-60 overflow-y-auto border shadow-lg"
        >
          {words.length > 0 ? (
            <div className="p-1">
              {words.map((word, index) => (
                <Button
                  key={word.id}
                  variant="ghost"
                  className={cn(
                    "w-full justify-start text-left h-auto p-3",
                    selectedIndex === index && "bg-accent"
                  )}
                  onClick={() => handleWordSelect(word)}
                >
                  <div className="flex items-center justify-between w-full">
                    <div className="flex items-center gap-2">
                      {showValidationStatus && getWordValidationIcon(word)}
                      <span className="font-medium">{word.text}</span>
                      {getWordTypeBadge(word)}
                    </div>
                    
                    {/* Translations preview */}
                    {word.translations && word.translations.length > 0 && (
                      <div className="text-xs text-muted-foreground">
                        {word.translations[0].text}
                      </div>
                    )}
                  </div>
                  
                  {/* Description for exception words */}
                  {word.type === 'exception' && word.description && (
                    <div className="text-xs text-muted-foreground mt-1 w-full text-left">
                      {word.description}
                    </div>
                  )}
                </Button>
              ))}
            </div>
          ) : search.length > 0 && !isLoading ? (
            <div className="p-4 text-center text-muted-foreground text-sm">
              No words found for "{search}"
            </div>
          ) : null}
        </Card>
      )}
    </div>
  );
}
