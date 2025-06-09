'use client';

import { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
  Users, 
  Database, 
  Activity, 
  ArrowUpDown,
  Download
} from 'lucide-react';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { getTenantUsage } from '@/app/_actions/super/analytics-actions';
import { TenantUsage } from '@/types/super/super-admin';

export default function TenantUsageAnalytics() {
  const [tenantUsage, setTenantUsage] = useState<TenantUsage[]>([]);
  const [loading, setLoading] = useState(true);
  const [sortBy, setSortBy] = useState<'users_count' | 'storage_used' | 'api_calls_count' | 'last_activity'>('users_count');
  const [sortOrder, setSortOrder] = useState<'asc' | 'desc'>('desc');

  useEffect(() => {
    const fetchTenantUsage = async () => {
      try {
        setLoading(true);
        
        const result = await getTenantUsage({
          limit: 50,
          sort_by: sortBy,
          sort_order: sortOrder
        });

        if (result.data) {
          setTenantUsage(result.data);
        }
      } catch (error) {
        console.error('Error fetching tenant usage:', error);
      } finally {
        setLoading(false);
      }
    };

    fetchTenantUsage();
  }, [sortBy, sortOrder]);

  const formatBytes = (bytes: number) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  const formatLastActivity = (dateString: string) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffInHours = Math.floor((now.getTime() - date.getTime()) / (1000 * 60 * 60));
    
    if (diffInHours < 1) return 'Just now';
    if (diffInHours < 24) return `${diffInHours}h ago`;
    const diffInDays = Math.floor(diffInHours / 24);
    if (diffInDays < 7) return `${diffInDays}d ago`;
    return date.toLocaleDateString();
  };

  const getActivityBadge = (lastActivity: string) => {
    const date = new Date(lastActivity);
    const now = new Date();
    const diffInHours = Math.floor((now.getTime() - date.getTime()) / (1000 * 60 * 60));
    
    if (diffInHours < 24) return <Badge className="bg-green-100 text-green-800">Active</Badge>;
    if (diffInHours < 168) return <Badge className="bg-yellow-100 text-yellow-800">Recent</Badge>;
    return <Badge className="bg-gray-100 text-gray-800">Inactive</Badge>;
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
      {/* Controls */}
      <div className="flex justify-between items-center">
        <div className="flex gap-4 items-center">
          <div className="flex items-center gap-2">
            <span className="text-sm font-medium">Sort by:</span>
            <Select value={sortBy} onValueChange={(value: any) => setSortBy(value)}>
              <SelectTrigger className="w-[180px]">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="users_count">User Count</SelectItem>
                <SelectItem value="storage_used">Storage Used</SelectItem>
                <SelectItem value="api_calls_count">API Calls</SelectItem>
                <SelectItem value="last_activity">Last Activity</SelectItem>
              </SelectContent>
            </Select>
          </div>
          
          <Button
            variant="outline"
            size="sm"
            onClick={() => setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc')}
            className="flex items-center gap-1"
          >
            <ArrowUpDown className="h-4 w-4" />
            {sortOrder === 'asc' ? 'Ascending' : 'Descending'}
          </Button>
        </div>

        <Button variant="outline" className="flex items-center gap-2">
          <Download className="h-4 w-4" />
          Export Data
        </Button>
      </div>

      {/* Tenant Usage List */}
      <div className="grid gap-4">
        {tenantUsage.map((tenant) => (
          <Card key={tenant.tenant_id} className="hover:shadow-md transition-shadow">
            <CardContent className="pt-6">
              <div className="flex items-center justify-between">
                <div className="flex-1">
                  <div className="flex items-center gap-3 mb-2">
                    <h3 className="text-lg font-semibold text-gray-900">{tenant.tenant_name}</h3>
                    {getActivityBadge(tenant.last_activity)}
                  </div>
                  
                  <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div className="flex items-center gap-2">
                      <Users className="h-4 w-4 text-blue-500" />
                      <div>
                        <p className="font-medium">{tenant.users_count} users</p>
                        <p className="text-gray-500">{tenant.active_users_count} active</p>
                      </div>
                    </div>
                    
                    <div className="flex items-center gap-2">
                      <Database className="h-4 w-4 text-green-500" />
                      <div>
                        <p className="font-medium">{formatBytes(tenant.storage_used)}</p>
                        <p className="text-gray-500">Storage used</p>
                      </div>
                    </div>
                    
                    <div className="flex items-center gap-2">
                      <Activity className="h-4 w-4 text-purple-500" />
                      <div>
                        <p className="font-medium">{tenant.api_calls_count.toLocaleString()}</p>
                        <p className="text-gray-500">API calls</p>
                      </div>
                    </div>
                    
                    <div>
                      <p className="font-medium">Last Activity</p>
                      <p className="text-gray-500">{formatLastActivity(tenant.last_activity)}</p>
                    </div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      {tenantUsage.length === 0 && (
        <Card>
          <CardContent className="pt-6">
            <div className="text-center py-8">
              <p className="text-gray-500">No tenant usage data available</p>
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
