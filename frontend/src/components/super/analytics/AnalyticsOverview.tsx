'use client';

import { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { 
  TrendingUp, 
  TrendingDown, 
  Activity, 
  Database,
  Zap,
  Clock
} from 'lucide-react';
import { getSystemOverview, getPerformanceMetrics } from '@/app/_actions/super/analytics-actions';
import { SystemOverview, PerformanceMetrics } from '@/types/super/super-admin';

export default function AnalyticsOverview() {
  const [overview, setOverview] = useState<SystemOverview | null>(null);
  const [performance, setPerformance] = useState<PerformanceMetrics | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchData = async () => {
      try {
        setLoading(true);
        
        const [overviewResult, performanceResult] = await Promise.all([
          getSystemOverview(),
          getPerformanceMetrics('24h')
        ]);

        if (overviewResult.data) {
          setOverview(overviewResult.data);
        }
        
        if (performanceResult.data) {
          setPerformance(performanceResult.data);
        }
      } catch (error) {
        console.error('Error fetching analytics data:', error);
      } finally {
        setLoading(false);
      }
    };

    fetchData();
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
      {/* System Overview Cards */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Total Tenants</CardTitle>
            <TrendingUp className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{overview?.total_tenants || 0}</div>
            <p className="text-xs text-muted-foreground">
              {overview?.active_tenants || 0} active
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Total Users</CardTitle>
            <Activity className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{overview?.total_users?.toLocaleString() || 0}</div>
            <p className="text-xs text-muted-foreground">
              {overview?.active_users || 0} active
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Storage Used</CardTitle>
            <Database className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {overview ? Math.round((overview.storage_used / 1024 / 1024 / 1024) * 100) / 100 : 0} GB
            </div>
            <p className="text-xs text-muted-foreground">
              of {overview ? Math.round((overview.storage_limit / 1024 / 1024 / 1024) * 100) / 100 : 0} GB limit
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">System Uptime</CardTitle>
            <Clock className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {overview ? Math.round((overview.system_uptime / 3600) * 100) / 100 : 0}h
            </div>
            <p className="text-xs text-muted-foreground">
              Current uptime
            </p>
          </CardContent>
        </Card>
      </div>

      {/* Performance Metrics */}
      {performance && (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Response Time</CardTitle>
              <Zap className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{performance.avg_response_time}ms</div>
              <p className="text-xs text-muted-foreground">
                Average response time
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">API Calls</CardTitle>
              <Activity className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{performance.api_calls_per_minute}</div>
              <p className="text-xs text-muted-foreground">
                Calls per minute
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Error Rate</CardTitle>
              <TrendingDown className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{(performance.error_rate * 100).toFixed(2)}%</div>
              <div className="flex items-center gap-1 mt-1">
                <Badge variant={performance.error_rate < 0.01 ? "default" : "destructive"} className="text-xs">
                  {performance.error_rate < 0.01 ? "Good" : "High"}
                </Badge>
              </div>
            </CardContent>
          </Card>
        </div>
      )}

      {/* Database Performance */}
      {performance?.database_performance && (
        <Card>
          <CardHeader>
            <CardTitle>Database Performance</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid gap-4 md:grid-cols-3">
              <div>
                <p className="text-sm font-medium">Average Query Time</p>
                <p className="text-2xl font-bold">{performance.database_performance.avg_query_time}ms</p>
              </div>
              <div>
                <p className="text-sm font-medium">Slow Queries</p>
                <p className="text-2xl font-bold">{performance.database_performance.slow_queries_count}</p>
              </div>
              <div>
                <p className="text-sm font-medium">Cache Hit Rate</p>
                <p className="text-2xl font-bold">{(performance.cache_hit_rate * 100).toFixed(1)}%</p>
              </div>
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
