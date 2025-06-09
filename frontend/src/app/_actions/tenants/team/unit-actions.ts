'use server';

import axiosInstance from '@/lib/axios';
import { ApiResponse } from '@/lib/axios';

interface Unit {
  id: number;
  learning_path_id: number;
  title: string;
  description: string;
  order: number;
  estimated_duration: number;
  is_published: boolean;
  created_at: string;
  updated_at: string;
}

interface ApiError {
  message: string;
  status: number;
}

function isAxiosError(error: unknown): error is { response?: { data?: ApiError } } {
  return error != null && typeof error === 'object' && 'isAxiosError' in error;
}

export async function getUnits(learningPathId?: number) {
  try {
    const url = learningPathId 
      ? `/admin/learning-paths/${learningPathId}/units`
      : '/admin/units';
    
    const response = await axiosInstance.get<ApiResponse<Unit[]>>(url);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch units'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function getUnit(id: number) {
  try {
    const response = await axiosInstance.get<ApiResponse<Unit>>(`/admin/units/${id}`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch unit'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function createUnit(learningPathId: number, formData: FormData) {
  try {
    const response = await axiosInstance.post<ApiResponse<Unit>>(`/admin/learning-paths/${learningPathId}/units`, {
      title: formData.get('title'),
      description: formData.get('description'),
      order: formData.get('order'),
      estimated_duration: formData.get('estimated_duration'),
      is_published: formData.get('is_published') === 'true'
    });

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to create unit'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function updateUnit(id: number, formData: FormData) {
  try {
    const response = await axiosInstance.put<ApiResponse<Unit>>(`/admin/units/${id}`, {
      title: formData.get('title'),
      description: formData.get('description'),
      order: formData.get('order'),
      estimated_duration: formData.get('estimated_duration'),
      is_published: formData.get('is_published') === 'true'
    });

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to update unit'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function deleteUnit(id: number) {
  try {
    await axiosInstance.delete<ApiResponse<void>>(`/admin/units/${id}`);
    return { error: null };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        error: error.response?.data?.message || 'Failed to delete unit'
      };
    }
    return {
      error: 'An unexpected error occurred'
    };
  }
}

export async function toggleUnitStatus(id: number) {
  try {
    const response = await axiosInstance.post<ApiResponse<{ is_published: boolean }>>(`/admin/units/${id}/toggle-status`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to toggle unit status'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function reorderUnits(learningPathId: number, unitOrders: { id: number; order: number }[]) {
  try {
    const response = await axiosInstance.put<ApiResponse<{ success: boolean }>>(
      `/admin/learning-paths/${learningPathId}/units/reorder`,
      { units: unitOrders }
    );
    return {
      success: response.data.data.success,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        success: false,
        error: error.response?.data?.message || 'Failed to reorder units'
      };
    }
    return {
      success: false,
      error: 'An unexpected error occurred'
    };
  }
}