'use server';

import axiosInstance from '@/lib/axios';
import { ApiResponse } from '@/lib/axios';

interface GrammarExercise {
  id: number;
  title: string;
  description: string;
  difficulty_level: string;
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

export async function getGrammarExercises() {
  try {
    const response = await axiosInstance.get<ApiResponse<GrammarExercise[]>>('/api/grammar');
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch grammar exercises'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function getGrammarExercise(id: number) {
  try {
    const response = await axiosInstance.get<ApiResponse<GrammarExercise>>(`/api/grammar/${id}`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch grammar exercise'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function submitExerciseAnswer(exerciseId: number, answers: Array<{ questionId: number, answer: string }>) {
  try {
    const response = await axiosInstance.post<ApiResponse<{
      score: number;
      total: number;
      correctAnswers: Array<{ questionId: number; correct: boolean }>;
    }>>(`/api/grammar/${exerciseId}/submit`, { answers });
    
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to submit exercise answers'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function getGrammarByLevel(level: string) {
  try {
    const response = await axiosInstance.get<ApiResponse<GrammarExercise[]>>(`/api/grammar/level/${level}`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch grammar exercises for the specified level'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}