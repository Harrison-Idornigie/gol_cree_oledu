'use client';

import { useState, useEffect } from 'react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { 
  Plus, 
  Trash2, 
  Languages,
} from 'lucide-react';
import { toast } from 'sonner';
import {
  createWord,
  updateWord,
  uploadWordAudio
} from '@/app/_actions/tenants/team/word-actions';
import {
  Word,
  WordTranslation,
  WORD_PARTS_OF_SPEECH
} from '@/types/tenant/word';

interface Language {
  id: number;
  code: string;
  name: string;
  native_name?: string;
}

interface WordModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSave: () => Promise<void>;
  editingWord: Word | null;
  languages: Language[];
}

interface WordFormData {
  language_id: number;
  text: string;
  pronunciation_key: string;
  part_of_speech: string;
  translations: WordTranslation[];
}

export function WordModal({ isOpen, onClose, onSave, editingWord, languages }: WordModalProps) {
  const [isLoading, setIsLoading] = useState(false);
  const [audioFile, setAudioFile] = useState<File | null>(null);
  const [formData, setFormData] = useState<WordFormData>({
    language_id: 0,
    text: '',
    pronunciation_key: '',
    part_of_speech: '',
    translations: [],
  });

  // Load word data when editing
  useEffect(() => {
    if (editingWord) {
      setFormData({
        language_id: editingWord.language_id,
        text: editingWord.text,
        pronunciation_key: editingWord.pronunciation_key || '',
        part_of_speech: editingWord.part_of_speech || '',
        translations: editingWord.translations || [],
      });
    } else {
      setFormData({
        language_id: 0,
        text: '',
        pronunciation_key: '',
        part_of_speech: '',
        translations: [],
      });
    }
    setAudioFile(null);
  }, [editingWord]);

  // Handle form submission
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);
    try {
      const submitData = new FormData();
      
      // Add word data
      submitData.append('language_id', formData.language_id.toString());
      submitData.append('text', formData.text);
      if (formData.pronunciation_key) {
        submitData.append('pronunciation_key', formData.pronunciation_key);
      }
      if (formData.part_of_speech) {
        submitData.append('part_of_speech', formData.part_of_speech);
      }
      
      // Add audio file
      if (audioFile) {
        submitData.append('pronunciation_audio', audioFile);
      }
      
      // Add translations
      if (formData.translations && formData.translations.length > 0) {
        submitData.append('translations', JSON.stringify(formData.translations));
      }

      let result;
      if (editingWord) {
        // Update existing word
        result = await updateWord(editingWord.id, submitData);
      } else {
        // Create new word
        result = await createWord(submitData);
      }

      if (result.error) {
        toast.error(result.error);
        return;
      }

      toast.success(editingWord ? 'Word updated successfully' : 'Word created successfully');
      
      // Upload additional audio if needed and word was created/updated successfully
      if (audioFile && result.data?.data && !editingWord) {
        const audioResult = await uploadWordAudio(result.data.data.id, audioFile);
        if (audioResult.error) {
          toast.error(`Word saved but audio upload failed: ${audioResult.error}`);
        }
      }

      await onSave();
      onClose();
    } catch (error) {
      console.error('Error saving word:', error);
      toast.error('Failed to save word');
    } finally {
      setIsLoading(false);
    }
  };

  // Add new translation
  const addTranslation = () => {
    setFormData(prev => ({
      ...prev,
      translations: [
        ...prev.translations,
        {
          language_id: 0,
          text: '',
          pronunciation_key: '',
          context_notes: '',
        } as WordTranslation
      ]
    }));
  };

  // Remove translation
  const removeTranslation = (index: number) => {
    setFormData(prev => ({
      ...prev,
      translations: prev.translations.filter((_, i) => i !== index)
    }));
  };

  // Handle audio file upload
  const handleAudioUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0] || null;
    setAudioFile(file);
  };

  // Update translation
  const updateTranslation = (index: number, field: string, value: string | number) => {
    setFormData(prev => ({
      ...prev,
      translations: prev.translations.map((t, i) => 
        i === index ? { ...t, [field]: value } : t
      )
    }));
  };

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>
            {editingWord ? 'Edit Word' : 'Add New Word'}
          </DialogTitle>
          <DialogDescription>
            {editingWord 
              ? 'Update the word details and translations'
              : 'Create a new word with translations and pronunciation'
            }
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Main Word Section */}
          <Card>
            <CardHeader>
              <CardTitle className="text-lg">Word Details</CardTitle>
              <CardDescription>
                Enter the main word information
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="language_id">Language</Label>
                  <Select
                    value={formData.language_id.toString()}
                    onValueChange={(value) => setFormData(prev => ({ ...prev, language_id: parseInt(value) }))}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Select language" />
                    </SelectTrigger>
                    <SelectContent>
                      {languages.map((language) => (
                        <SelectItem
                          key={language.id}
                          value={language.id.toString()}
                        >
                          {language.name} ({language.code})
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                
                <div className="space-y-2">
                  <Label htmlFor="part_of_speech">Part of Speech</Label>
                  <Select
                    value={formData.part_of_speech}
                    onValueChange={(value) => setFormData(prev => ({ ...prev, part_of_speech: value }))}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Select part of speech" />
                    </SelectTrigger>
                    <SelectContent>
                      {WORD_PARTS_OF_SPEECH.map((pos) => (
                        <SelectItem key={pos} value={pos}>
                          {pos.charAt(0).toUpperCase() + pos.slice(1)}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </div>

              <div className="space-y-2">
                <Label htmlFor="text">Word Text</Label>
                <Input
                  id="text"
                  placeholder="Enter the word"
                  value={formData.text}
                  onChange={(e) => setFormData(prev => ({ ...prev, text: e.target.value }))}
                  required
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="pronunciation_key">Pronunciation (IPA)</Label>
                <Input
                  id="pronunciation_key"
                  placeholder="e.g., /həˈloʊ/"
                  value={formData.pronunciation_key}
                  onChange={(e) => setFormData(prev => ({ ...prev, pronunciation_key: e.target.value }))}
                />
                <p className="text-sm text-muted-foreground">
                  International Phonetic Alphabet notation
                </p>
              </div>

              {/* Audio Upload */}
              <div className="space-y-2">
                <Label htmlFor="audio">Pronunciation Audio</Label>
                <Input
                  id="audio"
                  type="file"
                  accept="audio/*"
                  onChange={handleAudioUpload}
                />
                {audioFile && (
                  <p className="text-sm text-green-600">
                    Selected: {audioFile.name}
                  </p>
                )}
              </div>
            </CardContent>
          </Card>

          {/* Translations Section */}
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <div>
                  <CardTitle className="text-lg flex items-center gap-2">
                    <Languages className="h-5 w-5" />
                    Translations
                  </CardTitle>
                  <CardDescription>
                    Add translations in different languages
                  </CardDescription>
                </div>
                <Button type="button" onClick={addTranslation} variant="outline">
                  <Plus className="mr-2 h-4 w-4" />
                  Add Translation
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              {formData.translations.length === 0 ? (
                <div className="text-center py-8 text-muted-foreground">
                  <Languages className="mx-auto h-8 w-8 mb-2" />
                  <p>No translations added yet</p>
                  <Button 
                    type="button" 
                    onClick={addTranslation} 
                    variant="outline" 
                    className="mt-2"
                  >
                    Add First Translation
                  </Button>
                </div>
              ) : (
                <div className="space-y-4">
                  {formData.translations.map((translation, index) => (
                    <Card key={index} className="border-dashed">
                      <CardContent className="pt-4">
                        <div className="flex items-start justify-between mb-4">
                          <Badge variant="outline">Translation {index + 1}</Badge>
                          <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => removeTranslation(index)}
                            className="text-red-600"
                          >
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        </div>
                        
                        <div className="grid grid-cols-2 gap-4">
                          <div className="space-y-2">
                            <Label>Language</Label>
                            <Select
                              value={translation.language_id.toString()}
                              onValueChange={(value) => updateTranslation(index, 'language_id', parseInt(value))}
                            >
                              <SelectTrigger>
                                <SelectValue placeholder="Select language" />
                              </SelectTrigger>
                              <SelectContent>
                                {languages.map((language) => (
                                  <SelectItem
                                    key={language.id}
                                    value={language.id.toString()}
                                  >
                                    {language.name} ({language.code})
                                  </SelectItem>
                                ))}
                              </SelectContent>
                            </Select>
                          </div>
                          
                          <div className="space-y-2">
                            <Label>Translation Text</Label>
                            <Input
                              placeholder="Enter translation"
                              value={translation.text}
                              onChange={(e) => updateTranslation(index, 'text', e.target.value)}
                              required
                            />
                          </div>
                        </div>

                        <div className="space-y-2 mt-4">
                          <Label>Pronunciation (IPA)</Label>
                          <Input
                            placeholder="e.g., /həˈloʊ/"
                            value={translation.pronunciation_key}
                            onChange={(e) => updateTranslation(index, 'pronunciation_key', e.target.value)}
                          />
                        </div>

                        <div className="space-y-2 mt-4">
                          <Label>Context Notes</Label>
                          <Input
                            placeholder="Usage context, notes, or examples..."
                            value={translation.context_notes}
                            onChange={(e) => updateTranslation(index, 'context_notes', e.target.value)}
                          />
                        </div>
                      </CardContent>
                    </Card>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" disabled={isLoading}>
              {isLoading ? 'Saving...' : editingWord ? 'Update Word' : 'Create Word'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}