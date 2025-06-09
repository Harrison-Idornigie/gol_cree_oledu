'use server';

import axiosInstance from '@/lib/axios';
import { ApiResponse } from '@/lib/axios';

interface LearningPath {
  id: number;
  title: string;
  description: string;
  difficulty_level: string;
  estimated_duration: number;
  prerequisites: string[];
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

export async function getLearningPaths() {
  try {
    const response = await axiosInstance.get<ApiResponse<LearningPath[]>>('/admin/learning-paths');
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch learning paths'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function getLearningPath(id: number) {
  try {
    const response = await axiosInstance.get<ApiResponse<LearningPath>>(`/admin/learning-paths/${id}`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch learning path'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function createLearningPath(formData: FormData) {
  try {
    const response = await axiosInstance.post<ApiResponse<LearningPath>>('/admin/learning-paths', {
      title: formData.get('title'),
      description: formData.get('description'),
      difficulty_level: formData.get('difficulty_level'),
      estimated_duration: formData.get('estimated_duration'),
      prerequisites: formData.getAll('prerequisites'),
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
        error: error.response?.data?.message || 'Failed to create learning path'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function updateLearningPath(id: number, formData: FormData) {
  try {
    const response = await axiosInstance.put<ApiResponse<LearningPath>>(`/admin/learning-paths/${id}`, {
      title: formData.get('title'),
      description: formData.get('description'),
      difficulty_level: formData.get('difficulty_level'),
      estimated_duration: formData.get('estimated_duration'),
      prerequisites: formData.getAll('prerequisites'),
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
        error: error.response?.data?.message || 'Failed to update learning path'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function deleteLearningPath(id: number) {
  try {
    await axiosInstance.delete<ApiResponse<void>>(`/admin/learning-paths/${id}`);
    return { error: null };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        error: error.response?.data?.message || 'Failed to delete learning path'
      };
    }
    return {
      error: 'An unexpected error occurred'
    };
  }
}

export async function toggleLearningPathStatus(id: number) {
  try {
    const response = await axiosInstance.post<ApiResponse<{ is_published: boolean }>>(`/admin/learning-paths/${id}/toggle-status`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to toggle learning path status'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}