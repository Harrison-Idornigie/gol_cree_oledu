'use client';

import { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
  CheckCircle, 
  AlertTriangle, 
  XCircle, 
  RefreshCw,
  Database,
  Zap,
  HardDrive,
  Activity
} from 'lucide-react';
import { getSystemHealth, performHealthCheck } from '@/app/_actions/super/system-actions';
import { useToast } from '@/hooks/use-toast';

interface HealthCheck {
  status: 'healthy' | 'warning' | 'critical';
  checks: {
    database: {
      status: 'healthy' | 'warning' | 'critical';
      response_time: number;
      message?: string;
    };
    cache: {
      status: 'healthy' | 'warning' | 'critical';
      hit_rate: number;
      message?: string;
    };
    storage: {
      status: 'healthy' | 'warning' | 'critical';
      used_percentage: number;
      message?: string;
    };
    api: {
      status: 'healthy' | 'warning' | 'critical';
      response_time: number;
      message?: string;
    };
  };
  last_check: string;
}

export default function SystemHealthDashboard() {
  const [health, setHealth] = useState<HealthCheck | null>(null);
  const [loading, setLoading] = useState(true);
  const [checking, setChecking] = useState(false);
  const { toast } = useToast();

  useEffect(() => {
    const fetchHealthData = async () => {
      try {
        const result = await getSystemHealth();

        if (result.data) {
          setHealth(result.data);
        } else if (result.error) {
          toast({
            title: 'Error',
            description: result.error,
            variant: 'destructive',
          });
        }
      } catch (error) {
        console.error('Error fetching health data:', error);
      } finally {
        setLoading(false);
      }
    };

    fetchHealthData();
  }, [toast]);

  const runHealthCheck = async () => {
    try {
      setChecking(true);
      
      const result = await performHealthCheck();
      
      if (result.data) {
        setHealth(result.data);
        toast({
          title: 'Success',
          description: 'Health check completed successfully',
        });
      } else if (result.error) {
        toast({
          title: 'Error',
          description: result.error,
          variant: 'destructive',
        });
      }
    } catch {
      toast({
        title: 'Error',
        description: 'Failed to run health check',
        variant: 'destructive',
      });
    } finally {
      setChecking(false);
    }
  };

  const getStatusIcon = (status: 'healthy' | 'warning' | 'critical') => {
    switch (status) {
      case 'healthy':
        return <CheckCircle className="h-5 w-5 text-green-500" />;
      case 'warning':
        return <AlertTriangle className="h-5 w-5 text-yellow-500" />;
      case 'critical':
        return <XCircle className="h-5 w-5 text-red-500" />;
      default:
        return <XCircle className="h-5 w-5 text-gray-500" />;
    }
  };

  const getStatusBadge = (status: 'healthy' | 'warning' | 'critical') => {
    const variants = {
      healthy: 'bg-green-100 text-green-800',
      warning: 'bg-yellow-100 text-yellow-800',
      critical: 'bg-red-100 text-red-800',
    };
    
    return (
      <Badge className={variants[status]}>
        {status.charAt(0).toUpperCase() + status.slice(1)}
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
          <h1 className="text-2xl font-bold text-gray-900">System Health</h1>
          <p className="text-gray-600">Monitor system components and performance</p>
        </div>
        <Button 
          onClick={runHealthCheck} 
          disabled={checking}
          className="flex items-center gap-2"
        >
          <RefreshCw className={`h-4 w-4 ${checking ? 'animate-spin' : ''}`} />
          {checking ? 'Checking...' : 'Run Health Check'}
        </Button>
      </div>

      {/* Overall Status */}
      {health && (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              {getStatusIcon(health.status)}
              System Status
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="flex items-center justify-between">
              <div>
                <p className="text-lg font-semibold">Overall System Health</p>
                <p className="text-sm text-gray-600">Last checked: {new Date(health.last_check).toLocaleString()}</p>
              </div>
              {getStatusBadge(health.status)}
            </div>
          </CardContent>
        </Card>
      )}

      {/* Component Status */}
      {health && (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Database</CardTitle>
              <Database className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="flex items-center gap-2 mb-2">
                {getStatusIcon(health.checks.database.status)}
                {getStatusBadge(health.checks.database.status)}
              </div>
              <p className="text-xs text-muted-foreground">
                Connection and performance ({health.checks.database.response_time}ms)
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Cache</CardTitle>
              <Zap className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="flex items-center gap-2 mb-2">
                {getStatusIcon(health.checks.cache.status)}
                {getStatusBadge(health.checks.cache.status)}
              </div>
              <p className="text-xs text-muted-foreground">
                Hit rate: {(health.checks.cache.hit_rate * 100).toFixed(1)}%
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Storage</CardTitle>
              <HardDrive className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="flex items-center gap-2 mb-2">
                {getStatusIcon(health.checks.storage.status)}
                {getStatusBadge(health.checks.storage.status)}
              </div>
              <p className="text-xs text-muted-foreground">
                Used: {health.checks.storage.used_percentage.toFixed(1)}%
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">API</CardTitle>
              <Activity className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="flex items-center gap-2 mb-2">
                {getStatusIcon(health.checks.api.status)}
                {getStatusBadge(health.checks.api.status)}
              </div>
              <p className="text-xs text-muted-foreground">
                Response time: {health.checks.api.response_time}ms
              </p>
            </CardContent>
          </Card>
        </div>
      )}

      {/* System Status Summary */}
      {health && health.status === 'healthy' && (
        <Card>
          <CardContent className="pt-6">
            <div className="text-center py-8">
              <CheckCircle className="h-12 w-12 text-green-500 mx-auto mb-4" />
              <p className="text-lg font-semibold text-gray-900">All Systems Operational</p>
              <p className="text-gray-600">No issues detected in the current health check</p>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Warning Status */}
      {health && health.status === 'warning' && (
        <Card>
          <CardContent className="pt-6">
            <div className="text-center py-8">
              <AlertTriangle className="h-12 w-12 text-yellow-500 mx-auto mb-4" />
              <p className="text-lg font-semibold text-gray-900">System Warning</p>
              <p className="text-gray-600">Some components require attention</p>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Critical Status */}
      {health && health.status === 'critical' && (
        <Card>
          <CardContent className="pt-6">
            <div className="text-center py-8">
              <XCircle className="h-12 w-12 text-red-500 mx-auto mb-4" />
              <p className="text-lg font-semibold text-gray-900">Critical System Issues</p>
              <p className="text-gray-600">Immediate attention required</p>
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
