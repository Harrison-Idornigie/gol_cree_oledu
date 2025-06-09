'use server';

import axiosInstance from '@/lib/axios';
import { ApiResponse } from '@/lib/axios';

type ExerciseType = 'fill-in-blank' | 'matching' | 'multiple-choice' | 'ordering' | 'free-text';

interface Exercise {
  id: number;
  title: string;
  description: string;
  instructions: string;
  type: ExerciseType;
  difficulty_level: string;
  estimated_time: number;
  is_published: boolean;
  content: {
    questions: Array<{
      id: number;
      question: string;
      correct_answer: string;
      options?: string[];
      explanation?: string;
    }>;
    matching_pairs?: Array<{
      left: string;
      right: string;
    }>;
    order_items?: string[];
  };
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

export async function getExercises() {
  try {
    const response = await axiosInstance.get<ApiResponse<Exercise[]>>('/admin/exercises');
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch exercises'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function getExercise(id: number) {
  try {
    const response = await axiosInstance.get<ApiResponse<Exercise>>(`/admin/exercises/${id}`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch exercise'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function createExercise(formData: FormData) {
  try {
    // Parse content based on exercise type
    const type = formData.get('type') as ExerciseType;
    let content = {};

    switch (type) {
      case 'multiple-choice':
      case 'fill-in-blank':
      case 'free-text':
        content = {
          questions: JSON.parse(formData.get('questions') as string)
        };
        break;
      case 'matching':
        content = {
          matching_pairs: JSON.parse(formData.get('matching_pairs') as string)
        };
        break;
      case 'ordering':
        content = {
          order_items: JSON.parse(formData.get('order_items') as string)
        };
        break;
    }

    const response = await axiosInstance.post<ApiResponse<Exercise>>('/admin/exercises', {
      title: formData.get('title'),
      description: formData.get('description'),
      instructions: formData.get('instructions'),
      type: type,
      difficulty_level: formData.get('difficulty_level'),
      estimated_time: formData.get('estimated_time'),
      is_published: formData.get('is_published') === 'true',
      content: content
    });

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to create exercise'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function updateExercise(id: number, formData: FormData) {
  try {
    // Parse content based on exercise type
    const type = formData.get('type') as ExerciseType;
    let content = {};

    switch (type) {
      case 'multiple-choice':
      case 'fill-in-blank':
      case 'free-text':
        content = {
          questions: JSON.parse(formData.get('questions') as string)
        };
        break;
      case 'matching':
        content = {
          matching_pairs: JSON.parse(formData.get('matching_pairs') as string)
        };
        break;
      case 'ordering':
        content = {
          order_items: JSON.parse(formData.get('order_items') as string)
        };
        break;
    }

    const response = await axiosInstance.put<ApiResponse<Exercise>>(`/admin/exercises/${id}`, {
      title: formData.get('title'),
      description: formData.get('description'),
      instructions: formData.get('instructions'),
      type: type,
      difficulty_level: formData.get('difficulty_level'),
      estimated_time: formData.get('estimated_time'),
      is_published: formData.get('is_published') === 'true',
      content: content
    });

    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to update exercise'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function deleteExercise(id: number) {
  try {
    await axiosInstance.delete<ApiResponse<void>>(`/admin/exercises/${id}`);
    return { error: null };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        error: error.response?.data?.message || 'Failed to delete exercise'
      };
    }
    return {
      error: 'An unexpected error occurred'
    };
  }
}

export async function toggleExerciseStatus(id: number) {
  try {
    const response = await axiosInstance.post<ApiResponse<{ is_published: boolean }>>(`/admin/exercises/${id}/toggle-status`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to toggle exercise status'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function getExercisesByType(type: ExerciseType) {
  try {
    const response = await axiosInstance.get<ApiResponse<Exercise[]>>(`/admin/exercises/type/${type}`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch exercises by type'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}

export async function getExercisesByDifficulty(level: string) {
  try {
    const response = await axiosInstance.get<ApiResponse<Exercise[]>>(`/admin/exercises/difficulty/${level}`);
    return {
      data: response.data.data,
      error: null
    };
  } catch (error) {
    if (isAxiosError(error)) {
      return {
        data: null,
        error: error.response?.data?.message || 'Failed to fetch exercises by difficulty'
      };
    }
    return {
      data: null,
      error: 'An unexpected error occurred'
    };
  }
}