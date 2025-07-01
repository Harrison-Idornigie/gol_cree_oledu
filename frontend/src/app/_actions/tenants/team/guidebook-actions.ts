'use server';

import axiosInstance from '@/lib/axios';
import { ApiResponse } from '@/lib/axios';

interface GuidebookItem {
  id: number;
  word: string;
  definition: string;
  pronunciation: string;
  example_sentence: string;
  category: string;
  difficulty_level: string;
  notes: string | null;
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

export async function getGuidebookItems() {
  try {
    const response = await axiosInstance.get<ApiResponse<GuidebookItem[]>>('/admin/guidebook');
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch guidebook items'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function getGuidebookItem(id: number) {
  try {
    const response = await axiosInstance.get<ApiResponse<GuidebookItem>>(`/admin/guidebook/${id}`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch guidebook item'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function createGuidebookItem(formData: FormData) {
  try {
    const response = await axiosInstance.post<ApiResponse<GuidebookItem>>('/admin/guidebook', {
      word: formData.get('word'),
      definition: formData.get('definition'),
      pronunciation: formData.get('pronunciation'),
      example_sentence: formData.get('example_sentence'),
      category: formData.get('category'),
      difficulty_level: formData.get('difficulty_level'),
      notes: formData.get('notes'),
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
        error: error.response?.data?.message || 'Failed to create guidebook item'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function updateGuidebookItem(id: number, formData: FormData) {
  try {
    const response = await axiosInstance.put<ApiResponse<GuidebookItem>>(`/admin/guidebook/${id}`, {
      word: formData.get('word'),
      definition: formData.get('definition'),
      pronunciation: formData.get('pronunciation'),
      example_sentence: formData.get('example_sentence'),
      category: formData.get('category'),
      difficulty_level: formData.get('difficulty_level'),
      notes: formData.get('notes'),
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
        error: error.response?.data?.message || 'Failed to update guidebook item'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function deleteGuidebookItem(id: number) {
  try {
    await axiosInstance.delete<ApiResponse<void>>(`/admin/guidebook/${id}`);
    return { error: null };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        error: error.response?.data?.message || 'Failed to delete guidebook item'
      };
    }
    return {
      error: 'An unexpected error occurred'
    };
  }
}

export async function toggleGuidebookItemStatus(id: number) {
  try {
    const response = await axiosInstance.post<ApiResponse<{ is_published: boolean }>>(`/admin/guidebook/${id}/toggle-status`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to toggle guidebook item status'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function getGuidebookByCategory(category: string) {
  try {
    const response = await axiosInstance.get<ApiResponse<GuidebookItem[]>>(`/admin/guidebook/category/${category}`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch guidebook items by category'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function getGuidebookByDifficulty(level: string) {
  try {
    const response = await axiosInstance.get<ApiResponse<GuidebookItem[]>>(`/admin/guidebook/difficulty/${level}`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch guidebook items by difficulty'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function bulkImportGuidebook(formData: FormData) {
  try {
    const response = await axiosInstance.post<ApiResponse<{ 
      success: boolean;
      imported_count: number;
      errors?: Array<{ row: number; message: string }>;
    }>>('/admin/guidebook/import', formData);

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to import guidebook items'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function exportGuidebook(format: 'csv' | 'json' = 'csv') {
  try {
    const response = await axiosInstance.get(`/admin/guidebook/export?format=${format}`, {
      responseType: 'blob'
    });
    
    return {
      data: response.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to export guidebook items'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}