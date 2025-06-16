'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { ArrowLeft, BookOpen } from 'lucide-react';
import SentenceBuilder from './SentenceBuilder';
import { createSentence } from '@/app/_actions/tenants/team/sentence-actions';
import { toast } from 'sonner';

interface Language {
  id: number;
  name: string;
  code: string;
}

interface SentenceBuilderPageProps {
  languages: Language[];
  initialData?: {
    language_id?: number;
    preselectedWords?: Array<{
      id: string | number;
      text: string;
    }>;
    returnUrl?: string;
  };
}

export default function SentenceBuilderPage({
  languages,
  initialData
}: SentenceBuilderPageProps) {
  const router = useRouter();
  const [isLoading, setIsLoading] = useState(false);

  const handleSave = async (sentenceData: FormData) => {
    setIsLoading(true);
    try {
      const result = await createSentence(sentenceData);
      
      if (result.error) {
        toast.error('Failed to create sentence', {
          description: result.error
        });
        return;
      }

      toast.success('Sentence created successfully', {
        description: 'The sentence has been added to your content library.',
        action: {
          label: 'View Sentences',
          onClick: () => router.push('/admin/sentences')
        }
      });

      // Redirect to the return URL or sentences list
      const returnUrl = initialData?.returnUrl || '/admin/sentences';
      router.push(returnUrl);
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
    
    const returnUrl = initialData?.returnUrl || '/admin/sentences';
    router.push(returnUrl);
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
    <div className="container mx-auto py-6 space-y-6">
      {/* Header */}
      <div className="flex items-center gap-4">
        <Button
          variant="ghost"
          size="sm"
          onClick={handleCancel}
          disabled={isLoading}
          className="flex items-center gap-2"
        >
          <ArrowLeft className="h-4 w-4" />
          Back
        </Button>
        
        <div className="flex items-center gap-3">
          <div className="p-2 bg-primary/10 rounded-lg">
            <BookOpen className="h-5 w-5 text-primary" />
          </div>
          <div>
            <h1 className="text-2xl font-bold">Create New Sentence</h1>
            <p className="text-muted-foreground">
              Build sentences using registered words and exception terms
            </p>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <BookOpen className="h-5 w-5" />
            Sentence Builder
          </CardTitle>
        </CardHeader>
        <CardContent>
          <SentenceBuilder
            languages={languages}
            onSave={handleSave}
            onCancel={handleCancel}
            initialData={builderInitialData}
            isLoading={isLoading}
          />
        </CardContent>
      </Card>

      {/* Help Section */}
      <Card>
        <CardHeader>
          <CardTitle className="text-lg">Tips for Creating Sentences</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          <div className="grid md:grid-cols-2 gap-4 text-sm">
            <div>
              <h4 className="font-medium mb-2">Word Selection</h4>
              <ul className="space-y-1 text-muted-foreground">
                <li>• Only registered words can be used</li>
                <li>• Exception words (proper nouns) are allowed</li>
                <li>• Use the search to find available words</li>
                <li>• Words are validated in real-time</li>
              </ul>
            </div>
            <div>
              <h4 className="font-medium mb-2">Best Practices</h4>
              <ul className="space-y-1 text-muted-foreground">
                <li>• Set appropriate difficulty levels</li>
                <li>• Add pronunciation guides when needed</li>
                <li>• Include audio for better learning</li>
                <li>• Use tags for better organization</li>
              </ul>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
