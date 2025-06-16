'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { 
  Plus, 
  Search, 
  Filter, 
  BookOpen, 
  Volume2, 
  Edit, 
  Trash2,
  ExternalLink
} from 'lucide-react';
import { toast } from 'sonner';
import SentenceBuilderModal from './SentenceBuilderModal';
import { getSentences, deleteSentence, type Sentence } from '@/app/_actions/tenants/team/sentence-actions';

interface Language {
  id: number;
  name: string;
  code: string;
}

export default function SentencesManagementPage() {
  const router = useRouter();
  const [sentences, setSentences] = useState<Sentence[]>([]);
  const [languages, setLanguages] = useState<Language[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [filters, setFilters] = useState({
    search: '',
    language_id: '',
    difficulty: '',
    has_audio: ''
  });
  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0
  });

  // Load initial data
  useEffect(() => {
    loadLanguages();
    loadSentences();
  }, []);

  // Reload sentences when filters change
  useEffect(() => {
    const timeoutId = setTimeout(() => {
      loadSentences();
    }, 300);

    return () => clearTimeout(timeoutId);
  }, [filters, pagination.current_page]);

  const loadLanguages = async () => {
    try {
      // TODO: Implement getLanguages action or remove language filter
      // For now, set empty array
      setLanguages([]);
    } catch (error) {
      console.error('Failed to load languages:', error);
    }
  };

  const loadSentences = async () => {
    setIsLoading(true);
    try {
      const result = await getSentences({
        search: filters.search || undefined,
        language_id: filters.language_id ? parseInt(filters.language_id) : undefined,
        difficulty: filters.difficulty || undefined,
        has_audio: filters.has_audio ? filters.has_audio === 'true' : undefined,
        page: pagination.current_page,
        per_page: pagination.per_page
      });

      if (result.data?.data) {
        setSentences(result.data.data.sentences);
        setPagination(result.data.data.pagination);
      } else if (result.error) {
        toast.error('Failed to load sentences', {
          description: result.error
        });
      }
    } catch (error) {
      console.error('Failed to load sentences:', error);
      toast.error('Failed to load sentences');
    } finally {
      setIsLoading(false);
    }
  };

  const handleCreateSentence = () => {
    setIsModalOpen(true);
  };

  const handleCreateSentencePage = () => {
    router.push('/admin/sentences/create');
  };

  const handleSentenceCreated = (sentence: Sentence) => {
    setSentences(prev => [sentence, ...prev]);
    setIsModalOpen(false);
    toast.success('Sentence created successfully');
  };

  const handleDeleteSentence = async (id: number) => {
    if (!confirm('Are you sure you want to delete this sentence?')) {
      return;
    }

    try {
      const result = await deleteSentence(id);
      if (result.error) {
        toast.error('Failed to delete sentence', {
          description: result.error
        });
        return;
      }

      setSentences(prev => prev.filter(s => s.id !== id));
      toast.success('Sentence deleted successfully');
    } catch (error) {
      console.error('Failed to delete sentence:', error);
      toast.error('Failed to delete sentence');
    }
  };

  const getDifficultyColor = (difficulty?: string) => {
    switch (difficulty) {
      case 'beginner': return 'bg-green-100 text-green-800';
      case 'intermediate': return 'bg-yellow-100 text-yellow-800';
      case 'advanced': return 'bg-red-100 text-red-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  return (
    <div className="container mx-auto py-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="p-2 bg-primary/10 rounded-lg">
            <BookOpen className="h-5 w-5 text-primary" />
          </div>
          <div>
            <h1 className="text-2xl font-bold">Sentences</h1>
            <p className="text-muted-foreground">
              Manage sentences for language learning content
            </p>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <Button
            variant="outline"
            onClick={handleCreateSentencePage}
            className="flex items-center gap-2"
          >
            <ExternalLink className="h-4 w-4" />
            Full Editor
          </Button>
          <Button
            onClick={handleCreateSentence}
            className="flex items-center gap-2"
          >
            <Plus className="h-4 w-4" />
            Create Sentence
          </Button>
        </div>
      </div>

      {/* Filters */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Filter className="h-4 w-4" />
            Filters
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div className="relative">
              <Search className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search sentences..."
                value={filters.search}
                onChange={(e) => setFilters(prev => ({ ...prev, search: e.target.value }))}
                className="pl-10"
              />
            </div>

            <Select
              value={filters.language_id || "all"}
              onValueChange={(value) => setFilters(prev => ({ ...prev, language_id: value === "all" ? "" : value }))}
            >
              <SelectTrigger>
                <SelectValue placeholder="All languages" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All languages</SelectItem>
                {languages.map((language) => (
                  <SelectItem key={language.id} value={language.id.toString()}>
                    {language.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>

            <Select
              value={filters.difficulty || "all"}
              onValueChange={(value) => setFilters(prev => ({ ...prev, difficulty: value === "all" ? "" : value }))}
            >
              <SelectTrigger>
                <SelectValue placeholder="All difficulties" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All difficulties</SelectItem>
                <SelectItem value="beginner">Beginner</SelectItem>
                <SelectItem value="intermediate">Intermediate</SelectItem>
                <SelectItem value="advanced">Advanced</SelectItem>
              </SelectContent>
            </Select>

            <Select
              value={filters.has_audio || "all"}
              onValueChange={(value) => setFilters(prev => ({ ...prev, has_audio: value === "all" ? "" : value }))}
            >
              <SelectTrigger>
                <SelectValue placeholder="Audio status" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All sentences</SelectItem>
                <SelectItem value="true">With audio</SelectItem>
                <SelectItem value="false">Without audio</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      {/* Sentences List */}
      <Card>
        <CardHeader>
          <CardTitle>
            Sentences ({pagination.total})
          </CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="text-center py-8">
              <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div>
              <p className="text-muted-foreground mt-2">Loading sentences...</p>
            </div>
          ) : sentences.length > 0 ? (
            <div className="space-y-4">
              {sentences.map((sentence) => (
                <div
                  key={sentence.id}
                  className="border rounded-lg p-4 hover:bg-muted/50 transition-colors"
                >
                  <div className="flex items-start justify-between">
                    <div className="flex-1">
                      <div className="flex items-center gap-2 mb-2">
                        <p className="font-medium text-lg">{sentence.text}</p>
                        {sentence.has_audio && (
                          <Volume2 className="h-4 w-4 text-green-600" />
                        )}
                      </div>
                      
                      <div className="flex items-center gap-2 mb-2">
                        {sentence.metadata?.difficulty && (
                          <Badge className={getDifficultyColor(sentence.metadata.difficulty)}>
                            {sentence.metadata.difficulty}
                          </Badge>
                        )}
                        <span className="text-sm text-muted-foreground">
                          {sentence.words_count} words
                        </span>
                        {(sentence.translations_count || 0) > 0 && (
                          <span className="text-sm text-muted-foreground">
                            {sentence.translations_count} translations
                          </span>
                        )}
                      </div>

                      {sentence.metadata?.tags && sentence.metadata.tags.length > 0 && (
                        <div className="flex flex-wrap gap-1">
                          {sentence.metadata.tags.map((tag, index) => (
                            <Badge key={index} variant="outline" className="text-xs">
                              {tag}
                            </Badge>
                          ))}
                        </div>
                      )}
                    </div>

                    <div className="flex items-center gap-2 ml-4">
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => router.push(`/admin/sentences/${sentence.id}/edit`)}
                      >
                        <Edit className="h-4 w-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => handleDeleteSentence(sentence.id)}
                        className="text-red-600 hover:text-red-700"
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="text-center py-8">
              <BookOpen className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
              <h3 className="text-lg font-medium mb-2">No sentences found</h3>
              <p className="text-muted-foreground mb-4">
                {filters.search || filters.language_id || filters.difficulty
                  ? 'Try adjusting your filters or search terms.'
                  : 'Get started by creating your first sentence.'}
              </p>
              <Button onClick={handleCreateSentence}>
                <Plus className="h-4 w-4 mr-2" />
                Create Sentence
              </Button>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Sentence Builder Modal */}
      <SentenceBuilderModal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        onSuccess={handleSentenceCreated}
        languages={languages}
      />
    </div>
  );
}
