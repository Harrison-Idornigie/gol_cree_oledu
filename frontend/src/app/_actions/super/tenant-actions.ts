'use server';

import { revalidatePath } from 'next/cache';
import axiosInstance from '@/lib/axios';

interface Tenant {
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

interface TenantListResponse {
  data: Tenant[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

interface TenantCreateData {
  name: string;
  slug: string;
  domain?: string;
  description?: string;
  admin_email: string;
  admin_name: string;
}

interface TenantUpdateData {
  name?: string;
  domain?: string;
  description?: string;
  status?: 'active' | 'inactive' | 'suspended';
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
 * Get all tenants with optional filters
 */
export async function getTenants(params?: {
  page?: number;
  per_page?: number;
  search?: string;
  status?: string;
}) {
  try {
    const searchParams = new URLSearchParams();
    
    if (params?.page) searchParams.append('page', params.page.toString());
    if (params?.per_page) searchParams.append('per_page', params.per_page.toString());
    if (params?.search) searchParams.append('search', params.search);
    if (params?.status) searchParams.append('status', params.status);

    const response = await axiosInstance.get<ApiResponse<TenantListResponse>>(
      `/api/super-admin/tenants?${searchParams.toString()}`
    );

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error fetching tenants:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}

/**
 * Get a specific tenant by ID
 */
export async function getTenant(tenantId: number) {
  try {
    const response = await axiosInstance.get<ApiResponse<Tenant>>(
      `/api/super-admin/tenants/${tenantId}`
    );

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error fetching tenant:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}

/**
 * Create a new tenant
 */
export async function createTenant(tenantData: TenantCreateData) {
  try {
    const response = await axiosInstance.post<ApiResponse<Tenant>>(
      '/api/super-admin/tenants',
      tenantData
    );

    revalidatePath('/super/tenants');
    
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error creating tenant:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}

/**
 * Update an existing tenant
 */
export async function updateTenant(tenantId: number, tenantData: TenantUpdateData) {
  try {
    const response = await axiosInstance.put<ApiResponse<Tenant>>(
      `/api/super-admin/tenants/${tenantId}`,
      tenantData
    );

    revalidatePath('/super/tenants');
    revalidatePath(`/super/tenants/${tenantId}`);
    
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error updating tenant:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}

/**
 * Delete a tenant
 */
export async function deleteTenant(tenantId: number) {
  try {
    await axiosInstance.delete(`/api/super-admin/tenants/${tenantId}`);

    revalidatePath('/super/tenants');
    
    return {
      success: true,
      error: null
    };
  } catch (error) {
    console.error('Error deleting tenant:', error);
    return {
      success: false,
      error: getErrorMessage(error)
    };
  }
}

/**
 * Update tenant status
 */
export async function updateTenantStatus(tenantId: number, status: 'active' | 'inactive' | 'suspended') {
  try {
    const response = await axiosInstance.patch<ApiResponse<Tenant>>(
      `/api/super-admin/tenants/${tenantId}/status`,
      { status }
    );

    revalidatePath('/super/tenants');
    revalidatePath(`/super/tenants/${tenantId}`);
    
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error updating tenant status:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}

/**
 * Get tenant statistics
 */
export async function getTenantStatistics(tenantId: number) {
  try {
    const response = await axiosInstance.get<ApiResponse<{
      users_count: number;
      active_users_count: number;
      learning_paths_count: number;
      languages_count: number;
      total_lessons: number;
      total_exercises: number;
      storage_used: number;
      last_activity: string;
    }>>(
      `/api/super-admin/tenants/${tenantId}/statistics`
    );

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error fetching tenant statistics:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}

/**
 * Reset tenant trial period
 */
export async function resetTenantTrial(tenantId: number) {
  try {
    const response = await axiosInstance.post<ApiResponse<Tenant>>(
      `/api/super-admin/tenants/${tenantId}/reset-trial`
    );

    revalidatePath('/super/tenants');
    revalidatePath(`/super/tenants/${tenantId}`);
    
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error resetting tenant trial:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}

/**
 * Extend tenant subscription
 */
export async function extendTenantSubscription(tenantId: number, extensionData: {
  months: number;
  reason?: string;
}) {
  try {
    const response = await axiosInstance.post<ApiResponse<Tenant>>(
      `/api/super-admin/tenants/${tenantId}/extend-subscription`,
      extensionData
    );

    revalidatePath('/super/tenants');
    revalidatePath(`/super/tenants/${tenantId}`);
    
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    console.error('Error extending tenant subscription:', error);
    return {
      data: null,
      error: getErrorMessage(error)
    };
  }
}
