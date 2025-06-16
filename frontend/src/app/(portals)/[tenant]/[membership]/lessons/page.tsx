'use client';

import { useState, useEffect, use } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import {
  Plus,
  Search,
  Filter,
  BookOpen,
  Edit,
  Trash2,
  Clock,
  Trophy,
  Users
} from 'lucide-react';
import Link from 'next/link';
import { useTenant } from '@/app/providers/auth-provider';
import { AdminOrTeam } from '@/components/portals/MembershipGuard';
import { toast } from 'sonner';
import { getLessons, deleteLesson } from '@/app/_actions/tenants/team/lesson-actions';
import { Lesson } from '@/types/tenant/lesson';
import SentenceQuickActions from '@/components/tenants/teams/sentences/SentenceQuickActions';

interface LessonsPageProps {
  params: Promise<{
    tenant: string;
    membership: string;
  }>;
}

export default function LessonsPage({ params }: LessonsPageProps) {
  const resolvedParams = use(params);
  const { tenantSlug } = useTenant();
  const [lessons, setLessons] = useState<Lesson[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [filters, setFilters] = useState({
    search: '',
    status: '',
    difficulty: ''
  });

  // Build URL helper
  const buildUrl = (path: string) => `/${tenantSlug}/${resolvedParams.membership}${path}`;

  // Load lessons
  useEffect(() => {
    loadLessons();
  }, []);

  const loadLessons = async () => {
    setIsLoading(true);
    try {
      const result = await getLessons();
      if (result.data) {
        setLessons(result.data);
      } else if (result.error) {
        toast.error('Failed to load lessons', {
          description: result.error
        });
      }
    } catch (error) {
      console.error('Failed to load lessons:', error);
      toast.error('Failed to load lessons');
    } finally {
      setIsLoading(false);
    }
  };

  const handleDeleteLesson = async (id: number) => {
    if (!confirm('Are you sure you want to delete this lesson?')) {
      return;
    }

    try {
      const result = await deleteLesson(id);
      if (result.error) {
        toast.error('Failed to delete lesson', {
          description: result.error
        });
        return;
      }

      setLessons(prev => prev.filter(l => l.id !== id));
      toast.success('Lesson deleted successfully');
    } catch (error) {
      console.error('Failed to delete lesson:', error);
      toast.error('Failed to delete lesson');
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'published': return 'bg-green-100 text-green-800';
      case 'draft': return 'bg-yellow-100 text-yellow-800';
      case 'archived': return 'bg-gray-100 text-gray-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  const getDifficultyColor = (difficulty: string) => {
    switch (difficulty) {
      case 'beginner': return 'bg-blue-100 text-blue-800';
      case 'intermediate': return 'bg-orange-100 text-orange-800';
      case 'advanced': return 'bg-red-100 text-red-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  };

  // Filter lessons based on search and filters
  const filteredLessons = lessons.filter(lesson => {
    const matchesSearch = !filters.search || 
      lesson.title.toLowerCase().includes(filters.search.toLowerCase()) ||
      lesson.description.toLowerCase().includes(filters.search.toLowerCase());
    
    const matchesStatus = !filters.status || 
      (lesson.is_published ? 'published' : 'draft') === filters.status;
    
    const matchesDifficulty = !filters.difficulty || 
      lesson.difficulty_level === filters.difficulty;

    return matchesSearch && matchesStatus && matchesDifficulty;
  });

  return (
    <AdminOrTeam fallback={
      <div className="text-center py-12">
        <h2 className="text-2xl font-bold">Access Restricted</h2>
        <p className="text-muted-foreground">Only administrators and team members can manage lessons.</p>
      </div>
    }>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-primary/10 rounded-lg">
              <BookOpen className="h-5 w-5 text-primary" />
            </div>
            <div>
              <h1 className="text-2xl font-bold">Lessons</h1>
              <p className="text-muted-foreground">
                Manage lessons and learning content
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <SentenceQuickActions
              languages={[]} // TODO: Load languages
              context={{ type: 'general' }}
              variant="dropdown"
            />
            <Button asChild>
              <Link href={buildUrl('/lessons/create')}>
                <Plus className="h-4 w-4 mr-2" />
                Create Lesson
              </Link>
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
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="relative">
                <Search className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
                <Input
                  placeholder="Search lessons..."
                  value={filters.search}
                  onChange={(e) => setFilters(prev => ({ ...prev, search: e.target.value }))}
                  className="pl-10"
                />
              </div>

              <Select
                value={filters.status || "all"}
                onValueChange={(value) => setFilters(prev => ({ ...prev, status: value === "all" ? "" : value }))}
              >
                <SelectTrigger>
                  <SelectValue placeholder="All statuses" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All statuses</SelectItem>
                  <SelectItem value="published">Published</SelectItem>
                  <SelectItem value="draft">Draft</SelectItem>
                  <SelectItem value="archived">Archived</SelectItem>
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
            </div>
          </CardContent>
        </Card>

        {/* Lessons List */}
        <Card>
          <CardHeader>
            <CardTitle>
              Lessons ({filteredLessons.length})
            </CardTitle>
          </CardHeader>
          <CardContent>
            {isLoading ? (
              <div className="text-center py-8">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div>
                <p className="text-muted-foreground mt-2">Loading lessons...</p>
              </div>
            ) : filteredLessons.length > 0 ? (
              <div className="space-y-4">
                {filteredLessons.map((lesson) => (
                  <div
                    key={lesson.id}
                    className="border rounded-lg p-4 hover:bg-muted/50 transition-colors"
                  >
                    <div className="flex items-start justify-between">
                      <div className="flex-1">
                        <div className="flex items-center gap-2 mb-2">
                          <h3 className="font-semibold text-lg">{lesson.title}</h3>
                          <Badge className={getStatusColor(lesson.is_published ? 'published' : 'draft')}>
                            {lesson.is_published ? 'Published' : 'Draft'}
                          </Badge>
                          <Badge className={getDifficultyColor(lesson.difficulty_level)}>
                            {lesson.difficulty_level}
                          </Badge>
                        </div>
                        
                        <p className="text-muted-foreground mb-3">{lesson.description}</p>
                        
                        <div className="flex items-center gap-4 text-sm text-muted-foreground">
                          <div className="flex items-center gap-1">
                            <Clock className="h-4 w-4" />
                            {lesson.estimated_time} min
                          </div>
                          <div className="flex items-center gap-1">
                            <Trophy className="h-4 w-4" />
                            {lesson.xp_reward} XP
                          </div>
                          <div className="flex items-center gap-1">
                            <Users className="h-4 w-4" />
                            {lesson.exercises?.length || 0} exercises
                          </div>
                        </div>
                      </div>

                      <div className="flex items-center gap-2 ml-4">
                        <Button
                          variant="ghost"
                          size="sm"
                          asChild
                        >
                          <Link href={buildUrl(`/lessons/${lesson.id}/edit`)}>
                            <Edit className="h-4 w-4" />
                          </Link>
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => handleDeleteLesson(lesson.id)}
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
                <h3 className="text-lg font-medium mb-2">No lessons found</h3>
                <p className="text-muted-foreground mb-4">
                  {filters.search || filters.status || filters.difficulty
                    ? 'Try adjusting your filters or search terms.'
                    : 'Get started by creating your first lesson.'}
                </p>
                <Button asChild>
                  <Link href={buildUrl('/lessons/create')}>
                    <Plus className="h-4 w-4 mr-2" />
                    Create Lesson
                  </Link>
                </Button>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </AdminOrTeam>
  );
}
