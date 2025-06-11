'use client';

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Globe, Play, BookOpen, Trophy, Clock } from 'lucide-react';
import Link from 'next/link';
import { useTenant } from '@/app/providers/tenant-provider';
import { StudentOnly } from '@/components/portals/MembershipGuard';

interface LanguagesPageProps {
  params: {
    tenant: string;
    membership: string;
  };
}

export default function LanguagesPage({ params }: LanguagesPageProps) {
  const { tenantSlug } = useTenant();

  // Build URL helper
  const buildUrl = (path: string) => `/${tenantSlug}/${params.membership}${path}`;

  // Mock data
  const languages = [
    {
      id: 1,
      name: 'Cree',
      nativeName: 'ᓀᐦᐃᔭᐍᐏᐣ',
      description: 'Learn the Cree language and connect with Indigenous culture',
      progress: 65,
      lessonsCompleted: 24,
      totalLessons: 37,
      streak: 7,
      isActive: true,
      difficulty: 'Beginner',
      estimatedTime: '3 months'
    },
    {
      id: 2,
      name: 'Ojibwe',
      nativeName: 'ᐊᓂᔑᓈᐯᒧᐎᓐ',
      description: 'Discover the rich Ojibwe language and traditions',
      progress: 0,
      lessonsCompleted: 0,
      totalLessons: 42,
      streak: 0,
      isActive: false,
      difficulty: 'Beginner',
      estimatedTime: '4 months'
    },
    {
      id: 3,
      name: 'Inuktitut',
      nativeName: 'ᐃᓄᒃᑎᑐᑦ',
      description: 'Explore the Inuktitut language of the Arctic',
      progress: 0,
      lessonsCompleted: 0,
      totalLessons: 35,
      streak: 0,
      isActive: false,
      difficulty: 'Intermediate',
      estimatedTime: '5 months'
    }
  ];

  const activeLanguage = languages.find(lang => lang.isActive);
  const availableLanguages = languages.filter(lang => !lang.isActive);

  return (
    <StudentOnly fallback={
      <div className="text-center py-12">
        <h2 className="text-2xl font-bold">Access Restricted</h2>
        <p className="text-muted-foreground">This page is only available to students.</p>
      </div>
    }>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Languages</h1>
            <p className="text-muted-foreground">
              Continue your language learning journey
            </p>
          </div>
          <Button asChild variant="outline">
            <Link href={buildUrl('/progress')}>
              <Trophy className="mr-2 h-4 w-4" />
              View Progress
            </Link>
          </Button>
        </div>

        {/* Current Language */}
        {activeLanguage && (
          <Card className="border-primary/20 bg-primary/5">
            <CardHeader>
              <div className="flex items-center justify-between">
                <div>
                  <CardTitle className="text-xl flex items-center gap-2">
                    <Globe className="h-5 w-5 text-primary" />
                    {activeLanguage.name}
                    <span className="text-lg text-muted-foreground">
                      {activeLanguage.nativeName}
                    </span>
                  </CardTitle>
                  <CardDescription className="text-base">
                    {activeLanguage.description}
                  </CardDescription>
                </div>
                <Button asChild>
                  <Link href={buildUrl(`/languages/${activeLanguage.id}/continue`)}>
                    <Play className="mr-2 h-4 w-4" />
                    Continue Learning
                  </Link>
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              <div className="space-y-4">
                {/* Progress */}
                <div>
                  <div className="flex items-center justify-between mb-2">
                    <span className="text-sm font-medium">Overall Progress</span>
                    <span className="text-sm text-muted-foreground">
                      {activeLanguage.progress}%
                    </span>
                  </div>
                  <Progress value={activeLanguage.progress} className="h-2" />
                </div>

                {/* Stats */}
                <div className="grid grid-cols-3 gap-4">
                  <div className="text-center">
                    <div className="text-2xl font-bold text-primary">
                      {activeLanguage.lessonsCompleted}
                    </div>
                    <div className="text-sm text-muted-foreground">
                      Lessons Completed
                    </div>
                  </div>
                  <div className="text-center">
                    <div className="text-2xl font-bold text-orange-600">
                      {activeLanguage.streak}
                    </div>
                    <div className="text-sm text-muted-foreground">
                      Day Streak
                    </div>
                  </div>
                  <div className="text-center">
                    <div className="text-2xl font-bold text-green-600">
                      {activeLanguage.totalLessons - activeLanguage.lessonsCompleted}
                    </div>
                    <div className="text-sm text-muted-foreground">
                      Lessons Remaining
                    </div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Available Languages */}
        <div>
          <h2 className="text-2xl font-bold mb-4">Available Languages</h2>
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            {availableLanguages.map((language) => (
              <Card key={language.id} className="hover:shadow-md transition-shadow">
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Globe className="h-5 w-5" />
                    {language.name}
                  </CardTitle>
                  <div className="text-sm text-muted-foreground">
                    {language.nativeName}
                  </div>
                  <CardDescription>{language.description}</CardDescription>
                </CardHeader>
                <CardContent>
                  <div className="space-y-3">
                    {/* Language Info */}
                    <div className="flex items-center justify-between text-sm">
                      <span className="flex items-center">
                        <BookOpen className="mr-1 h-3 w-3" />
                        {language.totalLessons} lessons
                      </span>
                      <span className="flex items-center">
                        <Clock className="mr-1 h-3 w-3" />
                        {language.estimatedTime}
                      </span>
                    </div>
                    
                    <div className="flex items-center justify-between text-sm">
                      <span className="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs">
                        {language.difficulty}
                      </span>
                    </div>

                    <Button asChild className="w-full">
                      <Link href={buildUrl(`/languages/${language.id}/start`)}>
                        Start Learning
                      </Link>
                    </Button>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        </div>

        {/* Empty State */}
        {languages.length === 0 && (
          <Card className="text-center py-12">
            <CardContent>
              <Globe className="mx-auto h-12 w-12 text-muted-foreground mb-4" />
              <h3 className="text-lg font-semibold mb-2">No Languages Available</h3>
              <p className="text-muted-foreground mb-4">
                Your organization hasn't added any language courses yet.
              </p>
              <p className="text-sm text-muted-foreground">
                Contact your administrator to request new language courses.
              </p>
            </CardContent>
          </Card>
        )}
      </div>
    </StudentOnly>
  );
}
