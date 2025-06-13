'use client';

import { useState, useEffect } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Plus, Download, Upload, Search, Filter, MoreHorizontal } from 'lucide-react';
import { AdminOrTeam } from '@/components/portals/MembershipGuard';
import { useTenant } from '@/app/providers/auth-provider';
import { WordsTable } from '@/components/tenants/teams/words/WordsTable';
import { WordModal } from '@/components/tenants/teams/words/WordModal';
import { BulkImportModal } from '@/components/tenants/teams/words/BulkImportModal';
import { WordFilters } from '@/components/tenants/teams/words/WordFilters';
import { Input } from '@/components/ui/input';
import { 
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { toast } from 'sonner';
import axiosInstance from '@/lib/axios';
import {
  getWords,
  deleteWord,
  bulkDeleteWords,
  exportWords
} from '@/app/_actions/tenants/team/word-actions';
import {
  Word,
  WordFilters as WordFiltersType
} from '@/types/tenant/word';

interface Language {
  id: number;
  code: string;
  name: string;
  native_name?: string;
}


export default function WordsPage() {
  useTenant();
  
  // State management
  const [words, setWords] = useState<Word[]>([]);
  const [languages, setLanguages] = useState<Language[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [filters, setFilters] = useState<WordFiltersType>({
    search: '',
  });
  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
  });
  const [selectedWords, setSelectedWords] = useState<number[]>([]);
  
  // Modal states
  const [isWordModalOpen, setIsWordModalOpen] = useState(false);
  const [isBulkImportModalOpen, setIsBulkImportModalOpen] = useState(false);
  const [isFiltersOpen, setIsFiltersOpen] = useState(false);
  const [editingWord, setEditingWord] = useState<Word | null>(null);

  // Load initial data
  useEffect(() => {
    loadWords();
    loadLanguages();
  }, [filters, pagination.current_page]);

  // Load words from API
  const loadWords = async () => {
    try {
      setLoading(true);
      
      // Prepare filters with pagination
      const wordFilters: WordFiltersType = {
        ...filters,
        page: pagination.current_page,
        per_page: pagination.per_page,
      };

      const result = await getWords(wordFilters);
      
      if (result.error) {
        toast.error(result.error);
        return;
      }

      if (result.data) {
        setWords(result.data.data);
        setPagination({
          current_page: result.data.current_page,
          last_page: result.data.last_page,
          per_page: result.data.per_page,
          total: result.data.total,
        });
      }
    } catch (error) {
      console.error('Error loading words:', error);
      toast.error('Failed to load words');
    } finally {
      setLoading(false);
    }
  };

  // Load languages for filters
  const loadLanguages = async () => {
    try {
      // For now, we'll use axiosInstance for languages since we don't have a server action for it yet
      // This can be updated later when language server actions are created
      const response = await axiosInstance.get('/api/team/languages');
      if (response.data) {
        setLanguages(response.data);
      }
    } catch (error) {
      console.error('Error loading languages:', error);
    }
  };

  // Handle search
  const handleSearch = (value: string) => {
    setSearchTerm(value);
    setFilters(prev => ({ ...prev, search: value }));
    setPagination(prev => ({ ...prev, current_page: 1 }));
  };

  // Handle filter changes
  const handleFiltersChange = (newFilters: Partial<WordFiltersType>) => {
    setFilters(prev => ({ ...prev, ...newFilters }));
    setPagination(prev => ({ ...prev, current_page: 1 }));
  };

  // Handle pagination
  const handlePageChange = (page: number) => {
    setPagination(prev => ({ ...prev, current_page: page }));
  };

  // Handle word creation/editing - now handled by WordModal using server actions
  const handleWordSave = async () => {
    // The WordModal will handle the server action calls directly
    // This function is kept for compatibility but the actual save logic
    // is now in the WordModal component using createWord/updateWord server actions
    toast.success(editingWord ? 'Word updated successfully' : 'Word created successfully');
    setEditingWord(null);
    setIsWordModalOpen(false);
    loadWords();
  };

  // Handle word deletion
  const handleWordDelete = async (wordId: number) => {
    try {
      const result = await deleteWord(wordId);
      
      if (result.error) {
        toast.error(result.error);
        return;
      }

      toast.success('Word deleted successfully');
      loadWords();
    } catch (error) {
      console.error('Error deleting word:', error);
      toast.error('Failed to delete word');
    }
  };

  // Handle bulk delete
  const handleBulkDelete = async () => {
    if (selectedWords.length === 0) return;
    
    try {
      const result = await bulkDeleteWords(selectedWords);
      
      if (result.error) {
        toast.error(result.error);
        return;
      }

      const deletedCount = result.data?.deleted || selectedWords.length;
      toast.success(`${deletedCount} words deleted successfully`);
      setSelectedWords([]);
      loadWords();
    } catch (error) {
      console.error('Error deleting words:', error);
      toast.error('Failed to delete words');
    }
  };

  // Handle bulk import
  const handleBulkImport = async () => {
    // The BulkImportModal will handle the server action calls directly
    // This function is kept for compatibility but the actual import logic
    // is now in the BulkImportModal component using bulkImportWords server action
    setIsBulkImportModalOpen(false);
    loadWords();
  };

  // Handle export
  const handleExport = async () => {
    try {
      // Use current filters for export (excluding pagination)
      const exportFilters = {
        search: filters.search,
        language_id: filters.language_id,
        difficulty: filters.difficulty,
        part_of_speech: filters.part_of_speech,
        has_audio: filters.has_audio,
        tags: filters.tags,
      };

      const result = await exportWords(exportFilters);
      
      if (result.error) {
        toast.error(result.error);
        return;
      }

      if (result.data) {
        const url = window.URL.createObjectURL(result.data);
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', 'words.csv');
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
        
        toast.success('Words exported successfully');
      }
    } catch (error) {
      console.error('Error exporting words:', error);
      toast.error('Failed to export words');
    }
  };


  // Handle word edit
  const handleWordEdit = (word: Word) => {
    setEditingWord(word);
    setIsWordModalOpen(true);
  };

  // Handle word selection
  const handleWordSelection = (wordIds: number[]) => {
    setSelectedWords(wordIds);
  };

  return (
    <AdminOrTeam fallback={
      <div className="text-center py-12">
        <h2 className="text-2xl font-bold">Access Restricted</h2>
        <p className="text-muted-foreground">This page is only available to admin and team members.</p>
      </div>
    }>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Word Management</h1>
            <p className="text-muted-foreground">
              Manage your vocabulary database with translations and audio
            </p>
          </div>
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              onClick={() => setIsBulkImportModalOpen(true)}
            >
              <Upload className="mr-2 h-4 w-4" />
              Import
            </Button>
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline">
                  <MoreHorizontal className="h-4 w-4" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuLabel>Actions</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem onClick={handleExport}>
                  <Download className="mr-2 h-4 w-4" />
                  Export Words
                </DropdownMenuItem>
                {selectedWords.length > 0 && (
                  <>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem 
                      onClick={handleBulkDelete}
                      className="text-red-600"
                    >
                      Delete Selected ({selectedWords.length})
                    </DropdownMenuItem>
                  </>
                )}
              </DropdownMenuContent>
            </DropdownMenu>
            <Button onClick={() => setIsWordModalOpen(true)}>
              <Plus className="mr-2 h-4 w-4" />
              Add Word
            </Button>
          </div>
        </div>

        {/* Stats Cards */}
        <div className="grid gap-4 md:grid-cols-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Total Words</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{pagination.total}</div>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">With Audio</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">
                {words.filter(w => w.has_audio).length}
              </div>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">With Translations</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">
                {words.filter(w => w.translations_count > 0).length}
              </div>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Languages</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{languages.length}</div>
            </CardContent>
          </Card>
        </div>

        {/* Search and Filters */}
        <div className="flex items-center gap-4">
          <div className="flex-1">
            <div className="relative">
              <Search className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search words..."
                value={searchTerm}
                onChange={(e) => handleSearch(e.target.value)}
                className="pl-10"
              />
            </div>
          </div>
          <Button
            variant="outline"
            onClick={() => setIsFiltersOpen(!isFiltersOpen)}
          >
            <Filter className="mr-2 h-4 w-4" />
            Filters
          </Button>
        </div>

        {/* Advanced Filters */}
        {isFiltersOpen && (
          <Card>
            <CardHeader>
              <CardTitle className="text-lg">Advanced Filters</CardTitle>
              <CardDescription>
                Filter words by language, difficulty, and other criteria
              </CardDescription>
            </CardHeader>
            <CardContent>
              <WordFilters
                filters={filters}
                languages={languages}
                onFiltersChange={handleFiltersChange}
              />
            </CardContent>
          </Card>
        )}

        {/* Words Table */}
        <Card>
          <CardHeader>
            <CardTitle>Words</CardTitle>
            <CardDescription>
              {pagination.total > 0 ? (
                `Showing ${pagination.from} to ${pagination.to} of ${pagination.total} words`
              ) : (
                'No words found'
              )}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <WordsTable
              words={words}
              languages={languages}
              loading={loading}
              selectedWords={selectedWords}
              onSelectionChange={handleWordSelection}
              onEdit={handleWordEdit}
              onDelete={handleWordDelete}
              pagination={pagination}
              onPageChange={handlePageChange}
            />
          </CardContent>
        </Card>

        {/* Modals */}
        <WordModal
          isOpen={isWordModalOpen}
          onClose={() => {
            setIsWordModalOpen(false);
            setEditingWord(null);
          }}
          onSave={handleWordSave}
          editingWord={editingWord}
          languages={languages}
        />

        <BulkImportModal
          isOpen={isBulkImportModalOpen}
          onClose={() => setIsBulkImportModalOpen(false)}
          onImport={handleBulkImport}
          languages={languages}
        />
      </div>
    </AdminOrTeam>
  );
}