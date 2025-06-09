'use server';

import axiosInstance from '@/lib/axios';
import { ApiResponse } from '@/lib/axios';

interface ReadingPassage {
  id: number;
  title: string;
  content: string;
  difficulty_level: string;
  estimated_time: number;
  questions: Array<{
    id: number;
    question: string;
    correct_answer: string;
    options: string[];
  }>;
}

interface ApiError {
  message: string;
  status: number;
}

function isAxiosError(error: unknown): error is { response?: { data?: ApiError } } {
  return error != null && typeof error === 'object' && 'isAxiosError' in error;
}

export async function getReadingPassages() {
  try {
    const response = await axiosInstance.get<ApiResponse<ReadingPassage[]>>('/api/reading');
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch reading passages'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function getReadingPassage(id: number) {
  try {
    const response = await axiosInstance.get<ApiResponse<ReadingPassage>>(`/api/reading/${id}`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch reading passage'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function submitReadingAnswers(passageId: number, answers: Array<{ questionId: number, answer: string }>) {
  try {
    const response = await axiosInstance.post<ApiResponse<{
      score: number;
      total: number;
      correctAnswers: Array<{ questionId: number; correct: boolean }>;
    }>>(`/api/reading/${passageId}/submit`, { answers });
    
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to submit reading answers'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function markPassageAsComplete(id: number) {
  try {
    const response = await axiosInstance.post<ApiResponse<{ success: boolean }>>(`/api/reading/${id}/complete`);
    return {
      success: response.data.data.success,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        success: false,
        error: error.response?.data?.message || 'Failed to mark passage as complete'
      };
    }
    return {
      success: false,
      error: 'An unexpected error occurred'
    };
  }
}

export async function getReadingByLevel(level: string) {
  try {
    const response = await axiosInstance.get<ApiResponse<ReadingPassage[]>>(`/api/reading/level/${level}`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch reading passages for the specified level'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}