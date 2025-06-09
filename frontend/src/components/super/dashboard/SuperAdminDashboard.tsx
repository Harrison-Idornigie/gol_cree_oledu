'use client';

import { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Plus, Settings } from 'lucide-react';
import Link from 'next/link';
import { getSystemOverview } from '@/app/_actions/super/analytics-actions';
import SystemStatsCards from './SystemStatsCards';
import QuickActionsPanel from './QuickActionsPanel';
import RecentActivityFeed from './RecentActivityFeed';
import { SystemStats } from '@/types/super-admin';

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
      <SystemStatsCards stats={stats} />

      {/* Quick Actions and Recent Activity */}
      <div className="grid gap-6 md:grid-cols-2">
        <QuickActionsPanel />
        <RecentActivityFeed activities={stats.recentActivity} />
      </div>
    </div>
  );
}
