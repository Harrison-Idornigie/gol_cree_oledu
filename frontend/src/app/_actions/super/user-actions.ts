'use server';

import { revalidatePath } from 'next/cache';
import axiosInstance from '@/lib/axios';

interface GlobalUser {
  id: number;
  name: string;
  email: string;
  membership: string;
  tenant_id: number;
  tenant_name: string;
  is_active: boolean;
  email_verified_at: string | null;
  last_login: string | null;
  created_at: string;
  updated_at: string;
}

interface UserSearchResult {
  users: GlobalUser[];
  total: number;
  current_page: number;
  last_page: number;
  per_page: number;
}

interface UserAuditTrail {
  id: number;
  user_id: number;
  action: string;
  description: string;
  ip_address: string;
  user_agent: string;
  metadata: any;
  created_at: string;
}

interface ImpersonationSession {
  id: string;
  super_admin_id: number;
  target_user_id: number;
  target_tenant_id: number;
  started_at: string;
  expires_at: string;
  is_active: boolean;
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
 * Search users across all tenants
 */
export async function searchGlobalUsers(params?: {
  query?: string;
  tenant_id?: number;
  membership?: string;
  is_active?: boolean;
  page?: number;
  per_page?: number;
}) {
  try {
    const searchParams = new URLSearchParams();
    
    if (params?.query) searchParams.append('query', params.query);
    if (params?.tenant_id) searchParams.append('tenant_id', params.tenant_id.toString());
    if (params?.membership) searchParams.append('membership', params.membership);
    if (params?.is_active !== undefined) searchParams.append('is_active', params.is_active.toString());
    if (params?.page) searchParams.append('page', params.page.toString());
    if (params?.per_page) searchParams.append('per_page', params.per_page.toString());

    const response = await axiosInstance.get<ApiResponse<UserSearchResult>>(
      `/api/super-admin/users?${searchParams.toString()}`
    );

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error searching global users:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}

/**
 * Search users with specific query
 */
export async function searchUsers(query: string, filters?: {
  tenant_id?: number;
  membership?: string;
  limit?: number;
}) {
  try {
    const searchParams = new URLSearchParams({ query });
    
    if (filters?.tenant_id) searchParams.append('tenant_id', filters.tenant_id.toString());
    if (filters?.membership) searchParams.append('membership', filters.membership);
    if (filters?.limit) searchParams.append('limit', filters.limit.toString());

    const response = await axiosInstance.get<ApiResponse<GlobalUser[]>>(
      `/api/super-admin/users/search?${searchParams.toString()}`
    );

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error searching users:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}

/**
 * Start impersonating a user
 */
export async function impersonateUser(userId: number) {
  try {
    const response = await axiosInstance.post<ApiResponse<{
      session: ImpersonationSession;
      redirect_url: string;
    }>>(
      `/api/super-admin/users/${userId}/impersonate`
    );

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error starting impersonation:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}

/**
 * Stop impersonating a user
 */
export async function stopImpersonation() {
  try {
    const response = await axiosInstance.post<ApiResponse<{ stopped: boolean }>>(
      '/api/super-admin/users/stop-impersonation'
    );

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error stopping impersonation:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}

/**
 * Get user audit trail
 */
export async function getUserAuditTrail(userId: number, params?: {
  start_date?: string;
  end_date?: string;
  action?: string;
  limit?: number;
  page?: number;
}) {
  try {
    const searchParams = new URLSearchParams();
    
    if (params?.start_date) searchParams.append('start_date', params.start_date);
    if (params?.end_date) searchParams.append('end_date', params.end_date);
    if (params?.action) searchParams.append('action', params.action);
    if (params?.limit) searchParams.append('limit', params.limit.toString());
    if (params?.page) searchParams.append('page', params.page.toString());

    const response = await axiosInstance.get<ApiResponse<{
      audit_logs: UserAuditTrail[];
      total: number;
      current_page: number;
      last_page: number;
    }>>(
      `/api/super-admin/users/${userId}/audit-trail?${searchParams.toString()}`
    );

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error fetching user audit trail:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}

/**
 * Suspend a user
 */
export async function suspendUser(userId: number, reason?: string) {
  try {
    const response = await axiosInstance.patch<ApiResponse<GlobalUser>>(
      `/api/super-admin/users/${userId}/suspend`,
      { reason }
    );

    revalidatePath('/super/users');
    
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error suspending user:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}

/**
 * Activate a user
 */
export async function activateUser(userId: number) {
  try {
    const response = await axiosInstance.patch<ApiResponse<GlobalUser>>(
      `/api/super-admin/users/${userId}/activate`
    );

    revalidatePath('/super/users');
    
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error activating user:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}

/**
 * Get user details
 */
export async function getGlobalUserDetails(userId: number) {
  try {
    const response = await axiosInstance.get<ApiResponse<GlobalUser & {
      tenant: {
        id: number;
        name: string;
        slug: string;
        status: string;
      };
      statistics: {
        total_logins: number;
        last_activity: string;
        lessons_completed: number;
        exercises_completed: number;
        points_earned: number;
      };
    }>>(
      `/api/super-admin/users/${userId}`
    );

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error fetching user details:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}

/**
 * Reset user password (admin action)
 */
export async function resetUserPassword(userId: number) {
  try {
    const response = await axiosInstance.post<ApiResponse<{
      temporary_password: string;
      reset_required: boolean;
    }>>(
      `/api/super-admin/users/${userId}/reset-password`
    );

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error resetting user password:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}

/**
 * Transfer user to different tenant
 */
export async function transferUser(userId: number, targetTenantId: number, reason?: string) {
  try {
    const response = await axiosInstance.post<ApiResponse<GlobalUser>>(
      `/api/super-admin/users/${userId}/transfer`,
      { 
        target_tenant_id: targetTenantId,
        reason 
      }
    );

    revalidatePath('/super/users');
    
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error transferring user:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}
