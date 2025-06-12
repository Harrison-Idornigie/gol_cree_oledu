'use client';

import { useState, useEffect } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Users, BookOpen, TrendingUp, Settings, Plus, GraduationCap, Globe } from 'lucide-react';
import Link from 'next/link';
import { useAuth, useTenantAccess } from '@/app/providers/auth-provider';

interface DashboardPageProps {
  params: {
    tenant: string;
    membership: string;
  };
}

interface AdminStats {
  totalUsers: number;
  totalTeachers: number;
  totalStudents: number;
  totalLearningPaths: number;
  totalLessons: number;
  activeUsers: number;
}

interface StudentStats {
  completedLessons: number;
  currentStreak: number;
  totalPoints: number;
  activePaths: number;
}

export default function DashboardPage({ params }: DashboardPageProps) {
  const { user, isLoading, currentTenant, tenantSlug } = useAuth();
  const { canAccessMembership } = useTenantAccess();

  // Build URL helper
  const buildUrl = (path: string) => `/${tenantSlug}/${params.membership}${path}`;

  // Debug logging
  useEffect(() => {
    console.log('🔍 Dashboard Debug Info:', {
      user: user ? {
        id: user.id,
        email: user.email,
        membership: user.membership,
        tenantSlug: user.tenant?.slug
      } : null,
      currentTenant,
      tenantSlug,
      requestedMembership: params.membership,
      isLoading,
      canAccessAdmin: canAccessMembership('admin'),
      canAccessTeam: canAccessMembership('team'),
      canAccessStudent: canAccessMembership('student')
    });
  }, [user, currentTenant, tenantSlug, params.membership, isLoading, canAccessMembership]);

  // Show loading state while authentication is being resolved
  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <div className="text-center space-y-2">
          <div className="text-lg">Loading dashboard...</div>
          <div className="text-sm text-muted-foreground">
            Initializing...
          </div>
        </div>
      </div>
    );
  }

  // Check if user is authenticated
  if (!user) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <div className="text-center">
          <h2 className="text-xl font-semibold text-red-600">Authentication Required</h2>
          <p className="text-muted-foreground">Please log in to access the dashboard.</p>
        </div>
      </div>
    );
  }

  // Check if tenant context is available
  if (!currentTenant || !tenantSlug) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <div className="text-center">
          <h2 className="text-xl font-semibold text-red-600">Tenant Not Found</h2>
          <p className="text-muted-foreground">Unable to load tenant context.</p>
        </div>
      </div>
    );
  }

  // Render appropriate dashboard based on user membership and requested path
  const renderDashboard = () => {
    console.log('🎯 Rendering dashboard for:', {
      userMembership: user.membership,
      requestedMembership: params.membership,
      canAccessAdmin: canAccessMembership('admin'),
      canAccessTeam: canAccessMembership('team'),
      canAccessStudent: canAccessMembership('student')
    });

    // Check if user can access the requested membership level
    if (!canAccessMembership(params.membership)) {
      return (
        <div className="flex items-center justify-center min-h-[400px]">
          <div className="text-center">
            <h2 className="text-xl font-semibold text-red-600">Access Denied</h2>
            <p className="text-muted-foreground">
              You don&apos;t have permission to access the {params.membership} dashboard.
            </p>
          </div>
        </div>
      );
    }

    // Render dashboard based on requested membership
    switch (params.membership) {
      case 'admin':
        return (
          <AdminDashboard
            tenant={currentTenant}
            buildUrl={buildUrl}
            membership={params.membership}
          />
        );
      case 'team':
        return (
          <TeamDashboard
            tenant={currentTenant}
            buildUrl={buildUrl}
            membership={params.membership}
          />
        );
      case 'student':
        return (
          <StudentDashboard
            tenant={currentTenant}
            buildUrl={buildUrl}
            membership={params.membership}
            user={user}
          />
        );
      default:
        return (
          <div className="flex items-center justify-center min-h-[400px]">
            <div className="text-center">
              <h2 className="text-xl font-semibold text-red-600">Invalid Dashboard</h2>
              <p className="text-muted-foreground">
                Unknown membership type: {params.membership}
              </p>
            </div>
          </div>
        );
    }
  };

  return (
    <div className="space-y-6">
      {renderDashboard()}
    </div>
  );
}

// Admin Dashboard Component
function AdminDashboard({ tenant, buildUrl, membership }: any) {
  const [stats, setStats] = useState<AdminStats>({
    totalUsers: 156,
    totalTeachers: 12,
    totalStudents: 144,
    totalLearningPaths: 8,
    totalLessons: 45,
    activeUsers: 89,
  });

  return (
    <>
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">
            {tenant?.name || 'Organization'} Dashboard
          </h1>
          <p className="text-muted-foreground">
            Manage your organization&apos;s language learning platform
          </p>
        </div>
        <div className="flex gap-2">
          <Button asChild>
            <Link href={buildUrl('/users/invite')}>
              <Plus className="mr-2 h-4 w-4" />
              Invite Users
            </Link>
          </Button>
          <Button variant="outline" asChild>
            <Link href={buildUrl('/settings')}>
              <Settings className="mr-2 h-4 w-4" />
              Settings
            </Link>
          </Button>
        </div>
      </div>

      {/* Stats Cards */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Total Users</CardTitle>
            <Users className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{stats.totalUsers}</div>
            <p className="text-xs text-muted-foreground">
              {stats.totalTeachers} teachers, {stats.totalStudents} students
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Learning Paths</CardTitle>
            <BookOpen className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{stats.totalLearningPaths}</div>
            <p className="text-xs text-muted-foreground">
              {stats.totalLessons} total lessons
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Active Users</CardTitle>
            <TrendingUp className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{stats.activeUsers}</div>
            <p className="text-xs text-muted-foreground">
              {Math.round((stats.activeUsers / stats.totalUsers) * 100)}% engagement rate
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">This Month</CardTitle>
            <TrendingUp className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">+12%</div>
            <p className="text-xs text-muted-foreground">
              User growth this month
            </p>
          </CardContent>
        </Card>
      </div>

      {/* Quick Actions */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        <Card>
          <CardHeader>
            <CardTitle>User Management</CardTitle>
            <CardDescription>
              Manage teachers and students in your organization
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-2">
            <Button asChild className="w-full">
              <Link href={buildUrl('/users')}>View All Users</Link>
            </Button>
            <Button variant="outline" asChild className="w-full">
              <Link href={buildUrl('/users/invite')}>Invite New Users</Link>
            </Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Content Management</CardTitle>
            <CardDescription>
              Manage learning paths, lessons, and content
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-2">
            <Button asChild className="w-full">
              <Link href={buildUrl('/learning-paths')}>Manage Content</Link>
            </Button>
            <Button variant="outline" asChild className="w-full">
              <Link href={buildUrl('/learning-paths/new')}>Create New Content</Link>
            </Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Analytics & Reports</CardTitle>
            <CardDescription>
              View detailed analytics and generate reports
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-2">
            <Button asChild className="w-full">
              <Link href={buildUrl('/stats')}>View Analytics</Link>
            </Button>
            <Button variant="outline" asChild className="w-full">
              <Link href={buildUrl('/reports')}>Generate Reports</Link>
            </Button>
          </CardContent>
        </Card>
      </div>
    </>
  );
}

// Team Dashboard Component
function TeamDashboard({ tenant, buildUrl, membership }: any) {
  return (
    <>
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Team Dashboard</h1>
          <p className="text-muted-foreground">
            Create and manage content for {tenant?.name || 'your organization'}
          </p>
        </div>
        <Button asChild>
          <Link href={buildUrl('/learning-paths/new')}>
            <Plus className="mr-2 h-4 w-4" />
            Create Content
          </Link>
        </Button>
      </div>

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card className="p-4">
          <h3 className="mb-2 font-semibold">Learning Paths</h3>
          <p className="text-sm text-muted-foreground">
            Manage learning paths, units, and lessons
          </p>
          <Button asChild className="w-full mt-4">
            <Link href={buildUrl('/learning-paths')}>Manage Paths</Link>
          </Button>
        </Card>
        
        <Card className="p-4">
          <h3 className="mb-2 font-semibold">Team Members</h3>
          <p className="text-sm text-muted-foreground">
            Collaborate with other team members
          </p>
          <Button asChild className="w-full mt-4">
            <Link href={buildUrl('/team-members')}>View Team</Link>
          </Button>
        </Card>
        
        <Card className="p-4">
          <h3 className="mb-2 font-semibold">Progress Reports</h3>
          <p className="text-sm text-muted-foreground">
            Track student progress and performance
          </p>
          <Button asChild className="w-full mt-4">
            <Link href={buildUrl('/reports')}>View Reports</Link>
          </Button>
        </Card>

        <Card className="p-4">
          <h3 className="mb-2 font-semibold">Content Library</h3>
          <p className="text-sm text-muted-foreground">
            Access shared resources and materials
          </p>
          <Button asChild className="w-full mt-4">
            <Link href={buildUrl('/content')}>Browse Library</Link>
          </Button>
        </Card>
      </div>
    </>
  );
}

// Student Dashboard Component  
function StudentDashboard({ tenant, buildUrl, membership, user }: any) {
  const [stats, setStats] = useState<StudentStats>({
    completedLessons: 24,
    currentStreak: 7,
    totalPoints: 2450,
    activePaths: 2,
  });

  return (
    <>
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">
            Welcome back, {user?.name?.split(' ')[0] || 'Student'}!
          </h1>
          <p className="text-muted-foreground">
            Continue your language learning journey
          </p>
        </div>
        <Button asChild>
          <Link href={buildUrl('/languages')}>
            <Globe className="mr-2 h-4 w-4" />
            Browse Languages
          </Link>
        </Button>
      </div>

      {/* Student Stats */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Lessons Completed</CardTitle>
            <GraduationCap className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{stats.completedLessons}</div>
            <p className="text-xs text-muted-foreground">
              Keep up the great work!
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Current Streak</CardTitle>
            <TrendingUp className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{stats.currentStreak} days</div>
            <p className="text-xs text-muted-foreground">
              Don&apos;t break the chain!
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Total Points</CardTitle>
            <BookOpen className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{stats.totalPoints}</div>
            <p className="text-xs text-muted-foreground">
              You&apos;re doing amazing!
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Active Paths</CardTitle>
            <Globe className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{stats.activePaths}</div>
            <p className="text-xs text-muted-foreground">
              Languages in progress
            </p>
          </CardContent>
        </Card>
      </div>

      {/* Quick Actions for Students */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        <Card>
          <CardHeader>
            <CardTitle>Continue Learning</CardTitle>
            <CardDescription>
              Pick up where you left off
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Button asChild className="w-full">
              <Link href={buildUrl('/languages')}>Resume Lessons</Link>
            </Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>My Progress</CardTitle>
            <CardDescription>
              Track your learning journey
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Button asChild className="w-full">
              <Link href={buildUrl('/progress')}>View Progress</Link>
            </Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Profile & Settings</CardTitle>
            <CardDescription>
              Customize your learning experience
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Button asChild className="w-full">
              <Link href={buildUrl('/profile')}>Manage Profile</Link>
            </Button>
          </CardContent>
        </Card>
      </div>
    </>
  );
}
