// Super Admin Types

export interface Tenant {
  id: number;
  name: string;
  slug: string;
  domain?: string;
  description?: string;
  status: 'active' | 'inactive' | 'suspended';
  users_count: number;
  learning_paths_count: number;
  languages_count: number;
  created_at: string;
  updated_at: string;
}

export interface TenantListResponse {
  data: Tenant[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface SystemStats {
  totalTenants: number;
  activeTenants: number;
  totalUsers: number;
  totalLanguages: number;
  systemHealth: 'healthy' | 'warning' | 'critical';
  recentActivity: ActivityItem[];
}

export interface ActivityItem {
  id: number;
  type: 'tenant_created' | 'user_registered' | 'system_update';
  message: string;
  timestamp: string;
}

export interface GlobalUser {
  id: number;
  name: string;
  email: string;
  role: string;
  tenant_id: number;
  tenant_name: string;
  is_active: boolean;
  email_verified_at: string | null;
  last_login: string | null;
  created_at: string;
  updated_at: string;
}

export interface SystemOverview {
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

export interface SystemHealth {
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

export interface TenantCreateData {
  name: string;
  slug: string;
  domain?: string;
  description?: string;
  admin_email: string;
  admin_name: string;
}

export interface TenantUpdateData {
  name?: string;
  domain?: string;
  description?: string;
  status?: 'active' | 'inactive' | 'suspended';
}

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  message: string;
}
