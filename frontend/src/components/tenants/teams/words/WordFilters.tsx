'use client';

import { useState } from 'react';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { X, RotateCcw } from 'lucide-react';

interface Language {
  id: number;
  code: string;
  name: string;
  native_name?: string;
}

interface WordFiltersState {
  search: string;
  language_id?: number;
  difficulty?: string;
  part_of_speech?: string;
  has_audio?: boolean;
  tags?: string[];
}

interface WordFiltersProps {
  filters: WordFiltersState;
  languages: Language[];
  onFiltersChange: (filters: Partial<WordFiltersState>) => void;
}

const difficultyOptions = [
  { value: 'beginner', label: 'Beginner' },
  { value: 'intermediate', label: 'Intermediate' },
  { value: 'advanced', label: 'Advanced' },
];

const partOfSpeechOptions = [
  { value: 'noun', label: 'Noun' },
  { value: 'verb', label: 'Verb' },
  { value: 'adjective', label: 'Adjective' },
  { value: 'adverb', label: 'Adverb' },
  { value: 'pronoun', label: 'Pronoun' },
  { value: 'preposition', label: 'Preposition' },
  { value: 'conjunction', label: 'Conjunction' },
  { value: 'interjection', label: 'Interjection' },
  { value: 'determiner', label: 'Determiner' },
  { value: 'article', label: 'Article' },
];

export function WordFilters({ filters, languages, onFiltersChange }: WordFiltersProps) {
  const [newTag, setNewTag] = useState('');

  // Handle adding a new tag
  const addTag = () => {
    if (newTag.trim() && !filters.tags?.includes(newTag.trim())) {
      const updatedTags = [...(filters.tags || []), newTag.trim()];
      onFiltersChange({ tags: updatedTags });
      setNewTag('');
    }
  };

  // Handle removing a tag
  const removeTag = (tagToRemove: string) => {
    const updatedTags = filters.tags?.filter(tag => tag !== tagToRemove) || [];
    onFiltersChange({ tags: updatedTags });
  };

  // Handle Enter key press for adding tags
  const handleTagKeyPress = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      addTag();
    }
  };

  // Reset all filters
  const resetFilters = () => {
    onFiltersChange({
      search: '',
      language_id: undefined,
      difficulty: undefined,
      part_of_speech: undefined,
      has_audio: undefined,
      tags: [],
    });
  };

  // Check if any filters are active
  const hasActiveFilters = Boolean(
    filters.language_id ||
    filters.difficulty ||
    filters.part_of_speech ||
    filters.has_audio !== undefined ||
    (filters.tags && filters.tags.length > 0)
  );

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Language Filter */}
        <div className="space-y-2">
          <Label>Language</Label>
          <Select
            value={filters.language_id?.toString() || 'all'}
            onValueChange={(value) =>
              onFiltersChange({ language_id: value === 'all' ? undefined : parseInt(value) })
            }
          >
            <SelectTrigger>
              <SelectValue placeholder="All languages" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All languages</SelectItem>
              {languages.map((language) => (
                <SelectItem key={language.id} value={language.id.toString()}>
                  {language.name} ({language.code})
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {/* Difficulty Filter */}
        <div className="space-y-2">
          <Label>Difficulty</Label>
          <Select
            value={filters.difficulty || 'all'}
            onValueChange={(value) =>
              onFiltersChange({ difficulty: value === 'all' ? undefined : value })
            }
          >
            <SelectTrigger>
              <SelectValue placeholder="All levels" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All levels</SelectItem>
              {difficultyOptions.map((option) => (
                <SelectItem key={option.value} value={option.value}>
                  {option.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {/* Part of Speech Filter */}
        <div className="space-y-2">
          <Label>Part of Speech</Label>
          <Select
            value={filters.part_of_speech || 'all'}
            onValueChange={(value) =>
              onFiltersChange({ part_of_speech: value === 'all' ? undefined : value })
            }
          >
            <SelectTrigger>
              <SelectValue placeholder="All types" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All types</SelectItem>
              {partOfSpeechOptions.map((option) => (
                <SelectItem key={option.value} value={option.value}>
                  {option.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        {/* Audio Filter */}
        <div className="space-y-2">
          <Label>Audio Available</Label>
          <div className="flex items-center space-x-2 h-10">
            <Switch
              checked={filters.has_audio === true}
              onCheckedChange={(checked) => 
                onFiltersChange({ has_audio: checked ? true : undefined })
              }
            />
            <span className="text-sm text-muted-foreground">
              {filters.has_audio === true ? 'With audio only' : 'All words'}
            </span>
          </div>
        </div>
      </div>

      {/* Tags Section */}
      <div className="space-y-3">
        <Label>Tags</Label>
        
        {/* Add Tag Input */}
        <div className="flex gap-2">
          <Input
            placeholder="Add a tag..."
            value={newTag}
            onChange={(e) => setNewTag(e.target.value)}
            onKeyPress={handleTagKeyPress}
            className="flex-1"
          />
          <Button
            type="button"
            onClick={addTag}
            variant="outline"
            disabled={!newTag.trim()}
          >
            Add Tag
          </Button>
        </div>

        {/* Display Tags */}
        {filters.tags && filters.tags.length > 0 && (
          <div className="flex flex-wrap gap-2">
            {filters.tags.map((tag) => (
              <Badge key={tag} variant="secondary" className="flex items-center gap-1">
                {tag}
                <button
                  onClick={() => removeTag(tag)}
                  className="ml-1 hover:text-red-600"
                >
                  <X className="h-3 w-3" />
                </button>
              </Badge>
            ))}
          </div>
        )}
      </div>

      {/* Reset Filters */}
      {hasActiveFilters && (
        <div className="flex justify-end">
          <Button
            type="button"
            variant="ghost"
            onClick={resetFilters}
            className="text-muted-foreground"
          >
            <RotateCcw className="mr-2 h-4 w-4" />
            Reset Filters
          </Button>
        </div>
      )}

      {/* Active Filters Summary */}
      {hasActiveFilters && (
        <div className="space-y-2">
          <Label className="text-sm text-muted-foreground">Active Filters:</Label>
          <div className="flex flex-wrap gap-2">
            {filters.language_id && (
              <Badge variant="outline">
                Language: {languages.find(l => l.id === filters.language_id)?.name}
                <button
                  onClick={() => onFiltersChange({ language_id: undefined })}
                  className="ml-1 hover:text-red-600"
                >
                  <X className="h-3 w-3" />
                </button>
              </Badge>
            )}
            {filters.difficulty && (
              <Badge variant="outline">
                Difficulty: {difficultyOptions.find(d => d.value === filters.difficulty)?.label}
                <button
                  onClick={() => onFiltersChange({ difficulty: undefined })}
                  className="ml-1 hover:text-red-600"
                >
                  <X className="h-3 w-3" />
                </button>
              </Badge>
            )}
            {filters.part_of_speech && (
              <Badge variant="outline">
                Part of Speech: {partOfSpeechOptions.find(p => p.value === filters.part_of_speech)?.label}
                <button
                  onClick={() => onFiltersChange({ part_of_speech: undefined })}
                  className="ml-1 hover:text-red-600"
                >
                  <X className="h-3 w-3" />
                </button>
              </Badge>
            )}
            {filters.has_audio === true && (
              <Badge variant="outline">
                With Audio
                <button
                  onClick={() => onFiltersChange({ has_audio: undefined })}
                  className="ml-1 hover:text-red-600"
                >
                  <X className="h-3 w-3" />
                </button>
              </Badge>
            )}
          </div>
        </div>
      )}
    </div>
  );
}