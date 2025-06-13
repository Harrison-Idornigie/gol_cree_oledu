'use client';

import { useState } from 'react';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Badge } from '@/components/ui/badge';
import { 
  MoreHorizontal, 
  Edit, 
  Trash2, 
  Volume2,
  Languages,
  Calendar,
  ChevronLeft,
  ChevronRight
} from 'lucide-react';
import { format } from 'date-fns';
import { AudioPlayer } from './AudioPlayer';
import { deleteWord } from '@/app/_actions/tenants/team/word-actions';
import { Word } from '@/types/tenant/word';
import { toast } from 'sonner';

interface Language {
  id: number;
  code: string;
  name: string;
  native_name?: string;
}

interface Pagination {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

interface WordsTableProps {
  words: Word[];
  languages: Language[];
  loading: boolean;
  selectedWords: number[];
  onSelectionChange: (selectedIds: number[]) => void;
  onEdit: (word: Word) => void;
  onDelete?: (wordId: number) => void;
  pagination: Pagination;
  onPageChange: (page: number) => void;
}

export function WordsTable({
  words,
  languages,
  loading,
  selectedWords,
  onSelectionChange,
  onEdit,
  onDelete,
  pagination,
  onPageChange,
}: WordsTableProps) {
  const [playingAudio, setPlayingAudio] = useState<number | null>(null);

  // Get language name by code
  const getLanguageName = (code?: string) => {
    if (!code) return 'Unknown';
    const language = languages.find(lang => lang.code === code);
    return language ? language.name : code.toUpperCase();
  };

  // Handle select all
  const handleSelectAll = (checked: boolean) => {
    if (checked) {
      onSelectionChange(words.map(word => word.id));
    } else {
      onSelectionChange([]);
    }
  };

  // Handle individual selection
  const handleSelectWord = (wordId: number, checked: boolean) => {
    if (checked) {
      onSelectionChange([...selectedWords, wordId]);
    } else {
      onSelectionChange(selectedWords.filter(id => id !== wordId));
    }
  };

  // Handle audio play
  const handlePlayAudio = (wordId: number) => {
    if (playingAudio === wordId) {
      setPlayingAudio(null);
    } else {
      setPlayingAudio(wordId);
      // Audio will be played by the AudioPlayer component
    }
  };

  // Handle delete with server action
  const handleDelete = async (wordId: number) => {
    try {
      const result = await deleteWord(wordId);
      
      if (result.error) {
        toast.error(result.error);
        return;
      }

      toast.success('Word deleted successfully');
      
      // Call the parent's onDelete if provided (for refreshing data)
      if (onDelete) {
        onDelete(wordId);
      }
    } catch (error) {
      console.error('Error deleting word:', error);
      toast.error('Failed to delete word');
    }
  };

  // Get part of speech color
  const getPartOfSpeechColor = (partOfSpeech?: string) => {
    switch (partOfSpeech?.toLowerCase()) {
      case 'noun': return 'bg-blue-100 text-blue-800';
      case 'verb': return 'bg-green-100 text-green-800';
      case 'adjective': return 'bg-purple-100 text-purple-800';
      case 'adverb': return 'bg-orange-100 text-orange-800';
      case 'pronoun': return 'bg-pink-100 text-pink-800';
      case 'preposition': return 'bg-yellow-100 text-yellow-800';
      case 'conjunction': return 'bg-indigo-100 text-indigo-800';
      case 'interjection': return 'bg-red-100 text-red-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  // Generate page numbers for pagination
  const getPageNumbers = () => {
    const pages = [];
    const { current_page, last_page } = pagination;
    
    // Always show first page
    if (current_page > 3) {
      pages.push(1);
      if (current_page > 4) pages.push('...');
    }
    
    // Show pages around current page
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
      pages.push(i);
    }
    
    // Always show last page
    if (current_page < last_page - 2) {
      if (current_page < last_page - 3) pages.push('...');
      pages.push(last_page);
    }
    
    return pages;
  };

  if (loading) {
    return (
      <div className="space-y-3">
        {[...Array(5)].map((_, i) => (
          <div key={i} className="flex items-center space-x-4">
            <div className="w-4 h-4 bg-gray-200 rounded animate-pulse" />
            <div className="flex-1 space-y-2">
              <div className="h-4 bg-gray-200 rounded w-1/4 animate-pulse" />
              <div className="h-3 bg-gray-100 rounded w-1/3 animate-pulse" />
            </div>
          </div>
        ))}
      </div>
    );
  }

  if (words.length === 0) {
    return (
      <div className="text-center py-12">
        <Languages className="mx-auto h-12 w-12 text-muted-foreground mb-4" />
        <h3 className="text-lg font-semibold mb-2">No words found</h3>
        <p className="text-muted-foreground mb-4">
          Try adjusting your search criteria or create your first word.
        </p>
        <Button onClick={() => window.location.reload()}>Refresh</Button>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <div className="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-[50px]">
                <Checkbox
                  checked={selectedWords.length === words.length && words.length > 0}
                  onCheckedChange={handleSelectAll}
                  aria-label="Select all words"
                />
              </TableHead>
              <TableHead>Word</TableHead>
              <TableHead>Language</TableHead>
              <TableHead>Part of Speech</TableHead>
              <TableHead>Translations</TableHead>
              <TableHead>Audio</TableHead>
              <TableHead>Created</TableHead>
              <TableHead className="w-[50px]">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {words.map((word) => (
              <TableRow key={word.id}>
                <TableCell>
                  <Checkbox
                    checked={selectedWords.includes(word.id)}
                    onCheckedChange={(checked) => handleSelectWord(word.id, checked as boolean)}
                    aria-label={`Select word ${word.text}`}
                  />
                </TableCell>
                <TableCell>
                  <div className="space-y-1">
                    <div className="font-medium">{word.text}</div>
                    {word.pronunciation_key && (
                      <div className="text-sm text-muted-foreground">
                        /{word.pronunciation_key}/
                      </div>
                    )}
                  </div>
                </TableCell>
                <TableCell>
                  <Badge variant="outline">
                    {getLanguageName(word.language_code)}
                  </Badge>
                </TableCell>
                <TableCell>
                  {word.part_of_speech && (
                    <Badge className={getPartOfSpeechColor(word.part_of_speech)}>
                      {word.part_of_speech}
                    </Badge>
                  )}
                </TableCell>
                <TableCell>
                  <div className="flex items-center gap-1">
                    <span className="text-sm">{word.translations_count || 0}</span>
                    {(word.translations_count || 0) > 0 && (
                      <Languages className="h-3 w-3 text-muted-foreground" />
                    )}
                  </div>
                </TableCell>
                <TableCell>
                  {word.has_audio && word.pronunciation_url ? (
                    <AudioPlayer
                      src={word.pronunciation_url}
                      isPlaying={playingAudio === word.id}
                      onPlayPause={() => handlePlayAudio(word.id)}
                    />
                  ) : (
                    <div className="flex items-center gap-1 text-muted-foreground">
                      <Volume2 className="h-3 w-3" />
                      <span className="text-xs">No audio</span>
                    </div>
                  )}
                </TableCell>
                <TableCell>
                  <div className="flex items-center gap-1 text-sm text-muted-foreground">
                    <Calendar className="h-3 w-3" />
                    {format(new Date(word.created_at), 'MMM d, yyyy')}
                  </div>
                </TableCell>
                <TableCell>
                  <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                      <Button variant="ghost" className="h-8 w-8 p-0">
                        <span className="sr-only">Open menu</span>
                        <MoreHorizontal className="h-4 w-4" />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                      <DropdownMenuItem onClick={() => onEdit(word)}>
                        <Edit className="mr-2 h-4 w-4" />
                        Edit
                      </DropdownMenuItem>
                      <DropdownMenuSeparator />
                      <DropdownMenuItem
                        onClick={() => handleDelete(word.id)}
                        className="text-red-600"
                      >
                        <Trash2 className="mr-2 h-4 w-4" />
                        Delete
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      {/* Pagination */}
      {pagination.last_page > 1 && (
        <div className="flex items-center justify-between">
          <div className="text-sm text-muted-foreground">
            Showing {((pagination.current_page - 1) * pagination.per_page) + 1} to{' '}
            {Math.min(pagination.current_page * pagination.per_page, pagination.total)} of{' '}
            {pagination.total} words
          </div>
          <div className="flex items-center space-x-2">
            <Button
              variant="outline"
              size="sm"
              onClick={() => onPageChange(pagination.current_page - 1)}
              disabled={pagination.current_page === 1}
            >
              <ChevronLeft className="h-4 w-4" />
              Previous
            </Button>
            {getPageNumbers().map((page, index) => (
              <Button
                key={index}
                variant={page === pagination.current_page ? "default" : "outline"}
                size="sm"
                onClick={() => typeof page === 'number' && onPageChange(page)}
                disabled={typeof page !== 'number'}
              >
                {page}
              </Button>
            ))}
            <Button
              variant="outline"
              size="sm"
              onClick={() => onPageChange(pagination.current_page + 1)}
              disabled={pagination.current_page === pagination.last_page}
            >
              Next
              <ChevronRight className="h-4 w-4" />
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}