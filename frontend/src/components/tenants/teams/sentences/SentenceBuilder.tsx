'use client';

import { useState, useEffect, useCallback } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { AlertCircle, CheckCircle, X, Search, Wand2, Lightbulb } from 'lucide-react';
import { toast } from 'sonner';

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

interface Language {
  id: number;
  name: string;
  code: string;
}

interface SentenceBuilderProps {
  languages: Language[];
  onSave: (sentenceData: FormData) => Promise<void>;
  onCancel: () => void;
  initialData?: {
    language_id?: string | number;
    text?: string;
    pronunciation_key?: string;
    metadata?: {
      difficulty?: string;
      tags?: string[];
      notes?: string;
    };
  };
  isLoading?: boolean;
}

interface SelectedWord {
  word: Word;
  position: number;
}

export default function SentenceBuilder({
  languages,
  onSave,
  onCancel,
  initialData,
  isLoading: externalLoading = false
}: SentenceBuilderProps) {
  const [formData, setFormData] = useState({
    language_id: initialData?.language_id || '',
    text: initialData?.text || '',
    pronunciation_key: initialData?.pronunciation_key || '',
    difficulty: initialData?.metadata?.difficulty || 'beginner',
    tags: initialData?.metadata?.tags || [],
    notes: initialData?.metadata?.notes || ''
  });

  const [selectedWords, setSelectedWords] = useState<SelectedWord[]>([]);
  const [availableWords, setAvailableWords] = useState<Word[]>([]);
  const [wordSearch, setWordSearch] = useState('');
  const [isLoadingWords, setIsLoadingWords] = useState(false);
  const [validation, setValidation] = useState<{
    valid: boolean;
    errors: string[];
    warnings: string[];
    suggestions: string[];
  } | null>(null);
  const [isValidating, setIsValidating] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [aiSuggestions, setAiSuggestions] = useState<string[]>([]);
  const [showSuggestions, setShowSuggestions] = useState(false);

  // Debounced word search
  const debouncedWordSearch = useCallback(async (search: string, languageId: string) => {
    if (!languageId) return;

    setIsLoadingWords(true);
    try {
      const response = await fetch(`/api/team/sentences/available-words?language_id=${languageId}&search=${encodeURIComponent(search)}&limit=50`);
      const data = await response.json();

      if (data.success) {
        setAvailableWords(data.data.words || []);
      }
    } catch (error) {
      console.error('Failed to fetch words:', error);
    } finally {
      setIsLoadingWords(false);
    }
  }, []);

  // Load available words when language or search changes
  useEffect(() => {
    if (formData.language_id) {
      const timeoutId = setTimeout(() => {
        debouncedWordSearch(wordSearch, formData.language_id);
      }, 300);

      return () => clearTimeout(timeoutId);
    }
  }, [wordSearch, formData.language_id, debouncedWordSearch]);

  const validateSentence = useCallback(async () => {
    setIsValidating(true);
    try {
      const response = await fetch('/api/team/sentences/validate-words', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          text: formData.text,
          language_id: formData.language_id,
          words: selectedWords.map(sw => ({
            word_id: sw.word.id,
            position: sw.position
          }))
        })
      });

      const data = await response.json();
      setValidation(data.data);
    } catch (error) {
      console.error('Validation failed:', error);
    } finally {
      setIsValidating(false);
    }
  }, [formData.text, formData.language_id, selectedWords]);

  // Validate sentence when text or words change
  useEffect(() => {
    if (formData.text && formData.language_id && selectedWords.length > 0) {
      validateSentence();
    }
  }, [formData.text, selectedWords, formData.language_id, validateSentence]);

  const addWordToSentence = (word: Word) => {
    const position = selectedWords.length;
    setSelectedWords(prev => [...prev, { word, position }]);
    
    // Update sentence text
    const newText = selectedWords.map(sw => sw.word.text).concat(word.text).join(' ');
    setFormData(prev => ({ ...prev, text: newText }));
  };

  const removeWordFromSentence = (index: number) => {
    const newSelectedWords = selectedWords.filter((_, i) => i !== index);
    setSelectedWords(newSelectedWords);
    
    // Update sentence text
    const newText = newSelectedWords.map(sw => sw.word.text).join(' ');
    setFormData(prev => ({ ...prev, text: newText }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!validation?.valid) {
      toast.error('Please fix validation errors before saving');
      return;
    }

    setIsSaving(true);
    try {
      const submitData = new FormData();
      
      // Add sentence data
      submitData.append('language_id', formData.language_id);
      submitData.append('text', formData.text);
      if (formData.pronunciation_key) {
        submitData.append('pronunciation_key', formData.pronunciation_key);
      }
      
      // Add metadata
      const metadata = {
        difficulty: formData.difficulty,
        tags: formData.tags,
        notes: formData.notes
      };
      submitData.append('metadata', JSON.stringify(metadata));
      
      // Add words
      const words = selectedWords.map(sw => ({
        word_id: sw.word.id,
        position: sw.position
      }));
      submitData.append('words', JSON.stringify(words));
      
      // Add audio files
      if (audioFile) {
        submitData.append('audio', audioFile);
      }
      if (slowAudioFile) {
        submitData.append('audio_slow', slowAudioFile);
      }

      await onSave(submitData);
      toast.success('Sentence created successfully');
    } catch (error) {
      toast.error('Failed to save sentence');
      console.error('Save error:', error);
    } finally {
      setIsSaving(false);
    }
  };

  // Add this new function to generate sentence suggestions
  const generateSentenceSuggestions = async () => {
    if (!formData.language_id || selectedWords.length === 0) return;
    
    setShowSuggestions(true);
    // Mock AI suggestions - in production, call your AI service
    const wordTexts = selectedWords.map(sw => sw.word.text);
    const suggestions = [
      wordTexts.join(' ') + '.',
      wordTexts.reverse().join(' ') + '?',
      `${wordTexts[0]} ${wordTexts.slice(1).join(' ')}.`
    ];
    setAiSuggestions(suggestions);
  };

  // Add this function to apply a suggestion
  const applySuggestion = (suggestion: string) => {
    setFormData(prev => ({ ...prev, text: suggestion }));
    setShowSuggestions(false);
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      {/* Language Selection */}
      <Card>
        <CardHeader>
          <CardTitle>Basic Information</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="language_id">Language</Label>
              <Select
                value={formData.language_id.toString()}
                onValueChange={(value) => setFormData(prev => ({ ...prev, language_id: value }))}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Select language" />
                </SelectTrigger>
                <SelectContent>
                  {languages.map((language) => (
                    <SelectItem key={language.id} value={language.id.toString()}>
                      {language.name} ({language.code})
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-2">
              <Label htmlFor="difficulty">Difficulty Level</Label>
              <Select
                value={formData.difficulty}
                onValueChange={(value) => setFormData(prev => ({ ...prev, difficulty: value }))}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="beginner">Beginner</SelectItem>
                  <SelectItem value="intermediate">Intermediate</SelectItem>
                  <SelectItem value="advanced">Advanced</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <div className="space-y-2">
            <Label htmlFor="pronunciation_key">Pronunciation (IPA)</Label>
            <Input
              id="pronunciation_key"
              value={formData.pronunciation_key}
              onChange={(e) => setFormData(prev => ({ ...prev, pronunciation_key: e.target.value }))}
              placeholder="Optional pronunciation guide"
            />
          </div>
        </CardContent>
      </Card>

      {/* Word Selection */}
      {formData.language_id && (
        <Card>
          <CardHeader>
            <CardTitle>Build Sentence</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {/* Word Search */}
            <div className="space-y-2">
              <Label>Search Words</Label>
              <div className="relative">
                <Search className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
                <Input
                  value={wordSearch}
                  onChange={(e) => setWordSearch(e.target.value)}
                  placeholder="Search for words to add..."
                  className="pl-10"
                />
              </div>
            </div>

            {/* Available Words */}
            <div className="space-y-2">
              <Label>Available Words</Label>
              <div className="max-h-40 overflow-y-auto border rounded-md p-2">
                {isLoadingWords ? (
                  <div className="text-center py-4 text-muted-foreground">Loading words...</div>
                ) : availableWords.length > 0 ? (
                  <div className="flex flex-wrap gap-2">
                    {availableWords.map((word) => (
                      <Button
                        key={word.id}
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => addWordToSentence(word)}
                        className="text-xs"
                      >
                        {word.text}
                        {word.type === 'exception' && (
                          <Badge variant="secondary" className="ml-1 text-xs">
                            {word.exception_type}
                          </Badge>
                        )}
                      </Button>
                    ))}
                  </div>
                ) : (
                  <div className="text-center py-4 text-muted-foreground">
                    {wordSearch ? 'No words found' : 'Start typing to search for words'}
                  </div>
                )}
              </div>
            </div>

            {/* Selected Words */}
            <div className="space-y-2">
              <Label>Selected Words (Sentence Preview)</Label>
              <div className="min-h-16 border rounded-md p-3 bg-muted/30">
                {selectedWords.length > 0 ? (
                  <div className="flex flex-wrap gap-2">
                    {selectedWords.map((selectedWord, index) => (
                      <div key={index} className="flex items-center gap-1">
                        <Badge variant="default" className="flex items-center gap-1">
                          {selectedWord.word.text}
                          <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => removeWordFromSentence(index)}
                            className="h-4 w-4 p-0 hover:bg-destructive hover:text-destructive-foreground"
                          >
                            <X className="h-3 w-3" />
                          </Button>
                        </Badge>
                      </div>
                    ))}
                  </div>
                ) : (
                  <div className="text-muted-foreground">Select words to build your sentence</div>
                )}
              </div>
            </div>

            {/* Sentence Text */}
            <div className="space-y-2">
              <Label htmlFor="text">Final Sentence Text</Label>
              <Textarea
                id="text"
                value={formData.text}
                onChange={(e) => setFormData(prev => ({ ...prev, text: e.target.value }))}
                placeholder="The sentence will be built automatically as you select words, or you can edit it manually"
                className="min-h-20"
              />
            </div>

            {/* AI Suggestions */}
            {selectedWords.length > 0 && (
              <div className="space-y-2">
                <div className="flex items-center gap-2">
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={generateSentenceSuggestions}
                    className="flex items-center gap-2"
                  >
                    <Wand2 className="h-4 w-4" />
                    Generate Suggestions
                  </Button>
                  {showSuggestions && (
                    <span className="text-sm text-muted-foreground">
                      Click a suggestion to use it:
                    </span>
                  )}
                </div>
                {showSuggestions && aiSuggestions.length > 0 && (
                  <div className="space-y-1">
                    {aiSuggestions.map((suggestion, index) => (
                      <Button
                        key={index}
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => applySuggestion(suggestion)}
                        className="justify-start text-left h-auto p-2 hover:bg-muted"
                      >
                        <Lightbulb className="h-3 w-3 mr-2 text-yellow-500" />
                        {suggestion}
                      </Button>
                    ))}
                  </div>
                )}
              </div>
            )}

            {/* Validation Results */}
            {validation && (
              <div className={`p-3 rounded-md border ${validation.valid ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'}`}>
                <div className="flex items-center gap-2 mb-2">
                  {validation.valid ? (
                    <CheckCircle className="h-4 w-4 text-green-600" />
                  ) : (
                    <AlertCircle className="h-4 w-4 text-red-600" />
                  )}
                  <span className={`font-medium ${validation.valid ? 'text-green-800' : 'text-red-800'}`}>
                    {validation.valid ? 'Sentence is valid' : 'Validation errors found'}
                  </span>
                </div>
                
                {validation.errors?.length > 0 && (
                  <ul className="list-disc list-inside text-sm text-red-700 space-y-1">
                    {validation.errors.map((error: string, index: number) => (
                      <li key={index}>{error}</li>
                    ))}
                  </ul>
                )}
                
                {validation.suggestions?.length > 0 && (
                  <div className="mt-2">
                    <p className="text-sm font-medium text-blue-800">Suggestions:</p>
                    <ul className="list-disc list-inside text-sm text-blue-700 space-y-1">
                      {validation.suggestions.map((suggestion: string, index: number) => (
                        <li key={index}>{suggestion}</li>
                      ))}
                    </ul>
                  </div>
                )}
              </div>
            )}
          </CardContent>
        </Card>
      )}

      {/* Action Buttons */}
      <div className="flex justify-end gap-3">
        <Button type="button" variant="outline" onClick={onCancel}>
          Cancel
        </Button>
        <Button
          type="submit"
          disabled={isSaving || isValidating || externalLoading || !validation?.valid}
        >
          {(isSaving || externalLoading) ? 'Saving...' : 'Create Sentence'}
        </Button>
      </div>
    </form>
  );
}
