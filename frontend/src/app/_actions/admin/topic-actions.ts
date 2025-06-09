'use server';

import { revalidatePath } from 'next/cache';
import axiosInstance from '@/lib/axios';
import { ApiResponse } from '@/lib/axios';

interface Topic {
  id: number;
  unit_id: number;
  title: string;
  description: string;
  slug: string;
  icon: string;
  color: string;
  order: number;
  status: 'draft' | 'published' | 'archived';
  xp_reward: number;
  max_level: number;
  is_bonus: boolean;
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

export async function getTopics(unitId?: number) {
  try {
    const url = unitId 
      ? `/admin/units/${unitId}/topics`
      : '/admin/topics';
    
    const response = await axiosInstance.get<ApiResponse<Topic[]>>(url);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch topics'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function getTopic(id: number) {
  try {
    const response = await axiosInstance.get<ApiResponse<Topic>>(`/admin/topics/${id}?with_lessons=1`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch topic'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function createTopic(unitId: number, formData: FormData) {
  try {
    const response = await axiosInstance.post<ApiResponse<Topic>>(`/admin/topics`, {
      unit_id: unitId,
      title: formData.get('title'),
      description: formData.get('description'),
      icon: formData.get('icon'),
      color: formData.get('color'),
      order: formData.get('order'),
      status: formData.get('status') || 'draft',
      xp_reward: formData.get('xp_reward'),
      max_level: formData.get('max_level') || 5,
      is_bonus: formData.get('is_bonus') === 'true'
    });

    revalidatePath('/admin/units/[id]', 'page');
    revalidatePath('/admin/topics', 'page');

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to create topic'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function updateTopic(id: number, formData: FormData) {
  try {
    const response = await axiosInstance.put<ApiResponse<Topic>>(`/admin/topics/${id}`, {
      title: formData.get('title'),
      description: formData.get('description'),
      icon: formData.get('icon'),
      color: formData.get('color'),
      order: formData.get('order'),
      status: formData.get('status'),
      xp_reward: formData.get('xp_reward'),
      max_level: formData.get('max_level'),
      is_bonus: formData.get('is_bonus') === 'true'
    });

    revalidatePath('/admin/units/[id]', 'page');
    revalidatePath('/admin/topics/[id]', 'page');

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to update topic'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function deleteTopic(id: number) {
  try {
    await axiosInstance.delete<ApiResponse<void>>(`/admin/topics/${id}`);
    revalidatePath('/admin/units/[id]', 'page');
    revalidatePath('/admin/topics', 'page');
    return { error: null };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        error: error.response?.data?.message || 'Failed to delete topic'
      };
    }
    return {
      error: 'An unexpected error occurred'
    };
  }
}

export async function updateTopicStatus(id: number, status: string) {
  try {
    const response = await axiosInstance.patch<ApiResponse<Topic>>(`/admin/topics/${id}/status`, { status });
    revalidatePath('/admin/units/[id]', 'page');
    revalidatePath('/admin/topics/[id]', 'page');
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to update topic status'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function reorderTopics(unitId: number, topicOrders: { id: number; order: number }[]) {
  try {
    const response = await axiosInstance.post<ApiResponse<{ success: boolean }>>(
      `/admin/units/${unitId}/reorder-topics`,
      { topic_ids: topicOrders.map(t => t.id) }
    );
    revalidatePath('/admin/units/[id]', 'page');
    return {
      success: response.data.data.success,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        success: false,
        error: error.response?.data?.message || 'Failed to reorder topics'
      };
    }
    return {
      success: false,
      error: 'An unexpected error occurred'
    };
  }
}

export async function reorderLessons(topicId: number, lessonOrders: { id: number; order: number }[]) {
  try {
    const response = await axiosInstance.post<ApiResponse<{ success: boolean }>>(
      `/admin/topics/${topicId}/reorder-lessons`,
      { lesson_ids: lessonOrders.map(l => l.id) }
    );
    revalidatePath('/admin/topics/[id]', 'page');
    return {
      success: response.data.data.success,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        success: false,
        error: error.response?.data?.message || 'Failed to reorder lessons'
      };
    }
    return {
      success: false,
      error: 'An unexpected error occurred'
    };
  }
}
