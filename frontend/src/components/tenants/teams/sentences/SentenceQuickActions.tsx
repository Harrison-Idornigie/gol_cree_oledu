'use client';

import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { 
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
  DropdownMenuSeparator
} from '@/components/ui/dropdown-menu';
import { Plus, BookOpen, ExternalLink, Zap } from 'lucide-react';
import { useRouter } from 'next/navigation';
import SentenceBuilderModal from './SentenceBuilderModal';

interface Language {
  id: number;
  name: string;
  code: string;
}

interface SentenceQuickActionsProps {
  languages: Language[];
  context?: {
    type: 'lesson' | 'exercise' | 'word' | 'general';
    id?: number;
    languageId?: number;
    preselectedWords?: Array<{
      id: string | number;
      text: string;
    }>;
  };
  onSentenceCreated?: (sentence: any) => void;
  variant?: 'button' | 'dropdown' | 'fab';
  className?: string;
}

export default function SentenceQuickActions({
  languages,
  context,
  onSentenceCreated,
  variant = 'button',
  className = ''
}: SentenceQuickActionsProps) {
  const router = useRouter();
  const [isModalOpen, setIsModalOpen] = useState(false);

  const handleModalCreate = () => {
    setIsModalOpen(true);
  };

  const handlePageCreate = () => {
    const params = new URLSearchParams();
    
    if (context?.languageId) {
      params.set('language_id', context.languageId.toString());
    }
    
    if (context?.preselectedWords?.length) {
      params.set('word_ids', context.preselectedWords.map(w => w.id).join(','));
    }
    
    // Set return URL based on context
    const returnUrl = getReturnUrl();
    if (returnUrl) {
      params.set('return_url', returnUrl);
    }

    const url = `/admin/sentences/create${params.toString() ? `?${params.toString()}` : ''}`;
    router.push(url);
  };

  const getReturnUrl = () => {
    if (!context) return '/admin/sentences';
    
    switch (context.type) {
      case 'lesson':
        return context.id ? `/admin/lessons/${context.id}/edit` : '/admin/lessons';
      case 'exercise':
        return context.id ? `/admin/exercises/${context.id}/edit` : '/admin/exercises';
      case 'word':
        return context.id ? `/admin/words/${context.id}` : '/admin/words';
      default:
        return '/admin/sentences';
    }
  };

  const handleSentenceCreated = (sentence: any) => {
    setIsModalOpen(false);
    if (onSentenceCreated) {
      onSentenceCreated(sentence);
    }
  };

  // Floating Action Button variant
  if (variant === 'fab') {
    return (
      <>
        <Button
          onClick={handleModalCreate}
          className={`fixed bottom-6 right-6 h-14 w-14 rounded-full shadow-lg hover:shadow-xl transition-shadow z-40 ${className}`}
          size="lg"
        >
          <Plus className="h-6 w-6" />
        </Button>

        <SentenceBuilderModal
          isOpen={isModalOpen}
          onClose={() => setIsModalOpen(false)}
          onSuccess={handleSentenceCreated}
          languages={languages}
          initialData={{
            language_id: context?.languageId,
            preselectedWords: context?.preselectedWords,
            context: context?.type,
            contextId: context?.id
          }}
        />
      </>
    );
  }

  // Dropdown variant
  if (variant === 'dropdown') {
    return (
      <>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="outline" className={`flex items-center gap-2 ${className}`}>
              <Plus className="h-4 w-4" />
              Add Sentence
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="w-48">
            <DropdownMenuItem onClick={handleModalCreate}>
              <Zap className="h-4 w-4 mr-2" />
              Quick Create
            </DropdownMenuItem>
            <DropdownMenuItem onClick={handlePageCreate}>
              <ExternalLink className="h-4 w-4 mr-2" />
              Full Editor
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem onClick={() => router.push('/admin/sentences')}>
              <BookOpen className="h-4 w-4 mr-2" />
              Manage Sentences
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>

        <SentenceBuilderModal
          isOpen={isModalOpen}
          onClose={() => setIsModalOpen(false)}
          onSuccess={handleSentenceCreated}
          languages={languages}
          initialData={{
            language_id: context?.languageId,
            preselectedWords: context?.preselectedWords,
            context: context?.type,
            contextId: context?.id
          }}
        />
      </>
    );
  }

  // Default button variant
  return (
    <>
      <Button
        onClick={handleModalCreate}
        className={`flex items-center gap-2 ${className}`}
      >
        <Plus className="h-4 w-4" />
        Create Sentence
      </Button>

      <SentenceBuilderModal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        onSuccess={handleSentenceCreated}
        languages={languages}
        initialData={{
          language_id: context?.languageId,
          preselectedWords: context?.preselectedWords,
          context: context?.type,
          contextId: context?.id
        }}
      />
    </>
  );
}
