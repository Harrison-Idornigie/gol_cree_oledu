'use server';

import axiosInstance from '@/lib/axios';

interface SystemOverview {
  total_tenants: number;
  active_tenants: number;
  total_users: number;
  active_users: number;
  total_languages: number;
  total_learning_paths: number;
  total_lessons: number;
  total_exercises: number;
  system_uptime: number;
  storage_used: number;
  storage_limit: number;
}

interface TenantUsage {
  tenant_id: number;
  tenant_name: string;
  users_count: number;
  active_users_count: number;
  storage_used: number;
  api_calls_count: number;
  last_activity: string;
}

interface PerformanceMetrics {
  avg_response_time: number;
  api_calls_per_minute: number;
  error_rate: number;
  database_performance: {
    avg_query_time: number;
    slow_queries_count: number;
  };
  cache_hit_rate: number;
}

interface SystemHealth {
  status: 'healthy' | 'warning' | 'critical';
  database_status: 'healthy' | 'warning' | 'critical';
  cache_status: 'healthy' | 'warning' | 'critical';
  storage_status: 'healthy' | 'warning' | 'critical';
  api_status: 'healthy' | 'warning' | 'critical';
  last_check: string;
  issues: Array<{
    type: 'warning' | 'error';
    message: string;
    timestamp: string;
  }>;
}

interface UsageTrends {
  period: 'daily' | 'weekly' | 'monthly';
  data: Array<{
    date: string;
    users_active: number;
    api_calls: number;
    storage_used: number;
    new_tenants: number;
  }>;
}

interface ApiResponse<T> {
  success: boolean;
  data: T;
  message: string;
}

function getErrorMessage(error: unknown): string {
  if (error instanceof Error) return error.message;
  if (typeof error === 'object' && error && 'message' in error) {
    return String(error.message);
  }
  return 'An unexpected error occurred';
}

/**
 * Get system overview analytics
 */
export async function getSystemOverview() {
  try {
    const response = await axiosInstance.get<ApiResponse<SystemOverview>>(
      '/api/super-admin/analytics/overview'
    );

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error fetching system overview:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}

/**
 * Get tenant usage analytics
 */
export async function getTenantUsage(params?: {
  limit?: number;
  sort_by?: 'users_count' | 'storage_used' | 'api_calls_count' | 'last_activity';
  sort_order?: 'asc' | 'desc';
}) {
  try {
    const searchParams = new URLSearchParams();
    
    if (params?.limit) searchParams.append('limit', params.limit.toString());
    if (params?.sort_by) searchParams.append('sort_by', params.sort_by);
    if (params?.sort_order) searchParams.append('sort_order', params.sort_order);

    const response = await axiosInstance.get<ApiResponse<TenantUsage[]>>(
      `/api/super-admin/analytics/tenant-usage?${searchParams.toString()}`
    );

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error fetching tenant usage:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}

/**
 * Get performance metrics
 */
export async function getPerformanceMetrics(timeframe?: '1h' | '24h' | '7d' | '30d') {
  try {
    const searchParams = new URLSearchParams();
    if (timeframe) searchParams.append('timeframe', timeframe);

    const response = await axiosInstance.get<ApiResponse<PerformanceMetrics>>(
      `/api/super-admin/analytics/performance-metrics?${searchParams.toString()}`
    );

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error fetching performance metrics:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}

/**
 * Get system health status
 */
export async function getSystemHealth() {
  try {
    const response = await axiosInstance.get<ApiResponse<SystemHealth>>(
      '/api/super-admin/analytics/system-health'
    );

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error fetching system health:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}

/**
 * Get usage trends
 */
export async function getUsageTrends(params?: {
  period?: 'daily' | 'weekly' | 'monthly';
  start_date?: string;
  end_date?: string;
}) {
  try {
    const searchParams = new URLSearchParams();
    
    if (params?.period) searchParams.append('period', params.period);
    if (params?.start_date) searchParams.append('start_date', params.start_date);
    if (params?.end_date) searchParams.append('end_date', params.end_date);

    const response = await axiosInstance.get<ApiResponse<UsageTrends>>(
      `/api/super-admin/analytics/usage-trends?${searchParams.toString()}`
    );

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error fetching usage trends:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}

/**
 * Export analytics data
 */
export async function exportAnalyticsData(params: {
  type: 'overview' | 'tenant-usage' | 'performance' | 'health';
  format: 'csv' | 'json' | 'pdf';
  start_date?: string;
  end_date?: string;
}): Promise<{ data: Blob | null; error: string | null }> {
  try {
    const response = await axiosInstance.post(
      '/api/super-admin/analytics/export',
      params,
      {
        responseType: 'blob'
      }
    );

    return {
      data: response.data as Blob,
      error: null
    };
  } catch (error) {
    console.error('Error exporting analytics data:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}
