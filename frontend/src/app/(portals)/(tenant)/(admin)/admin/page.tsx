'use client';

import { useState, useEffect } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Users, BookOpen, TrendingUp, Settings, Plus } from 'lucide-react';
import Link from 'next/link';

interface AdminStats {
  totalUsers: number;
  totalTeachers: number;
  totalStudents: number;
  totalLearningPaths: number;
  totalLessons: number;
  activeUsers: number;
}

export default function TenantAdminDashboard() {
  const [stats, setStats] = useState<AdminStats>({
    totalUsers: 0,
    totalTeachers: 0,
    totalStudents: 0,
    totalLearningPaths: 0,
    totalLessons: 0,
    activeUsers: 0,
  });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // TODO: Fetch admin stats from API
    // For now, using mock data
    setTimeout(() => {
      setStats({
        totalUsers: 156,
        totalTeachers: 12,
        totalStudents: 144,
        totalLearningPaths: 8,
        totalLessons: 45,
        activeUsers: 89,
      });
      setLoading(false);
    }, 1000);
  }, []);

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="text-lg">Loading dashboard...</div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Tenant Admin Dashboard</h1>
          <p className="text-muted-foreground">
            Manage your organization's language learning platform
          </p>
        </div>
        <div className="flex gap-2">
          <Button asChild>
            <Link href="/admin/users/invite">
              <Plus className="mr-2 h-4 w-4" />
              Invite Users
            </Link>
          </Button>
          <Button variant="outline" asChild>
            <Link href="/admin/settings">
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
              <Link href="/admin/users">View All Users</Link>
            </Button>
            <Button variant="outline" asChild className="w-full">
              <Link href="/admin/users/invite">Invite New Users</Link>
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
              <Link href="/admin/content">Manage Content</Link>
            </Button>
            <Button variant="outline" asChild className="w-full">
              <Link href="/admin/content/create">Create New Content</Link>
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
              <Link href="/admin/analytics">View Analytics</Link>
            </Button>
            <Button variant="outline" asChild className="w-full">
              <Link href="/admin/reports">Generate Reports</Link>
            </Button>
          </CardContent>
        </Card>
      </div>

      {/* Recent Activity */}
      <Card>
        <CardHeader>
          <CardTitle>Recent Activity</CardTitle>
          <CardDescription>
            Latest activities in your organization
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="space-y-4">
            <div className="flex items-center space-x-4">
              <div className="w-2 h-2 bg-green-500 rounded-full"></div>
              <div className="flex-1">
                <p className="text-sm font-medium">New user registered</p>
                <p className="text-xs text-muted-foreground">john.doe@example.com joined as a student</p>
              </div>
              <div className="text-xs text-muted-foreground">2 hours ago</div>
            </div>
            <div className="flex items-center space-x-4">
              <div className="w-2 h-2 bg-blue-500 rounded-full"></div>
              <div className="flex-1">
                <p className="text-sm font-medium">Learning path completed</p>
                <p className="text-xs text-muted-foreground">Spanish Basics completed by 5 students</p>
              </div>
              <div className="text-xs text-muted-foreground">4 hours ago</div>
            </div>
            <div className="flex items-center space-x-4">
              <div className="w-2 h-2 bg-purple-500 rounded-full"></div>
              <div className="flex-1">
                <p className="text-sm font-medium">New lesson created</p>
                <p className="text-xs text-muted-foreground">Advanced Grammar lesson added by teacher</p>
              </div>
              <div className="text-xs text-muted-foreground">1 day ago</div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
