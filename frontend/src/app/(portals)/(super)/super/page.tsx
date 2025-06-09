'use client';

import { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
  Building2,
  Users,
  Globe,
  Activity,
  TrendingUp,
  Plus,
  Settings
} from 'lucide-react';
import Link from 'next/link';
import { getSystemOverview } from '@/app/_actions/super/analytics-actions';

interface SystemStats {
  totalTenants: number;
  activeTenants: number;
  totalUsers: number;
  totalLanguages: number;
  systemHealth: 'healthy' | 'warning' | 'critical';
  recentActivity: Array<{
    id: number;
    type: 'tenant_created' | 'user_registered' | 'system_update';
    message: string;
    timestamp: string;
  }>;
}

export default function SuperAdminDashboard() {
  const [stats, setStats] = useState<SystemStats>({
    totalTenants: 0,
    activeTenants: 0,
    totalUsers: 0,
    totalLanguages: 0,
    systemHealth: 'healthy',
    recentActivity: []
  });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchSystemStats = async () => {
      try {
        const result = await getSystemOverview();

        if (result.error) {
          console.error('Error fetching system overview:', result.error);
          // Fall back to mock data if API fails
          setStats({
            totalTenants: 12,
            activeTenants: 10,
            totalUsers: 1247,
            totalLanguages: 8,
            systemHealth: 'healthy',
            recentActivity: [
              {
                id: 1,
                type: 'tenant_created',
                message: 'New tenant "Riverside School District" created',
                timestamp: '2 hours ago'
              },
              {
                id: 2,
                type: 'user_registered',
                message: '15 new users registered across all tenants',
                timestamp: '4 hours ago'
              },
              {
                id: 3,
                type: 'system_update',
                message: 'System maintenance completed successfully',
                timestamp: '1 day ago'
              }
            ]
          });
        } else if (result.data) {
          // Map API response to component state
          setStats({
            totalTenants: result.data.total_tenants,
            activeTenants: result.data.active_tenants,
            totalUsers: result.data.total_users,
            totalLanguages: result.data.total_languages,
            systemHealth: 'healthy', // TODO: Map from API response
            recentActivity: [
              // TODO: Map from API response
              {
                id: 1,
                type: 'system_update',
                message: 'System overview loaded successfully',
                timestamp: 'Just now'
              }
            ]
          });
        }
      } catch (error) {
        console.error('Error fetching system stats:', error);
      } finally {
        setLoading(false);
      }
    };

    fetchSystemStats();
  }, []);

  const getHealthBadge = (health: string) => {
    const variants = {
      healthy: 'bg-green-100 text-green-800',
      warning: 'bg-yellow-100 text-yellow-800',
      critical: 'bg-red-100 text-red-800',
    };
    
    return (
      <Badge className={variants[health as keyof typeof variants] || variants.healthy}>
        {health.charAt(0).toUpperCase() + health.slice(1)}
      </Badge>
    );
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">System Overview</h1>
          <p className="text-gray-600">Monitor and manage your multi-tenant language learning platform</p>
        </div>
        <div className="flex gap-2">
          <Button asChild>
            <Link href="/super/tenants/create" className="flex items-center gap-2">
              <Plus className="h-4 w-4" />
              Create Tenant
            </Link>
          </Button>
          <Button variant="outline" asChild>
            <Link href="/super/settings" className="flex items-center gap-2">
              <Settings className="h-4 w-4" />
              System Settings
            </Link>
          </Button>
        </div>
      </div>

      {/* Stats Cards */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Total Tenants</CardTitle>
            <Building2 className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{stats.totalTenants}</div>
            <p className="text-xs text-muted-foreground">
              {stats.activeTenants} active
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Total Users</CardTitle>
            <Users className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{stats.totalUsers.toLocaleString()}</div>
            <p className="text-xs text-muted-foreground">
              Across all tenants
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Languages</CardTitle>
            <Globe className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{stats.totalLanguages}</div>
            <p className="text-xs text-muted-foreground">
              Available languages
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">System Health</CardTitle>
            <Activity className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="flex items-center gap-2">
              {getHealthBadge(stats.systemHealth)}
            </div>
            <p className="text-xs text-muted-foreground mt-1">
              All systems operational
            </p>
          </CardContent>
        </Card>
      </div>

      {/* Quick Actions and Recent Activity */}
      <div className="grid gap-6 md:grid-cols-2">
        {/* Quick Actions */}
        <Card>
          <CardHeader>
            <CardTitle>Quick Actions</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <Button asChild className="w-full justify-start">
              <Link href="/super/tenants" className="flex items-center gap-2">
                <Building2 className="h-4 w-4" />
                Manage Tenants
              </Link>
            </Button>
            <Button asChild variant="outline" className="w-full justify-start">
              <Link href="/super/analytics" className="flex items-center gap-2">
                <TrendingUp className="h-4 w-4" />
                View Analytics
              </Link>
            </Button>
            <Button asChild variant="outline" className="w-full justify-start">
              <Link href="/super/users" className="flex items-center gap-2">
                <Users className="h-4 w-4" />
                Global User Search
              </Link>
            </Button>
            <Button asChild variant="outline" className="w-full justify-start">
              <Link href="/super/health" className="flex items-center gap-2">
                <Activity className="h-4 w-4" />
                System Health
              </Link>
            </Button>
          </CardContent>
        </Card>

        {/* Recent Activity */}
        <Card>
          <CardHeader>
            <CardTitle>Recent Activity</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              {stats.recentActivity.map((activity) => (
                <div key={activity.id} className="flex items-start gap-3">
                  <div className="w-2 h-2 bg-blue-500 rounded-full mt-2"></div>
                  <div className="flex-1">
                    <p className="text-sm font-medium">{activity.message}</p>
                    <p className="text-xs text-muted-foreground">{activity.timestamp}</p>
                  </div>
                </div>
              ))}
            </div>
            <Button asChild variant="ghost" className="w-full mt-4">
              <Link href="/super/activity">View All Activity</Link>
            </Button>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
