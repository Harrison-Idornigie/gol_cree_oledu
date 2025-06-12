'use client';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Plus, BookOpen, Users, Clock } from 'lucide-react';
import Link from 'next/link';
import { useTenant } from '@/app/providers/auth-provider';
import { AdminOrTeam } from '@/components/portals/MembershipGuard';

interface LearningPathsPageProps {
  params: {
    tenant: string;
    membership: string;
  };
}

export default function LearningPathsPage({ params }: LearningPathsPageProps) {
  const { tenantSlug } = useTenant();

  // Build URL helper
  const buildUrl = (path: string) => `/${tenantSlug}/${params.membership}${path}`;

  // Mock data
  const learningPaths = [
    {
      id: 1,
      title: 'Cree Language Basics',
      description: 'Learn fundamental Cree vocabulary and grammar',
      lessons: 12,
      students: 45,
      duration: '4 weeks',
      status: 'active'
    },
    {
      id: 2,
      title: 'Advanced Cree Conversation',
      description: 'Practice conversational Cree with native speakers',
      lessons: 8,
      students: 23,
      duration: '6 weeks',
      status: 'active'
    },
    {
      id: 3,
      title: 'Cree Cultural Context',
      description: 'Understanding Cree culture through language',
      lessons: 15,
      students: 67,
      duration: '8 weeks',
      status: 'draft'
    }
  ];

  return (
    <AdminOrTeam fallback={
      <div className="text-center py-12">
        <h2 className="text-2xl font-bold">Access Restricted</h2>
        <p className="text-muted-foreground">Only administrators and team members can manage learning paths.</p>
      </div>
    }>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Learning Paths</h1>
            <p className="text-muted-foreground">
              Create and manage structured learning experiences
            </p>
          </div>
          <Button asChild>
            <Link href={buildUrl('/learning-paths/new')}>
              <Plus className="mr-2 h-4 w-4" />
              Create Learning Path
            </Link>
          </Button>
        </div>

        {/* Stats Cards */}
        <div className="grid gap-4 md:grid-cols-3">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Total Paths</CardTitle>
              <BookOpen className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{learningPaths.length}</div>
              <p className="text-xs text-muted-foreground">
                {learningPaths.filter(p => p.status === 'active').length} active
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Total Students</CardTitle>
              <Users className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">
                {learningPaths.reduce((sum, path) => sum + path.students, 0)}
              </div>
              <p className="text-xs text-muted-foreground">
                Across all paths
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Total Lessons</CardTitle>
              <Clock className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">
                {learningPaths.reduce((sum, path) => sum + path.lessons, 0)}
              </div>
              <p className="text-xs text-muted-foreground">
                Ready to learn
              </p>
            </CardContent>
          </Card>
        </div>

        {/* Learning Paths Grid */}
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {learningPaths.map((path) => (
            <Card key={path.id} className="hover:shadow-md transition-shadow">
              <CardHeader>
                <div className="flex items-center justify-between">
                  <CardTitle className="text-lg">{path.title}</CardTitle>
                  <span className={`px-2 py-1 text-xs rounded-full ${
                    path.status === 'active' 
                      ? 'bg-green-100 text-green-800' 
                      : 'bg-gray-100 text-gray-800'
                  }`}>
                    {path.status}
                  </span>
                </div>
                <CardDescription>{path.description}</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-2">
                  <div className="flex items-center justify-between text-sm">
                    <span className="flex items-center">
                      <BookOpen className="mr-1 h-3 w-3" />
                      {path.lessons} lessons
                    </span>
                    <span className="flex items-center">
                      <Users className="mr-1 h-3 w-3" />
                      {path.students} students
                    </span>
                  </div>
                  <div className="flex items-center text-sm text-muted-foreground">
                    <Clock className="mr-1 h-3 w-3" />
                    {path.duration}
                  </div>
                  <div className="pt-2">
                    <Button asChild className="w-full">
                      <Link href={buildUrl(`/learning-paths/${path.id}`)}>
                        Manage Path
                      </Link>
                    </Button>
                  </div>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>

        {/* Empty State */}
        {learningPaths.length === 0 && (
          <Card className="text-center py-12">
            <CardContent>
              <BookOpen className="mx-auto h-12 w-12 text-muted-foreground mb-4" />
              <h3 className="text-lg font-semibold mb-2">No Learning Paths Yet</h3>
              <p className="text-muted-foreground mb-4">
                Create your first learning path to get started with structured language learning.
              </p>
              <Button asChild>
                <Link href={buildUrl('/learning-paths/new')}>
                  <Plus className="mr-2 h-4 w-4" />
                  Create Your First Path
                </Link>
              </Button>
            </CardContent>
          </Card>
        )}
      </div>
    </AdminOrTeam>
  );
}
