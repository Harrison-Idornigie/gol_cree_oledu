'use server';

import { revalidatePath } from 'next/cache';
import axiosInstance from '@/lib/axios';

interface SystemSettings {
  maintenance_mode: boolean;
  registration_enabled: boolean;
  max_tenants: number;
  default_storage_limit: number;
  default_user_limit: number;
  email_notifications: boolean;
  backup_frequency: 'daily' | 'weekly' | 'monthly';
  log_retention_days: number;
  api_rate_limit: number;
  session_timeout: number;
}

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

interface MaintenanceMode {
  enabled: boolean;
  message?: string;
  scheduled_start?: string;
  scheduled_end?: string;
  allowed_ips?: string[];
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
 * Get system settings
 */
export async function getSystemSettings() {
  try {
    const response = await axiosInstance.get<ApiResponse<SystemSettings>>(
      '/api/super-admin/system/settings'
    );

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error fetching system settings:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}

/**
 * Update system settings
 */
export async function updateSystemSettings(settings: Partial<SystemSettings>) {
  try {
    const response = await axiosInstance.put<ApiResponse<SystemSettings>>(
      '/api/super-admin/system/settings',
      settings
    );

    revalidatePath('/super/settings');
    
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error updating system settings:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}

/**
 * Perform system health check
 */
export async function performHealthCheck() {
  try {
    const response = await axiosInstance.get<ApiResponse<HealthCheck>>(
      '/api/super-admin/system/health-check'
    );

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error performing health check:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}

/**
 * Get maintenance mode status
 */
export async function getMaintenanceMode() {
  try {
    const response = await axiosInstance.get<ApiResponse<MaintenanceMode>>(
      '/api/super-admin/system/maintenance-mode'
    );

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error fetching maintenance mode:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}

/**
 * Set maintenance mode
 */
export async function setMaintenanceMode(maintenanceData: {
  enabled: boolean;
  message?: string;
  scheduled_start?: string;
  scheduled_end?: string;
  allowed_ips?: string[];
}) {
  try {
    const response = await axiosInstance.post<ApiResponse<MaintenanceMode>>(
      '/api/super-admin/system/maintenance-mode',
      maintenanceData
    );

    revalidatePath('/super/settings');
    revalidatePath('/super/health');
    
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error setting maintenance mode:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}

/**
 * Clear system cache
 */
export async function clearSystemCache(cacheType?: 'all' | 'application' | 'database' | 'sessions') {
  try {
    const response = await axiosInstance.post<ApiResponse<{ cleared: boolean }>>(
      '/api/super-admin/system/clear-cache',
      { type: cacheType || 'all' }
    );

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error clearing system cache:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}

/**
 * Create system backup
 */
export async function createSystemBackup(backupType: 'full' | 'database' | 'files') {
  try {
    const response = await axiosInstance.post<ApiResponse<{
      backup_id: string;
      status: 'started' | 'completed' | 'failed';
      file_path?: string;
    }>>(
      '/api/super-admin/system/backup',
      { type: backupType }
    );

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error creating system backup:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}

/**
 * Get system logs
 */
export async function getSystemLogs(params?: {
  level?: 'debug' | 'info' | 'warning' | 'error';
  start_date?: string;
  end_date?: string;
  limit?: number;
  search?: string;
}) {
  try {
    const searchParams = new URLSearchParams();
    
    if (params?.level) searchParams.append('level', params.level);
    if (params?.start_date) searchParams.append('start_date', params.start_date);
    if (params?.end_date) searchParams.append('end_date', params.end_date);
    if (params?.limit) searchParams.append('limit', params.limit.toString());
    if (params?.search) searchParams.append('search', params.search);

    const response = await axiosInstance.get<ApiResponse<Array<{
      id: string;
      level: string;
      message: string;
      context: any;
      timestamp: string;
    }>>>(
      `/api/super-admin/system/logs?${searchParams.toString()}`
    );

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error fetching system logs:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}

/**
 * Update system configuration
 */
export async function updateSystemConfiguration(config: {
  key: string;
  value: any;
  description?: string;
}) {
  try {
    const response = await axiosInstance.put<ApiResponse<{ updated: boolean }>>(
      '/api/super-admin/system/configuration',
      config
    );

    revalidatePath('/super/settings');
    
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error updating system configuration:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}
