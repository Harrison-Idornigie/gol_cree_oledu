'use server';

import axiosInstance from '@/lib/axios';
import { ApiResponse } from '@/lib/axios';

interface VocabularyWord {
  id: number;
  word: string;
  definition: string;
  example_sentence: string;
  difficulty_level: string;
}

interface ApiError {
  message: string;
  status: number;
}

function isAxiosError(error: unknown): error is { response?: { data?: ApiError } } {
  return error != null && typeof error === 'object' && 'isAxiosError' in error;
}

export async function getVocabularyWords() {
  try {
    const response = await axiosInstance.get<ApiResponse<VocabularyWord[]>>('/api/vocabulary');
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch vocabulary words'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function getVocabularyWord(id: number) {
  try {
    const response = await axiosInstance.get<ApiResponse<VocabularyWord>>(`/api/vocabulary/${id}`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch vocabulary word'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function markWordAsLearned(id: number) {
  try {
    const response = await axiosInstance.post<ApiResponse<{ success: boolean }>>(`/api/vocabulary/${id}/mark-learned`);
    return {
      success: response.data.data.success,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        success: false,
        error: error.response?.data?.message || 'Failed to mark word as learned'
      };
    }
    return {
      success: false,
      error: 'An unexpected error occurred'
    };
  }
}

export async function getVocabularyByLevel(level: string) {
  try {
    const response = await axiosInstance.get<ApiResponse<VocabularyWord[]>>(`/api/vocabulary/level/${level}`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch vocabulary words for the specified level'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}