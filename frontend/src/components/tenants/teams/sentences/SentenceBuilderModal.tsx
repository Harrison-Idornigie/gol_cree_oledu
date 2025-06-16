'use client';

import { useState } from 'react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import SentenceBuilder from './SentenceBuilder';
import { createSentence } from '@/app/_actions/tenants/team/sentence-actions';
import { toast } from 'sonner';

interface Language {
  id: number;
  name: string;
  code: string;
}

interface SentenceBuilderModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess?: (sentence: any) => void;
  languages: Language[];
  initialData?: {
    language_id?: number;
    preselectedWords?: Array<{
      id: string | number;
      text: string;
    }>;
    context?: 'lesson' | 'exercise' | 'word' | 'general';
    contextId?: number;
  };
}

export default function SentenceBuilderModal({
  isOpen,
  onClose,
  onSuccess,
  languages,
  initialData
}: SentenceBuilderModalProps) {
  const [isLoading, setIsLoading] = useState(false);

  const handleSave = async (sentenceData: FormData) => {
    setIsLoading(true);
    try {
      // Add context information if provided
      if (initialData?.context) {
        sentenceData.append('context', initialData.context);
      }
      if (initialData?.contextId) {
        sentenceData.append('context_id', initialData.contextId.toString());
      }

      const result = await createSentence(sentenceData);
      
      if (result.error) {
        toast.error('Failed to create sentence', {
          description: result.error
        });
        return;
      }

      toast.success('Sentence created successfully', {
        description: 'The sentence has been added to your content library.'
      });

      // Call success callback with the created sentence
      if (onSuccess && result.data?.data?.sentence) {
        onSuccess(result.data.data.sentence);
      }

      onClose();
    } catch (error) {
      console.error('Error creating sentence:', error);
      toast.error('Failed to create sentence', {
        description: 'An unexpected error occurred. Please try again.'
      });
    } finally {
      setIsLoading(false);
    }
  };

  const handleCancel = () => {
    if (isLoading) return;
    onClose();
  };

  // Prepare initial data for the builder
  const builderInitialData = initialData ? {
    language_id: initialData.language_id || '',
    text: initialData.preselectedWords?.map(w => w.text).join(' ') || '',
    metadata: {
      difficulty: 'beginner',
      tags: [],
      notes: ''
    }
  } : undefined;

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="max-w-4xl max-h-[90vh] p-0">
        <DialogHeader className="p-6 pb-0">
          <DialogTitle className="text-xl font-semibold">
            Create New Sentence
          </DialogTitle>
          <DialogDescription>
            Build a sentence using registered words and exception terms. 
            {initialData?.context && (
              <span className="block mt-1 text-sm font-medium text-primary">
                Creating for: {initialData.context}
                {initialData.contextId && ` #${initialData.contextId}`}
              </span>
            )}
          </DialogDescription>
        </DialogHeader>

        <ScrollArea className="max-h-[calc(90vh-120px)]">
          <div className="p-6 pt-0">
            <SentenceBuilder
              languages={languages}
              onSave={handleSave}
              onCancel={handleCancel}
              initialData={builderInitialData}
              isLoading={isLoading}
            />
          </div>
        </ScrollArea>
      </DialogContent>
    </Dialog>
  );
}
