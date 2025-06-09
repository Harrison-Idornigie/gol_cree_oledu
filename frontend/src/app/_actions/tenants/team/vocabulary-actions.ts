'use server';

import axiosInstance from '@/lib/axios';
import { ApiResponse } from '@/lib/axios';

interface VocabularyItem {
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

export async function getVocabularyItems() {
  try {
    const response = await axiosInstance.get<ApiResponse<VocabularyItem[]>>('/admin/vocabulary');
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch vocabulary items'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function getVocabularyItem(id: number) {
  try {
    const response = await axiosInstance.get<ApiResponse<VocabularyItem>>(`/admin/vocabulary/${id}`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch vocabulary item'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function createVocabularyItem(formData: FormData) {
  try {
    const response = await axiosInstance.post<ApiResponse<VocabularyItem>>('/admin/vocabulary', {
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
        error: error.response?.data?.message || 'Failed to create vocabulary item'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function updateVocabularyItem(id: number, formData: FormData) {
  try {
    const response = await axiosInstance.put<ApiResponse<VocabularyItem>>(`/admin/vocabulary/${id}`, {
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
        error: error.response?.data?.message || 'Failed to update vocabulary item'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function deleteVocabularyItem(id: number) {
  try {
    await axiosInstance.delete<ApiResponse<void>>(`/admin/vocabulary/${id}`);
    return { error: null };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        error: error.response?.data?.message || 'Failed to delete vocabulary item'
      };
    }
    return {
      error: 'An unexpected error occurred'
    };
  }
}

export async function toggleVocabularyItemStatus(id: number) {
  try {
    const response = await axiosInstance.post<ApiResponse<{ is_published: boolean }>>(`/admin/vocabulary/${id}/toggle-status`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to toggle vocabulary item status'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function getVocabularyByCategory(category: string) {
  try {
    const response = await axiosInstance.get<ApiResponse<VocabularyItem[]>>(`/admin/vocabulary/category/${category}`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch vocabulary items by category'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function getVocabularyByDifficulty(level: string) {
  try {
    const response = await axiosInstance.get<ApiResponse<VocabularyItem[]>>(`/admin/vocabulary/difficulty/${level}`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch vocabulary items by difficulty'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function bulkImportVocabulary(formData: FormData) {
  try {
    const response = await axiosInstance.post<ApiResponse<{ 
      success: boolean;
      imported_count: number;
      errors?: Array<{ row: number; message: string }>;
    }>>('/admin/vocabulary/import', formData);

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to import vocabulary items'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function exportVocabulary(format: 'csv' | 'json' = 'csv') {
  try {
    const response = await axiosInstance.get(`/admin/vocabulary/export?format=${format}`, {
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
        error: error.response?.data?.message || 'Failed to export vocabulary items'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}